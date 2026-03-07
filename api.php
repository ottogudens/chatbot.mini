<?php
require 'db.php';
require 'auth.php';
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Actions that REQUIRE authentication
$secure_actions = ['create', 'update', 'delete'];
if (in_array($action, $secure_actions)) {
    if (!check_auth(false)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado. Por favor inicie sesión.']);
        exit;
    }
}

switch ($action) {
    case 'list':
        $query = "SELECT * FROM chatbot ORDER BY id DESC";
        $result = mysqli_query($conn, $query);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'create':
        $queries = $_POST['queries'] ?? '';
        $replies = $_POST['replies'] ?? '';
        $category = $_POST['category'] ?? 'general';

        if (empty($queries) || empty($replies)) {
            echo json_encode(['status' => 'error', 'message' => 'Consultas y respuestas son obligatorias.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO chatbot (queries, replies, category) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $queries, $replies, $category);

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

    case 'logs':
        $query = "SELECT * FROM conversation_logs ORDER BY id DESC LIMIT 100";
        $result = mysqli_query($conn, $query);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'stats':
        $total_rules = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM chatbot"))['c'];
        $total_logs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM conversation_logs"))['c'];
        $failed_matches = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM conversation_logs WHERE matched=0"))['c'];

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
        // Get counts for the last 7 days
        $query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                  FROM conversation_logs 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  GROUP BY DATE(created_at) 
                  ORDER BY date ASC";
        $result = mysqli_query($conn, $query);
        $labels = [];
        $values = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = date('d M', strtotime($row['date']));
            $values[] = (int) $row['count'];
        }

        echo json_encode(['status' => 'success', 'labels' => $labels, 'values' => $values]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
?>