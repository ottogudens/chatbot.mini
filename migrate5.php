<?php
require 'db.php';
require 'auth.php';
check_auth();

$sql = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assistant_id INT NOT NULL,
    client_id INT NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_email VARCHAR(150) NOT NULL,
    user_phone VARCHAR(50) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    google_event_id VARCHAR(255) NULL,
    google_calendar_id VARCHAR(255) DEFAULT 'primary',
    status ENUM('confirmed', 'cancelled') DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "<p style='color:green;font-family:monospace;'>✅ Tabla <b>appointments</b> creada (o ya existía).</p>";
} else {
    echo "<p style='color:red;font-family:monospace;'>❌ Error: " . mysqli_error($conn) . "</p>";
}
?>
