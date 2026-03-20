<?php
require 'db.php';
header('Content-Type: text/plain');
echo "Table Structure: pdf_templates\n";
$res = mysqli_query($conn, "DESCRIBE pdf_templates");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
