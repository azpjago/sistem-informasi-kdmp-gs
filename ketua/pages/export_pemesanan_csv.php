<?php
require 'koneksi/koneksi.php';
// Query sederhana
$query = "
    SELECT 
        p.id_pemesanan,
        DATE_FORMAT(p.tanggal_pesan, '%d/%m/%Y %H:%i') as tanggal,
        a.nama as nama_anggota,
        GROUP_CONCAT(CONCAT(pr.nama_produk, ' (', pd.jumlah, ' pcs)') SEPARATOR '; ') as produk,
        p.total_harga,
        p.metode,
        p.status
    FROM pemesanan p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pemesanan_detail pd ON p.id_pemesanan = pd.id_pemesanan
    LEFT JOIN produk pr ON pd.id_produk = pr.id_produk
    GROUP BY p.id_pemesanan
    ORDER BY p.tanggal_pesan DESC
";

$result = $conn->query($query);

// Set header untuk download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pemesanan_' . date('Y-m-d') . '.csv"');

// Output CSV
$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['NO_ORDER', 'TANGGAL', 'PEMESAN', 'PRODUK', 'TOTAL', 'METODE', 'STATUS']);

// Data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id_pemesanan'],
        $row['tanggal'],
        $row['nama_anggota'],
        $row['produk'],
        'Rp ' . number_format($row['total_harga'], 0, ',', '.'),
        $row['metode'],
        $row['status']
    ]);
}

fclose($output);
$conn->close();
exit;
