<?php
// export_anggota_simple.php
session_start();

// Cek role user - hanya ketua yang bisa akses
if ($_SESSION['role'] !== 'ketua') {
    header('Location: index.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// KUERI PERBAIKAN: Hitung total simpanan dari pembayaran
$query = "
    SELECT 
        a.id,
        a.nama,
        a.jenis_kelamin,
        a.no_hp,
        a.status_keanggotaan,
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'setor'
            AND p.jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela')
        ), 0) as total_simpanan,
        COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'tarik'
        ), 0) as total_tarikan,
        (COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'setor'
            AND p.jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela')
        ), 0) - COALESCE((
            SELECT SUM(p.jumlah) 
            FROM pembayaran p 
            WHERE p.anggota_id = a.id 
            AND (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
            AND p.jenis_transaksi = 'tarik'
        ), 0)) as saldo_aktual
    FROM anggota a
    ORDER BY a.id
";

$result = $conn->query($query);

// Set headers for CSV/Excel
$filename = 'data_anggota_koperasi_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers
fputcsv($output, [
    'No Anggota',
    'Nama Lengkap',
    'Jenis Kelamin',
    'No HP',
    'Status Keanggotaan',
    'Total Simpanan',
    'Total Tarikan',
    'Saldo Aktual'
]);

// Data
while ($anggota = $result->fetch_assoc()) {
    fputcsv($output, [
        $anggota['id'],
        $anggota['nama'],
        $anggota['jenis_kelamin'],
        $anggota['no_hp'],
        $anggota['status_keanggotaan'],
        $anggota['total_simpanan'],
        $anggota['total_tarikan'],
        $anggota['saldo_aktual']
    ]);
}

fclose($output);
$conn->close();
exit;
?>