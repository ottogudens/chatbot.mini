<?php
/**
 * AIHandler.php — INFRA-3
 * Handles all AI interactions: Gemini API calls, function calling dispatch,
 * response caching, and expired file URI cleanup.
 * Extracted from message.php to improve maintainability.
 */

require_once __DIR__ . '/gemini_client.php';
require_once __DIR__ . '/calendar_functions.php';
require_once __DIR__ . '/pdf_helper.php';

class AIHandler
{
    private $conn;
    private $assistant_id;
    private $client_id;
    private GeminiClient $gemini;

    const CACHE_TTL = 7200; // 2 hours in seconds

    public function __construct($conn, $assistant_id, $client_id)
    {
        $this->conn         = $conn;
        $this->assistant_id = $assistant_id;
        $this->client_id    = $client_id;
        $this->gemini       = new GeminiClient();
    }

    /**
     * Upload an audio file to Gemini and return a file reference.
     */
    public function handleAudio(array $audio_file, string $mime_type = 'audio/ogg'): ?array
    {
        $uri = $this->gemini->upload_file_to_gemini($audio_file['tmp_name'], $mime_type, 'whatsapp_voice_' . time());
        if ($uri) {
            return ['uri' => $uri, 'mime_type' => $mime_type];
        }
        return null;
    }

    /**
     * Check the response cache for an existing reply.
     * Only applies to plain text messages (no audio/files).
     */
    public function checkCache(string $user_msg): ?string
    {
        if (!$this->assistant_id) return null;

        $cache_key = $this->cacheKey($user_msg);
        $stmt = mysqli_prepare($this->conn,
            "SELECT cached_reply, id FROM gemini_response_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1"
        );
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, "s", $cache_key);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            // Update hit counter async-safe
            $upd = mysqli_prepare($this->conn,
                "UPDATE gemini_response_cache SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?"
            );
            if ($upd) {
                mysqli_stmt_bind_param($upd, "i", $row['id']);
                mysqli_stmt_execute($upd);
            }
            return $row['cached_reply'];
        }
        return null;
    }

    /**
     * Store a reply in the response cache.
     */
    private function storeCache(string $user_msg, string $reply): void
    {
        if (!$this->assistant_id) return;

        $cache_key  = $this->cacheKey($user_msg);
        $expires_at = date('Y-m-d H:i:s', time() + self::CACHE_TTL);

        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO gemini_response_cache (cache_key, assistant_id, user_message, cached_reply, expires_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cached_reply=VALUES(cached_reply), expires_at=VALUES(expires_at), hit_count=1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sisss", $cache_key, $this->assistant_id, $user_msg, $reply, $expires_at);
            mysqli_stmt_execute($stmt);
        }
    }

    private function cacheKey(string $user_msg): string
    {
        return md5($this->assistant_id . '::' . mb_strtolower(trim($user_msg)));
    }

    /**
     * Fetch the last N conversation turns for context.
     */
    private function loadHistory(int $limit = 10): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            $this->assistant_id
                ? "SELECT user_message, bot_reply FROM conversation_logs WHERE assistant_id = ? ORDER BY id DESC LIMIT $limit"
                : "SELECT user_message, bot_reply FROM conversation_logs WHERE assistant_id IS NULL ORDER BY id DESC LIMIT $limit"
        );

        $history = [];
        if ($stmt) {
            if ($this->assistant_id) {
                mysqli_stmt_bind_param($stmt, "i", $this->assistant_id);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    array_unshift($history, $row);
                }
            }
        }
        return $history;
    }

    /**
     * Dispatch a Gemini function call to the appropriate tool.
     */
    private function dispatchFunctionCall(array $call): mixed
    {
        $name = $call['name'];
        // Normalize args: GeminiClient casts args to stdClass (object), but we need array access.
        $args_raw = $call['args'] ?? [];
        // Recursively convert objects to arrays
        $args = json_decode(json_encode($args_raw), true) ?? [];

        switch ($name) {
            case 'check_availability':
                return check_calendar_availability($this->conn, $this->assistant_id, $args);

            case 'book_appointment':
                return book_calendar_appointment($this->conn, $this->assistant_id, $args);

            case 'list_pdf_templates':
                $helper = new PDFHelper($this->conn);
                return $helper->list_templates($this->client_id);

            case 'generate_pdf':
                $helper = new PDFHelper($this->conn);
                return $helper->generate_from_template(
                    $args['template_id'] ?? '',
                    $args['data'] ?? [],
                    $this->client_id,
                    $this->assistant_id
                );

            case 'register_lead':
                $stmt_lead = mysqli_prepare($this->conn,
                    "INSERT INTO leads (client_id, assistant_id, name, phone, email, notes, captured_data) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $name_v  = $args['name']       ?? 'S/N';
                $phone_v = $args['phone']      ?? '';
                $email_v = $args['email']      ?? '';
                $notes_v = $args['notes']      ?? '';
                // extra_info is now an object from Gemini tool call, convert to JSON string
                $extra_raw = $args['extra_info'] ?? null;
                $extra_v = is_array($extra_raw) || is_object($extra_raw)
                    ? json_encode($extra_raw, JSON_UNESCAPED_UNICODE)
                    : $extra_raw;

                if ($stmt_lead) {
                    mysqli_stmt_bind_param($stmt_lead, "iisssss",
                        $this->client_id, $this->assistant_id,
                        $name_v, $phone_v, $email_v, $notes_v, $extra_v
                    );
                    if (mysqli_stmt_execute($stmt_lead)) {
                        return "Prospecto '$name_v' registrado exitosamente en el CRM.";
                    }
                    return "Error al registrar prospecto: " . mysqli_error($this->conn);
                }
                return "Error: No se pudo preparar la sentencia.";

            default:
                return "Function '$name' not implemented.";
        }
    }

    /**
     * Main entry point: get an AI reply for the given message.
     * Handles caching, history, function calling, and cache storage.
     *
     * @param string $user_msg     Raw user message
     * @param string $system_prompt Custom system prompt from assistant config
     * @param string $info_text   Concatenated text info sources
     * @param array  $info_files  File URI references for Gemini
     * @param array  $ai_config   Model/temperature/etc config
     * @param bool   $can_cache   Whether caching is allowed for this request
     * @return string|null
     */
    public function getReply(
        string $user_msg,
        string $system_prompt,
        string $info_text,
        array  $info_files,
        array  $ai_config,
        bool   $can_cache = true
    ): ?string {

        // Cache check (only for text-only, contextless messages)
        if ($can_cache) {
            $cached = $this->checkCache($user_msg);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Pre-flight context check (logging only)
        $total_context_chars = mb_strlen($system_prompt) + mb_strlen($info_text) + mb_strlen($user_msg);
        if ($total_context_chars > 200000) {
            error_log("AIHandler Assistant {$this->assistant_id}: Large context detected (~$total_context_chars chars). Max Gemini limit is ~4M chars (1M tokens).");
        }

        $history  = $this->loadHistory();
        $ai_reply = $this->gemini->get_response($user_msg, $history, $system_prompt, $info_text, $info_files, null, $ai_config);

        $had_function_call = false;

        // Handle function calling
        if (is_array($ai_reply) && ($ai_reply['type'] ?? '') === 'function_call') {
            $had_function_call = true;
            $func_result = $this->dispatchFunctionCall($ai_reply['call']);

            $function_state = [
                'call'   => $ai_reply['call'],
                'result' => is_array($func_result) ? $func_result : ['message' => (string) $func_result]
            ];

            // Pass result back to Gemini for natural language response
            $ai_reply = $this->gemini->get_response(
                $user_msg, $history, $system_prompt, $info_text, $info_files, $function_state, $ai_config
            );
        }

        if (!is_string($ai_reply) || empty($ai_reply)) {
            return null;
        }

        // Cache the final reply if eligible (no function calls — those are time-sensitive)
        if ($can_cache && !$had_function_call) {
            $this->storeCache($user_msg, $ai_reply);
        }

        // Clean up any expired Gemini file URIs
        if (!empty($this->gemini->expired_file_uris)) {
            $del_stmt = mysqli_prepare($this->conn,
                "UPDATE information_sources SET gemini_file_uri = NULL WHERE gemini_file_uri = ?"
            );
            foreach ($this->gemini->expired_file_uris as $expired_uri) {
                if ($del_stmt) {
                    mysqli_stmt_bind_param($del_stmt, "s", $expired_uri);
                    mysqli_stmt_execute($del_stmt);
                }
                error_log("Cleared expired Gemini file URI from DB: $expired_uri");
            }
        }

        return $ai_reply;
    }
}
