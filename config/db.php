<?php
// Database Configuration
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'reg_mis';

// Create connection
$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");
?>
