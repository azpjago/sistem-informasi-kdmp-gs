<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$id_anggota = intval($_GET['id_anggota'] ?? 0);

// Hitung total simpanan anggota (Pokok + Wajib)
$simpanan_result = $conn->query("
    SELECT COALESCE(SUM(jumlah), 0) as total_simpanan 
    FROM pembayaran 
    WHERE anggota_id = $id_anggota 
    AND (status_bayar = 'Lunas' OR status = 'Lunas')
    AND jenis_transaksi = 'setor'
    AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
");

$simpanan_data = $simpanan_result->fetch_assoc();
$total_simpanan = $simpanan_data['total_simpanan'];

// Hitung max pinjaman (150% dari simpanan)
$max_pinjaman = $total_simpanan * 1.5;

header('Content-Type: application/json');
echo json_encode([
    'total_simpanan' => $total_simpanan,
    'max_pinjaman' => $max_pinjaman
]);
?>