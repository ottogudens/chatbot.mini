<?php
require 'db.php';

echo "--- DATABASE CONNECTION CHECK ---\n";
echo "Host: " . (getenv('MYSQLHOST') ?: 'localhost') . "\n";
echo "DB: " . (getenv('MYSQLDATABASE') ?: 'chatbot') . "\n";

$result = mysqli_query($conn, "SHOW TABLES");
if (!$result) {
    die("Error listing tables: " . mysqli_error($conn) . "\n");
}

$tables = [];
while ($row = mysqli_fetch_array($result)) {
    $tables[] = $row[0];
}

echo "Detected Tables:\n";
foreach ($tables as $t) {
    echo "- $t\n";
}

$expected = [
    'clients',
    'calendar_settings',
    'client_integrations',
    'assistants',
    'information_sources',
    'chatbot',
    'conversation_logs',
    'users',
    'appointments',
    'pdf_templates'
];

echo "\n--- VALIDATION ---\n";
foreach ($expected as $exp) {
    if (in_array($exp, $tables)) {
        echo "[OK] $exp exists.\n";
    } else {
        echo "[MISSING] $exp IS MISSING!\n";
    }
}
?>