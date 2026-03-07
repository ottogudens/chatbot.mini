<?php
require 'db.php';

echo "Iniciando migración de la base de datos...\n";

$queries = [
    "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS assistants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        system_prompt TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS information_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        content_text MEDIUMTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE
    )"
];

foreach ($queries as $index => $query) {
    if (mysqli_query($conn, $query)) {
        echo "Tabla $index creada/verificada.\n";
    } else {
        echo "Error en tabla $index: " . mysqli_error($conn) . "\n";
    }
}

// Agregar columnas a tablas existentes
$alter_chatbot = "ALTER TABLE chatbot ADD COLUMN assistant_id INT NULL AFTER id, ADD FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE";
if (mysqli_query($conn, $alter_chatbot)) {
    echo "Columna assistant_id agregada a chatbot.\n";
} else {
    echo "Error alterando chatbot (puede que ya exista): " . mysqli_error($conn) . "\n";
}

$alter_logs = "ALTER TABLE conversation_logs ADD COLUMN assistant_id INT NULL AFTER id, ADD FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE";
if (mysqli_query($conn, $alter_logs)) {
    echo "Columna assistant_id agregada a conversation_logs.\n";
} else {
    echo "Error alterando conversation_logs (puede que ya exista): " . mysqli_error($conn) . "\n";
}

echo "Migración finalizada.\n";
?>