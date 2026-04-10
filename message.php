<?php
/**
 * message.php — Chat endpoint
 * Handles incoming messages from the browser chat UI and the WhatsApp bridge.
 *
 * Architecture (INFRA-3):
 *   - Auth:          Dual-mode (CSRF session for browser, INTERNAL_TOKEN for bridge)
 *   - Matching:      ChatbotRouter::match() — exact + fuzzy rule matching
 *   - AI fallback:   AIHandler::getReply()  — Gemini + function calling + cache
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require 'db.php';
require_once 'ChatbotRouter.php';
require_once 'AIHandler.php';
require_once 'FlowManager.php';

// ─── 1. Auth ─────────────────────────────────────────────────────────────────

$user_msg     = $_POST['text']         ?? '';
$assistant_id = isset($_POST['assistant_id']) && is_numeric($_POST['assistant_id'])
    ? intval($_POST['assistant_id']) : null;
$internal_token   = $_POST['internal_token'] ?? '';
$expected_token   = getenv('INTERNAL_TOKEN') ?: '';
$has_valid_bridge = (!empty($expected_token) && $internal_token === $expected_token);

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf_from_session = isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token']);
$csrf_from_post    = isset($_POST['csrf_token'])    && !empty($_POST['csrf_token']);
$has_valid_csrf    = $csrf_from_session && $csrf_from_post
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

if (!$has_valid_bridge && !$has_valid_csrf) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing auth token.']);
    exit;
}

// ─── 2. Normalize message ────────────────────────────────────────────────────

$clean_msg = ChatbotRouter::normalize($user_msg);

$reply       = '';
$matched     = 0;
$suggestions = [];
$interactive = null;
$type        = 'text';

// ─── 3. Flow & Handover Check ────────────────────────────────────────────────
$remote_jid = $_POST['remote_jid'] ?? 'browser_user';
$flowManager = new FlowManager($conn, $assistant_id, $remote_jid);

// If paused for handover, stop AI
if ($flowManager->isPaused()) {
    echo json_encode(['status' => 'paused', 'message' => 'Bot is paused for human handover.']);
    exit;
}

// Process Flow
$interactive_id = $_POST['interactive_id'] ?? null;
$flowResult = $flowManager->process($user_msg, $interactive_id);
if ($flowResult) {
    $reply = $flowResult['reply'];
    $interactive = $flowResult['interactive'];
    $type = $flowResult['type'];
    $matched = 1;
}

// ─── 3. Assistant config ─────────────────────────────────────────────────────

$client_id          = null;
$custom_system_prompt = '';
$info_sources_text  = '';
$info_sources_files = [];
$voice_enabled      = 1;
$ai_config          = [];

if ($assistant_id) {
    $ast_stmt = mysqli_prepare($conn,
        "SELECT client_id, system_prompt, gemini_model, temperature, max_output_tokens, response_style, voice_enabled
         FROM assistants WHERE id = ?"
    );
    mysqli_stmt_bind_param($ast_stmt, "i", $assistant_id);
    mysqli_stmt_execute($ast_stmt);
    $ast_res = mysqli_stmt_get_result($ast_stmt);
    if ($ast_res && $ast_row = mysqli_fetch_assoc($ast_res)) {
        $client_id          = $ast_row['client_id'];
        $custom_system_prompt = $ast_row['system_prompt'] ?? '';
        $voice_enabled      = $ast_row['voice_enabled'] ?? 1;
        $ai_config = [
            'model'            => $ast_row['gemini_model']      ?? 'gemini-2.0-flash',
            'temperature'      => $ast_row['temperature']       ?? 0.7,
            'max_output_tokens'=> $ast_row['max_output_tokens'] ?? 1500,
            'response_style'   => $ast_row['response_style']    ?? 'balanced',
        ];
    }

    $info_stmt = mysqli_prepare($conn,
        "SELECT type, content_text, gemini_file_uri, file_type FROM information_sources WHERE assistant_id = ?"
    );
    mysqli_stmt_bind_param($info_stmt, "i", $assistant_id);
    mysqli_stmt_execute($info_stmt);
    $info_res = mysqli_stmt_get_result($info_stmt);

    while ($info_row = mysqli_fetch_assoc($info_res)) {
        if ($info_row['type'] === 'text' || $info_row['type'] === 'link') {
            $info_sources_text .= $info_row['content_text'] . "\n\n";
        } elseif ($info_row['type'] === 'file' && !empty($info_row['gemini_file_uri']) && !empty($info_row['file_type'])) {
            $info_sources_files[] = [
                'uri'       => $info_row['gemini_file_uri'],
                'mime_type' => $info_row['file_type'],
            ];
        }
    }

    // --- Safety Limits (TOKEN-FIX) ---
    $max_text_chars = 100000; // ~25k tokens
    if (mb_strlen($info_sources_text) > $max_text_chars) {
        $info_sources_text = mb_substr($info_sources_text, 0, $max_text_chars) . "\n... [Contenido truncado por longitud]";
        error_log("Assistant $assistant_id: Info sources text truncated (> $max_text_chars chars)");
    }

    $max_files = 10;
    if (count($info_sources_files) > $max_files) {
        $info_sources_files = array_slice($info_sources_files, 0, $max_files);
        error_log("Assistant $assistant_id: Info sources files limited (> $max_files files)");
    }
}

// ─── 4. Rule-based matching (ChatbotRouter) ───────────────────────────────────

$router = new ChatbotRouter($conn, $assistant_id);

if (!empty($clean_msg)) {
    $match = $router->match($clean_msg);
    if ($match) {
        $reply   = $match['reply'];
        $matched = 1;
    }
}

// ─── 5. Audio handling ───────────────────────────────────────────────────────

$audio_file = $_FILES['audio'] ?? null;
$has_audio  = false;

if ($matched === 0 && $audio_file && $audio_file['error'] === UPLOAD_ERR_OK) {
    if ($voice_enabled == 0) {
        $reply   = "Lo siento, este asistente solo recibe mensajes escritos.";
        $matched = 1;
    } else {
        $ai = new AIHandler($conn, $assistant_id, $client_id);
        $mime_type = $audio_file['type'] ?: 'audio/ogg';
        $audio_ref = $ai->handleAudio($audio_file, $mime_type);
        if ($audio_ref) {
            $info_sources_files[] = $audio_ref;
            $has_audio = true;
            if (empty($user_msg)) {
                $user_msg  = "El usuario envió un mensaje de voz. Por favor, escúchalo, transcríbelo y responde de forma natural.";
                $clean_msg = "voice_message_auto_prompt";
            }
        }
    }
}

// ─── 6. AI fallback (AIHandler) ──────────────────────────────────────────────

if ($matched === 0 && (!empty($clean_msg) || $has_audio)) {
    // Caching only for plain text without file context
    $can_cache = !$has_audio && empty($info_sources_files) && !empty($clean_msg) && $assistant_id;

    $ai    = new AIHandler($conn, $assistant_id, $client_id);
    
    // System instruction for Handover
    $handover_instruction = "\n\nIMPORTANTE: Si el usuario solicita hablar con un humano o un agente, o si detectas que la situación requiere atención humana urgente, responde incluyendo la etiqueta [HANDOVER] al final de tu mensaje.";
    $full_prompt = $custom_system_prompt . $handover_instruction;

    $reply = $ai->getReply(
        $user_msg,
        $full_prompt,
        $info_sources_text,
        $info_sources_files,
        $ai_config,
        $can_cache
    );

    if ($reply !== null && $reply !== '') {
        $matched = 1;

        // Handle Handover detection
        if (strpos($reply, '[HANDOVER]') !== false) {
            $reply = str_replace('[HANDOVER]', '', $reply);
            $flowManager->pause(7200); // Pause for 2 hours
            
            // Notify authorized agents via real WhatsApp message
            $agents = $flowManager->getAuthorizedAgents();
            
            // Get assistant name for better notification
            // FIX: Corregida variable $ast_id → $assistant_id (era undefined en este scope)
            $ast_name = "Asistente #$assistant_id";
            $stmt_ast = mysqli_prepare($conn, "SELECT name FROM assistants WHERE id = ?");
            if ($stmt_ast) {
                mysqli_stmt_bind_param($stmt_ast, "i", $assistant_id);
                mysqli_stmt_execute($stmt_ast);
                $res_ast = mysqli_stmt_get_result($stmt_ast);
                if ($row_ast = mysqli_fetch_assoc($res_ast)) {
                    $ast_name = $row_ast['name'];
                }
            }

            $notify_text = "🚨 *NOTIFICACIÓN DE TRASPASO*\n\n" .
                          "El usuario *$remote_jid* requiere atención humana en el chat de:\n" .
                          "🤖 *Asistente:* $ast_name\n\n" .
                          "El bot ha sido pausado por 2 horas para este usuario. Por favor, atiende la solicitud lo antes posible.";

            require_once 'whatsapp_helper.php';
            foreach ($agents as $agent_phone) {
                error_log("HANDOVER NOTIFICATION: User $remote_jid requires attention. Notifying agent $agent_phone.");
                send_whatsapp_message($ast_id, $agent_phone, $notify_text);
            }
        }
    }
}

// ─── 7. Fallback + suggestions ───────────────────────────────────────────────

if ($matched === 0 || $reply === null) {
    $reply = '';
    if (empty($clean_msg)) {
        $reply = "Por favor, escribe algo para poder ayudarte.";
    } else {
        $reply       = "¡Lo siento! Aún no entiendo eso. Todavía estoy aprendiendo.\n\n¿Tal vez podrías preguntarme alguna de estas cosas?";
        $suggestions = $router->getSuggestions(3);
    }
    $matched = 0;
}

// ─── 8. Format & respond ─────────────────────────────────────────────────────

// FIX: nl2br() movido para aplicarse SOLO en la respuesta JSON al cliente.
// El INSERT usa $reply sin HTML para mantener los logs de BD limpios (útil en exports CSV/JSON).
$stmt = mysqli_prepare($conn,
    "INSERT INTO conversation_logs (assistant_id, user_message, bot_reply, matched) VALUES (?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, "issi", $assistant_id, $user_msg, $reply, $matched);
mysqli_stmt_execute($stmt);

// Aplicar nl2br solo al output HTML del cliente
$reply_html = nl2br((string) $reply);

echo json_encode([
    'reply'       => $reply_html,
    'matched'     => (bool) $matched,
    'suggestions' => $suggestions,
    'interactive' => $interactive,
    'type'        => $type,
    'timestamp'   => date('H:i'),
], JSON_UNESCAPED_UNICODE);