<?php
session_start();
require_once 'functions/history_log.php'; // Pastikan include history log

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$ids = $_POST['ids'] ?? [];
$status = $_POST['status'] ?? '';

if (empty($ids) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

// Validasi status untuk SEMUA modul
$allowed_statuses = [
    'Menunggu',
    'Disiapkan',
    'Dibatalkan', // Monitoring
    'Belum Dikirim',
    'Assign Kurir',
    'Dalam Perjalanan',
    'Terkirim',
    'Gagal' // Pengiriman
];

if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Status tidak valid: ' . $status]);
    exit;
}

// FUNCTION: Kurangi stok ketika status menjadi "Terkirim"
function kurangiStokPesananTerkirim($conn, $id_pemesanan)
{
    // Ambil detail pesanan dengan quantity yang benar
    $query_detail = "
        SELECT pd.id_produk, pd.jumlah as qty_terjual, p.is_paket, p.jumlah as konversi_unit
        FROM pemesanan_detail pd 
        JOIN produk p ON pd.id_produk = p.id_produk 
        WHERE pd.id_pemesanan = ?
    ";

    $stmt_detail = $conn->prepare($query_detail);
    $stmt_detail->bind_param("i", $id_pemesanan);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();

    // Kurangi stok untuk setiap item
    while ($detail = $result_detail->fetch_assoc()) {
        if ($detail['is_paket'] == 0) {
            // PRODUK ECERAN - kurangi berdasarkan qty_terjual × konversi_unit
            $total_kurang = $detail['qty_terjual'] * $detail['konversi_unit'];

            $query_eceran = "
                UPDATE inventory_ready ir
                JOIN produk p ON ir.id_inventory = p.id_inventory
                SET ir.jumlah_tersedia = ir.jumlah_tersedia - ?
                WHERE p.id_produk = ?
            ";

            $stmt_eceran = $conn->prepare($query_eceran);
            $stmt_eceran->bind_param("di", $total_kurang, $detail['id_produk']);
            $stmt_eceran->execute();
            $stmt_eceran->close();

        } else {
            // PRODUK PAKET - kurangi masing-masing komponen
            $query_komponen = "
                SELECT pk.id_produk_komposisi, pk.quantity_komponen as qty_komponen
                FROM produk_paket_items pk 
                WHERE pk.id_produk_paket = ?
            ";

            $stmt_komponen = $conn->prepare($query_komponen);
            $stmt_komponen->bind_param("i", $detail['id_produk']);
            $stmt_komponen->execute();
            $result_komponen = $stmt_komponen->get_result();

            while ($komponen = $result_komponen->fetch_assoc()) {
                // Hitung: (qty_terjual × qty_komponen) × konversi_unit_komponen
                $query_komponen_detail = "
                    SELECT p.jumlah as konversi_komponen
                    FROM produk p 
                    WHERE p.id_produk = ?
                ";

                $stmt_komp_detail = $conn->prepare($query_komponen_detail);
                $stmt_komp_detail->bind_param("i", $komponen['id_produk_komposisi']);
                $stmt_komp_detail->execute();
                $result_komp_detail = $stmt_komp_detail->get_result();
                $komp_detail = $result_komp_detail->fetch_assoc();

                $total_kurang_komponen = $detail['qty_terjual'] * $komponen['qty_komponen'] * $komp_detail['konversi_komponen'];

                $stmt_komp_detail->close();

                $query_update_komponen = "
                    UPDATE inventory_ready ir
                    JOIN produk p ON ir.id_inventory = p.id_inventory
                    SET ir.jumlah_tersedia = ir.jumlah_tersedia - ?
                    WHERE p.id_produk = ?
                ";

                $stmt_update = $conn->prepare($query_update_komponen);
                $stmt_update->bind_param("di", $total_kurang_komponen, $komponen['id_produk_komposisi']);
                $stmt_update->execute();
                $stmt_update->close();
            }
            $stmt_komponen->close();
        }
    }
    $stmt_detail->close();
    return true;
}

// FUNCTION: Ambil info pesanan untuk log
function getPesananInfo($conn, $id_pemesanan)
{
    $query = "
        SELECT p.id_pemesanan, p.total_harga, a.nama as nama_anggota, 
               p.metode, p.bank_tujuan, p.status as old_status
        FROM pemesanan p
        JOIN anggota a ON p.id_anggota = a.id
        WHERE p.id_pemesanan = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_pemesanan);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data;
}

try {
    $conn->begin_transaction();

    $updated = 0;
    $errors = [];
    $log_details = [];

    foreach ($ids as $id) {
        $id = intval($id);

        // Ambil info pesanan sebelum update untuk log
        $pesanan_info = getPesananInfo($conn, $id);
        $old_status = $pesanan_info['old_status'] ?? 'Unknown';
        $nama_anggota = $pesanan_info['nama_anggota'] ?? 'Unknown';
        $total_harga = $pesanan_info['total_harga'] ?? 0;

        // Handle different status transitions
        switch ($status) {
            case 'Belum Dikirim':
                $sql = "UPDATE pemesanan 
                        SET status = ?, 
                            tanggal_pengiriman = CURDATE() 
                        WHERE id_pemesanan = ?";
                break;

            case 'Dalam Perjalanan':
                $sql = "UPDATE pemesanan 
                        SET status = ?, 
                            waktu_dikirim = NOW() 
                        WHERE id_pemesanan = ?";
                break;

            case 'Terkirim':
                $sql = "UPDATE pemesanan 
                        SET status = ?, 
                            waktu_selesai = NOW(),
                            waktu_diterima = NOW() 
                        WHERE id_pemesanan = ?";
                break;

            case 'Gagal':
                $alasan_gagal = $_POST['alasan_gagal'] ?? 'Tidak disebutkan';
                $sql = "UPDATE pemesanan 
                        SET status = ?, 
                            keterangan_gagal = ?,
                            waktu_selesai = NOW() 
                        WHERE id_pemesanan = ?";
                break;

            case 'Assign Kurir':
                // PERBAIKAN KRITIS: Ambil tanggal_pengiriman dari POST dan gunakan untuk update
                $kurir_id = $_POST['kurir_id'] ?? null;
                $tanggal_pengiriman = $_POST['tanggal_pengiriman'] ?? date('Y-m-d');

                if ($kurir_id) {
                    $sql = "UPDATE pemesanan 
                            SET status = ?, 
                                id_kurir = ?,
                                tanggal_pengiriman = ? 
                            WHERE id_pemesanan = ?";
                } else {
                    $errors[] = "Kurir ID diperlukan untuk status Assign Kurir";
                    continue 2; // Skip ke pesanan berikutnya
                }
                break;

            default:
                $sql = "UPDATE pemesanan SET status = ? WHERE id_pemesanan = ?";
                break;
        }

        $stmt = $conn->prepare($sql);

        // Bind parameters berdasarkan status
        if ($status == 'Assign Kurir' && isset($kurir_id)) {
            // PERBAIKAN: Tambahkan parameter tanggal_pengiriman
            $stmt->bind_param("sisi", $status, $kurir_id, $tanggal_pengiriman, $id);
            error_log("Assign Kurir - ID: $id, Kurir: $kurir_id, Tanggal: $tanggal_pengiriman");
        } else if ($status == 'Gagal') {
            $stmt->bind_param("ssi", $status, $alasan_gagal, $id);
        } else {
            $stmt->bind_param("si", $status, $id);
        }

        if ($stmt->execute()) {
            // JIKA STATUS TERKIRIM, KURANGI STOK
            if ($status === 'Terkirim') {
                kurangiStokPesananTerkirim($conn, $id);
            }

            $updated++;

            // ================== HISTORY LOG PER PESANAN ==================
            $additional_info = '';

            // Tambahkan info khusus berdasarkan status
            switch ($status) {
                case 'Assign Kurir':
                    $additional_info = " - Kurir ID: $kurir_id - Tanggal: $tanggal_pengiriman";
                    break;
                case 'Gagal':
                    $additional_info = " - Alasan: $alasan_gagal";
                    break;
                case 'Terkirim':
                    $additional_info = " - Stok otomatis dikurangi";
                    break;
            }

            $log_description = "Update status pesanan #$id - " .
                "Dari: $old_status → Ke: $status - " .
                "Anggota: $nama_anggota - " .
                "Total: Rp " . number_format($total_harga, 0, ',', '.') .
                $additional_info;

            // Log perubahan status per pesanan
            $log_result = log_status_pemesanan_change($id, $old_status, $status, $log_description);

            if ($log_result) {
                error_log("SUCCESS: Log status change for order #$id - Log ID: $log_result");
            } else {
                error_log("WARNING: Failed to log status change for order #$id");
            }

            $log_details[] = "Pesanan #$id: $old_status → $status";

            error_log("SUCCESS: Updated order #$id to status: $status");

        } else {
            $error_msg = "Gagal update pesanan #$id: " . $conn->error;
            $errors[] = $error_msg;
            error_log("ERROR: " . $error_msg);
        }
        $stmt->close();
    }

    $conn->commit();

    if ($updated > 0) {
        // ================== HISTORY LOG BULK ACTION ==================
        if ($updated > 1) {
            // Log bulk action untuk multiple pesanan
            $bulk_description = "Bulk update $updated pesanan ke status '$status' - " .
                "Pesanan: " . implode(', ', array_slice($log_details, 0, 5)) .
                (count($log_details) > 5 ? " dan " . (count($log_details) - 5) . " lainnya" : "");

            $bulk_log_result = log_bulk_action_activity($status, $updated, $bulk_description);

            if ($bulk_log_result) {
                error_log("SUCCESS: Bulk action logged - Log ID: $bulk_log_result");
            }
        }

        echo json_encode([
            'success' => true,
            'updated' => $updated,
            'message' => 'Status berhasil diupdate untuk ' . $updated . ' pesanan',
            'log_details' => $log_details // Untuk debugging
        ]);

    } else {
        throw new Exception('Update gagal: ' . implode(', ', $errors));
    }

} catch (Exception $e) {
    $conn->rollback();

    // HISTORY LOG: Error
    $user_id = $_SESSION['id'] ?? 0;
    $error_description = "Error update status: " . $e->getMessage() . " - Pesanan: " . implode(', ', $ids);
    log_activity($user_id, 'pengurus', 'error', $error_description, 'pemesanan', null);

    error_log("TRANSACTION ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
exit;
?>