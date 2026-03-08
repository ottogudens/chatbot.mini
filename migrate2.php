<?php
require 'db.php';

echo "Iniciando migración de la base de datos (Parte 2: Soporte para Archivos y RAG)...\n";

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Ensure the ENUM type and logic exists for information_sources
$alter_query = "
ALTER TABLE information_sources 
    ADD COLUMN type ENUM('text', 'file', 'link') DEFAULT 'text' AFTER assistant_id,
    MODIFY content_text MEDIUMTEXT NULL,
    ADD COLUMN file_path VARCHAR(255) NULL AFTER content_text,
    ADD COLUMN file_type VARCHAR(50) NULL AFTER file_path,
    ADD COLUMN file_size BIGINT NULL AFTER file_type,
    ADD COLUMN gemini_file_uri VARCHAR(255) NULL AFTER file_size
";

if (mysqli_query($conn, $alter_query)) {
    echo "Tabla information_sources alterada exitosamente con las nuevas columnas.\n";
} else {
    echo "Error alterando information_sources: " . mysqli_error($conn) . "\n";
}

echo "Migración finalizada.\n";
?>