<?php
/**
 * SkaleBot Database Updater
 * This script ensures all required tables exist in the database.
 * Usage: Visit this script via browser on the target environment.
 */

// Basic security check (optional: could add a token)
// if ($_GET['token'] !== 'some_secret') die('Unauthorized');

require_once 'db.php';

echo "<h2>SkaleBot Database Schema Update</h2>";
echo "<p>Checking and creating tables if missing...</p>";
echo "<pre>";

$tables = [
    "clients" => "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('empresa', 'marca_personal') NOT NULL,
        contact_email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "assistants" => "CREATE TABLE IF NOT EXISTS assistants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        base_prompt TEXT,
        model VARCHAR(50) DEFAULT 'gemini-2.0-flash',
        temperature FLOAT DEFAULT 0.7,
        max_output_tokens INT DEFAULT 1500,
        response_style ENUM('concise', 'balanced', 'detailed') DEFAULT 'balanced',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('superadmin', 'admin') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "calendar_settings" => "CREATE TABLE IF NOT EXISTS calendar_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL UNIQUE,
        calendar_id VARCHAR(255) DEFAULT 'primary',
        available_days VARCHAR(50) DEFAULT '1,2,3,4,5',
        start_time TIME DEFAULT '09:00:00',
        end_time TIME DEFAULT '18:00:00',
        slot_duration_minutes INT DEFAULT 30,
        timezone VARCHAR(50) DEFAULT 'UTC',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "appointments" => "CREATE TABLE IF NOT EXISTS appointments (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "pdf_templates" => "CREATE TABLE IF NOT EXISTS pdf_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        placeholders TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "information_sources" => "CREATE TABLE IF NOT EXISTS information_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        type ENUM('text', 'file', 'link') NOT NULL,
        title VARCHAR(255),
        content TEXT,
        file_path VARCHAR(255),
        source_url TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "chatbot" => "CREATE TABLE IF NOT EXISTS chatbot (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        queries TEXT NOT NULL,
        replies TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "conversation_logs" => "CREATE TABLE IF NOT EXISTS conversation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assistant_id INT NOT NULL,
        session_id VARCHAR(100),
        user_message TEXT,
        bot_reply TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "client_integrations" => "CREATE TABLE IF NOT EXISTS client_integrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL UNIQUE,
        provider ENUM('google_calendar') NOT NULL,
        access_token TEXT,
        refresh_token TEXT,
        token_expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($tables as $name => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "[OK] Tabla '$name' verificada/creada.\n";
    } else {
        echo "[ERROR] Error en tabla '$name': " . mysqli_error($conn) . "\n";
    }
}

echo "\n--- Proceso completado ---";
echo "</pre>";
?>