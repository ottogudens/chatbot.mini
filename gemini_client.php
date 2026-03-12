<?php

/**
 * Gemini AI Client for SkaleBot
 * Integrates Google Gemini Pro API for natural language responses.
 */
class GeminiClient
{
    private $api_key;
    private $model = "gemini-2.5-flash"; // Default: stable GA model available to all API keys

    // Models that use extended thinking — require thinkingBudget:0 to enable function calling
    private $thinking_models = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite', 'gemini-3-pro-preview', 'gemini-3-flash-preview'];
    private $api_url = "https://generativelanguage.googleapis.com/v1beta/models/";

    /** URIs that were found to be expired/inaccessible during the last get_response() call */
    public $expired_file_uris = [];

    public function __construct()
    {
        // Get API key from environment variable
        $this->api_key = getenv('GEMINI_API_KEY');
    }

    /**
     * Returns true if the given model is a thinking model that requires
     * thinkingConfig:{thinkingBudget:0} to use tools/function calling.
     */
    private function is_thinking_model($model)
    {
        foreach ($this->thinking_models as $tm) {
            if (stripos($model, $tm) !== false || stripos($model, $tm) === 0) {
                return true;
            }
        }
        // Also detect by suffix pattern: any 2.5+ model is likely a thinking model
        return (bool) preg_match('/gemini-(2\.5|3\.)/', $model);
    }

    /**
     * Check if a Gemini File URI is still valid (not expired or permission denied).
     * Returns true if accessible, false if expired/forbidden.
     */
    public function validate_file_uri($uri)
    {
        if (!$this->api_key || empty($uri))
            return false;
        // Extract file ID from URI like "https://generativelanguage.googleapis.com/v1beta/files/FILEID"
        $file_id = basename(parse_url($uri, PHP_URL_PATH));
        $check_url = "https://generativelanguage.googleapis.com/v1beta/files/{$file_id}?key=" . $this->api_key;
        $ch = curl_init($check_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $http_code === 200;
    }

    /**
     * Upload a file to Gemini File API
     * Returns the file URI or false on failure
     */
    public function upload_file_to_gemini($file_path, $mime_type, $display_name)
    {
        if (!$this->api_key || !file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);

        // 1. Initial Resumable Upload Request
        $init_url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key=" . $this->api_key;

        $init_headers = [
            "X-Goog-Upload-Protocol: resumable",
            "X-Goog-Upload-Command: start",
            "X-Goog-Upload-Header-Content-Length: " . $file_size,
            "X-Goog-Upload-Header-Content-Type: " . $mime_type,
            "Content-Type: application/json"
        ];

        $init_data = json_encode([
            "file" => ["displayName" => $display_name]
        ]);

        $ch = curl_init($init_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $init_headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $init_data);
        curl_setopt($ch, CURLOPT_HEADER, true); // Need headers to get upload URI

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        curl_close($ch);

        // Extract upload URI from headers
        $upload_url = "";
        foreach (explode("\r\n", $headers) as $header) {
            if (stripos($header, 'x-goog-upload-url:') === 0) {
                $upload_url = trim(substr($header, 18));
                break;
            }
        }

        if (empty($upload_url)) {
            return false;
        }

        // 2. Upload the actual file content
        $upload_headers = [
            "Content-Length: " . $file_size,
            "X-Goog-Upload-Offset: 0",
            "X-Goog-Upload-Command: upload, finalize"
        ];

        $ch2 = curl_init($upload_url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $upload_headers);
        curl_setopt($ch2, CURLOPT_POST, true);
        // Using CURLFile for direct streaming would be better for 500MB, but file_get_contents is simpler for now
        // A robust solution for 500MB would stream the file from disk using CURLOPT_INFILE
        $file_handle = fopen($file_path, 'r');
        curl_setopt($ch2, CURLOPT_PUT, true);
        curl_setopt($ch2, CURLOPT_INFILE, $file_handle);
        curl_setopt($ch2, CURLOPT_INFILESIZE, $file_size);

        $final_response = curl_exec($ch2);
        curl_close($ch2);
        fclose($file_handle);

        $result = json_decode($final_response, true);
        return $result['file']['uri'] ?? false;
    }

    /**
     * Get a response from Gemini
     */
    public function get_response($user_message, $history = [], $custom_system_prompt = "", $info_sources_text = "", $info_sources_files = [], $function_state = null, $config = [])
    {
        if (!$this->api_key) {
            return "Error: GEMINI_API_KEY no configurada.";
        }

        // Per-assistant AI config with safe defaults
        $model = $config['model'] ?? $this->model;
        $temperature = $config['temperature'] ?? 0.7;
        $max_tokens = $config['max_output_tokens'] ?? 1500;
        $response_style = $config['response_style'] ?? 'balanced';

        // Style instruction injected into the system prompt
        $style_instructions = [
            'concise' => "REGLA DE ESTILO: Responde de forma muy concisa y directa. Máximo 2-3 oraciones por punto. Si el usuario pide más detalle, entonces amplia tu respuesta.",
            'balanced' => "REGLA DE ESTILO: Responde de forma clara y completa. Incluye toda la información relevante pero sin extenderte innecesariamente. Si el usuario pide más detalle, amplia tu respuesta.",
            'detailed' => "REGLA DE ESTILO: Proporciona respuestas detalladas y exhaustivas. Explica cada punto a fondo y ofrece contexto adicional cuando sea útil."
        ];
        $style_hint = $style_instructions[$response_style] ?? $style_instructions['balanced'];

        $base_prompt = "Eres un asistente virtual avanzado y profesional de Skale IA. " .
            "Responde de forma concisa, útil y siempre en español latinoamericano. ";

        if (!empty($custom_system_prompt)) {
            $system_prompt = $base_prompt . "\n\nINSTRUCCIONES ESPECÍFICAS DEL ASISTENTE:\n" . $custom_system_prompt;
        } else {
            $system_prompt = $base_prompt . "\nSi no sabes algo de un tema técnico, ofrece contactar al equipo de soporte.";
        }

        $system_prompt .= "\n\n" . $style_hint;

        if (!empty($info_sources_text)) {
            $system_prompt .= "\n\nBASA TUS RESPUESTAS ESTRICTAMENTE EN LA SIGUIENTE INFORMACIÓN DE CONTEXTO:\n" . $info_sources_text;
        }

        $system_prompt .= "\n\nREGLA IMPORTANTE: Si un usuario quiere agendar una cita o reunión, pide su nombre, email y teléfono. Luego, DEBES utilizar las herramientas nativas disponibles para checkear disponibilidad y concretar la cita. Solo puedes confirmar una vez que la herramienta de reservas lo haya confirmado exitosamente. NUNCA inventes confirmaciones o fechas.";

        $system_prompt .= "\n\nHERRAMIENTAS DE DOCUMENTOS: Tienes la capacidad de generar archivos PDF para el usuario. " .
            "1. Usa `list_pdf_templates` para ver qué plantillas hay disponibles y qué datos (placeholders) requieren. " .
            "2. Usa `generate_pdf` para crear el documento una vez que tengas toda la información necesaria. " .
            "Siempre informa al usuario que estás generando el documento y proporciónale el enlace una vez creado.";

        $url = $this->api_url . $model . ":generateContent?key=" . $this->api_key;

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

        // Add current user message and ANY file data
        $user_parts = [];

        // Add files first
        foreach ($info_sources_files as $file_data) {
            if (!empty($file_data['uri']) && !empty($file_data['mime_type'])) {
                $user_parts[] = [
                    "fileData" => [
                        "mimeType" => $file_data['mime_type'],
                        "fileUri" => $file_data['uri']
                    ]
                ];
            }
        }

        // Add text and manage function states
        $user_parts[] = ["text" => $user_message];

        $contents[] = [
            "role" => "user",
            "parts" => $user_parts
        ];

        if ($function_state) {
            $contents[] = [
                "role" => "model",
                "parts" => [["functionCall" => $function_state['call']]]
            ];
            $contents[] = [
                "role" => "function",
                "parts" => [
                    [
                        "functionResponse" => [
                            "name" => $function_state['call']['name'],
                            "response" => ["content" => $function_state['result']]
                        ]
                    ]
                ]
            ];
        }

        $data = [
            "system_instruction" => [
                "parts" => [["text" => $system_prompt]]
            ],
            "contents" => $contents,
            "generationConfig" => array_filter([
                "temperature" => (float) $temperature,
                "maxOutputTokens" => (int) $max_tokens,
                // Disable extended thinking for thinking models so tools/function calling work correctly
                "thinkingConfig" => $this->is_thinking_model($model) ? ["thinkingBudget" => 0] : null,
            ], fn($v) => $v !== null),
            "tools" => [
                [
                    "functionDeclarations" => [
                        [
                            "name" => "check_availability",
                            "description" => "Llama esto para consultar fechas y horarios disponibles en la agenda real del cliente de los proximos 7 dias.",
                            "parameters" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "target_date" => ["type" => "STRING", "description" => "Fecha específica a consultar (YYYY-MM-DD). Si el usuario no dio fecha concreta, déjalo vacío o usa palabras como 'hoy', 'mañana' si quieres."],
                                ]
                            ]
                        ],
                        [
                            "name" => "book_appointment",
                            "description" => "Llama esto una vez que tengas acordados la FECHA, HORA, NOMBRE, EMAIL y TELEFONO con el usuario, para hacer la reserva en Google Calendar.",
                            "parameters" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "date" => ["type" => "STRING", "description" => "Fecha acordada en formato YYYY-MM-DD"],
                                    "time" => ["type" => "STRING", "description" => "Hora acordada en formato de 24 hrs HH:MM (ej. 15:30)"],
                                    "user_name" => ["type" => "STRING", "description" => "Nombre del cliente nuevo"],
                                    "user_email" => ["type" => "STRING", "description" => "Correo electrónico"],
                                    "user_phone" => ["type" => "STRING", "description" => "Celular o teléfono del cliente"]
                                ],
                                "required" => ["date", "time", "user_name", "user_email", "user_phone"]
                            ]
                        ],
                        [
                            "name" => "list_pdf_templates",
                            "description" => "Obtiene la lista de plantillas de PDF disponibles y los campos requeridos para cada una.",
                            "parameters" => [
                                "type" => "OBJECT",
                                "properties" => []
                            ]
                        ],
                        [
                            "name" => "generate_pdf",
                            "description" => "Genera un archivo PDF a partir de una plantilla y datos específicos.",
                            "parameters" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "template_id" => ["type" => "STRING", "description" => "ID de la plantilla a usar (ej: basic_info.txt)"],
                                    "data" => [
                                        "type" => "OBJECT",
                                        "description" => "Objeto con los pares clave-valor para los placeholders de la plantilla (ej: {'nombre': 'Juan', 'fecha': '2023-10-01'})",
                                        "additionalProperties" => ["type" => "STRING"]
                                    ]
                                ],
                                "required" => ["template_id", "data"]
                            ]
                        ]
                    ]
                ]
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

        // Handle expired/inaccessible file URIs: retry in text-only mode
        if (($http_code === 403 || $http_code === 404) && !empty($info_sources_files)) {
            $error_body = json_decode($response, true);
            $error_status = $error_body['error']['status'] ?? '';
            if (in_array($error_status, ['PERMISSION_DENIED', 'NOT_FOUND'])) {
                // Record all attached URIs as expired so caller can clean them up
                $this->expired_file_uris = array_column($info_sources_files, 'uri');
                error_log("Gemini file URI expired/inaccessible, retrying text-only. URIs: " . implode(', ', $this->expired_file_uris));

                // Rebuild user parts WITHOUT file attachments
                $user_parts_text_only = [["text" => $user_message]];
                foreach (array_reverse(array_keys($contents)) as $idx) {
                    if (($contents[$idx]['role'] ?? '') === 'user') {
                        $contents[$idx]['parts'] = $user_parts_text_only;
                        break;
                    }
                }
                $data['contents'] = $contents;

                $ch2 = curl_init($url);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch2);
                $http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
            }
        }

        if ($http_code !== 200) {
            error_log("Gemini API Error: " . $response);
            return "API Error ($http_code): " . $response;
        }

        $result = json_decode($response, true);
        $part = $result['candidates'][0]['content']['parts'][0] ?? null;

        if (isset($part['functionCall'])) {
            return [
                'type' => 'function_call',
                'call' => $part['functionCall']
            ];
        }

        return $part['text'] ?? null;
    }

    /**
     * Specialized method to analyze a PDF and extract possible placeholders/fields.
     * Returns an array of strings (field names).
     */
    public function analyze_pdf_placeholders($file_uri, $mime_type)
    {
        $prompt = "Analiza este documento PDF e identifica todos los campos, etiquetas o espacios donde un usuario debería ingresar información (ej: Nombre, Fecha, Dirección, Monto, etc.). 
        Devuelve ÚNICAMENTE un arreglo JSON con los nombres técnicos sugeridos para estos campos (en minúsculas, sin espacios, usando guiones bajos si es necesario).
        Ejemplo de salida: [\"nombre_cliente\", \"fecha_emision\", \"monto_total\"]";

        $response = $this->get_response($prompt, [], "Eres un extractor de metadatos de documentos.", "", [
            ['uri' => $file_uri, 'mime_type' => $mime_type]
        ]);

        if (is_array($response) && $response['type'] === 'function_call') {
            // Should not happen with this prompt, but handle just in case
            return [];
        }

        // Extract JSON from response (Gemini might wrap it in markdown)
        if (preg_match('/\[.*\]/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            return is_array($json) ? $json : [];
        }

        return [];
    }
}
?>