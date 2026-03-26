<?php
require_once 'db.php';

// 1. Create Internal Support Assistant
$name = "Asistente de Soporte Skale IA";
$prompt = "Eres el Asistente de Soporte Oficial de Skale IA. Tu única función es ayudar a los administradores y usuarios con el manejo, configuración y dudas técnicas sobre la aplicación.

REGLAS CRÍTICAS:
1. SOLO respondes sobre temas referentes a la aplicación Skale IA (sus módulos, funciones, integraciones).
2. Si el usuario pregunta por temas externos (cocina, deportes, política, etc.), responde: 'Lo siento, mi conocimiento está limitado únicamente al funcionamiento de Skale IA. ¿En qué módulo de la aplicación puedo ayudarte hoy?'
3. Utiliza la base de conocimiento adjunta (help_manual.md) para dar respuestas precisas.
4. Sé profesional, amable y conciso. Si mencionas un botón o sección, usa negritas para resaltarlo.

Tu objetivo es ser el manual virtual más eficiente para que los clientes aprovechen la plataforma al máximo.";

$model = "models/gemini-1.5-flash"; // Common default in this app
$temp = 0.3; // Lower temperature for more factual help
$tokens = 2048;
$style = "factual";

// Find or create a 'System' client (ID 1 is usually the first client, but let's check what client_id 0 or 1 is)
$client_id = 1; // Default to client 1 for support

// Check if already exists
$chk = mysqli_query($conn, "SELECT id FROM assistants WHERE name = '$name' LIMIT 1");
if (mysqli_num_rows($chk) > 0) {
    $row = mysqli_fetch_assoc($chk);
    $assistant_id = $row['id'];
    echo "Assistant already exists (ID: $assistant_id). Updating...\n";
    $stmt = mysqli_prepare($conn, "UPDATE assistants SET system_prompt = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $prompt, $assistant_id);
    mysqli_stmt_execute($stmt);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO assistants (client_id, name, system_prompt, gemini_model, temperature, max_output_tokens, response_style) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isssdis", $client_id, $name, $prompt, $model, $temp, $tokens, $style);
    mysqli_stmt_execute($stmt);
    $assistant_id = mysqli_insert_id($conn);
    echo "Support Assistant created (ID: $assistant_id).\n";
}

// 2. Add help_manual.md as Information Source (as TEXT for immediate availability)
$source_type = 'text';
$source_title = 'Manual de Usuario (Contenido)';
$source_content = file_exists('help_manual.md') ? file_get_contents('help_manual.md') : 'Contenido del manual no encontrado.';

$chk_source = mysqli_query($conn, "SELECT id FROM information_sources WHERE assistant_id = $assistant_id AND title = '$source_title' LIMIT 1");
if (mysqli_num_rows($chk_source) == 0) {
    $stmt = mysqli_prepare($conn, "INSERT INTO information_sources (assistant_id, type, title, content_text) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isss", $assistant_id, $source_type, $source_title, $source_content);
    mysqli_stmt_execute($stmt);
    echo "Knowledge base content loaded as text source.\n";
} else {
    $stmt = mysqli_prepare($conn, "UPDATE information_sources SET content_text = ? WHERE assistant_id = ? AND title = ?");
    mysqli_stmt_bind_param($stmt, "sis", $source_content, $assistant_id, $source_title);
    mysqli_stmt_execute($stmt);
    echo "Knowledge base content updated.\n";
}

echo "Migration 12 (Support Assistant) completed successfully.\n";
