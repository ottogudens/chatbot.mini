<?php
require_once 'db.php';

echo "Iniciando migración 9: Crear tabla generated_documents...\n";

$query = "CREATE TABLE IF NOT EXISTS generated_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    assistant_id INT DEFAULT NULL,
    template_id VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'deleted') DEFAULT 'active'
)";

if (mysqli_query($conn, $query)) {
    echo "✓ Tabla 'generated_documents' creada o ya existía.\n";
} else {
    echo "Error en la migración: " . mysqli_error($conn) . "\n";
}

// Also add a 'description' column to pdf_templates if it doesn't exist
$check_desc = mysqli_query($conn, "SHOW COLUMNS FROM pdf_templates LIKE 'description'");
if (mysqli_num_rows($check_desc) == 0) {
    mysqli_query($conn, "ALTER TABLE pdf_templates ADD COLUMN description TEXT AFTER name");
    echo "✓ Columna 'description' añadida a pdf_templates.\n";
}

echo "Migración completada.\n";
?>
