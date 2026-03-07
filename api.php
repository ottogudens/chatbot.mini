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
        $title = $_POST['title'] ?? '';
        $content = $_POST['content_text'] ?? '';
        $stmt = mysqli_prepare($conn, "INSERT INTO information_sources (assistant_id, title, content_text) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $assistant_id, $title, $content);
        echo json_encode(['status' => mysqli_stmt_execute($stmt) ? 'success' : 'error']);
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