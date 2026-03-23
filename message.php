<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide standard errors for JSON response
header('Content-Type: application/json');

require 'db.php';

// Get and sanitize user message & assistant ID
$user_msg = $_POST['text'] ?? '';
$assistant_id = isset($_POST['assistant_id']) && is_numeric($_POST['assistant_id']) ? intval($_POST['assistant_id']) : null;
$internal_token = $_POST['internal_token'] ?? '';

// Security: Dual-mode authentication
// Mode 1 (WhatsApp bridge / internal): validate secret token
// Mode 2 (Browser): validate same-origin CSRF token
$expected_token = getenv('INTERNAL_TOKEN') ?: 'local_secret_123';
$has_valid_internal_token = ($internal_token === $expected_token && !empty($expected_token));

// Same-origin browser check: Referer or session CSRF token
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf_from_session = isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token']);
$csrf_from_post    = isset($_POST['csrf_token']) && !empty($_POST['csrf_token']);
$has_valid_csrf    = $csrf_from_session && $csrf_from_post && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

if (!$has_valid_internal_token && !$has_valid_csrf) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing auth token.']);
    exit;
}

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
    // Get assistant + AI config — use prepared statement to prevent SQLi
    $ast_stmt = mysqli_prepare($conn, "SELECT client_id, system_prompt, gemini_model, temperature, max_output_tokens, response_style, voice_enabled FROM assistants WHERE id = ?");
    mysqli_stmt_bind_param($ast_stmt, "i", $assistant_id);
    mysqli_stmt_execute($ast_stmt);
    $ast_res = mysqli_stmt_get_result($ast_stmt);
    if ($ast_res && $ast_row = mysqli_fetch_assoc($ast_res)) {
        $client_id = $ast_row['client_id'];
        $custom_system_prompt = $ast_row['system_prompt'] ?? '';
        $voice_enabled = $ast_row['voice_enabled'] ?? 1;
        $ai_config = [
            'model' => $ast_row['gemini_model'] ?? 'gemini-2.5-flash',
            'temperature' => $ast_row['temperature'] ?? 0.7,
            'max_output_tokens' => $ast_row['max_output_tokens'] ?? 1500,
            'response_style' => $ast_row['response_style'] ?? 'balanced',
        ];
    }

    // Get info sources — use prepared statement
    $info_stmt = mysqli_prepare($conn, "SELECT type, content_text, gemini_file_uri, file_type FROM information_sources WHERE assistant_id = ?");
    mysqli_stmt_bind_param($info_stmt, "i", $assistant_id);
    mysqli_stmt_execute($info_stmt);
    $info_res = mysqli_stmt_get_result($info_stmt);

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
        // Normalize accents in keyword too for fair comparison
        $clean_kw = preg_replace('/[áàäâ]/u', 'a', $clean_kw);
        $clean_kw = preg_replace('/[éèëê]/u', 'e', $clean_kw);
        $clean_kw = preg_replace('/[íìïî]/u', 'i', $clean_kw);
        $clean_kw = preg_replace('/[óòöô]/u', 'o', $clean_kw);
        $clean_kw = preg_replace('/[úùüû]/u', 'u', $clean_kw);
        $clean_kw = preg_replace('/[^a-z0-9\s]/i', '', $clean_kw);
        if ($clean_msg === $clean_kw) {
            $reply = $rule['replies'];
            $matched = 1;
            break 2; // Break both loops
        }
    }
}

// 4. Fuzzy/Regex Match Check (If no exact match) — FIX-6: safe regex escaping
if ($matched === 0) {
    foreach ($rules as $rule) {
        // Split by | to handle multiple keywords, escape each one safely
        $keywords = array_map('trim', explode('|', $rule['queries']));
        $escaped = array_map(function ($kw) {
            return preg_quote($kw, '/');
        }, $keywords);
        $pattern = '/\b(' . implode('|', $escaped) . ')\b/iu';
        if (@preg_match($pattern, $clean_msg)) {
            $reply = $rule['replies'];
            $matched = 1;
            break;
        }
    }
}

require_once 'gemini_client.php';
$gemini = new GeminiClient();

// 5. Handle Audio Input (if any) - Moved outside of text check
$audio_file = $_FILES['audio'] ?? null;
$has_audio = false;

if ($audio_file && $audio_file['error'] === UPLOAD_ERR_OK) {
    if (isset($voice_enabled) && $voice_enabled == 0) {
        $reply = "Lo siento, este asistente solo recibe mensajes escritos.";
        $matched = 1;
    } else {
        $upload_path = $audio_file['tmp_name'];
        $mime_type = $audio_file['type'] ?: 'audio/ogg';
        $gemini_uri = $gemini->upload_file_to_gemini($upload_path, $mime_type, 'whatsapp_voice_' . time());

        if ($gemini_uri) {
            $info_sources_files[] = [
                'uri' => $gemini_uri,
                'mime_type' => $mime_type
            ];
            $has_audio = true;
            // If text is empty, set a default prompt for the audio
            if (empty($user_msg)) {
                $user_msg = "El usuario envió un mensaje de voz. Por favor, escúchalo, transcríbelo y responde de forma natural.";
                $clean_msg = "voice_message_auto_prompt"; // Set a dummy clean msg to pass checks if needed
            }
        }
    }
}

// 6. AI Fallback (Gemini)
if ($matched === 0 && (!empty($clean_msg) || $has_audio)) {

    // OPT-3: Gemini Response Cache
    // Only cache plain text-only queries (no audio, no file uploads in context) — TTL 2h
    $use_cache = !$has_audio && empty($info_sources_files) && !empty($clean_msg) && $assistant_id;
    $cache_key  = null;
    $cache_hit  = false;

    if ($use_cache) {
        $cache_key = md5($assistant_id . '::' . mb_strtolower(trim($user_msg)));
        $cache_stmt = mysqli_prepare($conn, "SELECT cached_reply, id FROM gemini_response_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1");
        if ($cache_stmt) {
            mysqli_stmt_bind_param($cache_stmt, "s", $cache_key);
            mysqli_stmt_execute($cache_stmt);
            $cache_res = mysqli_stmt_get_result($cache_stmt);
            if ($cache_row = mysqli_fetch_assoc($cache_res)) {
                $reply      = $cache_row['cached_reply'];
                $matched    = 1;
                $cache_hit  = true;
                // Update hit counter
                $upd = mysqli_prepare($conn, "UPDATE gemini_response_cache SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($upd, "i", $cache_row['id']);
                mysqli_stmt_execute($upd);
            }
        }
    }

    if (!$cache_hit) {
    // Fetch last 5 messages for context
    $history = [];
    $hist_stmt = mysqli_prepare($conn, "SELECT user_message, bot_reply FROM conversation_logs " .
        ($assistant_id ? "WHERE assistant_id = ? " : "WHERE assistant_id IS NULL ") .
        "ORDER BY id DESC LIMIT 5");
    if ($hist_stmt && $assistant_id) {
        mysqli_stmt_bind_param($hist_stmt, "i", $assistant_id);
    }
    if ($hist_stmt) {
        mysqli_stmt_execute($hist_stmt);
        $hist_result = mysqli_stmt_get_result($hist_stmt);
        if ($hist_result) {
            while ($row = mysqli_fetch_assoc($hist_result)) {
                array_unshift($history, $row);
            }
        }
    }

    $ai_reply = $gemini->get_response($user_msg, $history, $custom_system_prompt, $info_sources_text, $info_sources_files, null, $ai_config);

    // Handle function calling
    $had_function_call = false;
    if (is_array($ai_reply) && $ai_reply['type'] === 'function_call') {
        $had_function_call = true;
        require_once 'calendar_functions.php';
        $func_name = $ai_reply['call']['name'];
        $func_args = $ai_reply['call']['args'] ?? [];

        $func_result = "";
        if ($func_name === 'check_availability') {
            $func_result = check_calendar_availability($conn, $assistant_id, $func_args);
        } else if ($func_name === 'book_appointment') {
            $func_result = book_calendar_appointment($conn, $assistant_id, $func_args);
        } else if ($func_name === 'list_pdf_templates') {
            require_once 'pdf_helper.php';
            $pdf_helper = new PDFHelper($conn);
            $func_result = $pdf_helper->list_templates($client_id ?? null);
        } else if ($func_name === 'generate_pdf') {
            require_once 'pdf_helper.php';
            $pdf_helper = new PDFHelper($conn);
            $template_id = $func_args['template_id'] ?? '';
            $data = $func_args['data'] ?? [];
            $func_result = $pdf_helper->generate_from_template($template_id, $data, $client_id ?? null, $assistant_id ?? null);
        } else if ($func_name === 'register_lead') {
            $name  = $func_args['name'] ?? 'S/N';
            $phone = $func_args['phone'] ?? '';
            $email = $func_args['email'] ?? '';
            $notes = $func_args['notes'] ?? '';
            $extra = $func_args['extra_info'] ?? null;

            $stmt_lead = mysqli_prepare($conn, "INSERT INTO leads (client_id, assistant_id, name, phone, email, notes, captured_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_lead, "iisssss", $client_id, $assistant_id, $name, $phone, $email, $notes, $extra);
            if (mysqli_stmt_execute($stmt_lead)) {
                $func_result = "Prospecto '$name' registrado exitosamente en el CRM.";
            } else {
                $func_result = "Error al registrar prospecto: " . mysqli_error($conn);
            }
        } else {
            $func_result = "Function not implemented.";
        }

        // Pass result back to Gemini for final response
        $function_state = [
            'call'   => $ai_reply['call'],
            'result' => is_array($func_result) ? $func_result : ["message" => $func_result]
        ];

        $ai_reply = $gemini->get_response($user_msg, $history, $custom_system_prompt, $info_sources_text, $info_sources_files, $function_state, $ai_config);
    }

    if (is_string($ai_reply) && !empty($ai_reply)) {
        $reply   = $ai_reply;
        $matched = 1; // Mark as matched via AI

        // OPT-3: Store in cache only if: plain text, no function call results (time-sensitive), not expired
        if ($use_cache && $cache_key && !$had_function_call) {
            $cache_ttl    = 7200; // 2 hours
            $expires_at   = date('Y-m-d H:i:s', time() + $cache_ttl);
            $cache_ins = mysqli_prepare($conn,
                "INSERT INTO gemini_response_cache (cache_key, assistant_id, user_message, cached_reply, expires_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE cached_reply=VALUES(cached_reply), expires_at=VALUES(expires_at), hit_count=1"
            );
            if ($cache_ins) {
                mysqli_stmt_bind_param($cache_ins, "sisss", $cache_key, $assistant_id, $user_msg, $reply, $expires_at);
                mysqli_stmt_execute($cache_ins);
            }
        }
    }
    } // end if (!$cache_hit)

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