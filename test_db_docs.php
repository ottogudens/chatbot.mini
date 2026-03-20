<?php
require 'db.php';
header('Content-Type: text/plain');

echo "Checking generated_documents table:\n";
$q = mysqli_query($conn, "SELECT * FROM generated_documents");
if (!$q) {
    echo "Error: " . mysqli_error($conn) . "\n";
} else {
    echo "Count: " . mysqli_num_rows($q) . "\n";
    while ($row = mysqli_fetch_assoc($q)) {
        print_r($row);
    }
}

echo "\nChecking assistants table (client_id mapping):\n";
$q2 = mysqli_query($conn, "SELECT id, name, client_id FROM assistants");
while ($row = mysqli_fetch_assoc($q2)) {
    print_r($row);
}
?>
