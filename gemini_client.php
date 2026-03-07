<?php

/**
 * Gemini AI Client for SkaleBot
 * Integrates Google Gemini Pro API for natural language responses.
 */
class GeminiClient
{
    private $api_key;
    private $model = "gemini-1.5-flash";
    private $api_url = "https://generativelanguage.googleapis.com/v1/models/";

    public function __construct()
    {
        // Get API key from environment variable
        $this->api_key = getenv('GEMINI_API_KEY');
    }

    /**
     * Get a response from Gemini
     */
    public function get_response($user_message)
    {
        if (!$this->api_key) {
            return "Error: GEMINI_API_KEY no configurada.";
        }

        $system_prompt = "Eres SkaleBot, un asistente experto de Skale IA. " .
            "Eres amable, profesional e innovador. " .
            "Ayudas con soluciones tecnológicas de IA, automatización y desarrollo web. " .
            "Responde de forma concisa, útil y siempre en español latinoméricano. " .
            "Si no sabes algo de un tema técnico específico, ofrece contactar al equipo humano de Skale.";

        $url = $this->api_url . $this->model . ":generateContent?key=" . $this->api_key;

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $system_prompt . "\n\nUsuario dice: " . $user_message]
                    ]
                ]
            ],
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