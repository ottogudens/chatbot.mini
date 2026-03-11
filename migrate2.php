<?php
require 'db.php';

// Disable strict error throwing from mysqli to handle duplicates gracefully
mysqli_report(MYSQLI_REPORT_OFF);

echo "Iniciando migración de la base de datos...\n\n";

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// === Parte 2: Columns for information_sources (split into individual statements) ===
echo "--- Parte 2: information_sources ---\n";
$part2_columns = [
    "ALTER TABLE information_sources ADD COLUMN type ENUM('text', 'file', 'link') DEFAULT 'text' AFTER assistant_id",
    "ALTER TABLE information_sources MODIFY content_text MEDIUMTEXT NULL",
    "ALTER TABLE information_sources ADD COLUMN file_path VARCHAR(255) NULL AFTER content_text",
    "ALTER TABLE information_sources ADD COLUMN file_type VARCHAR(50) NULL AFTER file_path",
    "ALTER TABLE information_sources ADD COLUMN file_size BIGINT NULL AFTER file_type",
    "ALTER TABLE information_sources ADD COLUMN gemini_file_uri VARCHAR(255) NULL AFTER file_size"
];
foreach ($part2_columns as $q) {
    if (mysqli_query($conn, $q)) {
        echo "OK: " . substr($q, 0, 60) . "...\n";
    } else {
        echo "Info (ya existe o no aplica): " . mysqli_error($conn) . "\n";
    }
}

// === Parte 3: AI configuration columns for assistants ===
echo "\n--- Parte 3: assistants (AI config) ---\n";
$part3_columns = [
    "ALTER TABLE assistants ADD COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.5-flash-lite'",
    "ALTER TABLE assistants ADD COLUMN temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70",
    "ALTER TABLE assistants ADD COLUMN max_output_tokens INT NOT NULL DEFAULT 1500",
    "ALTER TABLE assistants ADD COLUMN response_style VARCHAR(20) NOT NULL DEFAULT 'balanced'"
];
foreach ($part3_columns as $q) {
    if (mysqli_query($conn, $q)) {
        echo "OK: " . substr($q, 0, 60) . "...\n";
    } else {
        echo "Info (ya existe o no aplica): " . mysqli_error($conn) . "\n";
    }
}

echo "\n✅ Migración finalizada.\n";
?>