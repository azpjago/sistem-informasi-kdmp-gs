<?php
session_start();
require_once 'functions/history_log.php';

if (ob_get_length())
    ob_clean();

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $id_anggota = $_POST['id_anggota'] ?? '';
    $jadwal_kirim = $_POST['jadwal_kirim'] ?? '';
    $nama_pemesan = $_POST['nama_pemesan'] ?? '';
    $no_anggota = $_POST['no_anggota'] ?? '';
    $alamat_pemesan = $_POST['alamat_pemesan'] ?? '';
    $no_hp = $_POST['no_hp_pemesan'] ?? '';
    $tanggal_pesan = $_POST['tanggal_pesan'] ?? '';
    $total_harga = $_POST['total_harga'] ?? 0;
    $metode = $_POST['metode'] ?? 'cash';
    $bank_tujuan = $_POST['bank_tujuan'] ?? '';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    // Validasi input
    if (empty($id_anggota) || empty($jadwal_kirim) || empty($tanggal_pesan) || empty($items)) {
        throw new Exception('Data yang diperlukan tidak lengkap');
    }

    // Validasi metode transfer
    if ($metode === 'transfer' && empty($bank_tujuan)) {
        throw new Exception('Bank tujuan harus dipilih untuk metode transfer');
    }

    // Ambil info anggota untuk log
    $query_anggota = "SELECT nama, no_anggota FROM anggota WHERE id = ?";
    $stmt_anggota = mysqli_prepare($conn, $query_anggota);
    mysqli_stmt_bind_param($stmt_anggota, 'i', $id_anggota);
    mysqli_stmt_execute($stmt_anggota);
    $result_anggota = mysqli_stmt_get_result($stmt_anggota);
    $anggota_data = mysqli_fetch_assoc($result_anggota);
    $nama_anggota = $anggota_data['nama'] ?? 'Unknown';
    $no_anggota = $anggota_data['no_anggota'] ?? 'Unknown';

    // Hitung jumlah item dan produk
    $total_items = 0;
    $total_produk = count($items);
    foreach ($items as $item) {
        $total_items += ($item['qty'] ?? 0);
    }

    // Mulai transaction
    mysqli_begin_transaction($conn);

    // 1. Insert ke tabel pemesanan - DENGAN METODE & BANK TUJUAN
    $query_pemesanan = "INSERT INTO pemesanan (id_anggota, jadwal_kirim, tanggal_pesan, no_anggota, nama_pemesan, alamat_pemesan, no_hp_pemesan, total_harga, status, metode, bank_tujuan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu', ?, ?)";

    $stmt = mysqli_prepare($conn, $query_pemesanan);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . mysqli_error($conn));

    mysqli_stmt_bind_param($stmt, 'issssssdss', $id_anggota, $jadwal_kirim, $tanggal_pesan, $no_anggota, $nama_pemesan, $alamat_pemesan, $no_hp, $total_harga, $metode, $bank_tujuan);

    if (!mysqli_stmt_execute($stmt))
        throw new Exception('Execute failed: ' . mysqli_error($conn));

    $id_pemesanan = mysqli_insert_id($conn);

    // 2. Insert items ke tabel pemesanan_detail
    $produk_list = [];
    foreach ($items as $item) {
        $id_produk = $item['id_produk'] ?? 0;
        $harga = $item['harga'] ?? 0;
        $qty = $item['qty'] ?? 0;
        $satuan_input = $item['satuan'] ?? '';

        // Ambil info produk untuk log
        $query_produk = "SELECT nama_produk, satuan FROM produk WHERE id_produk = ?";
        $stmt_produk = mysqli_prepare($conn, $query_produk);
        mysqli_stmt_bind_param($stmt_produk, 'i', $id_produk);
        mysqli_stmt_execute($stmt_produk);
        $result_produk = mysqli_stmt_get_result($stmt_produk);
        $produk_data = mysqli_fetch_assoc($result_produk);

        $nama_produk = $produk_data['nama_produk'] ?? 'Unknown Product';
        $satuan = $produk_data['satuan'] ?? $satuan_input;
        $subtotal = $harga * $qty;

        // Simpan untuk log
        $produk_list[] = "$nama_produk ($qty $satuan)";

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

    // ================== HISTORY LOG - IMPROVED ==================

    // Siapkan deskripsi detail untuk log
    $produk_text = implode(', ', array_slice($produk_list, 0, 3)); // Ambil 3 produk pertama
    if (count($produk_list) > 3) {
        $produk_text .= ' dan ' . (count($produk_list) - 3) . ' produk lainnya';
    }

    $metode_text = ($metode === 'transfer') ? "Transfer ($bank_tujuan)" : "Cash";

    $log_description = "Input pesanan manual #$id_pemesanan - " .
        "Anggota: $nama_anggota ($no_anggota) - " .
        "Total: Rp " . number_format($total_harga, 0, ',', '.') . " - " .
        "Metode: $metode_text - " .
        "Items: $total_produk produk ($total_items item) - " .
        "Produk: $produk_text";

    // Log menggunakan fungsi khusus pemesanan
    $log_result = log_pesanan_manual_activity(
        $id_pemesanan,
        $log_description
    );

    // Debug log untuk memastikan history tercatat
    error_log("HISTORY LOG - Pesanan Manual #$id_pemesanan: " . ($log_result ? "SUCCESS (ID: $log_result)" : "FAILED"));

    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil disimpan (Status: Menunggu)',
        'id_pemesanan' => $id_pemesanan,
        'metode' => $metode,
        'bank_tujuan' => $bank_tujuan,
        'log_id' => $log_result // Untuk debugging
    ]);

} catch (Exception $e) {
    // HISTORY LOG: Error
    if (isset($id_pemesanan)) {
        log_pemesanan_activity(
            $id_pemesanan,
            'error',
            "Error input pesanan manual: " . $e->getMessage()
        );
    } else {
        // Log error tanpa id_pemesanan
        $user_id = $_SESSION['id'] ?? 0;
        log_activity($user_id, 'pengurus', 'error', "Error input pesanan manual: " . $e->getMessage(), 'pemesanan', null);
    }

    if (isset($conn) && mysqli_thread_id($conn))
        mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    if (isset($conn))
        mysqli_close($conn);
}

exit;
?>
