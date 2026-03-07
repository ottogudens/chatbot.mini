<?php
// Script de prueba de conexión a la base de datos
require_once 'db.php';

header('Content-Type: application/json');

if ($conn) {
    echo json_encode([
        "status" => "success",
        "message" => "Conexión exitosa a la base de datos.",
        "config" => [
            "host" => getenv('MYSQLHOST') ?: "localhost",
            "database" => getenv('MYSQLDATABASE') ?: "chatbot",
            "user" => getenv('MYSQLUSER') ?: "chatbot_user"
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo establecer la conexión."
    ]);
}
?>