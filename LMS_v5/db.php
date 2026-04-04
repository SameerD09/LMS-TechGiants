<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "library_management_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>