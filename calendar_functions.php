<?php
require_once 'db.php';

function get_calendar_token($conn, $client_id)
{
    // Try google_drive first (legacy), or just google
    $stmt = mysqli_prepare($conn, "SELECT access_token, refresh_token, expires_at FROM client_integrations WHERE client_id = ? AND provider IN ('google_drive', 'google_calendar', 'google') ORDER BY (provider = 'google_calendar') DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $client_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $token_data = mysqli_fetch_assoc($res);

    if (!$token_data)
        return false;

    // Check expiration
    if (strtotime($token_data['expires_at']) <= time() + 300) {
        // Needs refresh
        $google_client_id = getenv('GOOGLE_CLIENT_ID');
        $google_client_secret = getenv('GOOGLE_CLIENT_SECRET');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $google_client_id,
            'client_secret' => $google_client_secret,
            'refresh_token' => $token_data['refresh_token'],
            'grant_type' => 'refresh_token'
        ]));

        $response = curl_exec($ch);
        // curl_close unnecessary in PHP 8.4+ (deprecated in 8.5)

        $json = json_decode($response, true);
        if (isset($json['access_token'])) {
            $new_access = $json['access_token'];
            $expires_in = $json['expires_in'] ?? 3600;
            $new_expires_at = date('Y-m-d H:i:s', time() + $expires_in);

            $upd = mysqli_prepare($conn, "UPDATE client_integrations SET access_token = ?, expires_at = ? WHERE client_id = ? AND provider = 'google_drive'");
            mysqli_stmt_bind_param($upd, "ssi", $new_access, $new_expires_at, $client_id);
            mysqli_stmt_execute($upd);

            return $new_access;
        }
        return false;
    }

    return $token_data['access_token'];
}

function get_client_id_from_assistant($conn, $assistant_id)
{
    $stmt = mysqli_prepare($conn, "SELECT client_id FROM assistants WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $assistant_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    return $row ? $row['client_id'] : null;
}

function check_calendar_availability($conn, $assistant_id, $args)
{
    $client_id = get_client_id_from_assistant($conn, $assistant_id);
    if (!$client_id)
        return ["error" => "Assistant not found or has no client."];

    // Load Settings
    $stmt = mysqli_prepare($conn, "SELECT * FROM calendar_settings WHERE client_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $client_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($res);

    if (!$settings)
        return ["error" => "El calendario no está configurado para este cliente."];

    $token = get_calendar_token($conn, $client_id);
    if (!$token)
        return ["error" => "El cliente no ha conectado su cuenta de Calendar/Drive."];

    $target_date = $args['target_date'] ?? date('Y-m-d');
    if (empty($target_date))
        $target_date = date('Y-m-d');

    // Convert to a DateTime just to get the next 7 days or specifically target_date
    $start_dt = new DateTime($target_date, new DateTimeZone($settings['timezone']));
    $end_dt = clone $start_dt;
    $end_dt->modify('+7 days');

    $timeMin = $start_dt->format(DateTime::RFC3339);
    $timeMax = $end_dt->format(DateTime::RFC3339);

    $calendar_id = urlencode($settings['calendar_id'] ?: 'primary');
    $url = "https://www.googleapis.com/calendar/v3/calendars/$calendar_id/events?timeMin=" . urlencode($timeMin) . "&timeMax=" . urlencode($timeMax) . "&singleEvents=true&orderBy=startTime";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close unnecessary in PHP 8.4+

    if ($http_code !== 200)
        return ["error" => "Error consultando Google Calendar: $response"];

    $events_data = json_decode($response, true);
    $busy_slots = [];
    if (isset($events_data['items'])) {
        foreach ($events_data['items'] as $item) {
            $start = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
            $end = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;
            if ($start && $end) {
                $busy_slots[] = ["start" => $start, "end" => $end];
            }
        }
    }

    // Return the settings along with the busy slots so Gemini can figure out the free slots
    return [
        "status" => "success",
        "message" => "He aquí los horarios definidos por la empresa, y los bloques OCUPADOS en Google Calendar. Deduce los bloques LIBRES cruzando estos dos datos.",
        "company_schedule" => [
            "available_days" => $settings['available_days'], // 1=Lunes, 0=Domingo
            "start_time" => $settings['start_time'],
            "end_time" => $settings['end_time'],
            "slot_duration_minutes" => $settings['slot_duration_minutes'],
            "timezone" => $settings['timezone']
        ],
        "busy_events" => $busy_slots
    ];
}

function book_calendar_appointment($conn, $assistant_id, $args)
{
    $client_id = get_client_id_from_assistant($conn, $assistant_id);
    if (!$client_id)
        return ["error" => "Assistant not found or has no client."];

    // Load Settings
    $stmt = mysqli_prepare($conn, "SELECT * FROM calendar_settings WHERE client_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $client_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($res);

    if (!$settings)
        return ["error" => "El calendario no está configurado."];

    $token = get_calendar_token($conn, $client_id);
    if (!$token)
        return ["error" => "El cliente no ha conectado su cuenta de Calendar/Drive."];

    $date = $args['date']; // YYYY-MM-DD
    $time = $args['time']; // HH:MM
    $name = $args['user_name'];
    $email = $args['user_email'];
    $phone = $args['user_phone'];

    $duration = $settings['slot_duration_minutes'] ?? 30;

    $start_dt = new DateTime("$date $time", new DateTimeZone($settings['timezone']));
    $end_dt = clone $start_dt;
    $end_dt->modify("+$duration minutes");

    $event = [
        "summary" => "Cita con $name",
        "description" => "Email: $email\nTeléfono: $phone\n\nReserva generada automáticamente por SkaleBot.",
        "start" => [
            "dateTime" => $start_dt->format(DateTime::RFC3339),
            "timeZone" => $settings['timezone']
        ],
        "end" => [
            "dateTime" => $end_dt->format(DateTime::RFC3339),
            "timeZone" => $settings['timezone']
        ],
        "attendees" => [
            ["email" => $email]
        ]
    ];

    $calendar_id = urlencode($settings['calendar_id'] ?: 'primary');
    $url = "https://www.googleapis.com/calendar/v3/calendars/$calendar_id/events?sendUpdates=all";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close unnecessary in PHP 8.4+

    if ($http_code === 200 || $http_code === 201) {
        $event_data = json_decode($response, true);
        $google_event_id = $event_data['id'] ?? null;
        $google_calendar_id = $settings['calendar_id'] ?: 'primary';

        // Save appointment to local DB
        $stmt = mysqli_prepare($conn, "INSERT INTO appointments
            (assistant_id, client_id, user_name, user_email, user_phone, appointment_date, appointment_time, google_event_id, google_calendar_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");
        mysqli_stmt_bind_param($stmt, "iisssssss",
            $assistant_id, $client_id,
            $name, $email, $phone,
            $date, $time,
            $google_event_id, $google_calendar_id
        );
        mysqli_stmt_execute($stmt);

        return ["status" => "success", "message" => "La reserva se ha creado con éxito."];
    } else {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error']['message'] ?? $response;
        return ["status" => "error", "message" => "Hubo un error al crear la reserva en Google Calendar: $error_msg"];
    }
}
?>