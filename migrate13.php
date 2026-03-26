<?php
require_once 'db.php';

// Migration 13: Marketing Campaigns
// Creates the table to store marketing campaign information and history.

$query = "CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'error') DEFAULT 'pending',
    target_type ENUM('all', 'selected') DEFAULT 'all',
    sent_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $query)) {
    echo "Migration 13 (Marketing Campaigns) completed successfully.\n";
} else {
    echo "Error creating marketing_campaigns table: " . mysqli_error($conn) . "\n";
}
?>
