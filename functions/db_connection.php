<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "dcsm_db_NEW"; // change to your database name

$conn = new mysqli($host, $user, $password, $database);
mysqli_set_charset($conn, "utf8mb4");
// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>