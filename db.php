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
    // Return JSON error if it's an API/AJAX call or HTML if it's a direct page
    $msg = "Error de conexión: " . $e->getMessage();
    $req_uri = $_SERVER['REQUEST_URI'] ?? '';
    $http_accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($req_uri, '.php') !== false && strpos($http_accept, 'application/json') === false) {
        die("<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#fff5f5;'>
                <h3>⚠️ Error de Base de Datos</h3>
                <p>$msg</p>
                <p><b>Sugerencia:</b> Verifica que las Variables de Entorno (MYSQLHOST, MYSQLUSER, etc.) estén configuradas en el dashboard de Railway y que el servicio de base de datos esté activo.</p>
              </div>");
    }
    die(json_encode(["error" => true, "message" => $msg]));
}
?>