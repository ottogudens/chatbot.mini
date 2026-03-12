<?php
require_once 'db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Robust Database Schema Fix</h2>";
echo "<pre>";

function safe_query($conn, $sql)
{
    echo "Executing: $sql\n";
    try {
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            echo "  [ERROR] " . mysqli_error($conn) . "\n";
            return false;
        }
        echo "  [OK]\n";
        return $res;
    } catch (Exception $e) {
        echo "  [EXCEPTION] " . $e->getMessage() . "\n";
        return false;
    }
}

// 1. Get current columns for clients
echo "--- Analyzing 'clients' table ---\n";
$res = mysqli_query($conn, "DESCRIBE clients");
$client_cols = [];
while ($row = mysqli_fetch_assoc($res)) {
    $client_cols[] = $row['Field'];
}
echo "Current columns: " . implode(", ", $client_cols) . "\n";

// Add missing columns
$cols_to_add = [
    'rut' => "VARCHAR(20) NULL",
    'address' => "TEXT NULL",
    'phone' => "VARCHAR(50) NULL",
    'business_line' => "VARCHAR(255) NULL",
    'representative_name' => "VARCHAR(255) NULL",
    'representative_phone' => "VARCHAR(50) NULL",
    'representative_email' => "VARCHAR(100) NULL",
    'type' => "ENUM('particular', 'empresa', 'marca_personal') DEFAULT 'particular'"
];

foreach ($cols_to_add as $name => $def) {
    if (!in_array($name, $client_cols)) {
        safe_query($conn, "ALTER TABLE clients ADD COLUMN `$name` $def");
    } else {
        echo "Column '$name' already exists. Updating definition...\n";
        safe_query($conn, "ALTER TABLE clients MODIFY COLUMN `$name` $def");
    }
}

// 2. Get current columns for users
echo "\n--- Analyzing 'users' table ---\n";
$res = mysqli_query($conn, "DESCRIBE users");
$user_cols = [];
$role_type = "";
while ($row = mysqli_fetch_assoc($res)) {
    $user_cols[] = $row['Field'];
    if ($row['Field'] === 'role')
        $role_type = $row['Type'];
}
echo "Current columns: " . implode(", ", $user_cols) . "\n";

// Rename password to password_hash if needed
if (in_array('password', $user_cols) && !in_array('password_hash', $user_cols)) {
    safe_query($conn, "ALTER TABLE users CHANGE `password` `password_hash` VARCHAR(255) NOT NULL");
}

// Update roles if needed
if (strpos($role_type, 'client') === false) {
    safe_query($conn, "ALTER TABLE users MODIFY COLUMN `role` ENUM('superadmin', 'admin', 'client') DEFAULT 'client'");
}

echo "\n--- Process Finished ---\n";
echo "</pre>";
?>