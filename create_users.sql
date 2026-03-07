-- Create Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
-- Password hash generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, password_hash) 
VALUES ('admin', '$2y$10$bdf29.aaMdrrkKTo9oKguOISCOMh9jazfJrbtDrfH/GoVojIPBHKO');
-- Note: The above hash is a placeholder for illustration, I will generate a real one in the implementation phase or ask the user to set it.
