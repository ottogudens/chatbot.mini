<?php
require 'db.php';

echo "Iniciando migración 6: Agregar columna voice_enabled a assistants...\n";

$sql = "ALTER TABLE assistants ADD COLUMN voice_enabled TINYINT(1) DEFAULT 1 AFTER response_style";

try {
    if (mysqli_query($conn, $sql)) {
        echo "✓ Columna 'voice_enabled' añadida exitosamente.\n";
    } else {
        echo "✗ Error al añadir columna: " . mysqli_error($conn) . "\n";
    }
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "! La columna 'voice_enabled' ya existe. Saltando...\n";
    } else {
        echo "✗ Excepción SQL: " . $e->getMessage() . "\n";
    }
}

echo "Migración completada.\n";
?>