<?php
/**
 * Database.php — Skale IA Core
 * ============================================================
 * Patrón Singleton para la gestión de la conexión MySQL/MariaDB.
 * Compatible con variables de entorno de Railway y entorno local.
 *
 * BENEFICIOS:
 *   - Garantiza una única conexión por proceso PHP-FPM.
 *   - Auto-reconexión transparente ante timeouts de Railway/MySQL.
 *   - API unificada para prepared statements que previene SQL Injection.
 *   - Logging centralizado de errores con contexto para debugging.
 *
 * USO:
 *   $db = Database::getInstance();
 *   $db->query("SELECT * FROM leads WHERE id = ?", "i", [$id]);
 *   $rows = $db->fetchAll();
 */

class Database
{
    // ── Instancia única (Singleton) ──────────────────────────────────────────
    private static ?Database $instance = null;

    // ── Conexión mysqli nativa ───────────────────────────────────────────────
    private ?mysqli $conn = null;

    // ── Credenciales leídas desde el entorno (Railway o local) ──────────────
    private string $host;
    private string $user;
    private string $pass;
    private string $name;
    private int    $port;

    // ── Estado del último statement ejecutado ───────────────────────────────
    private ?mysqli_stmt   $lastStmt   = null;
    private ?mysqli_result $lastResult = null;

    /**
     * Constructor privado: impide instanciación directa.
     * Lee las variables de entorno con fallback a valores locales.
     */
    private function __construct()
    {
        // Railway inyecta MYSQLHOST; fallback a MYSQL_HOST o localhost.
        $this->host = getenv('MYSQLHOST')     ?: (getenv('MYSQL_HOST')     ?: 'localhost');
        $this->user = getenv('MYSQLUSER')     ?: (getenv('MYSQL_USER')     ?: 'root');
        $this->pass = getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: '');
        $this->name = getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'chatbot');
        $this->port = (int)(getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);

        $this->connect();
    }

    /**
     * Previene la clonación del Singleton.
     */
    private function __clone() {}

    /**
     * Previene la deserialización del Singleton.
     */
    public function __wakeup()
    {
        throw new \Exception("No se puede deserializar un Singleton.");
    }

    // ── Métodos Públicos Estáticos ───────────────────────────────────────────

    /**
     * Punto de entrada global para obtener la instancia.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        } else {
            // Verificar que la conexión sigue viva antes de retornarla.
            self::$instance->ensureConnected();
        }
        return self::$instance;
    }

    /**
     * Retorna la conexión mysqli nativa para compatibilidad con código legacy.
     * DEPRECATION: Preferir los métodos de esta clase. Solo para transición.
     */
    public function getConnection(): mysqli
    {
        $this->ensureConnected();
        return $this->conn;
    }

    // ── API Principal de Queries ─────────────────────────────────────────────

    /**
     * Ejecuta una query con prepared statement.
     *
     * @param string $sql    La query SQL con placeholders (?).
     * @param string $types  Tipos de parámetros: "s"(string), "i"(int), "d"(double), "b"(blob).
     * @param array  $params Array de valores a vincular.
     * @return bool          True si la ejecución fue exitosa.
     *
     * Ejemplo:
     *   $db->query("INSERT INTO leads (name, phone) VALUES (?, ?)", "ss", ["Ana", "123"]);
     */
    public function query(string $sql, string $types = '', array $params = []): bool
    {
        $this->ensureConnected();
        $this->lastResult = null;
        $this->lastStmt   = null;

        $stmt = mysqli_prepare($this->conn, $sql);

        if (!$stmt) {
            $this->logError("Prepare failed: " . mysqli_error($this->conn), ['sql' => $sql]);
            return false;
        }

        // Bindear parámetros solo si existen.
        if ($types && $params) {
            // Pasar por referencia: mysqli_stmt_bind_param lo requiere.
            $bind_params = [];
            foreach ($params as &$p) {
                $bind_params[] = &$p;
            }
            unset($p);
            mysqli_stmt_bind_param($stmt, $types, ...$bind_params);
        }

        $ok = mysqli_stmt_execute($stmt);

        if (!$ok) {
            $this->logError("Execute failed: " . mysqli_stmt_error($stmt), ['sql' => $sql]);
            mysqli_stmt_close($stmt);
            return false;
        }

        $this->lastStmt   = $stmt;
        $this->lastResult = mysqli_stmt_get_result($stmt);
        return true;
    }

    /**
     * Retorna todas las filas de la última query como array asociativo.
     */
    public function fetchAll(): array
    {
        if (!$this->lastResult) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($this->lastResult)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Retorna una única fila de la última query.
     */
    public function fetchOne(): ?array
    {
        if (!$this->lastResult) return null;
        return mysqli_fetch_assoc($this->lastResult) ?: null;
    }

    /**
     * Retorna el ID del último registro insertado.
     */
    public function lastInsertId(): int
    {
        return (int) mysqli_insert_id($this->conn);
    }

    /**
     * Retorna el número de filas afectadas por la última operación.
     */
    public function affectedRows(): int
    {
        return (int) mysqli_affected_rows($this->conn);
    }

    /**
     * Escapa un string para uso seguro en contextos donde no se puede
     * usar prepared statements (ej: ORDER BY dinámico).
     * ATENCIÓN: Preferir prepared statements siempre que sea posible.
     */
    public function escape(string $value): string
    {
        $this->ensureConnected();
        return mysqli_real_escape_string($this->conn, $value);
    }

    // ── Gestión de Conexión ──────────────────────────────────────────────────

    /**
     * Establece la conexión inicial con la base de datos.
     *
     * @throws RuntimeException Si la conexión falla.
     */
    private function connect(): void
    {
        $this->conn = @mysqli_connect(
            $this->host,
            $this->user,
            $this->pass,
            $this->name,
            $this->port
        );

        if (!$this->conn) {
            // SEC: Nunca exponer credenciales en el error de usuario.
            $error_detail = "Host: {$this->host}:{$this->port}, DB: {$this->name}";
            $this->logError("Connection failed: " . mysqli_connect_error() . " | $error_detail");
            throw new \RuntimeException("No se pudo conectar a la base de datos. Contacta al administrador.");
        }

        // Forzar UTF-8 multibyte para soporte completo de emojis y caracteres especiales.
        mysqli_set_charset($this->conn, 'utf8mb4');
    }

    /**
     * Verifica que la conexión siga activa.
     * Si MySQL cerró la conexión (ej: por timeout de Railway), reconecta.
     */
    private function ensureConnected(): void
    {
        if (!$this->conn || !@mysqli_ping($this->conn)) {
            $this->logError("Conexión perdida (ping falló). Reconectando...");
            // Cerrar la conexión rota antes de abrir una nueva.
            if ($this->conn) {
                @mysqli_close($this->conn);
            }
            $this->connect();
        }
    }

    /**
     * Logger interno: escribe en el error_log de PHP (capturado por Railway).
     */
    private function logError(string $message, array $context = []): void
    {
        $ctx = $context ? ' | Context: ' . json_encode($context) : '';
        error_log("[Skale IA][Database] " . $message . $ctx);
    }

    /**
     * Cierra la conexión y resetea el Singleton.
     * Útil en scripts CLI (migraciones, crons) que necesitan reconectar.
     */
    public static function reset(): void
    {
        if (self::$instance && self::$instance->conn) {
            mysqli_close(self::$instance->conn);
        }
        self::$instance = null;
    }
}
