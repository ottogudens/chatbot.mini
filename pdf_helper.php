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

    public function generate_from_template($template_id, $data, $client_id = null)
    {
        $content = "";

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

        $content = file_get_contents($template_path);

        // Replace placeholders
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        // Clean up any remaining placeholders
        $content = preg_replace('/\{\{.*?\}\}/', '', $content);

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

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_url = $protocol . "://" . $host . str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);

        return [
            "success" => true,
            "filename" => $filename,
            "url" => rtrim($base_url, '/') . '/uploads/' . $filename
        ];
    }
}
