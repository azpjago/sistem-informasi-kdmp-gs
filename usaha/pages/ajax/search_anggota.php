<?php
session_start();
if ($_SESSION['role'] !== 'usaha') exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
header('Content-Type: application/json');

$search = $conn->real_escape_string($_GET['search'] ?? '');

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

// Search anggota
$result = $conn->query("
    SELECT id, no_anggota, nama 
    FROM anggota 
    WHERE status_keanggotaan = 'Aktif'
    AND (nama LIKE '%$search%' OR no_anggota LIKE '%$search%')
    ORDER BY nama
    LIMIT 10
");

$anggota = [];
while ($row = $result->fetch_assoc()) {
    $anggota[] = $row;
}

echo json_encode($anggota);
$conn->close();
?>