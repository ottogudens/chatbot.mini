<?php
/**
 * db.php — Skale IA Bootstrap de Base de Datos
 * ============================================================
 * Bootstrap de conexión: inicializa la clase Database (Singleton)
 * y expone $conn como variable global para compatibilidad con código legado.
 *
 * ARQUITECTURA:
 *   La lógica de conexión reside en Database.php.
 *   Este archivo actúa como puente durante la migración gradual.
 *   Objetivo final: todos los módulos usarán Database::getInstance().
 */

// Zona horaria del sistema (Chile)
date_default_timezone_set('America/Santiago');

// ── Cargar la clase Singleton ────────────────────────────────────────────────
require_once __DIR__ . '/Database.php';

// ── Inicializar la conexión y manejar errores de arranque ───────────────────
try {
    $db   = Database::getInstance();
    // COMPAT: Exponer $conn global para el código legado (api.php, message.php, etc.)
    // TODO: Reemplazar usos de $conn por $db->getConnection() progresivamente.
    $conn = $db->getConnection();

} catch (\RuntimeException $e) {
    // SEC: El error real ya fue loggeado por la clase Database.
    // Solo mostramos un mensaje genérico al exterior.
    error_log("[db.php] Fallo en bootstrap de BD: " . $e->getMessage());

    // Detectar si la petición espera JSON (AJAX/API) o HTML (navegador).
    $is_api_request = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
        str_contains($_SERVER['REQUEST_URI'] ?? '', 'api.php') ||
        str_contains($_SERVER['REQUEST_URI'] ?? '', 'message.php')
    );

    if ($is_api_request) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(503);
        die(json_encode([
            'status'  => 'error',
            'error'   => true,
            'message' => 'Servicio temporalmente no disponible. Por favor, inténtalo de nuevo en unos momentos.',
        ]));
    }

    // Respuesta HTML para acceso directo desde navegador.
    http_response_code(503);
    die("
        <div style='color:#c0392b; font-family:system-ui,sans-serif; padding:24px;
                    border:1px solid #e74c3c; background:#fff5f5; border-radius:10px;
                    max-width:560px; margin:40px auto; box-shadow:0 2px 8px rgba(0,0,0,.08);'>
            <h3 style='margin-top:0'>⚠️ Error de Base de Datos</h3>
            <p>No se pudo conectar al servidor de datos.<br>Por favor, contacta al administrador.</p>
        </div>
    ");
}

// ── Helpers de respuesta y logging (usados globalmente) ─────────────────────

/**
 * Emite una respuesta JSON estandarizada y termina la ejecución.
 *
 * @param string     $status  'success' | 'error' | 'warning'
 * @param string     $message Mensaje legible para el cliente.
 * @param array|null $data    Payload adicional (opcional).
 */
function send_response(string $status, string $message = '', $data = null): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $payload = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Loggea un mensaje con contexto opcional en el error_log de PHP.
 * Railway captura stdout/stderr de PHP-FPM automáticamente.
 *
 * @param string $message Descripción del error o evento.
 * @param array  $context Datos adicionales para debugging.
 */
function log_error(string $message, array $context = []): void
{
    $ctx_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[Skale IA] ' . $message . $ctx_str);
}