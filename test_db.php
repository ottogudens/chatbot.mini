<?php
require_once 'db.php';
echo "DB Connection test:\n";
if ($conn) {
    echo "SUCCESS\n";
    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM clients");
    if ($res) {
        $row = mysqli_fetch_assoc($res);
        echo "Clients count: " . $row['cnt'] . "\n";
    } else {
        echo "Query failed: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "FAILED\n";
}
?>