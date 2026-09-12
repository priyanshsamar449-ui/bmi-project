<?php
// Database connection settings.
// These defaults match XAMPP's default MySQL setup (root user, no password).
// If your setup is different, just change the values below.

$host    = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bmi_calculator";

$conn = mysqli_connect($host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
