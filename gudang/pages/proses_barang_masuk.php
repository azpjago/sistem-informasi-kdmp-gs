<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header("Location: ../dashboard.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include history log functions
require_once 'functions/history_log.php';

// Jika form disubmit
if (isset($_POST['simpan_barang_masuk'])) {
    $id_po = intval($_POST['id_po']);
    $no_surat_jalan = $conn->real_escape_string($_POST['no_surat_jalan']);
    $tanggal_terima = $conn->real_escape_string($_POST['tanggal_terima']);
    $penerima = $conn->real_escape_string($_POST['penerima']);
    $user_id = $_SESSION['user_id'];

    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // Ambil data PO untuk log
        $query_po = "SELECT po.*, s.nama_supplier 
                     FROM purchase_order po 
                     LEFT JOIN supplier s ON po.id_supplier = s.id_supplier 
                     WHERE po.id_po = '$id_po'";
        $result_po = $conn->query($query_po);
        $po_data = $result_po->fetch_assoc();

        // 1. Insert ke tabel barang_masuk
        $sql = "INSERT INTO barang_masuk (id_po, tanggal_terima, no_surat_jalan, penerima, status, created_by, created_at)
                VALUES ('$id_po', '$tanggal_terima', '$no_surat_jalan', '$penerima', 'draft', '$user_id', NOW())";

        if (!$conn->query($sql)) {
            throw new Exception("Gagal menyimpan barang masuk: " . $conn->error);
        }

        $id_barang_masuk = $conn->insert_id;

        // Panggil fungsi history log untuk pembuatan barang masuk
        $description = "Membuat barang masuk #$id_barang_masuk untuk PO " . $po_data['no_invoice'] . " - " . $po_data['nama_supplier'] . " dengan surat jalan: $no_surat_jalan";
        log_barang_masuk_activity($id_barang_masuk, 'create', $description, 'gudang');

        // 2. ✅ UPDATE STATUS PO MENJADI "SELESAI" atau "RECEIVED"
        $old_status = $po_data['status'];
        $new_status = 'selesai';

        $update_po = "UPDATE purchase_order SET status='$new_status' WHERE id_po='$id_po'";
        if (!$conn->query($update_po)) {
            throw new Exception("Gagal update status PO: " . $conn->error);
        }

        // Panggil fungsi history log untuk perubahan status PO
        $description_po = "Mengubah status PO " . $po_data['no_invoice'] . " dari $old_status menjadi $new_status karena barang sudah diterima";
        log_po_status_change($id_po, $old_status, $new_status, 'gudang');

        $conn->commit();
        $_SESSION['success'] = "Barang masuk berhasil ditambahkan dan status PO diperbarui!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: ../dashboard.php?page=barang_masuk");
    exit;
} else {
    $_SESSION['error'] = "Form tidak valid!";
    header("Location: ../dashboard.php?page=barang_masuk");
    exit;
}
?>
