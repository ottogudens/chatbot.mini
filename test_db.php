<?php
require 'c:\Users\Gudex\Documents\Repositorios\chatbot.mini\db.php';
header('Content-Type: text/plain');

echo "--- DB Connection ---\n";
if ($conn) {
    echo "Connected successfully to " . getenv('MYSQLDATABASE') . "\n";
} else {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
    exit;
}

$tables = ['clients', 'users', 'assistants', 'information_sources', 'chatbot', 'leads', 'marketing_campaigns', 'appointments'];
echo "\n--- Tables ---\n";
foreach ($tables as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($res) > 0) {
        $count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table");
        $count = mysqli_fetch_assoc($count_res)['cnt'];
        echo "Table '$table' exists. Rows: $count\n";
    } else {
        echo "Table '$table' DOES NOT EXIST.\n";
    }
}

echo "\n--- Users ---\n";
$res = mysqli_query($conn, "SELECT id, username, role FROM users");
while ($row = mysqli_fetch_assoc($res)) {
    echo "User: {$row['username']} (Role: {$row['role']})\n";
}

echo "\n--- Assistants ---\n";
$res = mysqli_query($conn, "SELECT id, name FROM assistants");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Assistant: {$row['name']} (ID: {$row['id']})\n";
}
?>
