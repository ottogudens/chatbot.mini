<?php
require 'gemini_client.php';
$client = new GeminiClient();
$res = $client->get_response("Hola", [], "", "", [], null, ['model' => 'gemini-2.5-flash']);
var_dump($res);
