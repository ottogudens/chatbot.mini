<?php
// Set local timezone (Chile)
date_default_timezone_set('America/Santiago');

// Configuración de la base de datos adaptable (Local y Railway)
$db_host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: "localhost";
$db_user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: "root";
$db_pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: "";
$db_name = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: "chatbot";
$db_port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: "3306";

// Force 127.0.0.1 if localhost to avoid unix socket errors in some environments
if ($db_host === "localhost" && !getenv('MYSQLHOST')) {
    // $db_host = "127.0.0.1";
}

try {
    // Basic connectivity check
    if (!$db_host || $db_host === "localhost") {
        // If we are on Railway and host is localhost, something is wrong with env vars
    }

    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

    if (!$conn) {
        throw new Exception(mysqli_connect_error() . " (Host: $db_host, Port: $db_port)");
    }

    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    // SEC: Log full error internally, never expose DB details to user
    error_log("DB Connection Error: " . $e->getMessage());
    
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
               (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) ||
               (strpos($_SERVER['REQUEST_URI'] ?? '', 'api.php') !== false) ||
               (strpos($_SERVER['REQUEST_URI'] ?? '', 'message.php') !== false);

    if ($is_ajax) {
        header('Content-Type: application/json');
        die(json_encode([
            "status" => "error",
            "error" => true, 
            "message" => "Error de conexión a la base de datos. Verifique la configuración del servidor."
        ]));
    }

    // Fallback for direct browser access (HTML)
    die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#fff5f5; border-radius:8px; max-width:600px; margin:20px auto;'>
            <h3>⚠️ Error de Base de Datos</h3>
            <p>No se pudo conectar a la base de datos. Contacta al administrador del sistema.</p>
            <small style='color:#666;'>Detalle técnico: La conexión ha sido rechazada o el servidor no responde.</small>
          </div>");
}
?>