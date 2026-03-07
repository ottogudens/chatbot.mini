<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide standard errors for JSON response
header('Content-Type: application/json');

require 'db.php';

// Get and sanitize user message
$user_msg = isset($_POST['text']) ? $_POST['text'] : '';
$clean_msg = strtolower(trim($user_msg));
// Remove accents and special punctuation to improve matching
$clean_msg = preg_replace('/[áàäâ]/u', 'a', $clean_msg);
$clean_msg = preg_replace('/[éèëê]/u', 'e', $clean_msg);
$clean_msg = preg_replace('/[íìïî]/u', 'i', $clean_msg);
$clean_msg = preg_replace('/[óòöô]/u', 'o', $clean_msg);
$clean_msg = preg_replace('/[úùüû]/u', 'u', $clean_msg);
$clean_msg = preg_replace('/[^a-z0-9\s]/i', '', $clean_msg);

$reply = "";
$matched = 0;
$suggestions = [];

// Fetch all rules from database
$query = "SELECT queries, replies, category FROM chatbot";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['reply' => 'Error consultando la base de datos.', 'matched' => false]);
    exit;
}

$rules = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rules[] = $row;
}

// 1. Exact Match Check (Highest Priority)
foreach ($rules as $rule) {
    $keywords = explode('|', $rule['queries']);
    foreach ($keywords as $keyword) {
        $clean_kw = strtolower(trim($keyword));
        if ($clean_msg === $clean_kw) {
            $reply = $rule['replies'];
            $matched = 1;
            break 2; // Break both loops
        }
    }
}

// 2. Fuzzy/Regex Match Check (If no exact match)
if ($matched === 0) {
    foreach ($rules as $rule) {
        // Build regex looking for whole words
        // Transform "hola|buenas" into "\b(hola|buenas)\b"
        $pattern = '/\b(' . str_replace(' ', '\s+', $rule['queries']) . ')\b/i';

        if (preg_match($pattern, $clean_msg)) {
            $reply = $rule['replies'];
            $matched = 1;
            break;
        }
    }
}

// 3. AI Fallback (New Stage 2)
if ($matched === 0 && !empty($clean_msg)) {
    require_once 'gemini_client.php';
    $gemini = new GeminiClient();
    $ai_reply = $gemini->get_response($user_msg);

    if ($ai_reply) {
        $reply = $ai_reply;
        $matched = 1; // Mark as matched via AI
    }
}

// 4. Final Fallback and Suggestions (If AI also fails or is not configured)
if ($matched === 0) {
    if (empty($clean_msg)) {
        $reply = "Por favor, escribe algo para poder ayudarte.";
    } else {
        $reply = "¡Lo siento! Aún no entiendo eso. Todavía estoy aprendiendo.\n\n¿Tal vez podrías preguntarme alguna de estas cosas?";
        // Get 3 random queries for suggestions
        $sugg_query = "SELECT queries FROM chatbot ORDER BY RAND() LIMIT 3";
        $sugg_res = mysqli_query($conn, $sugg_query);
        while ($row = mysqli_fetch_assoc($sugg_res)) {
            $opt = explode('|', $row['queries'])[0]; // Show only the first variation
            $suggestions[] = ucfirst($opt);
        }
    }
}

// Convert newline characters in replies to HTML <br> if any
$reply = nl2br($reply);

// Log conversation
$stmt = mysqli_prepare($conn, "INSERT INTO conversation_logs (user_message, bot_reply, matched) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssi", $user_msg, $reply, $matched);
mysqli_stmt_execute($stmt);

// Send JSON response
echo json_encode([
    'reply' => $reply,
    'matched' => (bool) $matched,
    'suggestions' => $suggestions,
    'timestamp' => date('H:i')
]);
?>