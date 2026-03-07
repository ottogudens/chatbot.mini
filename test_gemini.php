<?php
require_once 'gemini_client.php';

echo "Probando conexión con Gemini...\n";
$gemini = new GeminiClient();
$resp = $gemini->get_response("Hola, ¿quién eres?");

if ($resp) {
    echo "Respuesta recibida: " . $resp . "\n";
} else {
    echo "Error: No se recibió respuesta de Gemini. Verifica los logs o la API Key.\n";
}
?>