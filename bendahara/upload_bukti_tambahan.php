<?php
header('Content-Type: application/json');
require 'koneksi/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pembayaran_id'])) {
    $pembayaran_id = intval($_POST['pembayaran_id']);
    $bukti_path = null;

    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
        if ($_FILES['bukti']['size'] > 10485760) { // Maks 10MB
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Ukuran file tidak boleh lebih dari 10MB.']);
            exit();
        }

        // Tentukan folder berdasarkan jenis transaksi
        $stmt_jenis = $conn->prepare("SELECT jenis_transaksi FROM pembayaran WHERE id = ?");
        $stmt_jenis->bind_param("i", $pembayaran_id);
        $stmt_jenis->execute();
        $result_jenis = $stmt_jenis->get_result()->fetch_assoc();
        $upload_dir = ($result_jenis && $result_jenis['jenis_transaksi'] == 'sukarela') ? 'bukti_bayar/' : 'bukti_bayar/';
        $stmt_jenis->close();

        $file_name = uniqid() . '-' . basename($_FILES['bukti']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $target_file)) {
            $bukti_path = $target_file;

            // Update path bukti di database
            $stmt_update = $conn->prepare("UPDATE pembayaran SET bukti = ? WHERE id = ?");
            $stmt_update->bind_param("si", $bukti_path, $pembayaran_id);
            if ($stmt_update->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Bukti berhasil diupload!']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan path bukti ke database.']);
            }
            $stmt_update->close();
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file yang diupload.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang diupload atau terjadi error.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
}
$conn->close();
?>
