<?php
// pages/proses_pengurangan_stok.php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');

function kurangiStokPesananTerkirim($conn, $id_pemesanan)
{
    // 1. Pastikan status pesanan = "Terkirim"
    $query_cek_status = "SELECT status FROM pemesanan WHERE id_pemesanan = ?";
    $stmt_cek = mysqli_prepare($conn, $query_cek_status);
    mysqli_stmt_bind_param($stmt_cek, 'i', $id_pemesanan);
    mysqli_stmt_execute($stmt_cek);
    $result_cek = mysqli_stmt_get_result($stmt_cek);
    $pesanan = mysqli_fetch_assoc($result_cek);

    if ($pesanan['status'] !== 'Terkirim') {
        throw new Exception("Pengurangan stok hanya untuk pesanan dengan status 'Terkirim'");
    }

    // 2. Ambil detail pesanan
    $query_detail = "
        SELECT pd.id_produk, pd.jumlah as qty_terjual, p.is_paket 
        FROM pemesanan_detail pd 
        JOIN produk p ON pd.id_produk = p.id_produk 
        WHERE pd.id_pemesanan = ?
    ";

    $stmt_detail = mysqli_prepare($conn, $query_detail);
    mysqli_stmt_bind_param($stmt_detail, 'i', $id_pemesanan);
    mysqli_stmt_execute($stmt_detail);
    $result_detail = mysqli_stmt_get_result($stmt_detail);

    // 3. Kurangi stok untuk setiap item
    while ($detail = mysqli_fetch_assoc($result_detail)) {
        if ($detail['is_paket'] == 0) {
            // PRODUK ECERAN
            $query_eceran = "
                UPDATE inventory_ready ir
                JOIN produk p ON ir.id_inventory = p.id_inventory
                SET ir.jumlah_tersedia = ir.jumlah_tersedia - (p.jumlah * ?)
                WHERE p.id_produk = ?
            ";

            $stmt_eceran = mysqli_prepare($conn, $query_eceran);
            mysqli_stmt_bind_param($stmt_eceran, 'di', $detail['qty_terjual'], $detail['id_produk']);
            mysqli_stmt_execute($stmt_eceran);

        } else {
            // PRODUK PAKET
            $query_komponen = "
                SELECT ppi.id_produk_komposisi
                FROM produk_paket_items ppi 
                WHERE ppi.id_produk_paket = ?
            ";

            $stmt_komponen = mysqli_prepare($conn, $query_komponen);
            mysqli_stmt_bind_param($stmt_komponen, 'i', $detail['id_produk']);
            mysqli_stmt_execute($stmt_komponen);
            $result_komponen = mysqli_stmt_get_result($stmt_komponen);

            while ($komponen = mysqli_fetch_assoc($result_komponen)) {
                $query_update_komponen = "
                    UPDATE inventory_ready ir
                    JOIN produk p ON ir.id_inventory = p.id_inventory
                    SET ir.jumlah_tersedia = ir.jumlah_tersedia - (p.jumlah * ?)
                    WHERE p.id_produk = ?
                ";

                $stmt_update = mysqli_prepare($conn, $query_update_komponen);
                mysqli_stmt_bind_param($stmt_update, 'di', $detail['qty_terjual'], $komponen['id_produk_komposisi']);
                mysqli_stmt_execute($stmt_update);
            }
        }
    }

    return true;
}

// Contoh penggunaan saat update status pesanan
if (isset($_POST['update_status'])) {
    $id_pemesanan = $_POST['id_pemesanan'];
    $status_baru = $_POST['status'];

    mysqli_begin_transaction($conn);

    try {
        // Update status pesanan
        $query_update = "UPDATE pemesanan SET status = ? WHERE id_pemesanan = ?";
        $stmt_update = mysqli_prepare($conn, $query_update);
        mysqli_stmt_bind_param($stmt_update, 'si', $status_baru, $id_pemesanan);
        mysqli_stmt_execute($stmt_update);

        // Jika status menjadi "Terkirim", kurangi stok
        if ($status_baru === 'Terkirim') {
            kurangiStokPesananTerkirim($conn, $id_pemesanan);
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Status updated']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
