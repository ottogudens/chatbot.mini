<?php

/**
 * Gemini AI Client for SkaleBot
 * Integrates Google Gemini Pro API for natural language responses.
 */
class GeminiClient
{
    private $api_key;
    private $model = "gemini-3-flash-preview";
    private $api_url = "https://generativelanguage.googleapis.com/v1beta/models/";

    public function __construct()
    {
        // Get API key from environment variable
        $this->api_key = getenv('GEMINI_API_KEY');
    }

    /**
     * Get a response from Gemini
     */
    public function get_response($user_message, $history = [], $custom_system_prompt = "", $info_sources = "")
    {
        if (!$this->api_key) {
            return "Error: GEMINI_API_KEY no configurada.";
        }

        $base_prompt = "Eres un asistente virtual avanzado y profesional de Skale IA. " .
            "Responde de forma concisa, útil y siempre en español latinoamericano. ";

        if (!empty($custom_system_prompt)) {
            $system_prompt = $base_prompt . "\n\nINSTRUCCIONES ESPECÍFICAS DEL ASISTENTE:\n" . $custom_system_prompt;
        } else {
            $system_prompt = $base_prompt . "\nSi no sabes algo de un tema técnico, ofrece contactar al equipo de soporte.";
        }

        if (!empty($info_sources)) {
            $system_prompt .= "\n\nBASA TUS RESPUESTAS ESTRICTAMENTE EN LA SIGUIENTE INFORMACIÓN DE CONTEXTO:\n" . $info_sources;
        }

        $url = $this->api_url . $this->model . ":generateContent?key=" . $this->api_key;

        $contents = [];

        // Add chat history
        foreach ($history as $msg) {
            if (!empty($msg['user_message'])) {
                $contents[] = [
                    "role" => "user",
                    "parts" => [["text" => $msg['user_message']]]
                ];
            }
            if (!empty($msg['bot_reply'])) {
                $contents[] = [
                    "role" => "model",
                    "parts" => [["text" => $msg['bot_reply']]]
                ];
            }
        }

        // Add current user message
        $contents[] = [
            "role" => "user",
            "parts" => [["text" => $user_message]]
        ];

        $data = [
            "system_instruction" => [
                "parts" => [["text" => $system_prompt]]
            ],
            "contents" => $contents,
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 800,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For some environments without local certs

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("Gemini API Error: " . $response);
            return "API Error ($http_code): " . $response;
        }

        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
?>