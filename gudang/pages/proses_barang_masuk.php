<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header("Location: ../dashboard.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Jika form disubmit
if (isset($_POST['simpan_barang_masuk'])) {
    $id_po         = intval($_POST['id_po']);
    $no_surat_jalan = $conn->real_escape_string($_POST['no_surat_jalan']);
    $tanggal_terima = $conn->real_escape_string($_POST['tanggal_terima']);
    $penerima       = $conn->real_escape_string($_POST['penerima']);
    $user_id        = $_SESSION['user_id'];

    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // 1. Insert ke tabel barang_masuk
        $sql = "INSERT INTO barang_masuk (id_po, tanggal_terima, no_surat_jalan, penerima, status, created_by, created_at)
                VALUES ('$id_po', '$tanggal_terima', '$no_surat_jalan', '$penerima', 'draft', '$user_id', NOW())";
        
        if (!$conn->query($sql)) {
            throw new Exception("Gagal menyimpan barang masuk: " . $conn->error);
        }

        $id_barang_masuk = $conn->insert_id;

        // 2. ✅ UPDATE STATUS PO MENJADI "SELESAI" atau "RECEIVED"
        $update_po = "UPDATE purchase_order SET status='selesai' WHERE id_po='$id_po'";
        if (!$conn->query($update_po)) {
            throw new Exception("Gagal update status PO: " . $conn->error);
        }

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