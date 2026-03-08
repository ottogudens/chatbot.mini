<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Actions that REQUIRE authentication
$secure_actions = [
    'create',
    'update',
    'delete',
    'clients_create',
    'clients_update',
    'clients_delete',
    'assistants_create',
    'assistants_update',
    'assistants_delete',
    'info_create',
    'info_update',
    'info_delete'
];
if (in_array($action, $secure_actions)) {
    if (!check_auth(false)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado. Por favor inicie sesión.']);
        exit;
    }
}

switch ($action) {
    // ---- Clients ----
    case 'clients_list':
        $query = "SELECT * FROM clients ORDER BY id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;
    case 'clients_create':
        $name = $_POST['name'] ?? '';
        $email = $_POST['contact_email'] ?? '';
        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Nombre requerido.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO clients (name, contact_email) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $name, $email);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'clients_update':
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['contact_email'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE clients SET name=?, contact_email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'clients_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM clients WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Assistants ----
    case 'assistants_list':
        $client_id = $_GET['client_id'] ?? null;
        $query = "SELECT * FROM assistants";
        if ($client_id) {
            $query .= " WHERE client_id = " . intval($client_id);
        }
        $query .= " ORDER BY id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;
    case 'assistants_create':
        $client_id = $_POST['client_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $sp = $_POST['system_prompt'] ?? '';
        $stmt = mysqli_prepare($conn, "INSERT INTO assistants (client_id, name, system_prompt) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $client_id, $name, $sp);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'assistants_update':
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $sp = $_POST['system_prompt'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE assistants SET name=?, system_prompt=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $sp, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'assistants_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM assistants WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Info Sources ----
    case 'info_list':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if (!$assistant_id) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }
        $query = "SELECT * FROM information_sources WHERE assistant_id = " . intval($assistant_id) . " ORDER BY id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;
    case 'info_create':
        $assistant_id = $_POST['assistant_id'] ?? '';
        $type = $_POST['type'] ?? 'text';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content_text'] ?? '';

        $file_path = null;
        $file_type = null;
        $file_size = null;
        $gemini_uri = null;

        // Check if POST is empty but Content-Length is large (post_max_size exceeded)
        $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (empty($_POST) && $content_length > 0) {
            echo json_encode(['status' => 'error', 'message' => "El archivo subido excede el límite máximo permitido por el servidor (post_max_size). Tamaño intentado: " . round($content_length / 1024 / 1024, 2) . " MB."]);
            exit;
        }

        if (empty($assistant_id) || empty($title)) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (Asistente o Título).']);
            exit;
        }

        // --- Logic for Links ---
        if ($type === 'link') {
            $url = trim($content);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                // Basic scraper
                $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                $html = @file_get_contents($url, false, $ctx);
                if ($html !== false) {
                    // Extract body content roughly
                    $content = strip_tags(preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html));
                    $content = preg_replace('/\s+/', ' ', $content); // compress spaces
                    $content = "Contenido extraído de URL ($url):\n\n" . mb_substr($content, 0, 50000); // Limit size
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No se pudo acceder a la URL proporcionada.']);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'URL inválida.']);
                exit;
            }
        }

        // --- Logic for Files ---
        if ($type === 'file' && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_base_dir = __DIR__ . '/uploads';

            // Need client_id for folder structure
            $client_id_query = mysqli_query($conn, "SELECT client_id FROM assistants WHERE id = " . intval($assistant_id));
            $client_id = mysqli_fetch_assoc($client_id_query)['client_id'] ?? 'unknown';

            $target_dir = $upload_base_dir . "/clients/{$client_id}/assistants/{$assistant_id}/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $original_name = basename($_FILES['file_upload']['name']);
            // Sanitize filename
            $safe_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $original_name);
            $target_file = $target_dir . $safe_filename;

            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
                // Save relative path
                $file_path = "uploads/clients/{$client_id}/assistants/{$assistant_id}/" . $safe_filename;
                $file_type = $_FILES['file_upload']['type'];
                $file_size = filesize($target_file);
                $content = "Archivo subido: $original_name";

                // Upload to Gemini directly
                require_once 'gemini_client.php';
                $gemini = new GeminiClient();
                $uri = $gemini->upload_file_to_gemini($target_file, $file_type, $title);
                if ($uri) {
                    $gemini_uri = $uri;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Error subiendo el archivo a Google Gemini API.']);
                    exit;
                }
            } else {
                $upload_error_msg = error_get_last()['message'] ?? 'Desconocido';
                echo json_encode(['status' => 'error', 'message' => "Error moviendo el archivo subido al directorio físico. Revisa los permisos de escritura del volumen. " . $upload_error_msg]);
                exit;
            }
        } else if ($type === 'file') {
            $err_code = $_FILES['file_upload']['error'] ?? 'Ningún archivo recibido (puede que exceda upload_max_filesize)';
            echo json_encode(['status' => 'error', 'message' => "Error en la subida del archivo. Código PHP: $err_code."]);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO information_sources (assistant_id, type, title, content_text, file_path, file_type, file_size, gemini_file_uri) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssssis", $assistant_id, $type, $title, $content, $file_path, $file_type, $file_size, $gemini_uri);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            // Rollback file upload if DB fails
            if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                unlink(__DIR__ . '/' . $file_path);
            }
            echo json_encode(['status' => 'error', 'message' => 'Error guardando en base de datos.']);
        }
        break;
    case 'info_update':
        $id = $_POST['id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $content = $_POST['content_text'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE information_sources SET title=?, content_text=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'info_delete':
        $id = $_POST['id'] ?? 0;

        // Before deleting, try to remove the physical file if it exists
        $info_query = mysqli_query($conn, "SELECT file_path FROM information_sources WHERE id = " . intval($id));
        if ($row = mysqli_fetch_assoc($info_query)) {
            if (!empty($row['file_path'])) {
                $full_path = __DIR__ . '/' . $row['file_path'];
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM information_sources WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Chatbot Rules ----
    case 'list':
        $assistant_id = $_GET['assistant_id'] ?? null;
        $query = "SELECT * FROM chatbot";
        if ($assistant_id) {
            $query .= " WHERE assistant_id = " . intval($assistant_id);
        } else {
            $query .= " WHERE assistant_id IS NULL";
        }
        $query .= " ORDER BY id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'create':
        $assistant_id = empty($_POST['assistant_id']) ? null : $_POST['assistant_id'];
        $queries = $_POST['queries'] ?? '';
        $replies = $_POST['replies'] ?? '';
        $category = $_POST['category'] ?? 'general';

        if (empty($queries) || empty($replies)) {
            echo json_encode(['status' => 'error', 'message' => 'Consultas y respuestas son obligatorias.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO chatbot (assistant_id, queries, replies, category) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $assistant_id, $queries, $replies, $category);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Regla agregada exitosamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al agregar regla.']);
        }
        break;

    case 'update':
        $id = $_POST['id'] ?? 0;
        $queries = $_POST['queries'] ?? '';
        $replies = $_POST['replies'] ?? '';
        $category = $_POST['category'] ?? 'general';

        $stmt = mysqli_prepare($conn, "UPDATE chatbot SET queries=?, replies=?, category=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $queries, $replies, $category, $id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Regla actualizada exitosamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar regla.']);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM chatbot WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Regla eliminada exitosamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al eliminar regla.']);
        }
        break;

    // ---- Logs & Stats ----
    case 'logs':
        $assistant_id = $_GET['assistant_id'] ?? null;
        $query = "SELECT * FROM conversation_logs";
        if ($assistant_id) {
            $query .= " WHERE assistant_id = " . intval($assistant_id);
        } else {
            $query .= " WHERE assistant_id IS NULL";
        }
        $query .= " ORDER BY id DESC LIMIT 100";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'stats':
        $assistant_id = $_GET['assistant_id'] ?? null;
        $where = $assistant_id ? "WHERE assistant_id = " . intval($assistant_id) : "WHERE assistant_id IS NULL";

        $total_rules = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM chatbot $where"))['c'] ?? 0;
        $total_logs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM conversation_logs $where"))['c'] ?? 0;
        $failed_matches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM conversation_logs $where AND matched=0"))['c'] ?? 0;

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_rules' => $total_rules,
                'total_interactions' => $total_logs,
                'failed_matches' => $failed_matches,
                'accuracy' => $total_logs > 0 ? round((($total_logs - $failed_matches) / $total_logs) * 100) : 0
            ]
        ]);
        break;

    case 'chart_data':
        $assistant_id = $_GET['assistant_id'] ?? null;
        $where = $assistant_id ? "assistant_id = " . intval($assistant_id) : "assistant_id IS NULL";

        // Get counts for the last 7 days
        $query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                  FROM conversation_logs 
                  WHERE $where AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  GROUP BY DATE(created_at) 
                  ORDER BY date ASC";
        $result = mysqli_query($conn, $query);
        $labels = [];
        $values = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $labels[] = date('d M', strtotime($row['date']));
                $values[] = (int) $row['count'];
            }
        }

        echo json_encode(['status' => 'success', 'labels' => $labels, 'values' => $values]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
?>