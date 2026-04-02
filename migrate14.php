<?php
require_once 'db.php';

// Migration 14: Add attachment support to marketing_campaigns
// This adds columns to store the file path and type for campaign messages.

$query = "ALTER TABLE marketing_campaigns 
    ADD COLUMN attachment_url VARCHAR(255) NULL AFTER message,
    ADD COLUMN attachment_type VARCHAR(50) NULL AFTER attachment_url;";

try {
    if (mysqli_query($conn, $query)) {
        echo "Migration 14 (Marketing Campaign Attachments) completed successfully.\n";
    }
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Migration 14 already applied (columns exist).\n";
    } else {
        echo "Error in Migration 14: " . $e->getMessage() . "\n";
    }
}

?>
