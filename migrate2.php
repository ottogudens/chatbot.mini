<?php
require 'db.php';

echo "Iniciando migración de la base de datos (Parte 2: Soporte para Archivos y RAG)...\n";

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Parte 2: Ensure the ENUM type and logic exists for information_sources
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
    echo "Info (information_sources): " . mysqli_error($conn) . "\n";
}

// === Parte 3: AI Configuration per assistant ===
echo "Agregando columnas de configuración IA a la tabla assistants...\n";

$ai_columns = [
    "ALTER TABLE assistants ADD COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.0-flash-lite'",
    "ALTER TABLE assistants ADD COLUMN temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70",
    "ALTER TABLE assistants ADD COLUMN max_output_tokens INT NOT NULL DEFAULT 1500",
    "ALTER TABLE assistants ADD COLUMN response_style VARCHAR(20) NOT NULL DEFAULT 'balanced'"
];

foreach ($ai_columns as $q) {
    if (mysqli_query($conn, $q)) {
        echo "OK: Columna agregada exitosamente.\n";
    } else {
        echo "Info: " . mysqli_error($conn) . "\n"; // 'Duplicate column' is safe to ignore
    }
}

echo "Migración finalizada.\n";
?>