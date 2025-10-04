<?php
session_start();
if ($_SESSION['role'] !== 'usaha') exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
header('Content-Type: application/json');

$id_anggota = intval($_GET['id_anggota'] ?? 0);

// Hitung total simpanan
$result = $conn->query("
    SELECT COALESCE(SUM(jumlah), 0) as total_simpanan 
    FROM pembayaran 
    WHERE anggota_id = $id_anggota 
    AND (status_bayar = 'Lunas' OR status = 'Lunas')
    AND jenis_transaksi = 'setor'
    AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
");

$data = $result->fetch_assoc();
$max_pinjaman = $data['total_simpanan'] * 1.5;

echo json_encode([
    'max_pinjaman' => $max_pinjaman,
    'total_simpanan' => $data['total_simpanan']
]);

$conn->close();
?>