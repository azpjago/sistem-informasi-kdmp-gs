<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['id_supplier'])) {
    $id_supplier = $conn->real_escape_string($_GET['id_supplier']);

    $query = "SELECT nama_supplier, alamat, no_telp, email 
              FROM supplier 
              WHERE id_supplier = '$id_supplier'";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $supplier = $result->fetch_assoc();
        echo json_encode($supplier);
    } else {
        echo json_encode(['error' => 'Supplier tidak ditemukan']);
    }
} else {
    echo json_encode(['error' => 'ID Supplier tidak valid']);
}

$conn->close();
?>