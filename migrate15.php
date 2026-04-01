<?php
require_once 'db.php';

// Migration 15: Add target_ids column to marketing_campaigns 
// to store selected lead IDs for the campaign.

$query = "ALTER TABLE marketing_campaigns 
    ADD COLUMN target_ids TEXT NULL AFTER target_type;";

if (mysqli_query($conn, $query)) {
    echo "Migration 15 (Campaign Target IDs) completed successfully.\n";
} else {
    if (strpos(mysqli_error($conn), 'Duplicate column') !== false) {
        echo "Migration 15 already applied.\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n";
    }
}
?>
