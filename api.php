<?php
require 'db.php';
require 'auth.php';
require_once 'whatsapp_helper.php';
header('Content-Type: application/json');


$action = isset($_GET['action']) ? $_GET['action'] : '';

// Actions that REQUIRE authentication
$secure_actions = [
    'create', 'update', 'delete',
    'clients_list', 'clients_create', 'clients_update', 'clients_delete',
    'assistants_list', 'assistants_create', 'assistants_update', 'assistants_delete',
    'info_list', 'info_create', 'info_update', 'info_delete',
    'whatsapp_connector', 'whatsapp_status', 'whatsapp_qr', 'whatsapp_connect', 'whatsapp_disconnect',
    'leads_list', 'leads_create', 'leads_update', 'leads_delete', 'leads_export',
    'pdf_templates_list', 'pdf_templates_save', 'pdf_templates_rename', 'pdf_templates_delete', 'pdf_templates_download',
    'pdf_templates_save_config', 'pdf_templates_preview', 'pdf_templates_logo_upload', 'pdf_templates_analyze',
    'pdf_generated_list', 'pdf_generated_delete',
    'campaigns_list', 'campaigns_create', 'campaigns_delete', 'campaigns_send',
    'appointments_list', 'appointments_cancel',
    'calendar_settings_get', 'calendar_settings_update',
    'users_list', 'users_create', 'users_update', 'users_delete',
    'stats', 'chart_data', 'logs', 'list',
    'logs_export', 'agents_list', 'agents_create', 'agents_delete',
    'flows_list', 'flows_create', 'flows_update', 'flows_delete', 'flows_steps_list', 'flows_steps_update'
];
if (in_array($action, $secure_actions)) {
    if (!check_auth(false)) {
        http_response_code(401);
        send_response('error', 'No autorizado. Por favor inicie sesión.');
    }
}

// OPT-2: CSRF protection for all state-mutating POST actions
$csrf_protected_actions = [
    'create', 'update', 'delete',
    'clients_create', 'clients_update', 'clients_delete',
    'assistants_create', 'assistants_update', 'assistants_delete',
    'info_create', 'info_update', 'info_delete',
    'leads_create', 'leads_update', 'leads_delete',
    'calendar_settings_update',
    'pdf_templates_save', 'pdf_templates_save_config', 'users_create', 'users_update', 'users_delete',
    'whatsapp_disconnect',
    'campaigns_create', 'campaigns_delete', 'campaigns_send',
    'agents_create', 'agents_delete',
    'flows_create', 'flows_update', 'flows_delete', 'flows_steps_update'
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, $csrf_protected_actions, true)) {
    $csrf_post  = $_POST['csrf_token'] ?? '';
    $csrf_sess  = $_SESSION['csrf_token'] ?? '';
    if (empty($csrf_post) || empty($csrf_sess) || !hash_equals($csrf_sess, $csrf_post)) {
        http_response_code(403);
        send_response('error', 'Token CSRF inválido. Recarga la página e intenta de nuevo.');
    }
}

$is_superadmin = ($_SESSION['role'] ?? 'client') === 'superadmin';
$session_client_id = $_SESSION['client_id'] ?? null;
define('WHATSAPP_API_URL', 'http://localhost:3001'); // URL of the Node.js bridge

function check_ast_owner($conn, $ast_id)
{
    global $is_superadmin, $session_client_id;
    if ($is_superadmin) return true;
    if (!$ast_id || !$session_client_id) return false;
    $stmt = mysqli_prepare($conn, "SELECT id FROM assistants WHERE id = ? AND client_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $ast_id, $session_client_id);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}
function check_client_owner($conn, $client_id)
{
    global $is_superadmin, $session_client_id;
    if ($is_superadmin)
        return true;
    return $client_id && $session_client_id && intval($client_id) === intval($session_client_id);
}
function check_item_owner($conn, $table, $id)
{
    global $is_superadmin, $session_client_id;
    $allowed_tables = ['chatbot', 'information_sources'];
    if (!in_array($table, $allowed_tables, true)) return false;
    if ($is_superadmin) return true;
    if (!$id || !$session_client_id) return false;
    $stmt = mysqli_prepare($conn, "SELECT a.id FROM $table t JOIN assistants a ON t.assistant_id = a.id WHERE t.id = ? AND a.client_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $session_client_id);
    mysqli_stmt_execute($stmt);
    return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
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
    send_response('error', 'Acceso denegado. Se requiere ser superadmin.');
}

switch ($action) {
    // ---- Clients ----
    case 'clients_list':
        if (!$is_superadmin && $session_client_id) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM clients WHERE id = ? ORDER BY id DESC");
            mysqli_stmt_bind_param($stmt, "i", $session_client_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM clients ORDER BY id DESC");
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        send_response('success', '', $data);
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
            send_response('error', 'Nombre y Email son requeridos.');
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO clients (name, contact_email, type, rut, address, phone, business_line, representative_name, representative_phone, representative_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssssssss", $name, $email, $type, $rut, $address, $phone, $giro, $rep_name, $rep_phone, $rep_email);

        if (mysqli_stmt_execute($stmt)) {
            $new_client_id = mysqli_insert_id($conn);

            // Create user automatically with a secure random password
            $password = bin2hex(random_bytes(8)); // 16-char hex random password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, role, client_id) VALUES (?, ?, 'client', ?)");
            mysqli_stmt_bind_param($user_stmt, "ssi", $email, $hash, $new_client_id);
            mysqli_stmt_execute($user_stmt);

            send_response('success', 'Cliente creado con éxito.', [
                'client_id' => $new_client_id,
                'temp_password' => $password,
                'note' => 'Contraseña temporal. El cliente debe cambiarla al primer acceso.'
            ]);
        } else {
            log_error('clients_create failed', ['error' => mysqli_error($conn)]);
            send_response('error', 'Error al crear el cliente.');
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
            send_response('error', 'Nombre y Email son requeridos.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE clients SET name=?, contact_email=?, type=?, rut=?, address=?, phone=?, business_line=?, representative_name=?, representative_phone=?, representative_email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssssssssssi", $name, $email, $type, $rut, $address, $phone, $giro, $rep_name, $rep_phone, $rep_email, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Cliente actualizado.');
        } else {
            log_error('clients_update failed', ['id' => $id, 'error' => mysqli_error($conn)]);
            send_response('error', 'Error al actualizar el cliente.');
        }
        break;
    case 'clients_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "DELETE FROM clients WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Cliente eliminado.');
        } else {
            send_response('error', 'Error al eliminar cliente.');
        }
        break;

    // ---- Users (Superadmin only) ----
    case 'users_list':
        $stmt = mysqli_prepare($conn, "SELECT u.id, u.username, u.role, u.client_id, c.name as client_name, u.created_at FROM users u LEFT JOIN clients c ON u.client_id = c.id ORDER BY u.id DESC");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        send_response('success', '', $data);
        break;
    case 'users_create':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'client';
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;

        if (empty($username) || empty($password)) {
            send_response('error', 'Usuario y contraseña requeridos.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password_hash, role, client_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssi", $username, $hash, $role, $client_id);
        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Usuario creado.');
        } else {
            send_response('error', 'Error al crear usuario. Posible nombre duplicado.');
        }
        break;
    case 'users_update':
        $id = $_POST['id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'client';
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;

        if (empty($username)) {
            send_response('error', 'Usuario requerido.');
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
            send_response('success', 'Usuario actualizado.');
        } else {
            send_response('error', 'Error al actualizar usuario.');
        }
        break;
    case 'users_delete':
        $id = $_POST['id'] ?? 0;
        if ($id == $_SESSION['admin_id']) {
            send_response('error', 'No puedes eliminar tu propia cuenta.');
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Usuario eliminado.');
        } else {
            send_response('error', 'Error al eliminar usuario.');
        }
        break;

    // ---- Leads (Marketing) ----
    case 'leads_list':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        $filter_assistant = isset($_GET['assistant_id']) && is_numeric($_GET['assistant_id']) ? intval($_GET['assistant_id']) : null;

        if (!$is_superadmin && $req_client_id != $session_client_id) {
            send_response('error', 'No autorizado');
        }

        $query = "SELECT l.*, a.name as assistant_name FROM leads l LEFT JOIN assistants a ON l.assistant_id = a.id WHERE ";
        $where = [];
        $params = [];
        $types = "";

        if ($req_client_id !== null) {
            $where[] = "l.client_id = ?";
            $params[] = $req_client_id;
            $types .= "i";
        }
        if ($filter_assistant) {
            $where[] = "l.assistant_id = ?";
            $params[] = $filter_assistant;
            $types .= "i";
        }

        $query .= (!empty($where) ? implode(" AND ", $where) : "1=1") . " ORDER BY l.id DESC";
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
        }
        send_response('success', '', $data);
        break;

    case 'leads_create':
        $client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $client_id != $session_client_id) {
            send_response('error', 'No autorizado');
        }
        $ast_id = !empty($_POST['assistant_id']) ? intval($_POST['assistant_id']) : null;
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $stmt = mysqli_prepare($conn, "INSERT INTO leads (client_id, assistant_id, name, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iissss", $client_id, $ast_id, $name, $phone, $email, $notes);
        if (mysqli_stmt_execute($stmt))
            send_response('success', 'Prospecto registrado.');
        else {
            log_error('leads_create error', ['error' => mysqli_error($conn)]);
            send_response('error', 'Error al crear el prospecto.');
        }
        break;

    case 'leads_update':
        $id = $_POST['id'] ?? 0;
        $stmt_check = mysqli_prepare($conn, "SELECT client_id FROM leads WHERE id = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $id);
        mysqli_stmt_execute($stmt_check);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
        if (!$row || (!$is_superadmin && !empty($row['client_id']) && $row['client_id'] != $session_client_id)) {
            send_response('error', 'No autorizado');
        }
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $status = $_POST['status'] ?? 'nuevo';
        $notes = $_POST['notes'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE leads SET name=?, phone=?, email=?, status=?, notes=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssssi", $name, $phone, $email, $status, $notes, $id);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Prospecto actualizado.');
        else send_response('error', 'Error al actualizar.');
        break;

    case 'leads_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "SELECT client_id FROM leads WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if (!$row || (!$is_superadmin && !empty($row['client_id']) && $row['client_id'] != $session_client_id)) {
            send_response('error', 'No autorizado');
        }
        $del = mysqli_prepare($conn, "DELETE FROM leads WHERE id=?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) send_response('success', 'Prospecto eliminado.');
        else send_response('error', 'Error al eliminar');
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
        
        $sql = "SELECT l.id, l.name, l.phone, l.email, l.status, l.notes, a.name as assistant_name, l.created_at FROM leads l LEFT JOIN assistants a ON l.assistant_id = a.id WHERE l.client_id = ?";
        if (!empty($_GET['assistant_id'])) {
            $sql .= " AND l.assistant_id = ?";
            $stmt = mysqli_prepare($conn, $sql . " ORDER BY l.id DESC");
            mysqli_stmt_bind_param($stmt, "ii", $req_client_id, $_GET['assistant_id']);
        } else {
            $stmt = mysqli_prepare($conn, $sql . " ORDER BY l.id DESC");
            mysqli_stmt_bind_param($stmt, "i", $req_client_id);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    // ---- Marketing Campaigns ----
    case 'campaigns_list':
        $cid = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $cid != $session_client_id) send_response('error', 'No autorizado');
        $stmt = mysqli_prepare($conn, "SELECT * FROM marketing_campaigns WHERE client_id = ? ORDER BY id DESC");
        mysqli_stmt_bind_param($stmt, "i", $cid);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while($row = mysqli_fetch_assoc($res)) $data[] = $row;
        send_response('success', '', $data);
        break;

    case 'campaigns_create':
        $cid = $_POST['client_id'] ?? $session_client_id;
        if (!check_client_owner($conn, $cid)) {
            send_response('error', 'No autorizado');
        }
        $name = $_POST['name'] ?? '';
        $message = $_POST['message'] ?? '';
        $target_type = $_POST['target_type'] ?? 'all';

        $attachment_url = null;
        $attachment_type = null;

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/campaigns/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $orig_name = $_FILES['attachment']['name'];
            $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
            $safe_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_file = $upload_dir . $safe_name;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_url = 'uploads/campaigns/' . $safe_name;
                $mime = $_FILES['attachment']['type'];
                if (strpos($mime, 'image/') === 0) $attachment_type = 'image';
                elseif (strpos($mime, 'video/') === 0) $attachment_type = 'video';
                else $attachment_type = 'document';
            }
        }

        if (mysqli_stmt_execute($stmt)) send_response('success', 'Campaña creada.');
        else {
            log_error('campaigns_create error', ['error' => mysqli_error($conn)]);
            send_response('error', 'Error al crear la campaña.');
        }
        break;

    case 'campaigns_delete':
        $id = $_POST['id'] ?? 0;
        $chk_stmt = mysqli_prepare($conn, "SELECT client_id FROM marketing_campaigns WHERE id = ?");
        mysqli_stmt_bind_param($chk_stmt, "i", $id);
        mysqli_stmt_execute($chk_stmt);
        $res = mysqli_stmt_get_result($chk_stmt);
        $row_chk = mysqli_fetch_assoc($res);
        if (!$row_chk || (!$is_superadmin && $row_chk['client_id'] != $session_client_id)) {
            send_response('error', 'No autorizado');
        }
        $del = mysqli_prepare($conn, "DELETE FROM marketing_campaigns WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) send_response('success', 'Campaña eliminada.');
        else send_response('error', 'Error al eliminar campaña.');
        break;

    case 'campaigns_send':
        $id = $_POST['id'] ?? 0;
        $assistant_id = $_POST['assistant_id'] ?? 0;
        $lead_ids = isset($_POST['lead_ids']) ? explode(',', $_POST['lead_ids']) : [];

        $q_stmt = mysqli_prepare($conn, "SELECT * FROM marketing_campaigns WHERE id = ?");
        mysqli_stmt_bind_param($q_stmt, "i", $id);
        mysqli_stmt_execute($q_stmt);
        $campaign = mysqli_fetch_assoc(mysqli_stmt_get_result($q_stmt));

        if (!$campaign || (!$is_superadmin && $campaign['client_id'] != $session_client_id)) {
            send_response('error', 'No autorizado o campaña no existe');
        }

        $leads = [];
        if ($campaign['target_type'] === 'all') {
            $leads_q = mysqli_prepare($conn, "SELECT phone FROM leads WHERE client_id = ?");
            mysqli_stmt_bind_param($leads_q, "i", $campaign['client_id']);
            mysqli_stmt_execute($leads_q);
            $q_lres = mysqli_stmt_get_result($leads_q);
            while ($rl = mysqli_fetch_assoc($q_lres)) $leads[] = $rl['phone'];
        } else {
            $ids_to_use = !empty($lead_ids) ? $lead_ids : (!empty($campaign['target_ids']) ? explode(',', $campaign['target_ids']) : []);
            if (!empty($ids_to_use)) {
                $ids_str = implode(',', array_map('intval', $ids_to_use));
                // Since $ids_str is built from intval, it's safe for IN clause, 
                // but for total compliance we still check client_id with prepared statement
                $sql_leads = "SELECT phone FROM leads WHERE client_id = ? AND id IN ($ids_str)";
                $stmt_leads = mysqli_prepare($conn, $sql_leads);
                mysqli_stmt_bind_param($stmt_leads, "i", $campaign['client_id']);
                mysqli_stmt_execute($stmt_leads);
                $q_leads_res = mysqli_stmt_get_result($stmt_leads);
                while ($rl = mysqli_fetch_assoc($q_leads_res)) $leads[] = $rl['phone'];
            }
        }

        if (empty($leads)) send_response('error', 'No hay destinatarios válidos seleccionados');
        
        // Optimize for long execution
        set_time_limit(0);
        ignore_user_abort(true);

        // 2. Sending Loop (via Proxy to whatsapp.js)
        $success_count = 0;
        $error_count = 0;
        $baseUrl = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://" . $_SERVER['HTTP_HOST'] . "/";
        
        foreach ($leads as $phone) {
            $mediaUrl = !empty($campaign['attachment_url']) ? ($baseUrl . $campaign['attachment_url']) : null;
            $res = send_whatsapp_message($assistant_id, $phone, $campaign['message'], $mediaUrl, $campaign['attachment_type'] ?? 'document');
            
            if ($res['status'] === 'success') {
                $success_count++;
            } else {
                $error_count++;
            }
            usleep(500000); // 0.5s delay
        }

        $new_status = ($error_count === 0) ? 'sent' : 'error';
        $upd_stmt = mysqli_prepare($conn, "UPDATE marketing_campaigns SET status = ?, sent_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "si", $new_status, $id);
        mysqli_stmt_execute($upd_stmt);

        send_response('success', 'Envío completado', [
            'sent' => $success_count,
            'failed' => $error_count
        ]);
        break;

    // ---- Assistants ----
    case 'assistants_list':
        $requested_client_id = $_GET['client_id'] ?? null;
        if (!$is_superadmin) $requested_client_id = $session_client_id;

        if ($requested_client_id) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM assistants WHERE client_id = ? ORDER BY id DESC");
            mysqli_stmt_bind_param($stmt, "i", $requested_client_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM assistants ORDER BY id DESC");
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
        }
        send_response('success', '', $data);
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
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Asistente creado.');
        else {
            log_error('assistants_create failed', ['error' => mysqli_error($conn)]);
            send_response('error', 'Error al crear asistente.');
        }
        break;
    case 'assistants_update':
        $id = $_POST['id'] ?? 0;
        if (!check_ast_owner($conn, $id)) {
            send_response('error', 'No autorizado');
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
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Asistente actualizado.');
        else {
            log_error('assistants_update failed', ['id' => $id, 'error' => mysqli_error($conn)]);
            send_response('error', 'Error al actualizar.');
        }
        break;
    case 'assistants_delete':
        $id = $_POST['id'] ?? 0;
        if (!check_ast_owner($conn, $id)) send_response('error', 'No autorizado');
        $stmt = mysqli_prepare($conn, "DELETE FROM assistants WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Asistente eliminado.');
        else send_response('error', 'Error al eliminar.');
        break;

    // ---- Info Sources ----
    case 'info_list':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if (!$assistant_id || !check_ast_owner($conn, $assistant_id)) {
            send_response('success', '', []);
        }
        $stmt = mysqli_prepare($conn, "SELECT * FROM information_sources WHERE assistant_id = ? ORDER BY id DESC");
        mysqli_stmt_bind_param($stmt, "i", $assistant_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
        }
        send_response('success', '', $data);
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
            send_response('error', "El archivo subido excede el límite máximo permitido por el servidor (post_max_size). Tamaño intentado: " . round($content_length / 1024 / 1024, 2) . " MB.");
        }

        if (empty($assistant_id) || empty($title)) {
            send_response('error', 'Faltan datos requeridos (Asistente o Título).');
        }
        if (!check_ast_owner($conn, $assistant_id)) {
            send_response('error', 'No tienes permisos sobre este asistente.');
        }

        // --- Logic for Links ---
        if ($type === 'link') {
            $url = trim($content);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                // SSRF Protection: block private/internal IPs and non-http schemes
                $parsed = parse_url($url);
                $scheme = strtolower($parsed['scheme'] ?? '');
                if (!in_array($scheme, ['http', 'https'], true)) {
                    send_response('error', 'Solo se permiten URLs http y https.');
                }
                $host = $parsed['host'] ?? '';
                // Resolve hostname to IP for SSRF check
                $ip = gethostbyname($host);
                if (
                    $ip === '127.0.0.1' ||
                    substr($ip, 0, 4) === '10.' ||
                    substr($ip, 0, 8) === '192.168.' ||
                    preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $ip) ||
                    $ip === '::1' ||
                    strtolower($host) === 'localhost'
                ) {
                    send_response('error', 'No se puede acceder a recursos internos de red.');
                }

                // Fetch with timeout and size limit
                $ctx = stream_context_create(['http' => [
                    'timeout' => 8,
                    'max_redirects' => 3,
                    'header' => 'User-Agent: SkaleBot-Scraper/1.0'
                ]]);
                $html = @file_get_contents($url, false, $ctx);
                if ($html !== false) {
                    // Extract body content roughly
                    $content = strip_tags(preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html));
                    $content = preg_replace('/\s+/', ' ', $content); // compress spaces
                    $content = "Contenido extraído de URL ($url):\n\n" . mb_substr($content, 0, 50000); // Limit size
                } else {
                    send_response('error', 'No se pudo acceder a la URL proporcionada.');
                }
            } else {
                send_response('error', 'URL inválida.');
            }
        }

        // --- Logic for Files ---
        if ($type === 'file' && isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_base_dir = __DIR__ . '/uploads';

            // Need client_id for folder structure
            $ast_stmt = mysqli_prepare($conn, "SELECT client_id FROM assistants WHERE id = ?");
            mysqli_stmt_bind_param($ast_stmt, "i", $assistant_id);
            mysqli_stmt_execute($ast_stmt);
            $client_id_res = mysqli_stmt_get_result($ast_stmt);
            $client_id = mysqli_fetch_assoc($client_id_res)['client_id'] ?? 'unknown';

            $target_dir = $upload_base_dir . "/clients/{$client_id}/assistants/{$assistant_id}/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
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
                    send_response('error', 'Error subiendo el archivo a Google Gemini API.');
                }
            } else {
                $upload_error_msg = error_get_last()['message'] ?? 'Desconocido';
                send_response('error', "Error moviendo el archivo subido al directorio físico. Revisa los permisos de escritura del volumen. " . $upload_error_msg);
            }
        } else if ($type === 'file') {
            $err_code = $_FILES['file_upload']['error'] ?? 'Ningún archivo recibido (puede que exceda upload_max_filesize)';
            send_response('error', "Error en la subida del archivo. Código PHP: $err_code.");
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO information_sources (assistant_id, type, title, content_text, file_path, file_type, file_size, gemini_file_uri) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssssis", $assistant_id, $type, $title, $content, $file_path, $file_type, $file_size, $gemini_uri);

        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Información registrada.');
        } else {
            // Rollback file upload if DB fails
            if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                unlink(__DIR__ . '/' . $file_path);
            }
            send_response('error', 'Error guardando en base de datos.');
        }
        break;
    case 'info_update':
        $id = $_POST['id'] ?? 0;
        if (!check_item_owner($conn, 'information_sources', $id)) {
            send_response('error', 'No autorizado');
        }
        $title = $_POST['title'] ?? '';
        $content = $_POST['content_text'] ?? '';
        $stmt = mysqli_prepare($conn, "UPDATE information_sources SET title=?, content_text=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $id);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Actualizado.');
        else send_response('error', 'Error al actualizar.');
        break;
    case 'info_delete':
        $id = $_POST['id'] ?? 0;
        if (!check_item_owner($conn, 'information_sources', $id)) {
            send_response('error', 'No autorizado');
        }

        // Before deleting, try to remove the physical file if it exists
        $info_stmt = mysqli_prepare($conn, "SELECT file_path FROM information_sources WHERE id = ?");
        mysqli_stmt_bind_param($info_stmt, "i", $id);
        mysqli_stmt_execute($info_stmt);
        $info_res = mysqli_stmt_get_result($info_stmt);
        if ($row = mysqli_fetch_assoc($info_res)) {
            if (!empty($row['file_path'])) {
                $full_path = __DIR__ . '/' . $row['file_path'];
                if (file_exists($full_path)) unlink($full_path);
            }
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM information_sources WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Eliminado.');
        else send_response('error', 'Error al eliminar.');
        break;

    // ---- Chatbot Rules ----
    case 'list':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            send_response('success', '', []);
        }

        if ($assistant_id) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM chatbot WHERE assistant_id = ? ORDER BY id DESC");
            mysqli_stmt_bind_param($stmt, "i", $assistant_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM chatbot WHERE assistant_id IS NULL ORDER BY id DESC");
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
        }
        send_response('success', '', $data);
        break;

    case 'create':
        $assistant_id = empty($_POST['assistant_id']) ? null : $_POST['assistant_id'];
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            send_response('error', 'No autorizado');
        }

        $queries = $_POST['queries'] ?? '';
        $replies = $_POST['replies'] ?? '';
        $category = $_POST['category'] ?? 'general';

        if (empty($queries) || empty($replies)) {
            send_response('error', 'Consultas y respuestas son obligatorias.');
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO chatbot (assistant_id, queries, replies, category) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $assistant_id, $queries, $replies, $category);

        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Regla agregada exitosamente.');
        } else {
            send_response('error', 'Error al agregar regla.');
        }
        break;

    case 'update':
        $id = $_POST['id'] ?? 0;
        if (!check_item_owner($conn, 'chatbot', $id)) {
            send_response('error', 'No autorizado');
        }

        $queries = $_POST['queries'] ?? '';
        $replies = $_POST['replies'] ?? '';
        $category = $_POST['category'] ?? 'general';

        $stmt = mysqli_prepare($conn, "UPDATE chatbot SET queries=?, replies=?, category=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $queries, $replies, $category, $id);

        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Regla actualizada exitosamente.');
        } else {
            send_response('error', 'Error al actualizar regla.');
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        if (!check_item_owner($conn, 'chatbot', $id)) {
            send_response('error', 'No autorizado');
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM chatbot WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Regla eliminada exitosamente.');
        } else {
            send_response('error', 'Error al eliminar regla.');
        }
        break;

    // ---- Logs & Stats ----
    case 'logs':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            send_response('success', '', []);
        }

        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        if ($assistant_id) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM conversation_logs WHERE assistant_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
            mysqli_stmt_bind_param($stmt, "iii", $assistant_id, $limit, $offset);
            $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id = ?");
            mysqli_stmt_bind_param($count_stmt, "i", $assistant_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT * FROM conversation_logs WHERE assistant_id IS NULL ORDER BY id DESC LIMIT ? OFFSET ?");
            mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
            $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id IS NULL");
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $data[] = $row;
        }

        mysqli_stmt_execute($count_stmt);
        $total_count = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['c'] ?? 0;

        send_response('success', '', [
            'data' => $data,
            'total' => (int) $total_count,
            'page' => $page,
            'per_page' => $limit
        ]);
        break;

    case 'stats':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            send_response('success', '', ['total_rules' => 0, 'total_interactions' => 0, 'failed_matches' => 0, 'accuracy' => 0]);
        }

        if ($assistant_id) {
            $q_rules = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM chatbot WHERE assistant_id = ?");
            $q_logs = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id = ?");
            $q_failed = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id = ? AND matched=0");
            mysqli_stmt_bind_param($q_rules, "i", $assistant_id);
            mysqli_stmt_bind_param($q_logs, "i", $assistant_id);
            mysqli_stmt_bind_param($q_failed, "i", $assistant_id);
        } else {
            $q_rules = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM chatbot WHERE assistant_id IS NULL");
            $q_logs = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id IS NULL");
            $q_failed = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE assistant_id IS NULL AND matched=0");
        }

        mysqli_stmt_execute($q_rules); $total_rules = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rules))['c'] ?? 0;
        mysqli_stmt_execute($q_logs); $total_logs = mysqli_fetch_assoc(mysqli_stmt_get_result($q_logs))['c'] ?? 0;
        mysqli_stmt_execute($q_failed); $failed_matches = mysqli_fetch_assoc(mysqli_stmt_get_result($q_failed))['c'] ?? 0;

        send_response('success', '', [
            'total_rules' => (int)$total_rules,
            'total_interactions' => (int)$total_logs,
            'failed_matches' => (int)$failed_matches,
            'accuracy' => $total_logs > 0 ? round((($total_logs - $failed_matches) / $total_logs) * 100) : 0
        ]);
        break;

    case 'chart_data':
        $assistant_id = $_GET['assistant_id'] ?? null;
        if ($assistant_id && !check_ast_owner($conn, $assistant_id)) {
            send_response('success', '', ['labels' => [], 'values' => []]);
        }

        $where = $assistant_id ? "assistant_id = " . intval($assistant_id) : "assistant_id IS NULL";

        // Get counts for the last 7 days
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM conversation_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        if ($assistant_id) {
            $sql .= " AND assistant_id = ?";
            $stmt = mysqli_prepare($conn, $sql . " GROUP BY DATE(created_at) ORDER BY date ASC");
            mysqli_stmt_bind_param($stmt, "i", $assistant_id);
        } else {
            $sql .= " AND assistant_id IS NULL";
            $stmt = mysqli_prepare($conn, $sql . " GROUP BY DATE(created_at) ORDER BY date ASC");
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $labels = [];
        $values = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = date('d M', strtotime($row['date']));
            $values[] = (int) $row['count'];
        }

        send_response('success', '', ['labels' => $labels, 'values' => $values]);
        break;
    case 'calendar_settings_get':
        $req_client_id = $_GET['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            send_response('error', 'No autorizado para ver este cliente');
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
        send_response('success', '', $data);
        break;

    case 'calendar_settings_update':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            send_response('error', 'No autorizado');
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
            error_log('calendar_settings_update error: ' . mysqli_error($conn));
            echo json_encode(['status' => 'error', 'message' => 'Error guardando configuraciones.']);
        }
        break;

    // ---- PDF Templates ----
    case 'pdf_templates_list':
        $req_client_id = $_GET['client_id'] ?? null;
        if (!$is_superadmin) $req_client_id = $session_client_id;
        require_once 'pdf_helper.php';
        $helper = new PDFHelper($conn);
        $templates = $helper->list_templates($req_client_id);
        send_response('success', '', $templates);
        break;

    case 'pdf_templates_analyze':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) send_response('error', 'No autorizado');
        if (!isset($_FILES['template_file'])) send_response('error', 'Archivo no proporcionado');

        set_time_limit(300);
        $temp_dir = __DIR__ . "/uploads/temp/";
        if (!is_dir($temp_dir)) @mkdir($temp_dir, 0755, true);

        $file = $_FILES['template_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $temp_filename = "analyze_" . time() . "_" . uniqid() . "." . $ext;
        $temp_path = $temp_dir . $temp_filename;

        if (move_uploaded_file($file['tmp_name'], $temp_path)) {
            $placeholders = [];
            if ($ext === 'pdf') {
                require_once 'gemini_client.php';
                $gemini = new GeminiClient();
                $uri = $gemini->upload_file_to_gemini($temp_path, 'application/pdf', 'Análisis Temporal');
                if ($uri) $placeholders = $gemini->analyze_pdf_placeholders($uri, 'application/pdf');
            } else {
                $content = file_get_contents($temp_path);
                preg_match_all('/\{\{(.*?)\}\}/', $content, $matches);
                $placeholders = isset($matches[1]) ? array_unique($matches[1]) : [];
            }
            send_response('success', '', [
                'detected_fields' => array_values($placeholders),
                'temp_file' => "uploads/temp/" . $temp_filename
            ]);
        } else {
            send_response('error', 'Error al procesar archivo temporal');
        }
        break;

    case 'pdf_templates_save':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) {
            send_response('error', 'No autorizado');
        }

        $name = $_POST['name'] ?? '';
        $desc = $_POST['description'] ?? '';
        $placeholders_json = $_POST['placeholders'] ?? '[]';
        $temp_file = $_POST['temp_file_path'] ?? '';

        if (empty($name) || empty($temp_file)) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            exit;
        }

        $full_temp_path = __DIR__ . '/' . $temp_file;
        if (!file_exists($full_temp_path)) {
            echo json_encode(['status' => 'error', 'message' => 'El archivo temporal ha expirado']);
            exit;
        }

        $upload_dir = "uploads/clients/{$req_client_id}/pdf_templates/";
        if (!is_dir(__DIR__ . '/' . $upload_dir)) @mkdir(__DIR__ . '/' . $upload_dir, 0755, true);

        $filename = basename($temp_file);
        $final_path = $upload_dir . $filename;
        
        if (rename($full_temp_path, __DIR__ . '/' . $final_path)) {
            $client_id_int = intval($req_client_id);
            $stmt = mysqli_prepare($conn, "INSERT INTO pdf_templates (client_id, name, description, file_path, placeholders) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issss", $client_id_int, $name, $desc, $final_path, $placeholders_json);
            
            if (mysqli_stmt_execute($stmt)) echo json_encode(['status' => 'success']);
            else { error_log('pdf_templates_save error: ' . mysqli_error($conn)); echo json_encode(['status' => 'error', 'message' => 'Error al guardar plantilla.']); }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al mover archivo final']);
        }
        break;

    case 'pdf_templates_save_config':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) send_response('error', 'No autorizado');
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $dtype  = trim($_POST['doc_type'] ?? 'generic');
        $config = $_POST['template_config'] ?? '';
        $id     = intval($_POST['id'] ?? 0);

        if (empty($name) || empty($config)) send_response('error', 'Faltan datos (nombre o config)');
        $decoded = json_decode($config, true);
        if (!$decoded) send_response('error', 'La configuracion no es JSON valido');

        // Extract placeholders from config fields for backward compat
        $fields = $decoded['fields'] ?? [];
        $ph_list = array_map(function($f) { return $f['name'] ?? ''; }, $fields);
        $ph_json = json_encode(array_values(array_filter($ph_list)));
        $client_id_int = intval($req_client_id);

        if ($id > 0) {
            $chk = mysqli_prepare($conn, 'SELECT client_id FROM pdf_templates WHERE id = ?');
            mysqli_stmt_bind_param($chk, 'i', $id); mysqli_stmt_execute($chk);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
            if (!$row || (!$is_superadmin && $row['client_id'] != $session_client_id)) send_response('error', 'No autorizado');
            $stmt = mysqli_prepare($conn, 'UPDATE pdf_templates SET name=?, description=?, doc_type=?, template_config=?, placeholders=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssssi', $name, $desc, $dtype, $config, $ph_json, $id);
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO pdf_templates (client_id, name, description, doc_type, template_config, placeholders, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $fp = '';
            mysqli_stmt_bind_param($stmt, 'issssss', $client_id_int, $name, $desc, $dtype, $config, $ph_json, $fp);
        }
        if (mysqli_stmt_execute($stmt)) {
            send_response('success', 'Configuración guardada.', ['id' => $id ?: mysqli_insert_id($conn)]);
        } else {
            log_error('pdf_templates_save_config error', ['error' => mysqli_error($conn)]);
            send_response('error', 'Error al guardar configuración de plantilla.');
        }
        break;

    case 'pdf_templates_preview':
        $req_client_id = $_GET['client_id'] ?? $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) send_response('error', 'No autorizado');
        $config = json_decode($_POST['template_config'] ?? '', true);
        if (!$config) send_response('error', 'Config invalida');
        require_once __DIR__ . '/pdf_helper.php';
        $helper = new PDFHelper($conn);
        $sample_data = [];
        foreach ($config['fields'] ?? [] as $f) $sample_data[$f['name']] = $f['label'] ?? $f['name'];
        $result = $helper->generate_from_config($config, $sample_data, null, null, null, true);
        send_response('success', '', ['url' => $result['url'] ?? '']);
        break;

    case 'pdf_templates_logo_upload':
        $req_client_id = $_POST['client_id'] ?? $session_client_id;
        if (!$is_superadmin && $req_client_id != $session_client_id) send_response('error', 'No autorizado');
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) send_response('error', 'Sin archivo o error de subida');
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($_FILES['logo']['type'], $allowed)) send_response('error', 'Tipo de archivo no permitido');
        $dir = __DIR__ . '/uploads/clients/' . intval($req_client_id) . '/logos/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $fname = 'logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $fname)) {
            send_response('success', '', ['url' => 'uploads/clients/' . intval($req_client_id) . '/logos/' . $fname]);
        } else {
            send_response('error', 'Error al guardar');
        }
        break;

    case 'pdf_generated_list':
        $req_client_id = $_GET['client_id'] ?? null;
        $ast_id = $_GET['assistant_id'] ?? null;
        $sql = "SELECT g.*, a.name as assistant_name, COALESCE(t.name, g.template_id) as template_name FROM generated_documents g LEFT JOIN assistants a ON g.assistant_id = a.id LEFT JOIN pdf_templates t ON g.template_id = CAST(t.id AS CHAR) WHERE 1=1";
        $params = []; $types = "";
        if (!$is_superadmin) { $sql .= " AND g.client_id = ?"; $params[] = $session_client_id; $types .= "i"; }
        else if ($req_client_id) { $sql .= " AND g.client_id = ?"; $params[] = intval($req_client_id); $types .= "i"; }
        if ($ast_id) { $sql .= " AND g.assistant_id = ?"; $params[] = intval($ast_id); $types .= "i"; }
        $sql .= " ORDER BY g.created_at DESC LIMIT 100";
        $stmt = mysqli_prepare($conn, $sql);
        if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $docs = [];
        while($row = mysqli_fetch_assoc($res)) $docs[] = $row;
        send_response('success', '', $docs);
        break;

    case 'pdf_generated_delete':
        $id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "SELECT file_name, client_id FROM generated_documents WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($row) {
            if (!$is_superadmin && $row['client_id'] != $session_client_id) send_response('error', 'No autorizado');
            $fpath = __DIR__ . '/uploads/' . $row['file_name'];
            if (file_exists($fpath)) @unlink($fpath);
            $del = mysqli_prepare($conn, "DELETE FROM generated_documents WHERE id = ?");
            mysqli_stmt_bind_param($del, "i", $id);
            mysqli_stmt_execute($del);
            send_response('success', 'Documento eliminado.');
        } else {
            send_response('error', 'No encontrado');
        }
        break;

    case 'pdf_templates_delete':
        $id = intval($_POST['id'] ?? 0);
        $chk = mysqli_prepare($conn, "SELECT client_id, file_path FROM pdf_templates WHERE id = ?");
        mysqli_stmt_bind_param($chk, "i", $id);
        mysqli_stmt_execute($chk);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        if ($row) {
            if (!$is_superadmin && $row['client_id'] != $session_client_id) send_response('error', 'No autorizado');
            if ($row['file_path'] && file_exists(__DIR__ . '/' . $row['file_path'])) unlink(__DIR__ . '/' . $row['file_path']);
            $del = mysqli_prepare($conn, "DELETE FROM pdf_templates WHERE id = ?");
            mysqli_stmt_bind_param($del, "i", $id);
            mysqli_stmt_execute($del);
            send_response('success', 'Plantilla eliminada.');
        } else {
            send_response('error', 'Plantilla no encontrada');
        }
        break;

    case 'pdf_templates_rename':
        $id = intval($_POST['id'] ?? 0);
        $new_name = trim($_POST['name'] ?? '');
        $new_desc = trim($_POST['description'] ?? '');
        if (empty($new_name)) send_response('error', 'El nombre es requerido');
        $chk = mysqli_prepare($conn, "SELECT client_id FROM pdf_templates WHERE id = ?");
        mysqli_stmt_bind_param($chk, "i", $id); mysqli_stmt_execute($chk);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
        if ($row) {
            if (!$is_superadmin && $row['client_id'] != $session_client_id) send_response('error', 'No autorizado');
            $stmt = mysqli_prepare($conn, "UPDATE pdf_templates SET name = ?, description = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $new_name, $new_desc, $id);
            if (mysqli_stmt_execute($stmt)) send_response('success', 'Cambios guardados.');
            else send_response('error', 'Error al renombrar plantilla.');
        } else {
            send_response('error', 'Plantilla no encontrada');
        }
        break;
    // ---- WhatsApp Integration (Proxy to Node.js) ----
    case 'whatsapp_status':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $res = @file_get_contents(WHATSAPP_API_URL . "/status/" . $ast_id);
        if ($res === false) send_response('offline', 'Servicio de WhatsApp no disponible');
        else { header('Content-Type: application/json'); echo $res; exit; }
        break;

    case 'whatsapp_qr':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $res = @file_get_contents(WHATSAPP_API_URL . "/qr/" . $ast_id);
        if ($res === false) send_response('offline', 'Servicio de WhatsApp no disponible');
        else { header('Content-Type: application/json'); echo $res; exit; }
        break;

    case 'whatsapp_connect':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $ch = curl_init(WHATSAPP_API_URL . "/connect/" . $ast_id);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        if (curl_errno($ch)) send_response('offline', 'Servicio de WhatsApp no disponible');
        else { header('Content-Type: application/json'); echo $res; exit; }
        break;

    case 'whatsapp_disconnect':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!$ast_id || !check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $ch = curl_init(WHATSAPP_API_URL . "/disconnect/" . $ast_id);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        if (curl_errno($ch)) send_response('offline', 'Servicio de WhatsApp no disponible');
        else { header('Content-Type: application/json'); echo $res; exit; }
        break;

    case 'appointments_cancel':
        $appt_id = $_POST['id'] ?? 0;
        $stmt = mysqli_prepare($conn, "SELECT a.*, ci.access_token, ci.refresh_token, ci.expires_at FROM appointments a JOIN clients c ON a.client_id = c.id LEFT JOIN client_integrations ci ON ci.client_id = a.client_id AND ci.provider = 'google_drive' WHERE a.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $appt_id);
        mysqli_stmt_execute($stmt);
        $appt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$appt) send_response('error', 'Reserva no encontrada.');
        if (!$is_superadmin && $appt['client_id'] != $session_client_id) send_response('error', 'No autorizado.');

        $google_cancelled = false;
        if ($appt['google_event_id'] && $appt['access_token']) {
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
                if (!empty($ref_res['access_token'])) $access_token = $ref_res['access_token'];
            }
            $cal_id = urlencode($appt['google_calendar_id'] ?: 'primary');
            $event_id = urlencode($appt['google_event_id']);
            $ch = curl_init("https://www.googleapis.com/calendar/v3/calendars/$cal_id/events/$event_id");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
            curl_exec($ch);
            $google_cancelled = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 204);
        }

        $upd = mysqli_prepare($conn, "UPDATE appointments SET status='cancelled' WHERE id=?");
        mysqli_stmt_bind_param($upd, "i", $appt_id);
        mysqli_stmt_execute($upd);

        send_response('success', $google_cancelled ? 'Reserva cancelada en el sistema y en Google Calendar.' : 'Reserva cancelada en el sistema (no se pudo eliminar de Google Calendar).');
        break;

    case 'pdf_templates_download':
        $id = $_GET['id'] ?? '';
        if (empty($id)) send_response('error', 'ID de plantilla no proporcionado');

        $file_path = ''; $display_name = '';

        if (is_numeric($id)) {
            $stmt = mysqli_prepare($conn, "SELECT name, file_path, client_id FROM pdf_templates WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($row) {
                if (!$is_superadmin && $row['client_id'] != $session_client_id) send_response('error', 'No autorizado');
                $file_path = __DIR__ . '/' . $row['file_path'];
                $display_name = $row['name'];
                $ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
                if (strtolower(pathinfo($display_name, PATHINFO_EXTENSION)) !== strtolower($ext)) $display_name .= '.' . $ext;
            } else send_response('error', 'Plantilla no encontrada');
        } else {
            $safe_id = basename($id);
            $file_path = __DIR__ . '/pdf_templates/' . $safe_id;
            $display_name = $safe_id;
            if (!file_exists($file_path) || !is_file($file_path)) send_response('error', 'Archivo de plantilla no encontrado');
        }

        if (file_exists($file_path)) {
            $mime = (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) === 'pdf') ? 'application/pdf' : 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . $display_name . '"');
            header('Content-Length: ' . filesize($file_path));
            if (ob_get_level()) ob_end_clean();
            readfile($file_path);
            exit;
        } else {
            send_response('error', 'El archivo no existe físicamente.');
        }
        break;

    case 'logs_export':
        $format = $_GET['format'] ?? 'csv';
        $ast_id = $_GET['assistant_id'] ?? null;
        
        $sql = "SELECT * FROM conversation_logs";
        if ($ast_id) {
            $sql .= " WHERE assistant_id = ?";
            $stmt = mysqli_prepare($conn, $sql . " ORDER BY created_at DESC");
            mysqli_stmt_bind_param($stmt, "i", $ast_id);
        } else {
            $stmt = mysqli_prepare($conn, $sql . " ORDER BY created_at DESC");
        }
        
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $data = [];
        while($row = mysqli_fetch_assoc($res)) $data[] = $row;

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="logs_export_' . date('Ymd_His') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="logs_export_' . date('Ymd_His') . '.csv"');
            $output = fopen('php://output', 'w');
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
        break;

    case 'agents_list':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $stmt = mysqli_prepare($conn, "SELECT * FROM authorized_agents WHERE assistant_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $ast_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while($r = mysqli_fetch_assoc($res)) $list[] = $r;
        send_response('success', '', $list);
        break;

    case 'agents_create':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $name = $_POST['agent_name'] ?? '';
        $phone = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');
        if (!$phone) send_response('error', 'Teléfono inválido');
        
        $stmt = mysqli_prepare($conn, "INSERT INTO authorized_agents (assistant_id, agent_name, phone_number) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $ast_id, $name, $phone);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Agente autorizado.');
        else send_response('error', 'Error al autorizar agente.');
        break;

    case 'agents_delete':
        $id = $_POST['id'] ?? null;
        if (!$is_superadmin) {
            $stmt = mysqli_prepare($conn, "SELECT a.client_id FROM authorized_agents g JOIN assistants a ON g.assistant_id = a.id WHERE g.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$r || $r['client_id'] != $session_client_id) send_response('error', 'No autorizado');
        }
        $del = mysqli_prepare($conn, "DELETE FROM authorized_agents WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $id);
        if (mysqli_stmt_execute($del)) send_response('success', 'Agente eliminado.');
        else send_response('error', 'Error al eliminar.');
        break;

    case 'agents_toggle':
        $id = $_POST['id'] ?? null;
        $status = $_POST['is_active'] ?? 1;
        if (!$is_superadmin) {
            $stmt = mysqli_prepare($conn, "SELECT a.client_id FROM authorized_agents g JOIN assistants a ON g.assistant_id = a.id WHERE g.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$r || $r['client_id'] != $session_client_id) send_response('error', 'No autorizado');
        }
        $upd = mysqli_prepare($conn, "UPDATE authorized_agents SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "ii", $status, $id);
        if (mysqli_stmt_execute($upd)) send_response('success', 'Estado del agente actualizado.');
        else send_response('error', 'Error al actualizar estado.');
        break;

    case 'flows_list':
        $ast_id = $_GET['assistant_id'] ?? null;
        if (!check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $stmt = mysqli_prepare($conn, "SELECT * FROM conversation_flows WHERE assistant_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $ast_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while($r = mysqli_fetch_assoc($res)) $list[] = $r;
        send_response('success', '', $list);
        break;

    case 'flows_create':
        $ast_id = $_POST['assistant_id'] ?? null;
        if (!check_ast_owner($conn, $ast_id)) send_response('error', 'No autorizado');
        $name = $_POST['name'] ?? 'Nuevo Flujo';
        $trigger = $_POST['trigger_keyword'] ?? null;
        $stmt = mysqli_prepare($conn, "INSERT INTO conversation_flows (assistant_id, name, trigger_keyword) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $ast_id, $name, $trigger);
        if (mysqli_stmt_execute($stmt)) send_response('success', 'Flujo creado.', ['id' => mysqli_insert_id($conn)]);
        else send_response('error', 'Error al crear flujo.');
        break;

    case 'flows_delete':
        $id = $_POST['id'] ?? null;
        if (!$is_superadmin) {
            $stmt = mysqli_prepare($conn, "SELECT a.client_id FROM conversation_flows f JOIN assistants a ON f.assistant_id = a.id WHERE f.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$r || $r['client_id'] != $session_client_id) send_response('error', 'No autorizado');
        }
        $del1 = mysqli_prepare($conn, "DELETE FROM conversation_flows WHERE id = ?");
        mysqli_stmt_bind_param($del1, "i", $id);
        mysqli_stmt_execute($del1);
        $del2 = mysqli_prepare($conn, "DELETE FROM flow_steps WHERE flow_id = ?");
        mysqli_stmt_bind_param($del2, "i", $id);
        mysqli_stmt_execute($del2);
        send_response('success', 'Flujo eliminado.');
        break;

    case 'flows_steps_list':
        $flow_id = $_GET['flow_id'] ?? null;
        $stmt = mysqli_prepare($conn, "SELECT * FROM flow_steps WHERE flow_id = ? ORDER BY step_order ASC");
        mysqli_stmt_bind_param($stmt, "i", $flow_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $list = [];
        while($r = mysqli_fetch_assoc($res)) $list[] = $r;
        send_response('success', '', $list);
        break;

    case 'flows_steps_update':
        $flow_id = $_POST['flow_id'] ?? null;
        $steps = json_decode($_POST['steps'] ?? '[]', true);
        if (!$flow_id || !is_array($steps)) send_response('error', 'Datos inválidos');
        
        if (!$is_superadmin) {
            $stmt = mysqli_prepare($conn, "SELECT a.client_id FROM conversation_flows f JOIN assistants a ON f.assistant_id = a.id WHERE f.id = ?");
            mysqli_stmt_bind_param($stmt, "i", $flow_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$r || $r['client_id'] != $session_client_id) send_response('error', 'No autorizado');
        }

        $del = mysqli_prepare($conn, "DELETE FROM flow_steps WHERE flow_id = ?");
        mysqli_stmt_bind_param($del, "i", $flow_id);
        mysqli_stmt_execute($del);

        foreach($steps as $idx => $step) {
            $order = $idx + 1;
            $type = $step['step_type'] ?? 'text';
            $content = $step['content'] ?? '';
            $config = json_encode($step['interactive_config'] ?? null);
            $next = $step['next_step_id'] ?? null;
            $stmt = mysqli_prepare($conn, "INSERT INTO flow_steps (flow_id, step_order, step_type, content, interactive_config, next_step_id) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisssi", $flow_id, $order, $type, $content, $config, $next);
            mysqli_stmt_execute($stmt);
        }
        send_response('success', 'Pasos actualizados.');
        break;

    default:
        send_response('error', 'Acción no válida');
        break;
}
?>