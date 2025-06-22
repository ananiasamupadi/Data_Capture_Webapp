<?php
$host = 'localhost';
$dbname = 'data_capture';
$username = 'root';
$password = '';

$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    die(json_encode(['error' => "Connection failed: " . mysqli_connect_error()]));
}
?>