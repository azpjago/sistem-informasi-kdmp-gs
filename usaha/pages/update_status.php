<?php
ob_start();
session_start();

header('Content-Type: application/json');

// Use absolute include path (avoid relative mistakes)
$history_log_path = __DIR__ . '/functions/history_log.php';
if (file_exists($history_log_path)) {
    require_once $history_log_path;
} else {
    error_log("Missing history_log: $history_log_path");
    // don't die immediately; we can continue but log_activity() will be missing
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    error_log("DB CONNECT ERROR: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Normalize incoming data
$ids = $_POST['ids'] ?? [];
$status = $_POST['status'] ?? '';
$action_type = $_POST['action_type'] ?? 'bulk_update';

// If ids is a JSON string or comma separated string, try to decode it
if (!is_array($ids)) {
    if (is_string($ids)) {
        // try JSON decode
        $decoded = json_decode($ids, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $ids = $decoded;
        } else {
            // maybe comma separated
            $ids = array_filter(array_map('trim', explode(',', $ids)));
        }
    } else {
        $ids = [];
    }
}

// sanitize ids (integers)
$ids = array_map('intval', $ids);
$ids = array_values(array_filter($ids, function($v){ return $v > 0; }));

$userType = $_SESSION['role'] ?? 'system';
if (strlen($userType) > 50) $userType = substr($userType, 0, 50);

error_log("UPDATE_STATUS REQUEST - IDs: " . implode(',', $ids) . " Status: $status Action: $action_type UserType: $userType");

if (empty($ids) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

$allowed_statuses = [
    'Menunggu','Disiapkan','Dibatalkan','Belum Dikirim',
    'Assign Kurir','Dalam Perjalanan','Terkirim','Gagal'
];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Status tidak valid: ' . $status]);
    exit;
}

/* ---------------------------
   FUNCTIONS (tidak diubah banyak)
   --------------------------- */

function kurangiStokPesananTerkirim($conn, $id_pemesanan) {
    // (sama seperti versimu) - tetapi tambahkan pengecekan prepare/execute
    $query_detail = "
        SELECT pd.id_produk, pd.jumlah as qty_terjual, p.is_paket, p.jumlah as konversi_unit
        FROM pemesanan_detail pd 
        JOIN produk p ON pd.id_produk = p.id_produk 
        WHERE pd.id_pemesanan = ?
    ";

    if (!($stmt_detail = $conn->prepare($query_detail))) {
        error_log("prepare failed (detail): " . $conn->error);
        return false;
    }
    $stmt_detail->bind_param("i", $id_pemesanan);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();

    while ($detail = $result_detail->fetch_assoc()) {
        if ($detail['is_paket'] == 0) {
            $total_kurang = $detail['qty_terjual'] * $detail['konversi_unit'];
            $query_eceran = "
                UPDATE inventory_ready ir
                JOIN produk p ON ir.id_inventory = p.id_inventory
                SET ir.jumlah_tersedia = ir.jumlah_tersedia - ?
                WHERE p.id_produk = ?
            ";
            if (!($stmt_eceran = $conn->prepare($query_eceran))) {
                error_log("prepare failed (eceran): " . $conn->error);
                continue;
            }
            $stmt_eceran->bind_param("di", $total_kurang, $detail['id_produk']);
            $stmt_eceran->execute();
            if ($stmt_eceran->error) error_log("eceran execute error: " . $stmt_eceran->error);
            $stmt_eceran->close();
        } else {
            $query_komponen = "
                SELECT pk.id_produk_komposisi, pk.quantity_komponen as qty_komponen
                FROM produk_paket_items pk 
                WHERE pk.id_produk_paket = ?
            ";
            if (!($stmt_komponen = $conn->prepare($query_komponen))) {
                error_log("prepare failed (komponen): " . $conn->error);
                continue;
            }
            $stmt_komponen->bind_param("i", $detail['id_produk']);
            $stmt_komponen->execute();
            $result_komponen = $stmt_komponen->get_result();

            while ($komponen = $result_komponen->fetch_assoc()) {
                $query_komponen_detail = "SELECT p.jumlah as konversi_komponen FROM produk p WHERE p.id_produk = ?";
                if (!($stmt_komp_detail = $conn->prepare($query_komponen_detail))) {
                    error_log("prepare failed (komp_detail): " . $conn->error);
                    continue;
                }
                $stmt_komp_detail->bind_param("i", $komponen['id_produk_komposisi']);
                $stmt_komp_detail->execute();
                $result_komp_detail = $stmt_komp_detail->get_result();
                $komp_detail = $result_komp_detail->fetch_assoc();
                $stmt_komp_detail->close();

                $konversi = $komp_detail['konversi_komponen'] ?? 1;
                $total_kurang_komponen = $detail['qty_terjual'] * $komponen['qty_komponen'] * $konversi;

                $query_update_komponen = "
                    UPDATE inventory_ready ir
                    JOIN produk p ON ir.id_inventory = p.id_inventory
                    SET ir.jumlah_tersedia = ir.jumlah_tersedia - ?
                    WHERE p.id_produk = ?
                ";
                if (!($stmt_update = $conn->prepare($query_update_komponen))) {
                    error_log("prepare failed (update komponen): " . $conn->error);
                    continue;
                }
                $stmt_update->bind_param("di", $total_kurang_komponen, $komponen['id_produk_komposisi']);
                $stmt_update->execute();
                if ($stmt_update->error) error_log("update komponen execute error: " . $stmt_update->error);
                $stmt_update->close();
            }
            $stmt_komponen->close();
        }
    }
    $stmt_detail->close();
    return true;
}

function getPesananInfo($conn, $id_pemesanan) {
    $query = "
        SELECT p.id_pemesanan, p.total_harga, a.nama as nama_anggota, 
               p.metode, p.bank_tujuan, p.status as old_status
        FROM pemesanan p
        JOIN anggota a ON p.id_anggota = a.id
        WHERE p.id_pemesanan = ?
    ";
    if (!($stmt = $conn->prepare($query))) {
        error_log("prepare failed (getPesananInfo): " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $id_pemesanan);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc() ?: [];
    $stmt->close();
    return $data;
}

/* ---------------------------
   MAIN TRANSACTION
   --------------------------- */

$updated = 0;
$errors = [];
$log_details = [];

$kurir_id = $_POST['kurir_id'] ?? null;
$tanggal_pengiriman = $_POST['tanggal_pengiriman'] ?? date('Y-m-d');
$alasan_gagal = $_POST['alasan_gagal'] ?? 'Tidak disebutkan';

try {
    $conn->begin_transaction();

    foreach ($ids as $id) {
        $id = intval($id);
        if ($id <= 0) continue;

        $pesanan_info = getPesananInfo($conn, $id);
        $old_status = $pesanan_info['old_status'] ?? 'Unknown';
        $nama_anggota = $pesanan_info['nama_anggota'] ?? 'Unknown';
        $total_harga = $pesanan_info['total_harga'] ?? 0;

        // build sql / params
        $sql = "";
        $param_types = "";
        $params = [];

        switch ($status) {
            case 'Belum Dikirim':
                $sql = "UPDATE pemesanan SET status = ?, tanggal_pengiriman = CURDATE() WHERE id_pemesanan = ?";
                $param_types = "si";
                $params = [$status, $id];
                break;
            case 'Dalam Perjalanan':
                $sql = "UPDATE pemesanan SET status = ?, waktu_dikirim = NOW() WHERE id_pemesanan = ?";
                $param_types = "si";
                $params = [$status, $id];
                break;
            case 'Terkirim':
                $sql = "UPDATE pemesanan SET status = ?, waktu_selesai = NOW(), waktu_diterima = NOW() WHERE id_pemesanan = ?";
                $param_types = "si";
                $params = [$status, $id];
                break;
            case 'Gagal':
                $sql = "UPDATE pemesanan SET status = ?, keterangan_gagal = ?, waktu_selesai = NOW() WHERE id_pemesanan = ?";
                $param_types = "ssi";
                $params = [$status, $alasan_gagal, $id];
                break;
            case 'Assign Kurir':
                if ($kurir_id) {
                    $sql = "UPDATE pemesanan SET status = ?, id_kurir = ?, tanggal_pengiriman = ? WHERE id_pemesanan = ?";
                    $param_types = "sisi";
                    $params = [$status, intval($kurir_id), $tanggal_pengiriman, $id];
                } else {
                    $errors[] = "Kurir ID diperlukan untuk status Assign Kurir (order $id)";
                    continue;
                }
                break;
            default:
                $sql = "UPDATE pemesanan SET status = ? WHERE id_pemesanan = ?";
                $param_types = "si";
                $params = [$status, $id];
                break;
        }

        if (!$sql) continue;

        if (!($stmt = $conn->prepare($sql))) {
            $errors[] = "Gagal prepare untuk pesanan #$id: " . $conn->error;
            error_log(end($errors));
            continue;
        }

        // Bind using call_user_func_array because bind_param requires references
        $bind_names[] = $param_types;
        for ($i = 0; $i < count($params); $i++) {
            // convert all to appropriate scalar types
            if (is_int($params[$i])) $bind_names[] = $params[$i];
            else $bind_names[] = (string)$params[$i];
        }
        // create references
        $tmp = [];
        foreach ($bind_names as $key => $val) $tmp[$key] = &$bind_names[$key];
        call_user_func_array([$stmt, 'bind_param'], $tmp);

        if ($stmt->execute()) {
            if ($status === 'Terkirim') {
                kurangiStokPesananTerkirim($conn, $id);
            }
            $updated++;
            // build log description
            $additional_info = '';
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
            $log_description = "Update status pesanan #$id - Dari: $old_status → Ke: $status - Anggota: $nama_anggota - Total: Rp " . number_format($total_harga, 0, ',', '.') . $additional_info;
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'] ?? 0, $userType, 'update_status', $log_description, 'pemesanan', $id);
            } else {
                error_log("log_activity() not found; skipping per-order log");
            }
            $log_details[] = "Pesanan #$id: $old_status → $status";
        } else {
            $errors[] = "Gagal update pesanan #$id: " . $stmt->error;
            error_log(end($errors));
        }
        $stmt->close();
        unset($bind_names, $tmp);
    }

    // Commit only after loop and checks
    if (!empty($errors) && $updated === 0) {
        // nothing updated and errors exist -> rollback
        $conn->rollback();
        throw new Exception('Update gagal: ' . implode(' | ', $errors));
    } else {
        $conn->commit();
    }

    if ($updated > 0) {
        if ($updated > 1 && $action_type === 'bulk_update') {
            $bulk_description = "Bulk update $updated pesanan ke status '$status' - Pesanan: " . implode(', ', array_slice($log_details, 0, 5)) . (count($log_details) > 5 ? " dan " . (count($log_details) - 5) . " lainnya" : "");
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'] ?? 0, $userType, 'bulk_update', $bulk_description, 'pemesanan', null);
            } else {
                error_log("log_activity() not found; skipping bulk log");
            }
        }
        echo json_encode([
            'success' => true,
            'updated' => $updated,
            'message' => 'Status berhasil diupdate untuk ' . $updated . ' pesanan',
            'action_type' => $action_type,
            'log_details' => $log_details
        ]);
    } else {
        // no update made but no fatal error
        echo json_encode(['success' => false, 'error' => 'Tidak ada pesanan yang diupdate', 'details' => $errors]);
    }

} catch (Exception $e) {
    // Ensure rollback only if transaction active
    if ($conn->errno === 0) {
        // nothing
    }
    $conn->rollback();
    error_log("TRANSACTION ERROR: " . $e->getMessage());
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user_id'] ?? 0, $userType, 'error', "Error update status: " . $e->getMessage() . " - Pesanan: " . implode(', ', $ids), 'pemesanan', null);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
exit;
?>
