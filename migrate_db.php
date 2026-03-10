<?php
require_once 'db.php';

function column_exists($conn, $table, $column)
{
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
    return mysqli_num_rows($res) > 0;
}

echo "<h2>Migrando base de datos para Portal Multi-Tenant e Integración Drive...</h2>";

// 1. Create users table if not exists
$sql_users_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'client') DEFAULT 'client',
    client_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);";

if (mysqli_query($conn, $sql_users_table)) {
    echo "<p>Tabla <b>users</b> verificada/creada correctamente.</p>";
} else {
    echo "<p>Error creando tabla <b>users</b>: " . mysqli_error($conn) . "</p>";
}

// 1.1 Set existing admin as superadmin or create one
$hashed_password = password_hash('Ing3N3tZ##', PASSWORD_DEFAULT);
$sql_admin = "INSERT IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$hashed_password', 'superadmin');";
mysqli_query($conn, $sql_admin);
// Also update if it exists but is not superadmin
mysqli_query($conn, "UPDATE users SET role = 'superadmin' WHERE username = 'admin'");


// 2. Modify information_sources type ENUM
$sql_enum = "
ALTER TABLE information_sources 
    MODIFY COLUMN type ENUM('text', 'file', 'link', 'drive_file') DEFAULT 'text';
";
if (mysqli_query($conn, $sql_enum)) {
    echo "<p>Tabla <b>information_sources</b> actualizada con 'drive_file'.</p>";
} else {
    echo "<p>Error actualizando <b>information_sources</b>: " . mysqli_error($conn) . "</p>";
}

// 3. Create client_integrations table
$sql_integrations = "
CREATE TABLE IF NOT EXISTS client_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY client_provider_unique (client_id, provider)
);
";
if (mysqli_query($conn, $sql_integrations)) {
    echo "<p>Tabla <b>client_integrations</b> creada.</p>";
} else {
    echo "<p>Error creando tabla <b>client_integrations</b>: " . mysqli_error($conn) . "</p>";
}

// 4. Create calendar_settings table
$sql_calendar = "
CREATE TABLE IF NOT EXISTS calendar_settings (
    client_id INT PRIMARY KEY,
    calendar_id VARCHAR(255) DEFAULT 'primary',
    available_days VARCHAR(50) DEFAULT '1,2,3,4,5',
    start_time TIME DEFAULT '09:00:00',
    end_time TIME DEFAULT '18:00:00',
    slot_duration_minutes INT DEFAULT 30,
    timezone VARCHAR(50) DEFAULT 'America/Santiago',
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
";
if (mysqli_query($conn, $sql_calendar)) {
    echo "<p>Tabla <b>calendar_settings</b> creada.</p>";
} else {
    echo "<p>Error creando tabla <b>calendar_settings</b>: " . mysqli_error($conn) . "</p>";
}

// Phase 4: Extended Client Profiles
$columns = [
    'type' => "ENUM('particular', 'empresa') DEFAULT 'particular'",
    'rut' => "VARCHAR(20) NULL",
    'address' => "TEXT NULL",
    'phone' => "VARCHAR(50) NULL",
    'business_line' => "VARCHAR(255) NULL", // Giro
    'representative_name' => "VARCHAR(255) NULL",
    'representative_phone' => "VARCHAR(50) NULL",
    'representative_email' => "VARCHAR(100) NULL"
];

foreach ($columns as $name => $def) {
    if (!column_exists($conn, 'clients', $name)) {
        mysqli_query($conn, "ALTER TABLE clients ADD COLUMN $name $def");
        echo "Columna '$name' agregada a la tabla 'clients'.<br>";
    }
}

echo "Migraciones completadas correctamente.";
?>