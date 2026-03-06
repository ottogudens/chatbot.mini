<?php
// Configuración de la base de datos centralizada
$db_host = "localhost";
$db_user = "chatbot_user";
$db_pass = "Ing3N3tZ##";
$db_name = "chatbot";

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    die(json_encode([
        "error" => true,
        "message" => "Error de conexión a la base de datos: " . $e->getMessage()
    ]));
}
?>