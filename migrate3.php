<?php
require 'db.php';

mysqli_report(MYSQLI_REPORT_OFF);

echo "Iniciando migración 3 (AI config columns)...\n\n";

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// === Parte 1: AI configuration columns for assistants (gemini-2.0-flash) ===
echo "--- Parte 1: assistants (AI config, modelo actualizado) ---\n";
$columns = [
    "ALTER TABLE assistants ADD COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.0-flash'",
    "ALTER TABLE assistants ADD COLUMN temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70",
    "ALTER TABLE assistants ADD COLUMN max_output_tokens INT NOT NULL DEFAULT 1500",
    "ALTER TABLE assistants ADD COLUMN response_style VARCHAR(20) NOT NULL DEFAULT 'balanced'"
];
foreach ($columns as $q) {
    if (mysqli_query($conn, $q)) {
        echo "OK: " . substr($q, 0, 70) . "...\n";
    } else {
        echo "Info (ya existe o no aplica): " . mysqli_error($conn) . "\n";
    }
}

// === Parte 2: Update any existing rows that still use the deprecated model ===
echo "\n--- Parte 2: Actualizando registros con modelo deprecado ---\n";
$update_sql = "UPDATE assistants SET gemini_model='gemini-2.0-flash' WHERE gemini_model IN ('gemini-2.0-flash-lite','gemini-2.5-flash-lite')";
if (mysqli_query($conn, $update_sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "OK: $affected asistentes actualizados a gemini-2.0-flash.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// === Parte 3: Update DB column default to the new model name ===
echo "\n--- Parte 3: Actualizando DEFAULT de columna gemini_model ---\n";
$alter_default = "ALTER TABLE assistants MODIFY COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.0-flash'";
if (mysqli_query($conn, $alter_default)) {
    echo "OK: DEFAULT de gemini_model actualizado a gemini-2.0-flash.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

echo "\n✅ Migración 3 finalizada.\n";
?>