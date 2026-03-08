<?php
require_once 'db.php';

echo "<h2>Migrando base de datos para Portal Multi-Tenant e Integración Drive...</h2>";

// 1. Alter users table
// Use IF NOT EXISTS equivalent logic or simply suppress errors if columns already exist.
$sql_check_role = "SHOW COLUMNS FROM users LIKE 'role'";
$res_role = mysqli_query($conn, $sql_check_role);
if (mysqli_num_rows($res_role) == 0) {
    $sql_users = "
    ALTER TABLE users 
        ADD COLUMN role ENUM('superadmin', 'client') DEFAULT 'client' AFTER password_hash,
        ADD COLUMN client_id INT NULL AFTER role,
        ADD CONSTRAINT fk_user_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
    ";
    if (mysqli_query($conn, $sql_users)) {
        echo "<p>Tabla <b>users</b> actualizada con 'role' y 'client_id' (Foreign Key).</p>";
    } else {
        echo "<p>Error actualizando <b>users</b>: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p>Tabla <b>users</b> ya contiene las columnas de rol.</p>";
}

// 1.1 Set existing admin as superadmin
$sql_admin = "UPDATE users SET role = 'superadmin' WHERE username = 'admin';";
mysqli_query($conn, $sql_admin);

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

echo "<h3>Migración finalizada.</h3>";
?>