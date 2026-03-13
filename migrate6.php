<?php
require 'db.php';

echo "Iniciando migración 6: Agregar columna voice_enabled a assistants...\n";

$sql = "ALTER TABLE assistants ADD COLUMN voice_enabled TINYINT(1) DEFAULT 1 AFTER response_style";

if (mysqli_query($conn, $sql)) {
    echo "✓ Columna 'voice_enabled' añadida exitosamente.\n";
} else {
    $error = mysqli_error($conn);
    if (strpos($error, "Duplicate column name") !== false) {
        echo "! La columna 'voice_enabled' ya existe. Saltando...\n";
    } else {
        echo "✗ Error al añadir columna: " . $error . "\n";
        exit(1);
    }
}

echo "Migración completada.\n";
?>