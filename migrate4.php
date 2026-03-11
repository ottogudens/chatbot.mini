<?php
require 'db.php';
mysqli_report(MYSQLI_REPORT_OFF);

echo "Iniciando migración 4 (corrección modelo a gemini-2.0-flash)...\n\n";

// Fix any assistants still using deprecated/incompatible models
$models_to_fix = ['gemini-2.0-flash-lite', 'gemini-2.5-flash-lite'];
foreach ($models_to_fix as $old_model) {
    $safe = mysqli_real_escape_string($conn, $old_model);
    if (mysqli_query($conn, "UPDATE assistants SET gemini_model='gemini-2.5-flash' WHERE gemini_model='$safe'")) {
        $n = mysqli_affected_rows($conn);
        if ($n > 0)
            echo "OK: $n asistentes actualizados de '$old_model' a 'gemini-2.5-flash'.\n";
    }
}

// Fix the column DEFAULT back to gemini-2.0-flash
if (mysqli_query($conn, "ALTER TABLE assistants MODIFY COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.5-flash'")) {
    echo "OK: DEFAULT de gemini_model corregido a 'gemini-2.5-flash'.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Verify
$res = mysqli_query($conn, "SHOW COLUMNS FROM assistants LIKE 'gemini_model'");
$row = mysqli_fetch_assoc($res);
echo "\nEstado actual:\n";
echo "  Campo: " . $row['Field'] . "\n";
echo "  Default: " . $row['Default'] . "\n";

echo "\n✅ Migración 4 finalizada.\n";
?>