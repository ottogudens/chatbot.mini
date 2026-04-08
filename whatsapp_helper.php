<?php

/**
 * WhatsApp Helper for SkaleBot
 * Centralizes communication with the Node.js WhatsApp bridge.
 */

if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', 'http://localhost:3001');
}

/**
 * Sends a WhatsApp message via the internal Node.js bridge.
 * 
 * @param int|string $assistant_id The ID of the assistant session to use.
 * @param string $to The recipient's phone number (with country code).
 * @param string $text The message content.
 * @param string $mediaUrl Optional. URL of the media file to send.
 * @param string $mediaType Optional. 'image', 'video', or 'document'.
 * @return array Result with status and message.
 */
function send_whatsapp_message($assistant_id, $to, $text, $mediaUrl = null, $mediaType = null)
{
    $token = getenv('INTERNAL_TOKEN') ?: '';
    
    $payload = [
        'to' => $to,
        'text' => $text,
        'internal_token' => $token
    ];

    if ($mediaUrl) {
        $payload['mediaUrl'] = $mediaUrl;
        $payload['mediaType'] = $mediaType ?: 'document';
    }

    $ch = curl_init(WHATSAPP_API_URL . "/send/" . intval($assistant_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Internal-Token: ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Local connection

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        return ['status' => 'error', 'message' => "CURL Error: $error"];
    }

    $result = json_decode($response, true);
    if ($http_code !== 200) {
        return ['status' => 'error', 'message' => $result['message'] ?? "HTTP Error $http_code"];
    }

    return $result ?: ['status' => 'error', 'message' => 'Respuesta de API inválida'];
}
