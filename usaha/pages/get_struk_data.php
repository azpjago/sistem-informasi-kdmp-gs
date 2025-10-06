<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$ids = $_POST['ids'] ?? [];
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No orders selected']);
    exit;
}

$id_list = implode(',', array_map('intval', $ids));

// Query untuk mendapatkan data pesanan lengkap
$query = "
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.alamat,
        a.no_hp,
        k.nama as nama_kurir,
        SUM(dp.jumlah) as total_item,
        SUM(dp.subtotal) as total_pembayaran,
        DATE_FORMAT(p.tanggal_pengiriman, '%d/%m/%Y') as tanggal_pengiriman_formatted
    FROM pemesanan p
    JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN kurir k ON p.id_kurir = k.id
    JOIN pemesanan_detail dp ON p.id_pemesanan = dp.id_pemesanan
    WHERE p.id_pemesanan IN ($id_list)
    GROUP BY p.id_pemesanan
";

$result = mysqli_query($conn, $query);
if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

$data = [];
while ($pesanan = mysqli_fetch_assoc($result)) {
    // Ambil detail items - PERBAIKAN: JOIN dengan tabel produk untuk dapatkan nama_produk
    $items_query = "
        SELECT 
            pd.id_produk,
            pd.jumlah,
            pd.subtotal,
            pr.nama_produk as nama_produk
        FROM pemesanan_detail pd
        JOIN produk pr ON pd.id_produk = pr.id_produk
        WHERE pd.id_pemesanan = {$pesanan['id_pemesanan']}
    ";

    $items_result = mysqli_query($conn, $items_query);
    $items = [];

    if ($items_result) {
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = $item;
        }
    } else {
        // Jika query gagal, log error
        error_log("Items query error: " . mysqli_error($conn));
    }

    $pesanan['items'] = $items;
    $data[] = $pesanan;
}

echo json_encode(['success' => true, 'data' => $data]);

mysqli_close($conn);
?>