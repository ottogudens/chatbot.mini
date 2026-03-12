<?php
require_once 'db.php';
echo "<pre>";
$res = mysqli_query($conn, "DESCRIBE clients");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
echo "\n--- USERS ---\n";
$res = mysqli_query($conn, "DESCRIBE users");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
echo "</pre>";
?>