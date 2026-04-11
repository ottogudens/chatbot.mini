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
        $this->uploads_dir   = __DIR__ . '/uploads/';
        $this->conn          = $conn;
        // SEC FIX: 0755 en lugar de 0777 — el directorio no necesita ser escribible
        // por todos los usuarios del sistema (solo el proceso PHP/Nginx).
        if (!is_dir($this->uploads_dir)) {
            mkdir($this->uploads_dir, 0755, true);
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
        if ($this->conn) {
            if ($client_id) {
                $stmt = mysqli_prepare($this->conn, "SELECT id, name, description, doc_type, file_path, placeholders, template_config FROM pdf_templates WHERE client_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $client_id);
            } else {
                $stmt = mysqli_prepare($this->conn, "SELECT id, name, description, doc_type, file_path, placeholders, template_config FROM pdf_templates");
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                // If this template has a canvas config, extract fields from it
                $placeholders = json_decode($row['placeholders'], true) ?: [];
                if (!empty($row['template_config'])) {
                    $config = json_decode($row['template_config'], true);
                    if (!empty($config['fields'])) {
                        $placeholders = array_map(fn($f) => $f['name'] ?? '', $config['fields']);
                        $placeholders = array_filter($placeholders);
                        $placeholders = array_values($placeholders);
                    }
                }
                $templates[] = [
                    'id' => (string) $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'] ?? '',
                    'doc_type' => $row['doc_type'] ?? 'generic',
                    'placeholders' => $placeholders,
                    'source' => !empty($row['template_config']) ? 'canvas' : 'db',
                    'db_id' => $row['id']
                ];
            }
        }

        return $templates;
    }

    public function generate_from_template($template_id, $data, $client_id = null, $assistant_id = null)
    {
        $content = "";

        // Ensure data is an array (TOKEN-FIX / 500-FIX)
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                // If it's still a string, it might be Gemini sending plain text or malformed JSON
                error_log("PDFHelper Warning: Expected array for 'data', got string: " . substr($data, 0, 100));
                $data = []; 
            }
        }

        if (!is_array($data)) {
            error_log("PDFHelper Error: Data is not an array (Type: " . gettype($data) . ")");
            $data = [];
        }

        // Check if it's a DB template (numeric ID)
        if (is_numeric($template_id) && $this->conn) {
            $stmt = mysqli_prepare($this->conn, "SELECT file_path, template_config FROM pdf_templates WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $template_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                // Canvas template — delegate to generate_from_config
                if (!empty($row['template_config'])) {
                    $config = json_decode($row['template_config'], true);
                    if ($config) {
                        return $this->generate_from_config($config, $data, $client_id, $assistant_id, $template_id);
                    }
                }
                // Sanitize DB-provided path
                $template_path = __DIR__ . '/' . ltrim($row['file_path'], './');
            } else {
                return ["error" => "Template DB ID not found: $template_id"];
            }
        } else {
            // Static template — STRONGLY SANITIZE to avoid Path Traversal
            $safe_template_id = basename($template_id);
            $template_path = $this->templates_dir . $safe_template_id;
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
            if ($content === false) {
                return ["error" => "Could not read template file: $template_path"];
            }
            // Replace placeholders
            foreach ($data as $key => $value) {
                // Ensure value is stringable
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
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
        // utf8_decode is deprecated in PHP 8.2+
        if (function_exists('mb_convert_encoding')) {
            $content = mb_convert_encoding((string)$content, 'ISO-8859-1', 'UTF-8');
        } else {
            $content = @utf8_decode((string)$content);
        }

        $lines = explode("\n", (string)$content);
        foreach ($lines as $line) {
            $pdf->MultiCell(0, 10, $line);
        }

        $filename = 'doc_' . time() . '_' . uniqid() . '.pdf';
        $filepath = $this->uploads_dir . $filename;

        $pdf->Output('F', $filepath);

        // SEC FIX: Eliminado pdf_debug.log en /uploads/ (directorio público HTTP).
        // Cualquier visitante podía leer /uploads/pdf_debug.log con datos de clientes.
        // El logging se delega a error_log() que Railway captura en sus logs internos.
        if ($this->conn && isset($client_id) && $client_id !== null) {
            $file_url_part = 'uploads/' . $filename;
            $full_url      = $this->get_base_url() . '/' . $file_url_part;

            $stmt = mysqli_prepare($this->conn, "INSERT INTO generated_documents (client_id, assistant_id, template_id, file_name, file_url) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("PDFHelper Error: Failed to prepare statement: " . mysqli_error($this->conn));
            } else {
                mysqli_stmt_bind_param($stmt, "iisss", $client_id, $assistant_id, $template_id, $filename, $full_url);
                if (!mysqli_stmt_execute($stmt)) {
                    error_log("PDFHelper Error: Failed to execute statement: " . mysqli_stmt_error($stmt));
                } else {
                    error_log("PDFHelper Success: Recorded document $filename for client $client_id");
                    $recorded = true;
                }
            }
        } else {
            if (!$this->conn) error_log("PDFHelper Warning: No DB connection provided.");
            if ($client_id === null) error_log("PDFHelper Warning: No client_id provided for recording.");
        }

        return [
            "success"  => true,
            "recorded" => $recorded,
            "filename" => $filename,
            "url"      => $this->get_base_url() . '/uploads/' . $filename
        ];
    }
    /**
     * Encode string for FPDF (ISO-8859-1)
     */
    private function enc(string $str): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str) ?: $str;
    }

    /**
     * Construye la URL base del servidor de forma segura.
     *
     * SEC FIX: Usar $_SERVER['HTTP_HOST'] directamente para construir URLs
     * es un vector de "Host Header Injection". Un atacante puede enviar:
     *   Host: evil.com
     * y hacer que la URL del PDF apunte a un dominio externo.
     * Validamos contra una allowlist de la variable de entorno APP_URL.
     */
    private function get_base_url(): string
    {
        // Prioridad 1: Variable de entorno APP_URL (configurada en Railway dashboard)
        $app_url = getenv('APP_URL');
        if ($app_url && filter_var($app_url, FILTER_VALIDATE_URL)) {
            return rtrim($app_url, '/');
        }

        // Fallback: detectar desde el servidor (solo para desarrollo local)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https' : 'http';

        // Validar que HTTP_HOST solo contenga caracteres válidos de hostname
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/^[a-zA-Z0-9._:\-]+$/', $host)) {
            $host = 'localhost';
        }

        return $protocol . '://' . $host;
    }

    /**
     * Parse a hex color like #1a3a5c into [R, G, B]
     */
    private function hexRGB(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    }

    /**
     * Generate a PDF from a JSON canvas template config.
     * This is used instead of generate_from_template() for canvas-designed templates.
     */
    public function generate_from_config(array $config, array $data, $client_id = null, $assistant_id = null, $template_id = null, bool $preview = false): array
    {
        $design = $config['design'] ?? [];
        $header = $config['header'] ?? [];
        $sections = $config['sections'] ?? [];

        $primary  = $this->hexRGB($design['primary_color']  ?? '#1a3a5c');
        $accent   = $this->hexRGB($design['accent_color']   ?? '#f0a500');
        $font     = $design['font'] ?? 'Arial';

        // Replace {{placeholder}} in data values
        $resolve = fn(string $key) => isset($data[$key]) ? (string)$data[$key] : '';

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // --- HEADER ---
        $logo_url = $header['logo_url'] ?? '';
        if ($logo_url && file_exists(__DIR__ . '/' . ltrim($logo_url, '/'))) {
            $logo_path = __DIR__ . '/' . ltrim($logo_url, '/');
            $pdf->Image($logo_path, 15, 12, 35);
            $pdf->SetXY(55, 12);
        } else {
            $pdf->SetXY(15, 12);
        }

        // Company block
        $pdf->SetFont($font, 'B', 15);
        $pdf->SetTextColor($primary[0], $primary[1], $primary[2]);
        $pdf->Cell(0, 8, $this->enc($header['company_name'] ?? ''), 0, 1);
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(80, 80, 80);
        foreach (['company_rut', 'company_address', 'company_phone', 'company_email'] as $field) {
            if (!empty($header[$field])) {
                $pdf->Cell(0, 5, $this->enc($header[$field]), 0, 1);
            }
        }

        // Document title bar
        $doc_labels = [
            'budget'             => 'PRESUPUESTO',
            'receipt'            => 'RECIBO / FACTURA',
            'vehicle_inspection' => 'INSPECCION VEHICULAR',
            'vehicle_diagnostic' => 'DIAGNOSTICO VEHICULAR',
            'generic'            => 'DOCUMENTO',
        ];
        $doc_type  = $config['doc_type'] ?? 'generic';
        $doc_title = $doc_labels[$doc_type] ?? strtoupper($doc_type);

        $pdf->Ln(5);
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 13);
        $pdf->Cell(0, 10, $this->enc($doc_title), 0, 1, 'C', true);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Ln(4);

        // --- SECTIONS ---
        foreach ($sections as $section) {
            $stype  = $section['type'] ?? 'notes';
            $slabel = $section['label'] ?? '';

            switch ($stype) {
                case 'client_info':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'DATOS DEL CLIENTE');
                    $fields = $section['fields'] ?? [];
                    $this->renderKeyValueGrid($pdf, $font, $fields, $data);
                    $pdf->Ln(4);
                    break;

                case 'vehicle_info':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'DATOS DEL VEHICULO');
                    $fields = $section['fields'] ?? [];
                    $this->renderKeyValueGrid($pdf, $font, $fields, $data);
                    $pdf->Ln(4);
                    break;

                case 'general_info':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'INFORMACION GENERAL');
                    $fields = $section['fields'] ?? [];
                    $this->renderKeyValueGrid($pdf, $font, $fields, $data);
                    $pdf->Ln(4);
                    break;

                case 'items_table':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'DETALLE');
                    $this->renderItemsTable($pdf, $font, $primary, $accent, $data, $section);
                    $pdf->Ln(4);
                    break;

                case 'checklist':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'LISTA DE VERIFICACION');
                    $this->renderChecklist($pdf, $font, $primary, $data, $section);
                    $pdf->Ln(4);
                    break;

                case 'notes':
                    $this->renderSectionTitle($pdf, $font, $primary, $slabel ?: 'OBSERVACIONES');
                    $noteKey = $section['field'] ?? 'observaciones';
                    $noteText = $data[$noteKey] ?? $section['default_text'] ?? '';
                    $pdf->SetFont($font, '', 10);
                    $pdf->SetFillColor(248, 248, 248);
                    $pdf->MultiCell(0, 6, $this->enc($noteText), 1, 'L', true);
                    $pdf->Ln(4);
                    break;

                case 'footer':
                    $footerText = $this->enc($section['text'] ?? '');
                    if ($footerText) {
                        $pdf->Ln(6);
                        $pdf->SetDrawColor($primary[0], $primary[1], $primary[2]);
                        $pdf->SetLineWidth(0.5);
                        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
                        $pdf->Ln(3);
                        $pdf->SetFont($font, 'I', 8);
                        $pdf->SetTextColor(120, 120, 120);
                        $pdf->MultiCell(0, 5, $footerText, 0, 'C');
                    }
                    break;

                case 'signature':
                    $pdf->Ln(10);
                    $pdf->SetFont($font, '', 10);
                    $pdf->Cell(90, 20, '', 'T', 0, 'C');
                    $pdf->Cell(10, 20, '', 0);
                    $pdf->Cell(90, 20, '', 'T', 1, 'C');
                    $pdf->SetFont($font, '', 8);
                    $pdf->Cell(90, 5, $this->enc('Firma Cliente'), 0, 0, 'C');
                    $pdf->Cell(10, 5, '', 0);
                    $pdf->Cell(90, 5, $this->enc('Firma Empresa'), 0, 1, 'C');
                    break;
            }
        }

        // Save
        $filename = 'doc_' . time() . '_' . uniqid() . '.pdf';
        $filepath = $this->uploads_dir . $filename;
        $pdf->Output('F', $filepath);

        // Record in DB
        $recorded = false;
        if (!$preview && $this->conn && $client_id !== null) {
            $tid      = (string)($template_id ?? 'canvas');
            $full_url = $this->get_base_url() . '/uploads/' . $filename;
            $stmt = mysqli_prepare($this->conn, 'INSERT INTO generated_documents (client_id, assistant_id, template_id, file_name, file_url) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iisss', $client_id, $assistant_id, $tid, $filename, $full_url);
                $recorded = mysqli_stmt_execute($stmt);
            }
            return ['success' => true, 'recorded' => $recorded, 'filename' => $filename, 'url' => $full_url];
        }

        return ['success' => true, 'recorded' => false, 'filename' => $filename, 'url' => $this->get_base_url() . '/uploads/' . $filename];
    }

    private function renderSectionTitle(FPDF $pdf, string $font, array $color, string $title): void
    {
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor($color[0], $color[1], $color[2]);
        $pdf->Cell(0, 7, $this->enc(strtoupper($title)), 'B', 1);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Ln(1);
    }

    private function renderKeyValueGrid(FPDF $pdf, string $font, array $fields, array $data): void
    {
        $pdf->SetFont($font, '', 9);
        $colW = 85;
        $count = 0;
        foreach ($fields as $fieldName) {
            $label = ucfirst(str_replace('_', ' ', $fieldName));
            $value = $data[$fieldName] ?? '-';
            if (is_array($value)) $value = implode(', ', $value);

            $pdf->SetFont($font, 'B', 9);
            $pdf->Cell($colW * 0.4, 7, $this->enc($label . ':'), 0, 0);
            $pdf->SetFont($font, '', 9);
            $pdf->Cell($colW * 0.6, 7, $this->enc((string)$value), 0, 0);
            $count++;
            if ($count % 2 === 0) {
                $pdf->Ln();
            } else {
                $pdf->Cell(10, 7, '', 0, 0);
            }
        }
        if ($count % 2 !== 0) $pdf->Ln();
    }

    private function renderItemsTable(FPDF $pdf, string $font, array $primary, array $accent, array $data, array $section): void
    {
        $cols  = $section['columns'] ?? [['key'=>'descripcion','label'=>'Descripcion','w'=>80],['key'=>'cantidad','label'=>'Cant.','w'=>20],['key'=>'precio','label'=>'Precio','w'=>40],['key'=>'total','label'=>'Total','w'=>40]];
        $items = $data['items'] ?? [];

        // Header row
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 9);
        foreach ($cols as $col) {
            $pdf->Cell($col['w'], 7, $this->enc($col['label']), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Data rows
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont($font, '', 9);
        $subtotal = 0;
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $i => $item) {
                $fill = ($i % 2 === 0);
                $pdf->SetFillColor(245, 245, 245);
                foreach ($cols as $col) {
                    $val = is_array($item) ? ($item[$col['key']] ?? '') : '';
                    if ($col['key'] === 'total' && isset($item['cantidad'], $item['precio'])) {
                        $val = (float)$item['cantidad'] * (float)$item['precio'];
                        $subtotal += $val;
                        $val = number_format($val, 2);
                    }
                    $pdf->Cell($col['w'], 6, $this->enc((string)$val), 1, 0, 'L', $fill);
                }
                $pdf->Ln();
            }
        } else {
            $total_w = array_sum(array_column($cols, 'w'));
            $pdf->Cell($total_w, 6, $this->enc('Sin items'), 1, 1, 'C');
        }

        // Totals
        if (!empty($section['show_totals'])) {
            $tax_rate = (float)($section['tax_rate'] ?? 0);
            $tax = $subtotal * ($tax_rate / 100);
            $total = $subtotal + $tax;
            $last_col_w = end($cols)['w'] ?? 40;
            $label_w = array_sum(array_column($cols, 'w')) - $last_col_w;
            $pdf->SetFont($font, 'B', 9);
            $pdf->Cell($label_w, 6, $this->enc('Subtotal:'), 1, 0, 'R');
            $pdf->Cell($last_col_w, 6, '$' . number_format($subtotal, 2), 1, 1, 'R');
            if ($tax_rate > 0) {
                // FIX: Corregido typo "%}:" — la llave extra } generaba texto incorrecto en el PDF.
                $pdf->Cell($label_w, 6, $this->enc("IVA ({$tax_rate}%):"), 1, 0, 'R');
                $pdf->Cell($last_col_w, 6, '$' . number_format($tax, 2), 1, 1, 'R');
            }
            $pdf->SetFillColor($accent[0], $accent[1], $accent[2]);
            $pdf->Cell($label_w, 7, $this->enc('TOTAL:'), 1, 0, 'R', true);
            $pdf->Cell($last_col_w, 7, '$' . number_format($total, 2), 1, 1, 'R', true);
        }
    }

    private function renderChecklist(FPDF $pdf, string $font, array $primary, array $data, array $section): void
    {
        $items = $section['items'] ?? [];
        $pdf->SetFont($font, '', 9);
        foreach ($items as $item) {
            $key     = $item['key'] ?? '';
            $label   = $item['label'] ?? $key;
            $value   = $data[$key] ?? 'N/A';
            $status_color = match(strtolower($value)) {
                'ok', 'bien', 'bueno' => [34, 197, 94],
                'falla', 'malo', 'mal' => [239, 68, 68],
                default => [156, 163, 175]
            };
            $pdf->Cell(120, 6, $this->enc($label), 'LTB', 0);
            $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(60, 6, $this->enc(strtoupper($value)), 'RTB', 1, 'C', true);
            $pdf->SetTextColor(50, 50, 50);
        }
    }
}
