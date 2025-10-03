<?php
// pages/simpan_pesanan_manual.php (VERSION WITH METHOD & BANK)

session_start();
include 'functions/history_log.php';

if (ob_get_length())
    ob_clean();

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

function log_error($message)
{
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $id_anggota = $_POST['id_anggota'] ?? '';
    $jadwal_kirim = $_POST['jadwal_kirim'] ?? '';
    $nama_pemesan = $_POST['nama_pemesan'] ?? '';
    $alamat_pemesan = $_POST['alamat_pemesan'] ?? '';
    $no_hp = $_POST['no_hp_pemesan'] ?? '';
    $tanggal_pesan = $_POST['tanggal_pesan'] ?? '';
    $total_harga = $_POST['total_harga'] ?? 0;
    $metode = $_POST['metode'] ?? 'Tunai';
    $bank_tujuan = $_POST['bank_tujuan'] ?? '';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    // Validasi input
    if (empty($id_anggota) || empty($jadwal_kirim) || empty($tanggal_pesan) || empty($items)) {
        throw new Exception('Data yang diperlukan tidak lengkap');
    }

    // Validasi metode transfer
    if ($metode === 'Transfer' && empty($bank_tujuan)) {
        throw new Exception('Bank tujuan harus dipilih untuk metode transfer');
    }

    // Mulai transaction
    mysqli_begin_transaction($conn);

    // 1. Insert ke tabel pemesanan - DENGAN METODE & BANK TUJUAN
    $query_pemesanan = "INSERT INTO pemesanan (id_anggota, jadwal_kirim, tanggal_pesan, nama_pemesan, alamat_pemesan, no_hp_pemesan, total_harga, status, metode, bank_tujuan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu', ?, ?)";

    $stmt = mysqli_prepare($conn, $query_pemesanan);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . mysqli_error($conn));

    mysqli_stmt_bind_param($stmt, 'isssssdss', $id_anggota, $jadwal_kirim, $tanggal_pesan, $nama_pemesan, $alamat_pemesan, $no_hp, $total_harga, $metode, $bank_tujuan);

    if (!mysqli_stmt_execute($stmt))
        throw new Exception('Execute failed: ' . mysqli_error($conn));

    $id_pemesanan = mysqli_insert_id($conn);

    // 2. Insert items ke tabel pemesanan_detail
    foreach ($items as $item) {
        $id_produk = $item['id_produk'] ?? 0;
        $harga = $item['harga'] ?? 0;
        $qty = $item['qty'] ?? 0;
        $satuan_input = $item['satuan'] ?? '';

        // Ambil satuan dari database produk
        $query_satuan = "SELECT satuan FROM produk WHERE id_produk = ?";
        $stmt_satuan = mysqli_prepare($conn, $query_satuan);
        mysqli_stmt_bind_param($stmt_satuan, 'i', $id_produk);
        mysqli_stmt_execute($stmt_satuan);
        $result_satuan = mysqli_stmt_get_result($stmt_satuan);
        $produk_data = mysqli_fetch_assoc($result_satuan);

        // Gunakan satuan dari database, fallback ke input
        $satuan = $produk_data['satuan'] ?? $satuan_input;
        $subtotal = $harga * $qty;

        $query_detail = "INSERT INTO pemesanan_detail (id_pemesanan, id_produk, jumlah, satuan, harga_satuan, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)";

        $stmt_detail = mysqli_prepare($conn, $query_detail);
        mysqli_stmt_bind_param($stmt_detail, 'iidsdd', $id_pemesanan, $id_produk, $qty, $satuan, $harga, $subtotal);

        if (!mysqli_stmt_execute($stmt_detail)) {
            throw new Exception('Execute detail failed: ' . mysqli_error($conn));
        }
    }

    // Commit transaction
    mysqli_commit($conn);

    // HISTORY LOG
    $description = "Input pesanan manual #$id_pemesanan - Status: Menunggu - Metode: $metode" . ($metode === 'Transfer' ? " - Bank: $bank_tujuan" : "");
    $user_id = $_SESSION['id'] ?? 0;
    log_activity($user_id, 'pengurus', 'order_create_manual', $description, 'pemesanan', $id_pemesanan);

    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil disimpan (Status: Menunggu)',
        'id_pemesanan' => $id_pemesanan,
        'metode' => $metode,
        'bank_tujuan' => $bank_tujuan
    ]);

} catch (Exception $e) {
    if (isset($conn) && mysqli_thread_id($conn))
        mysqli_rollback($conn);
    log_error($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    if (isset($conn))
        mysqli_close($conn);
}

exit;
?>