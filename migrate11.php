<?php
require_once 'db.php';

echo "Iniciando migración 11: Agregar columnas de canvas a pdf_templates...\n";

// 1. Add template_config (JSON) column
$check = mysqli_query($conn, "SHOW COLUMNS FROM pdf_templates LIKE 'template_config'");
if (mysqli_num_rows($check) == 0) {
    if (mysqli_query($conn, "ALTER TABLE pdf_templates ADD COLUMN template_config LONGTEXT DEFAULT NULL AFTER placeholders")) {
        echo "✓ Columna 'template_config' añadida a pdf_templates.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "! La columna 'template_config' ya existe. Saltando...\n";
}

// 2. Add doc_type column
$check2 = mysqli_query($conn, "SHOW COLUMNS FROM pdf_templates LIKE 'doc_type'");
if (mysqli_num_rows($check2) == 0) {
    if (mysqli_query($conn, "ALTER TABLE pdf_templates ADD COLUMN doc_type VARCHAR(50) DEFAULT 'generic' AFTER description")) {
        echo "✓ Columna 'doc_type' añadida a pdf_templates.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "! La columna 'doc_type' ya existe. Saltando...\n";
}

// 3. Add preview_url column
$check3 = mysqli_query($conn, "SHOW COLUMNS FROM pdf_templates LIKE 'preview_url'");
if (mysqli_num_rows($check3) == 0) {
    if (mysqli_query($conn, "ALTER TABLE pdf_templates ADD COLUMN preview_url VARCHAR(500) DEFAULT NULL AFTER doc_type")) {
        echo "✓ Columna 'preview_url' añadida a pdf_templates.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "! La columna 'preview_url' ya existe. Saltando...\n";
}

echo "Migración 11 completada.\n";
?>
