<?php
// pages/complete_delivery.php  
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ids = $_POST['ids'];
    $cod_diterima = $_POST['cod_diterima'] ?? [];

    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'Tidak ada pesanan dipilih']);
        exit();
    }

    $id_list = implode(',', array_map('intval', $ids));
    $waktu_sekarang = date('Y-m-d H:i:s');

    foreach ($ids as $id) {
        $jumlah_cod = isset($cod_diterima[$id]) ? floatval($cod_diterima[$id]) : 0;

        // Update status pengiriman dan catat COD
        $query = "UPDATE pemesanan 
                  SET status_pengiriman = 'Terkirim', 
                      waktu_selesai = '$waktu_sekarang',
                      cod_diterima = $jumlah_cod
                  WHERE id_pemesanan = $id 
                  AND status_pengiriman = 'Dalam Perjalanan'";

        mysqli_query($conn, $query);
    }

    echo json_encode(['success' => true, 'updated' => count($ids)]);
}
?>