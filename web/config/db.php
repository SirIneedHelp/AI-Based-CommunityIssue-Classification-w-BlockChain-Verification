<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "community_system";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
