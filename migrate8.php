<?php
require_once 'db.php';

echo "Iniciando migración 8: Actualizar modelos obsoletos a gemini-2.5-flash...\n";

// Update all assistants using gemini-2.0-flash or gemini-2.5-flash-lite (the one that caused 400 error earlier)
$query = "UPDATE assistants SET gemini_model = 'gemini-2.5-flash' WHERE gemini_model IN ('gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.5-flash-lite', 'gemini-2.5-flash-lite-001')";

if (mysqli_query($conn, $query)) {
    echo "Migración completada. Modelos actualizados.\n";
} else {
    echo "Error en la migración: " . mysqli_error($conn) . "\n";
}
?>
