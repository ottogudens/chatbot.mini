-- Create Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'client') DEFAULT 'client',
    client_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123)
-- Password hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, password_hash, role) 
VALUES ('admin', '$2y$10$bdf29.aaMdrrkKTo9oKguOISCOMh9jazfJrbtDrfH/GoVojIPBHKO', 'superadmin');
