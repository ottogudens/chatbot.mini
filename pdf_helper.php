<?php

require_once __DIR__ . '/fpdf/fpdf.php';

class PDFHelper
{
    private $templates_dir;
    private $uploads_dir;
    private $conn;

    public function __construct($conn = null)
    {
        $this->templates_dir = __DIR__ . '/pdf_templates/';
        $this->uploads_dir = __DIR__ . '/uploads/';
        $this->conn = $conn;
        if (!is_dir($this->uploads_dir)) {
            mkdir($this->uploads_dir, 0777, true);
        }
    }

    public function list_templates($client_id = null)
    {
        $templates = [];

        // 1. Static templates (from folder)
        if (is_dir($this->templates_dir)) {
            $files = scandir($this->templates_dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..')
                    continue;
                $path = $this->templates_dir . $file;
                if (!is_file($path))
                    continue;

                $content = file_get_contents($path);
                preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
                $placeholders = isset($matches[1]) ? array_unique($matches[1]) : [];

                $templates[] = [
                    'id' => $file,
                    'name' => str_replace(['.txt', '.html'], '', $file),
                    'placeholders' => $placeholders,
                    'source' => 'static'
                ];
            }
        }

        // 2. Dynamic templates (from DB)
        if ($this->conn && $client_id) {
            $stmt = mysqli_prepare($this->conn, "SELECT id, name, file_path, placeholders FROM pdf_templates WHERE client_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $templates[] = [
                    'id' => (string) $row['id'], // Use ID as string to distinguish
                    'name' => $row['name'],
                    'placeholders' => json_decode($row['placeholders'], true) ?: [],
                    'source' => 'db',
                    'db_id' => $row['id']
                ];
            }
        }

        return $templates;
    }

    public function generate_from_template($template_id, $data, $client_id = null, $assistant_id = null)
    {
        $content = "";

        // Ensure data is an array
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        // Check if it's a DB template (numeric ID)
        if (is_numeric($template_id) && $this->conn) {
            $stmt = mysqli_prepare($this->conn, "SELECT file_path FROM pdf_templates WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $template_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $template_path = __DIR__ . '/' . $row['file_path'];
            } else {
                return ["error" => "Template DB ID not found: $template_id"];
            }
        } else {
            // Static template
            $template_path = $this->templates_dir . $template_id;
        }

        if (!file_exists($template_path)) {
            return ["error" => "Template physical file not found: $template_path"];
        }

        if (strtolower(pathinfo($template_path, PATHINFO_EXTENSION)) === 'pdf') {
            // PDF-based template: Use Gemini to "fill" it
            require_once 'gemini_client.php';
            $gemini = new GeminiClient();

            // 1. Upload original PDF to Gemini if not already there (helper)
            $uri = $gemini->upload_file_to_gemini($template_path, 'application/pdf', 'Original Template');

            // 2. Ask Gemini to "re-fill" the content
            $data_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $prompt = "Actúa como un procesador de documentos experto. Toma el PDF adjunto como estructura y plantilla base. 
            Tu objetivo es generar el contenido de un NUEVO documento que complete todos los campos variables del original usando ESTOS DATOS: $data_json.
            
            REGLAS:
            1. Respeta el tono, los encabezados y la disposición lógica de la información del original.
            2. Si un dato no está en el JSON pero es necesario, intenta inferirlo del contexto o déjalo como [Pendiente].
            3. Devuelve ÚNICAMENTE el texto final completo del documento, listo para ser convertido a PDF.
            4. NO incluyas explicaciones, saludos, ni bloques de código markdown (```). Solo el texto plano.";

            $filled_content = $gemini->get_response($prompt, [], "Eres un generador de documentos profesionales.", "", [
                ['uri' => $uri, 'mime_type' => 'application/pdf']
            ], null, [], false);

            $content = $filled_content;
        } else {
            $content = file_get_contents($template_path);
            // Replace placeholders
            foreach ($data as $key => $value) {
                $content = str_replace('{{' . $key . '}}', (string) $value, $content);
            }
            // Clean up any remaining placeholders
            $content = preg_replace('/\{\{.*?\}\}/', '', $content);
        }

        // Generate PDF using FPDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);

        // Handle line breaks and UTF-8 to ISO-8859-1 conversion for FPDF
        $content = utf8_decode($content);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $pdf->MultiCell(0, 10, $line);
        }

        $filename = 'doc_' . time() . '_' . uniqid() . '.pdf';
        $filepath = $this->uploads_dir . $filename;

        $pdf->Output('F', $filepath);

        // Record in database if connection available
        if ($this->conn && isset($client_id) && $client_id !== null) {
            $file_url_part = 'uploads/' . $filename;
            $stmt = mysqli_prepare($this->conn, "INSERT INTO generated_documents (client_id, assistant_id, template_id, file_name, file_url) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("PDFHelper Error: Failed to prepare statement: " . mysqli_error($this->conn));
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base_url = $protocol . "://" . $host . str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), "", $_SERVER['SCRIPT_NAME'] ?? '');
                $full_url = rtrim($base_url, '/') . '/' . $file_url_part;
                
                mysqli_stmt_bind_param($stmt, "iisss", $client_id, $assistant_id, $template_id, $filename, $full_url);
                if (!mysqli_stmt_execute($stmt)) {
                    error_log("PDFHelper Error: Failed to execute statement: " . mysqli_stmt_error($stmt));
                } else {
                    error_log("PDFHelper Success: Recorded document $filename for client $client_id");
                }
            }
        } else {
            if (!$this->conn) error_log("PDFHelper Warning: No DB connection provided.");
            if (!$client_id) error_log("PDFHelper Warning: No client_id provided for recording.");
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_url = $protocol . "://" . $host . str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), "", $_SERVER['SCRIPT_NAME'] ?? '');

        return [
            "success" => true,
            "filename" => $filename,
            "url" => rtrim($base_url, '/') . '/uploads/' . $filename
        ];
    }
}
