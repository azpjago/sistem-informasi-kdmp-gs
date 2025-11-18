<?php
// pages/get_order_detail.php

// Pastikan tidak ada output sebelum ini
if (ob_get_length())
    ob_clean();

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);
header('Content-Type: application/json');

// Matikan error reporting untuk production
error_reporting(0);
ini_set('display_errors', 0);

// Function untuk log error
function log_error($message)
{
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

try {
    $id = $_GET['id'] ?? 0;

    if (empty($id) || !is_numeric($id)) {
        throw new Exception('ID pesanan tidak valid');
    }

    // Query data pemesanan
    $query_pemesanan = "SELECT p.*, a.nama as nama_anggota, a.no_hp, a.alamat
                        FROM pemesanan p
                        JOIN anggota a ON p.id_anggota = a.id
                        WHERE p.id_pemesanan = ?";

    $stmt = mysqli_prepare($conn, $query_pemesanan);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result_pemesanan = mysqli_stmt_get_result($stmt);
    $pemesanan = mysqli_fetch_assoc($result_pemesanan);

    if (!$pemesanan) {
        throw new Exception('Pesanan tidak ditemukan');
    }

    // Query items pesanan
    $query_items = "SELECT dp.*, pr.nama_produk
                    FROM pemesanan_detail dp
                    JOIN produk pr ON dp.id_produk = pr.id_produk
                    WHERE dp.id_pemesanan = ?";

    $stmt_items = mysqli_prepare($conn, $query_items);
    if (!$stmt_items) {
        throw new Exception('Prepare items failed: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt_items, 'i', $id);
    mysqli_stmt_execute($stmt_items);
    $result_items = mysqli_stmt_get_result($stmt_items);
    $items = [];

    while ($row = mysqli_fetch_assoc($result_items)) {
        $items[] = $row;
    }

    // Siapkan response
    $response = [
        'success' => true,
        'pemesanan' => $pemesanan,
        'items' => $items
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Log error
    log_error($e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}

// Pastikan tidak ada output setelah ini
exit;
?>
