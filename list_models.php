<?php
$api_key = getenv('GEMINI_API_KEY');
if (!$api_key) {
    die("Error: GEMINI_API_KEY no configurada.\n");
}

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>