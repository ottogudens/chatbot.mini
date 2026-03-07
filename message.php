<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide standard errors for JSON response
header('Content-Type: application/json');

require 'db.php';

// Get and sanitize user message & assistant ID
$user_msg = isset($_POST['text']) ? $_POST['text'] : '';
$assistant_id = isset($_POST['assistant_id']) && is_numeric($_POST['assistant_id']) ? intval($_POST['assistant_id']) : null;

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

$custom_system_prompt = "";
$info_sources = "";

// 1. Fetch Assistant Config (if any)
if ($assistant_id) {
    // Get assistant
    $ast_query = "SELECT system_prompt FROM assistants WHERE id = $assistant_id";
    $ast_res = mysqli_query($conn, $ast_query);
    if ($ast_row = mysqli_fetch_assoc($ast_res)) {
        $custom_system_prompt = $ast_row['system_prompt'] ?? '';
    }

    // Get info sources
    $info_query = "SELECT content_text FROM information_sources WHERE assistant_id = $assistant_id";
    $info_res = mysqli_query($conn, $info_query);
    while ($info_row = mysqli_fetch_assoc($info_res)) {
        $info_sources .= $info_row['content_text'] . "\n\n";
    }
}

// 2. Fetch specific and global rules
$rules_query = "SELECT queries, replies, category FROM chatbot WHERE assistant_id IS NULL";
if ($assistant_id) {
    $rules_query .= " OR assistant_id = $assistant_id";
}
$result = mysqli_query($conn, $rules_query);

$rules = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rules[] = $row;
    }
}

// 3. Exact Match Check (Highest Priority)
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

// 4. Fuzzy/Regex Match Check (If no exact match)
if ($matched === 0) {
    foreach ($rules as $rule) {
        $pattern = '/\b(' . str_replace(' ', '\s+', $rule['queries']) . ')\b/i';
        if (preg_match($pattern, $clean_msg)) {
            $reply = $rule['replies'];
            $matched = 1;
            break;
        }
    }
}

// 5. AI Fallback (Gemini)
if ($matched === 0 && !empty($clean_msg)) {
    // Fetch last 5 messages for context
    $history = [];
    $hist_query = "SELECT user_message, bot_reply FROM conversation_logs ";
    $hist_query .= $assistant_id ? "WHERE assistant_id = $assistant_id " : "WHERE assistant_id IS NULL ";
    $hist_query .= "ORDER BY id DESC LIMIT 5";

    $hist_result = mysqli_query($conn, $hist_query);
    if ($hist_result) {
        while ($row = mysqli_fetch_assoc($hist_result)) {
            array_unshift($history, $row);
        }
    }

    require_once 'gemini_client.php';
    $gemini = new GeminiClient();
    $ai_reply = $gemini->get_response($user_msg, $history, $custom_system_prompt, $info_sources);

    if ($ai_reply) {
        $reply = $ai_reply;
        $matched = 1; // Mark as matched via AI
    }
}

// 6. Final Fallback and Suggestions
if ($matched === 0) {
    if (empty($clean_msg)) {
        $reply = "Por favor, escribe algo para poder ayudarte.";
    } else {
        $reply = "¡Lo siento! Aún no entiendo eso. Todavía estoy aprendiendo.\n\n¿Tal vez podrías preguntarme alguna de estas cosas?";
        // Get 3 random queries for suggestions (from rules applying to this assistant)
        $sugg_query = "SELECT queries FROM chatbot WHERE assistant_id IS NULL";
        if ($assistant_id)
            $sugg_query .= " OR assistant_id = $assistant_id";
        $sugg_query .= " ORDER BY RAND() LIMIT 3";

        $sugg_res = mysqli_query($conn, $sugg_query);
        if ($sugg_res) {
            while ($row = mysqli_fetch_assoc($sugg_res)) {
                $opt = explode('|', $row['queries'])[0]; // Show only the first variation
                $suggestions[] = ucfirst($opt);
            }
        }
    }
}

// Convert newline characters in replies to HTML <br> if any
$reply = nl2br($reply);

// Log conversation
$stmt = mysqli_prepare($conn, "INSERT INTO conversation_logs (assistant_id, user_message, bot_reply, matched) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "issi", $assistant_id, $user_msg, $reply, $matched);
mysqli_stmt_execute($stmt);

// Send JSON response
echo json_encode([
    'reply' => $reply,
    'matched' => (bool) $matched,
    'suggestions' => $suggestions,
    'timestamp' => date('H:i')
]);
?>