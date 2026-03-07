<?php
// Configuración de la base de datos adaptable (Local y Railway)
$db_host = getenv('MYSQLHOST') ?: "localhost";
$db_user = getenv('MYSQLUSER') ?: "chatbot_user";
$db_pass = getenv('MYSQLPASSWORD') ?: "Ing3N3tZ##";
$db_name = getenv('MYSQLDATABASE') ?: "chatbot";
$db_port = getenv('MYSQLPORT') ?: "3306";

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    die(json_encode([
        "error" => true,
        "message" => "Error de conexión a la base de datos: " . $e->getMessage()
    ]));
}
?>