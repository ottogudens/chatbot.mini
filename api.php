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
    'info_delete',
    'whatsapp_disconnect',
    'leads_list',
    'leads_create',
    'leads_update',
    'leads_delete',
    'leads_export'
];
if (in_array($action, $secure_actions)) {
    if (!check_auth(false)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado. Por favor inicie sesión.']);
        exit;
    }
}

$is_superadmin = ($_SESSION['role'] ?? 'client') === 'superadmin';
$session_client_id = $_SESSION['client_id'] ?? null;
define('WHATSAPP_API_URL', 'http://localhost:3001'); // URL of the Node.js bridge

function check_ast_owner($conn, $ast_id)
{
    global $is_superadmin, $session_client_id;
    if ($is_superadmin)
        return true;
    if (!$ast_id || !$session_client_id)
        return false;
    $q = mysqli_query($conn, "SELECT id FROM assistants WHERE id=" . intval($ast_id) . " AND client_id=" . intval($session_client_id));
    return mysqli_num_rows($q) > 0;
}
function check_item_owner($conn, $table, $id)
{
    global $is_superadmin, $session_client_id;
    if ($is_superadmin)
        return true;
    if (!$id || !$session_client_id)
        return false;
    $q = mysqli_query($conn, "SELECT a.id FROM $table t JOIN assistants a ON t.assistant_id = a.id WHERE t.id=" . intval($id) . " AND a.client_id=" . intval($session_client_id));
    return mysqli_num_rows($q) > 0;
}

// Superadmin-only actions
$superadmin_actions = [
    'clients_create',
    'clients_update',
    'clients_delete',
    'users_list',
    'users_create',
    'users_delete'
];
if (in_array($action, $superadmin_actions) && !$is_superadmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Se requiere ser superadmin.']);
    exit;
}

switch ($action) {
    // ---- Clients ----
    case 'clients_list':
        if (!$is_superadmin && $session_client_id) {
            $query = "SELECT * FROM clients WHERE id = " . intval($session_client_id) . " ORDER BY id DESC";
        } else {
            $query = "SELECT * FROM clients ORDER BY id DESC";
        }
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
        $type = $_POST['type'] ?? 'particular';
        $name = $_POST['name'] ?? '';
        $rut = $_POST['rut'] ?? null;
        $address = $_POST['address'] ?? null;
        $email = $_POST['contact_email'] ?? '';
        $phone = $_POST['phone'] ?? null;
        $giro = $_POST['business_line'] ?? null;
        $rep_name = $_POST['representative_name'] ?? null;
        $rep_phone = $_POST['representative_phone'] ?? null;
        $rep_email = $_POST['representative_email'] ?? null;

        if (empty($name) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Nombre y Email son requeridos.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO clients (name, contact_email, type, rut, address, phone, business_line, representative_name, representative_phone, representative_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssssssss", $name, $email, $type, $rut, $address, $phone, $giro, $rep_name, $rep_phone, $rep_email);

        if (mysqli_stmt_execute($stmt)) {
            $new_client_id = mysqli_insert_id($conn);

            // Create user automatically
            $password = "admin123!";
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, role, client_id) VALUES (?, ?, 'client', ?)");
            mysqli_stmt_bind_param($user_stmt, "ssi", $email, $hash, $new_client_id);
            mysqli_stmt_execute($user_stmt);

            echo json_encode(['status' => 'success', 'client_id' => $new_client_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        break;
    case 'clients_update':
        $id = $_POST['id'] ?? 0;
        $type = $_POST['type'] ?? 'particular';
        $name = $_POST['name'] ?? '';
        $rut = $_POST['rut'] ?? null;
        $address = $_POST['address'] ?? null;
        $email = $_POST['contact_email'] ?? '';
        $phone = $_POST['phone'] ?? null;
        $giro = $_POST['business_line'] ?? null;
        $rep_name = $_POST['representative_name'] ?? null;
        $rep_phone = $_POST['representative_phone'] ?? null;
        $rep_email = $_POST['representative_email'] ?? null;

        if (empty($name) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Nombre y Email son requeridos.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE clients SET name=?, contact_email=?, type=?, rut=?, address=?, phone=?, business_line=?, representative_name=?, representative_phone=?, representative_email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssssssssssi", $name, $email, $type, $rut, $address, $phone, $giro, $rep_name, $rep_phone, $rep_email, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'clients_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM clients WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Users (Superadmin only) ----
    case 'users_list':
        $query = "SELECT u.id, u.username, u.role, u.client_id, c.name as client_name, u.created_at FROM users u LEFT JOIN clients c ON u.client_id = c.id ORDER BY u.id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;
    case 'users_create':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'client';
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Usuario y contraseña requeridos.']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, role, client_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssi", $username, $hash, $role, $client_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al crear usuario. Posible nombre duplicado.']);
        }
        break;
    case 'users_update':
        $id = $_POST['id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'client';
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;

        if (empty($username)) {
            echo json_encode(['status' => 'error', 'message' => 'Usuario requerido.']);
            exit;
        }

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, password_hash=?, role=?, client_id=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssssi", $username, $hash, $role, $client_id, $id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, role=?, client_id=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssii", $username, $role, $client_id, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar usuario.']);
        }
        break;
    case 'users_delete':
        $id = $_POST['id'] ?? 0;
        if ($id == $_SESSION['admin_id']) {
            echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar tu propia cuenta.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Leads (Marketing) ----
    case 'leads_list':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $query = "SELECT l.*, a.name as assistant_name FROM leads l LEFT JOIN assistants a ON l.assistant_id = a.id WHERE l.client_id = " . intval($req_client_id) . " ORDER BY l.id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'leads_create':
        $client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $ast_id = !empty($_POST['assistant_id']) ? intval($_POST['assistant_id']) : null;
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $stmt = mysqli_prepare($conn, "INSERT INTO leads (client_id, assistant_id, name, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iissss", $client_id, $ast_id, $name, $phone, $email, $notes);
        if (mysqli_stmt_execute($stmt))
            echo json_encode(['status' => 'success']);
        else
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        break;

    case 'leads_update':
        $id = $_POST['id'] ?? 0;
        $q = mysqli_query($conn, "SELECT client_id FROM leads WHERE id=" . intval($id));
        $row = mysqli_fetch_assoc($q);
        if (!$row || (!$is_superadmin && !empty($row['client_id']) && $row['client_id'] != $session_client_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $status = $_POST['status'] ?? 'nuevo';
        $notes = $_POST['notes'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE leads SET name=?, phone=?, email=?, status=?, notes=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssssi", $name, $phone, $email, $status, $notes, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    case 'leads_delete':
        $id = $_POST['id'] ?? 0;
        $q = mysqli_query($conn, "SELECT client_id FROM leads WHERE id=" . intval($id));
        $row = mysqli_fetch_assoc($q);
        if (!$row || (!$is_superadmin && !empty($row['client_id']) && $row['client_id'] != $session_client_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        mysqli_query($conn, "DELETE FROM leads WHERE id=" . intval($id));
        echo json_encode(['status' => 'success']);
        break;

    case 'leads_export':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            die("No autorizado");
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="prospectos_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nombre', 'Telefono', 'Email', 'Estado', 'Notas', 'Asistente', 'Fecha']);
        $query = "SELECT l.id, l.name, l.phone, l.email, l.status, l.notes, a.name as assistant_name, l.created_at FROM leads l LEFT JOIN assistants a ON l.assistant_id = a.id WHERE l.client_id = " . intval($req_client_id);
        if (!empty($_GET['assistant_id'])) {
            $query .= " AND l.assistant_id = " . intval($_GET['assistant_id']);
        }
        $query .= " ORDER BY l.id DESC";
        $result = mysqli_query($conn, $query);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    // ---- Assistants ----
    case 'assistants_list':
        $requested_client_id = $_GET['client_id'] ?? null;

        // Security: If not superadmin, FORCE the session's client_id
        if (!$is_superadmin) {
            $requested_client_id = $session_client_id;
        }

        $query = "SELECT * FROM assistants";
        if ($requested_client_id) {
            $query .= " WHERE client_id = " . intval($requested_client_id);
        } else if (!$is_superadmin) {
            // Fallback for safety: if somehow session_client_id is null and not superadmin, return empty
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
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
        $client_id = !$is_superadmin ? $session_client_id : ($_POST['client_id'] ?? '');
        $name = $_POST['name'] ?? '';
        $sp = $_POST['system_prompt'] ?? '';
        $gemini_model = $_POST['gemini_model'] ?? 'gemini-2.5-flash';
        $temperature = floatval($_POST['temperature'] ?? 0.70);
        $max_tokens = intval($_POST['max_output_tokens'] ?? 1500);
        $response_style = $_POST['response_style'] ?? 'balanced';
        $voice_enabled = isset($_POST['voice_enabled']) ? intval($_POST['voice_enabled']) : 1;
        $stmt = mysqli_prepare($conn, "INSERT INTO assistants (client_id, name, system_prompt, gemini_model, temperature, max_output_tokens, response_style, voice_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssdiis", $client_id, $name, $sp, $gemini_model, $temperature, $max_tokens, $response_style, $voice_enabled);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error', 'error' => mysqli_error($conn)]);
        break;
    case 'assistants_update':
        $id = $_POST['id'] ?? 0;
        if (!check_ast_owner($conn, $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $name = $_POST['name'] ?? '';
        $sp = $_POST['system_prompt'] ?? '';
        $gemini_model = $_POST['gemini_model'] ?? 'gemini-2.5-flash';
        $temperature = floatval($_POST['temperature'] ?? 0.70);
        $max_tokens = intval($_POST['max_output_tokens'] ?? 1500);
        $response_style = $_POST['response_style'] ?? 'balanced';
        $voice_enabled = isset($_POST['voice_enabled']) ? intval($_POST['voice_enabled']) : 1;
        $stmt = mysqli_prepare($conn, "UPDATE assistants SET name=?, system_prompt=?, gemini_model=?, temperature=?, max_output_tokens=?, response_style=?, voice_enabled=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssssdiis", $name, $sp, $gemini_model, $temperature, $max_tokens, $response_style, $voice_enabled, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error', 'error' => mysqli_error($conn)]);
        break;
    case 'assistants_delete':
        $id = $_POST['id'] ?? 0;
        if (!check_ast_owner($conn, $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM assistants WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;

    // ---- Info Sources ----
    case 'info_list':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if (!$assistant_id || !check_ast_owner($conn, $assistant_id)) {
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
        if (!check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No tienes permisos sobre este asistente.']);
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
        if (!check_item_owner($conn, 'information_sources', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $title = $_POST['title'] ?? '';
        $content = $_POST['content_text'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE information_sources SET title=?, content_text=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $id);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
        break;
    case 'info_delete':
        $id = $_POST['id'] ?? 0;
        if (!check_item_owner($conn, 'information_sources', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }

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
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }

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
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }

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
        if (!check_item_owner($conn, 'chatbot', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }

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
        if (!check_item_owner($conn, 'chatbot', $id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
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
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'success', 'data' => []]);
            exit;
        }

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
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'success', 'data' => ['total_rules' => 0, 'total_interactions' => 0, 'failed_matches' => 0, 'accuracy' => 0]]);
            exit;
        }

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
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            echo json_encode(['status' => 'success', 'labels' => [], 'values' => []]);
            exit;
        }

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
    case 'calendar_settings_get':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado para ver este cliente']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "SELECT calendar_id, available_days, start_time, end_time, slot_duration_minutes, timezone FROM calendar_settings WHERE client_id=?");
        mysqli_stmt_bind_param($stmt, "i", $req_client_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($res);
        if (!$data) {
            $data = [
                'calendar_id' => 'primary',
                'available_days' => '1,2,3,4,5',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'slot_duration_minutes' => 30,
                'timezone' => 'America/Santiago'
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'calendar_settings_update':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        // $req_client_id was extracted correctly. Now processing form data:
        $calendar_id = $_POST['calendar_id'] ?? 'primary';
        $available_days = isset($_POST['available_days']) ? implode(',', (array) $_POST['available_days']) : '1,2,3,4,5';
        $start_time = $_POST['start_time'] ?? '09:00:00';
        $end_time = $_POST['end_time'] ?? '18:00:00';
        $slot_duration_minutes = intval($_POST['slot_duration_minutes'] ?? 30);
        $timezone = $_POST['timezone'] ?? 'America/Santiago';

        $stmt = mysqli_prepare($conn, "INSERT INTO calendar_settings (client_id, calendar_id, available_days, start_time, end_time, slot_duration_minutes, timezone) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE calendar_id=VALUES(calendar_id), available_days=VALUES(available_days), start_time=VALUES(start_time), end_time=VALUES(end_time), slot_duration_minutes=VALUES(slot_duration_minutes), timezone=VALUES(timezone)");
        mysqli_stmt_bind_param($stmt, "issssis", $req_client_id, $calendar_id, $available_days, $start_time, $end_time, $slot_duration_minutes, $timezone);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error guardando configuraciones: ' . mysqli_error($conn)]);
        }
        break;

    // ---- PDF Templates ----
    case 'pdf_templates_list':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        require_once 'pdf_helper.php';
        $pdf_helper = new PDFHelper($conn);
        $templates = $pdf_helper->list_templates($req_client_id);
        echo json_encode(['status' => 'success', 'data' => $templates]);
        break;

    case 'pdf_templates_upload':
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }

        // Ensure no previous output (warnings, etc) breaks the JSON
        if (ob_get_length())
            ob_clean();
        error_log("Starting PDF upload for client $req_client_id");

        $name = $_POST['name'] ?? '';
        if (empty($name) || !isset($_FILES['template_file'])) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos']);
            exit;
        }

        // Increase time limit for Gemini analysis
        set_time_limit(300);

        $upload_dir = __DIR__ . "/uploads/clients/{$req_client_id}/pdf_templates/";
        if (!is_dir($upload_dir)) {
            if (!@mkdir($upload_dir, 0777, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Error de permisos: No se pudo crear el directorio de carga.']);
                exit;
            }
        }

        $file = $_FILES['template_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $file['name']);
        $target_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $placeholders = [];

            if ($ext === 'pdf') {
                require_once 'gemini_client.php';
                $gemini = new GeminiClient();
                $uri = $gemini->upload_file_to_gemini($target_path, 'application/pdf', $name);
                if ($uri) {
                    $placeholders = $gemini->analyze_pdf_placeholders($uri, 'application/pdf');
                }
            } else {
                // Existing logic for .txt/standard files
                $content = file_get_contents($target_path);
                preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
                $placeholders = isset($matches[1]) ? array_unique($matches[1]) : [];
            }

            $placeholders_json = json_encode(array_values($placeholders));
            $relative_path = "uploads/clients/{$req_client_id}/pdf_templates/" . $safe_filename;

            $stmt = mysqli_prepare($conn, "INSERT INTO pdf_templates (client_id, name, file_path, placeholders) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $req_client_id, $name, $relative_path, $placeholders_json);

            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success', 'detected_fields' => array_values($placeholders)]);
            } else {
                unlink($target_path);
                echo json_encode(['status' => 'error', 'message' => 'Error en base de datos: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error moviendo el archivo']);
        }
        break;

    case 'pdf_templates_delete':
        $id = $_POST['id'] ?? 0;
        // Check ownership
        $q = mysqli_query($conn, "SELECT client_id, file_path FROM pdf_templates WHERE id = " . intval($id));
        if ($row = mysqli_fetch_assoc($q)) {
            if (!$is_superadmin && $row['client_id'] != $session_client_id) {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                exit;
            }
            // Delete file
            $fpath = __DIR__ . '/' . $row['file_path'];
            if (file_exists($fpath)) {
                unlink($fpath);
            }
            // Delete DB record
            mysqli_query($conn, "DELETE FROM pdf_templates WHERE id = " . intval($id));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
        }
        break;

    case 'pdf_templates_rename':
        $id = $_POST['id'] ?? 0;
        $new_name = $_POST['name'] ?? '';
        if (empty($new_name)) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre es requerido']);
            exit;
        }
        // Check ownership
        $q = mysqli_query($conn, "SELECT client_id FROM pdf_templates WHERE id = " . intval($id));
        if ($row = mysqli_fetch_assoc($q)) {
            if (!$is_superadmin && $row['client_id'] != $session_client_id) {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "UPDATE pdf_templates SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_name, $id);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al renombrar: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
        }
        break;

    case 'appointments_list':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $ast_filter = $_GET['assistant_id'] ?? null;
        $where = "a.client_id = " . intval($req_client_id);
        if ($ast_filter)
            $where .= " AND a.assistant_id = " . intval($ast_filter);
        $query = "SELECT a.id, a.user_name, a.user_email, a.user_phone,
                         a.appointment_date, a.appointment_time,
                         a.google_event_id, a.google_calendar_id, a.status, a.created_at,
                         ast.name AS assistant_name
                  FROM appointments a
                  JOIN assistants ast ON a.assistant_id = ast.id
                  WHERE $where
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC
                  LIMIT 200";
        $result = mysqli_query($conn, $query);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result))
                $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    // ---- WhatsApp Integration (Proxy to Node.js) ----
    case 'whatsapp_status':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $res = @file_get_contents(WHATSAPP_API_URL . "/status/" . $ast_id);
        if ($res === false) {
            echo json_encode(['status' => 'offline', 'message' => 'Servicio de WhatsApp no disponible']);
        } else {
            echo $res;
        }
        break;

    case 'whatsapp_qr':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $res = @file_get_contents(WHATSAPP_API_URL . "/qr/" . $ast_id);
        if ($res === false) {
            echo json_encode(['status' => 'offline', 'message' => 'Servicio de WhatsApp no disponible']);
        } else {
            echo $res;
        }
        break;

    case 'whatsapp_connect':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        // Use cURL for POST proxy
        $ch = curl_init(WHATSAPP_API_URL . "/connect/" . $ast_id);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        if (curl_errno($ch)) {
            echo json_encode(['status' => 'offline', 'message' => 'Servicio de WhatsApp no disponible']);
        } else {
            echo $res;
        }
        // curl_close is unnecessary in PHP 8.4+ and deprecated in 8.5
        break;

    case 'whatsapp_disconnect':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $ch = curl_init(WHATSAPP_API_URL . "/disconnect/" . $ast_id);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        if (curl_errno($ch)) {
            echo json_encode(['status' => 'offline', 'message' => 'Servicio de WhatsApp no disponible']);
        } else {
            echo $res;
        }
        // curl_close is unnecessary in PHP 8.4+ and deprecated in 8.5
        break;

    case 'appointments_cancel':
        $appt_id = $_POST['id'] ?? 0;
        // Fetch appointment data
        $appt_query = mysqli_query($conn, "SELECT a.*, ci.access_token, ci.refresh_token, ci.expires_at
            FROM appointments a
            JOIN clients c ON a.client_id = c.id
            LEFT JOIN client_integrations ci ON ci.client_id = a.client_id AND ci.provider = 'google_drive'
            WHERE a.id = " . intval($appt_id));
        $appt = mysqli_fetch_assoc($appt_query);
        if (!$appt) {
            echo json_encode(['status' => 'error', 'message' => 'Reserva no encontrada.']);
            exit;
        }
        if (!$is_superadmin && $appt['client_id'] != $session_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
            exit;
        }

        // Try to cancel in Google Calendar
        $google_cancelled = false;
        if ($appt['google_event_id'] && $appt['access_token']) {
            // Refresh token if needed
            $access_token = $appt['access_token'];
            if (strtotime($appt['expires_at']) <= time() + 300 && $appt['refresh_token']) {
                $ch = curl_init('https://oauth2.googleapis.com/token');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'client_id' => getenv('GOOGLE_CLIENT_ID'),
                    'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
                    'refresh_token' => $appt['refresh_token'],
                    'grant_type' => 'refresh_token'
                ]));
                $ref_res = json_decode(curl_exec($ch), true);
                if (!empty($ref_res['access_token']))
                    $access_token = $ref_res['access_token'];
            }
            $cal_id = urlencode($appt['google_calendar_id'] ?: 'primary');
            $event_id = urlencode($appt['google_event_id']);
            $del_url = "https://www.googleapis.com/calendar/v3/calendars/$cal_id/events/$event_id";
            $ch = curl_init($del_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
            curl_exec($ch);
            $del_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $google_cancelled = ($del_code === 204 || $del_code === 200);
        }

        // Update local status
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status='cancelled' WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $appt_id);
        mysqli_stmt_execute($stmt);

        echo json_encode([
            'status' => 'success',
            'message' => $google_cancelled
                ? 'Reserva cancelada en el sistema y en Google Calendar.'
                : 'Reserva cancelada en el sistema (no se pudo eliminar de Google Calendar).'
        ]);
        break;

    default:

        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
?>