<?php
$conn = new mysqli('localhost', 'admin', 'password123', 'kdmpgs');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);
?>
