<?php
require_once 'db.php';

echo "<h2>Fixing Database Schema for Clients and Users</h2>";
echo "<pre>";

function column_exists($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($res) > 0;
}

// 1. Fix Clients Table
echo "--- Fixing 'clients' table ---\n";

// Add missing columns
$cols_to_add = [
    'rut' => "VARCHAR(20) NULL",
    'address' => "TEXT NULL",
    'phone' => "VARCHAR(50) NULL",
    'business_line' => "VARCHAR(255) NULL",
    'representative_name' => "VARCHAR(255) NULL",
    'representative_phone' => "VARCHAR(50) NULL",
    'representative_email' => "VARCHAR(100) NULL"
];

foreach ($cols_to_add as $name => $def) {
    if (!column_exists($conn, 'clients', $name)) {
        if (mysqli_query($conn, "ALTER TABLE clients ADD COLUMN `$name` $def")) {
            echo "[OK] Columna '$name' agregada.\n";
        } else {
            echo "[ERROR] Fallo al agregar '$name': " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "[SKIP] Columna '$name' ya existe.\n";
    }
}

// Fix 'type' ENUM
if (!column_exists($conn, 'clients', 'type')) {
    mysqli_query($conn, "ALTER TABLE clients ADD COLUMN `type` ENUM('particular', 'empresa', 'marca_personal') DEFAULT 'particular' AFTER name");
    echo "[OK] Columna 'type' creada.\n";
} else {
    mysqli_query($conn, "ALTER TABLE clients MODIFY COLUMN `type` ENUM('particular', 'empresa', 'marca_personal') DEFAULT 'particular'");
    echo "[OK] Tipo de ENUM en 'clients' actualizado.\n";
}

// 2. Fix Users Table
echo "\n--- Fixing 'users' table ---\n";

// Rename 'password' to 'password_hash' if it exists
if (column_exists($conn, 'users', 'password') && !column_exists($conn, 'users', 'password_hash')) {
    if (mysqli_query($conn, "ALTER TABLE users CHANGE `password` `password_hash` VARCHAR(255) NOT NULL")) {
        echo "[OK] Columna 'password' renombrada a 'password_hash'.\n";
    } else {
        echo "[ERROR] Fallo al renombrar 'password': " . mysqli_error($conn) . "\n";
    }
}

// Fix 'role' ENUM to include 'client'
mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN `role` ENUM('superadmin', 'admin', 'client') DEFAULT 'client'");
echo "[OK] Roles en 'users' actualizados.\n";

echo "\n--- Fin del proceso ---";
echo "</pre>";
?>