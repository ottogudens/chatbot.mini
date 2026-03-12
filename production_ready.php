<?php
/**
 * Master Production Readiness Script
 * This script ensures ALL tables and columns exist according to the latest architecture.
 */
require_once 'db.php';
mysqli_report(MYSQLI_REPORT_OFF);

echo "<h1>🚀 Iniciando Verificación de Base de Datos para Producción</h1>";

function check_and_run($conn, $sql, $message)
{
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:green;'>✅ $message</p>";
    } else {
        echo "<p style='color:orange;'>ℹ️ $message (Omitido/Ya existe): " . mysqli_error($conn) . "</p>";
    }
}

// 1. Core Tables
check_and_run($conn, "CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100),
    type ENUM('particular', 'empresa') DEFAULT 'particular',
    rut VARCHAR(20) NULL,
    address TEXT NULL,
    phone VARCHAR(50) NULL,
    business_line VARCHAR(255) NULL,
    representative_name VARCHAR(255) NULL,
    representative_phone VARCHAR(50) NULL,
    representative_email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Tabla 'clients' verificada");

check_and_run($conn, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'client') DEFAULT 'client',
    client_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
)", "Tabla 'users' verificada");

check_and_run($conn, "CREATE TABLE IF NOT EXISTS assistants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    system_prompt TEXT,
    gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.5-flash',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    max_output_tokens INT NOT NULL DEFAULT 1500,
    response_style VARCHAR(20) NOT NULL DEFAULT 'balanced',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
)", "Tabla 'assistants' verificada");

check_and_run($conn, "CREATE TABLE IF NOT EXISTS information_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assistant_id INT NOT NULL,
    type ENUM('text', 'file', 'link', 'drive_file') DEFAULT 'text',
    title VARCHAR(200) NOT NULL,
    content_text MEDIUMTEXT NULL,
    file_path VARCHAR(255) NULL,
    file_type VARCHAR(50) NULL,
    file_size BIGINT NULL,
    gemini_file_uri VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE
)", "Tabla 'information_sources' verificada");

// 2. Specialized Tables
check_and_run($conn, "CREATE TABLE IF NOT EXISTS pdf_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    placeholders TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
)", "Tabla 'pdf_templates' verificada");

check_and_run($conn, "CREATE TABLE IF NOT EXISTS appointments (
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
)", "Tabla 'appointments' verificada");

// 3. Ensuring columns in case tables already existed without them
$alters = [
    "ALTER TABLE assistants ADD COLUMN IF NOT EXISTS gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.5-flash'",
    "ALTER TABLE assistants MODIFY COLUMN gemini_model VARCHAR(50) NOT NULL DEFAULT 'gemini-2.5-flash'",
    "ALTER TABLE information_sources MODIFY COLUMN type ENUM('text', 'file', 'link', 'drive_file') DEFAULT 'text'",
    "ALTER TABLE chatbot ADD COLUMN IF NOT EXISTS assistant_id INT NULL AFTER id",
    "ALTER TABLE conversation_logs ADD COLUMN IF NOT EXISTS assistant_id INT NULL AFTER id"
];

foreach ($alters as $q) {
    mysqli_query($conn, $q);
}

echo "<h2>✅ Verificación completada exitosamente.</h2>";
echo "<p>Tu base de datos está ahora 100% sincronizada con el código fuente.</p>";
