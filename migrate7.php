<?php
require 'db.php';

echo "Iniciando migración 7: Crear tabla leads para gestión de prospectos...\n";

$sql = "CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    assistant_id INT DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    status ENUM('nuevo', 'contactado', 'interesado', 'cerrado', 'descartado') DEFAULT 'nuevo',
    notes TEXT,
    captured_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assistant_id) REFERENCES assistants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    if (mysqli_query($conn, $sql)) {
        echo "✓ Tabla 'leads' creada exitosamente.\n";
    } else {
        echo "✗ Error al crear tabla: " . mysqli_error($conn) . "\n";
    }
} catch (mysqli_sql_exception $e) {
    echo "✗ Excepción SQL: " . $e->getMessage() . "\n";
}

echo "Migración completada.\n";
?>