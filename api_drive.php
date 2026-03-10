<?php
require 'db.php';
require 'auth.php';
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!check_auth(false)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
    exit;
}

$action = $_GET['action'] ?? '';
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id && ($_SESSION['role'] ?? '') !== 'superadmin') {
    echo json_encode(['status' => 'error', 'message' => 'No tienes un cliente asociado.']);
    exit;
}
if (!$client_id && ($_SESSION['role'] ?? '') === 'superadmin') {
    $client_id = $_GET['client_id'] ?? $_POST['client_id'] ?? null;
    // Special case for Google Callback: client_id is passed in 'state'
    if (!$client_id && $action === 'callback') {
        $client_id = $_GET['state'] ?? null;
    }

    if (!$client_id) {
        echo json_encode(['status' => 'error', 'message' => 'Superadmin debe especificar client_id en la petición (ej. seleccionando un asistente).']);
        exit;
    }
}

$google_client_id = getenv('GOOGLE_CLIENT_ID') ?: 'TU_GOOGLE_CLIENT_ID';
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_GOOGLE_CLIENT_SECRET';
$protocol = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$redirect_uri = getenv('GOOGLE_REDIRECT_URI') ?: ($protocol . "://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], '?') . "?action=callback");

function refresh_google_token($conn, $client_id_db, $refresh_token, $google_client_id, $google_client_secret)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token'
    ]));
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $access_token = $data['access_token'];
        $expires_in = $data['expires_in'];
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        $stmt = mysqli_prepare($conn, "UPDATE client_integrations SET access_token=?, expires_at=? WHERE client_id=? AND provider='google_drive'");
        mysqli_stmt_bind_param($stmt, "ssi", $access_token, $expires_at, $client_id_db);
        mysqli_stmt_execute($stmt);
        return $access_token;
    }
    return false;
}

function get_valid_token($conn, $client_id_db, $google_client_id, $google_client_secret)
{
    $stmt = mysqli_prepare($conn, "SELECT access_token, refresh_token, expires_at FROM client_integrations WHERE client_id=? AND provider='google_drive'");
    mysqli_stmt_bind_param($stmt, "i", $client_id_db);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        if (strtotime($row['expires_at']) < time() + 300) { // expires in less than 5 minutes
            if ($row['refresh_token']) {
                return refresh_google_token($conn, $client_id_db, $row['refresh_token'], $google_client_id, $google_client_secret);
            }
            return false;
        }
        return $row['access_token'];
    }
    return false;
}

switch ($action) {
    case 'status':
        $token = get_valid_token($conn, $client_id, $google_client_id, $google_client_secret);
        echo json_encode(['status' => 'success', 'connected' => $token !== false]);
        break;

    case 'auth_url':
        $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $google_client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $client_id // passing client id in state to know who is connecting
        ]);
        echo json_encode(['status' => 'success', 'url' => $url]);
        break;

    case 'callback':
        $code = $_GET['code'] ?? null;
        $state_client_id = $_GET['state'] ?? null;

        if (!$code || !$state_client_id) {
            die("Error: Faltan parámetros en el callback.");
        }

        // Ensure superadmin logic or matching client logic
        if (($_SESSION['role'] ?? '') !== 'superadmin' && $client_id != $state_client_id) {
            die("Error: Violación de seguridad en estado OAuth.");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $google_client_id,
            'client_secret' => $google_client_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ]));
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $access_token = $data['access_token'];
            $refresh_token = $data['refresh_token'] ?? null;
            $expires_at = date('Y-m-d H:i:s', time() + $data['expires_in']);

            // Upsert
            $check = mysqli_query($conn, "SELECT id FROM client_integrations WHERE client_id=" . intval($state_client_id) . " AND provider='google_drive'");
            if (mysqli_num_rows($check) > 0) {
                if ($refresh_token) {
                    $stmt = mysqli_prepare($conn, "UPDATE client_integrations SET access_token=?, refresh_token=?, expires_at=? WHERE client_id=? AND provider='google_drive'");
                    mysqli_stmt_bind_param($stmt, "sssi", $access_token, $refresh_token, $expires_at, $state_client_id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE client_integrations SET access_token=?, expires_at=? WHERE client_id=? AND provider='google_drive'");
                    mysqli_stmt_bind_param($stmt, "ssi", $access_token, $expires_at, $state_client_id);
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO client_integrations (client_id, provider, access_token, refresh_token, expires_at) VALUES (?, 'google_drive', ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "isss", $state_client_id, $access_token, $refresh_token, $expires_at);
            }
            mysqli_stmt_execute($stmt);

            // Redirect back to admin
            header("Location: admin.php?drive_success=1");
            exit;
        } else {
            die("Error obteniendo tokens de Google: " . json_encode($data));
        }
        break;

    case 'list_files':
        $token = get_valid_token($conn, $client_id, $google_client_id, $google_client_secret);
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'No hay conexión activa con Google Drive.']);
            exit;
        }

        $folder_id = $_GET['folder_id'] ?? 'root';
        $q = "('{$folder_id}' in parents) and (mimeType='application/vnd.google-apps.folder' or mimeType='application/vnd.google-apps.spreadsheet' or mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='text/csv' or mimeType='application/pdf' or mimeType='application/msword' or mimeType='application/vnd.openxmlformats-officedocument.wordprocessingml.document' or mimeType='application/vnd.google-apps.document')";

        $url = "https://www.googleapis.com/drive/v3/files?" . http_build_query([
            'q' => $q,
            'fields' => 'files(id, name, mimeType, modifiedTime)',
            'orderBy' => 'folder,name',
            'pageSize' => 50
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        echo $response; // Return raw google response
        break;

    case 'list_calendars':
        $token = get_valid_token($conn, $client_id, $google_client_id, $google_client_secret);
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Sin conexión activa a Google.']);
            exit;
        }

        $url = "https://www.googleapis.com/calendar/v3/users/me/calendarList";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        break;

    case 'create_calendar':
        $token = get_valid_token($conn, $client_id, $google_client_id, $google_client_secret);
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Sin conexión activa a Google.']);
            exit;
        }

        $summary = $_POST['summary'] ?? 'Asistente Chatbot';
        $url = "https://www.googleapis.com/calendar/v3/calendars";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['summary' => $summary]));
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        break;

    case 'sync_file':
        $token = get_valid_token($conn, $client_id, $google_client_id, $google_client_secret);
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Sin conexión activa a Google Drive.']);
            exit;
        }

        $file_id = $_POST['file_id'] ?? '';
        $file_name = $_POST['file_name'] ?? '';
        $mime_type = $_POST['mime_type'] ?? '';
        $assistant_id = $_POST['assistant_id'] ?? '';

        if (!$file_id || !$assistant_id) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos. Revisa el ID y el asistente seleccionado.']);
            exit;
        }

        $is_superadmin = ($_SESSION['role'] ?? 'client') === 'superadmin';
        if (!$is_superadmin) {
            $chk = mysqli_query($conn, "SELECT id FROM assistants WHERE id=" . intval($assistant_id) . " AND client_id=" . intval($client_id));
            if (mysqli_num_rows($chk) == 0) {
                echo json_encode(['status' => 'error', 'message' => 'No tienes asignado este asistente.']);
                exit;
            }
        }

        // Configurar exportación para Google Docs Nativos
        $export_mime = 'application/pdf';
        $ext = '.pdf';
        if ($mime_type === 'application/vnd.google-apps.spreadsheet') {
            $export_mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $ext = '.xlsx';
        } else if ($mime_type === 'application/vnd.google-apps.document') {
            $export_mime = 'application/pdf';
        } else if (strpos($file_name, '.') !== false) {
            $ext = ''; // Mantiene extensión original en descargas directas
        }

        // Endpoint de descarga dinámico según el tipo de archivo de Drive
        if (strpos($mime_type, 'vnd.google-apps') !== false) {
            $downloadUrl = "https://www.googleapis.com/drive/v3/files/{$file_id}/export?mimeType=" . urlencode($export_mime);
        } else {
            $downloadUrl = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
        }

        // Descargar el archivo
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $file_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200 || !$file_content) {
            echo json_encode(['status' => 'error', 'message' => "Error de descarga desde Google Drive (HTTP $http_code). ¿Es un archivo válido y no vacío?"]);
            exit;
        }

        // Guardar locálmente el cache
        $safe_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $file_name) . $ext;
        $rel_path = "uploads/clients/" . ($is_superadmin ? "global" : $client_id) . "/assistants/{$assistant_id}/";
        $target_dir = __DIR__ . "/" . $rel_path;
        if (!file_exists($target_dir))
            mkdir($target_dir, 0777, true);
        $target_file = $target_dir . $safe_filename;
        file_put_contents($target_file, $file_content);

        // Subir a Gemini File API
        require_once 'gemini_client.php';
        $gemini = new GeminiClient();
        $calc_mime = mime_content_type($target_file) ?: 'application/octet-stream';
        $uri = $gemini->upload_file_to_gemini($target_file, $calc_mime, "DriveSinc: " . $file_name);

        if ($uri) {
            // Registrar en Base de Datos como nueva Fuente
            $final_path = $rel_path . $safe_filename;
            $stmt = mysqli_prepare($conn, "INSERT INTO information_sources (assistant_id, type, title, content_text, file_path, file_type, file_size, gemini_file_uri) VALUES (?, 'file', ?, ?, ?, ?, ?, ?)");
            $content_msg = "Sincronizado vía Google Drive: $file_name (Última vez: " . date('Y-m-d H:i:s') . ")";
            $fsize = filesize($target_file);

            mysqli_stmt_bind_param($stmt, "issssis", $assistant_id, $file_name, $content_msg, $final_path, $calc_mime, $fsize, $uri);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success', 'uri' => $uri]);
            } else {
                error_log("DB Error in sync_file: " . mysqli_error($conn));
                echo json_encode(['status' => 'error', 'message' => 'Error guardando en la BD: ' . mysqli_error($conn)]);
            }
        } else {
            // Rollback si falla subir a Gemini
            if (file_exists($target_file))
                unlink($target_file);
            error_log("Gemini Upload failed for file: $file_name");
            echo json_encode(['status' => 'error', 'message' => 'El archivo se descargó de Drive, pero la subida a Google Gemini falló. Revisa los logs del servidor.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción Drive no válida']);
        break;
}
