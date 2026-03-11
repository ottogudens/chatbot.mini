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
$info_sources_text = "";
$info_sources_files = [];

// 1. Fetch Assistant Config (if any)
$ai_config = [];
if ($assistant_id) {
    // Get assistant + AI config
    $ast_query = "SELECT system_prompt, gemini_model, temperature, max_output_tokens, response_style FROM assistants WHERE id = $assistant_id";
    $ast_res = mysqli_query($conn, $ast_query);
    if ($ast_row = mysqli_fetch_assoc($ast_res)) {
        $custom_system_prompt = $ast_row['system_prompt'] ?? '';
        $ai_config = [
            'model' => $ast_row['gemini_model'] ?? 'gemini-2.5-flash',
            'temperature' => $ast_row['temperature'] ?? 0.7,
            'max_output_tokens' => $ast_row['max_output_tokens'] ?? 1500,
            'response_style' => $ast_row['response_style'] ?? 'balanced',
        ];
    }

    // Get info sources
    $info_query = "SELECT type, content_text, gemini_file_uri, file_type FROM information_sources WHERE assistant_id = $assistant_id";
    $info_res = mysqli_query($conn, $info_query);

    $info_sources_text = "";
    $info_sources_files = [];

    while ($info_row = mysqli_fetch_assoc($info_res)) {
        if ($info_row['type'] === 'text' || $info_row['type'] === 'link') {
            $info_sources_text .= $info_row['content_text'] . "\n\n";
        } elseif (
            ($info_row['type'] === 'file') &&
            !empty($info_row['gemini_file_uri']) &&
            !empty($info_row['file_type'])
        ) {
            // Only include files with a valid URI and mimeType to avoid Gemini INVALID_ARGUMENT errors
            $info_sources_files[] = [
                'uri' => $info_row['gemini_file_uri'],
                'mime_type' => $info_row['file_type']
            ];
        }
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
    $ai_reply = $gemini->get_response($user_msg, $history, $custom_system_prompt, $info_sources_text, $info_sources_files, null, $ai_config);

    // Handle function calling
    if (is_array($ai_reply) && $ai_reply['type'] === 'function_call') {
        require_once 'calendar_functions.php';
        $func_name = $ai_reply['call']['name'];
        $func_args = $ai_reply['call']['args'] ?? [];

        $func_result = "";
        if ($func_name === 'check_availability') {
            $func_result = check_calendar_availability($conn, $assistant_id, $func_args);
        } else if ($func_name === 'book_appointment') {
            $func_result = book_calendar_appointment($conn, $assistant_id, $func_args);
        } else {
            $func_result = "Function not implemented.";
        }

        // Pass result back to Gemini for final response
        $function_state = [
            'call' => $ai_reply['call'],
            'result' => is_array($func_result) ? $func_result : ["message" => $func_result]
        ];

        $ai_reply = $gemini->get_response($user_msg, $history, $custom_system_prompt, $info_sources_text, $info_sources_files, $function_state, $ai_config);
    }

    if (is_string($ai_reply) && !empty($ai_reply)) {
        $reply = $ai_reply;
        $matched = 1; // Mark as matched via AI
    }

    // Clean up any expired Gemini file URIs from the DB so future requests don't fail
    if (!empty($gemini->expired_file_uris)) {
        foreach ($gemini->expired_file_uris as $expired_uri) {
            $safe_uri = mysqli_real_escape_string($conn, $expired_uri);
            mysqli_query($conn, "UPDATE information_sources SET gemini_file_uri = NULL WHERE gemini_file_uri = '$safe_uri'");
            error_log("Cleared expired Gemini file URI from DB: $expired_uri");
        }
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