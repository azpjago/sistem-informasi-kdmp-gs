<?php
session_start();
require 'koneksi/koneksi.php';

if ($conn->connect_error) {
    die(json_encode([]));
}

$search = $_GET['search'] ?? '';

$stmt = $conn->prepare("
    SELECT id, no_anggota, nama 
    FROM anggota 
    WHERE (nama LIKE ? OR no_anggota LIKE ?) 
    AND status_keanggotaan = 'Aktif'
    ORDER BY nama
    LIMIT 10
");

$searchTerm = "%$search%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$anggota = [];
while ($row = $result->fetch_assoc()) {
    $anggota[] = $row;
}

header('Content-Type: application/json');
echo json_encode($anggota);
?>
