<?php
function log_activity($user_id, $user_type, $activity_type, $description, $table_affected = null, $record_id = null)
{
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        return false;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $query = "INSERT INTO history_activity 
              (user_id, user_type, activity_type, description, table_affected, record_id, user_agent, ip_address, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        $conn->close();
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'issssiss', $user_id, $user_type, $activity_type, $description, $table_affected, $record_id, $user_agent, $ip_address);
    $result = mysqli_stmt_execute($stmt);
    $insert_id = mysqli_insert_id($conn);

    mysqli_stmt_close($stmt);
    $conn->close();

    return $result ? $insert_id : false;
}

// Helper function untuk mendapatkan user info yang konsisten
function get_session_user_info()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = 0;
    $user_role = 'system';

    // Cek berbagai kemungkinan session variable
    if (isset($_SESSION['id'])) {
        $user_id = $_SESSION['id'];
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    if (isset($_SESSION['role'])) {
        $user_role = $_SESSION['role'];
    } elseif (isset($_SESSION['user_role'])) {
        $user_role = $_SESSION['user_role'];
    }

    return ['user_id' => $user_id, 'user_role' => $user_role];
}

// ================== FUNGSI UNTUK MODUL USAHA ==================

// Fungsi untuk log aktivitas pemesanan
function log_pemesanan_activity($pemesanan_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pemesanan', $pemesanan_id);
}

// Fungsi untuk log aktivitas produk
function log_produk_activity($produk_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'produk', $produk_id);
}

// Fungsi untuk log perubahan status pesanan
function log_status_pemesanan_change($pemesanan_id, $old_status, $new_status, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status pesanan #$pemesanan_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'pemesanan', $pemesanan_id);
}

// Fungsi untuk log input pesanan manual
function log_pesanan_manual_activity($pemesanan_id, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, 'create', $description, 'pemesanan', $pemesanan_id);
}

// Fungsi untuk log bulk action pada monitoring
function log_bulk_action_activity($action, $count, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Melakukan bulk action '$action' pada $count pesanan";
    return log_activity($user_id, $user_role, 'bulk_action', $description, 'pemesanan', null);
}
function log_order_activity($order_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pemesanan', $order_id);
}
function log_product_activity($product_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'produk', $product_id);
}
function log_user_activity($user_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id_session = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id_session, $user_role, $activity_type, $description, 'anggota', $user_id);
}
function log_delivery_activity($delivery_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pengiriman', $delivery_id);
}
function log_auth_activity($user_id, $user_type, $activity_type, $description)
{
    return log_activity($user_id, $user_type, $activity_type, $description, 'users', $user_id);
}
function log_order_status_change($order_id, $old_status, $new_status, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status pesanan #$order_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'pemesanan', $order_id);
}
function log_pinjaman_activity($pinjaman_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pinjaman', $pinjaman_id);
}
function log_pengajuan_pinjaman($pinjaman_id, $id_anggota, $jumlah_pinjaman, $tenor_bulan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengajukan pinjaman #$pinjaman_id untuk anggota #$id_anggota sebesar Rp " .
        number_format($jumlah_pinjaman, 0, ',', '.') . " dengan tenor $tenor_bulan bulan";
    return log_activity($user_id, $user_role, 'pengajuan', $description, 'pinjaman', $pinjaman_id);
}
function log_approval_pinjaman($pinjaman_id, $no_anggota, $nama_anggota, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menyetujui pinjaman #$pinjaman_id untuk anggota $nama_anggota ($no_anggota)";
    return log_activity($user_id, $user_role, 'approval', $description, 'pinjaman', $pinjaman_id);
}
function log_rejection_pinjaman($pinjaman_id, $no_anggota, $nama_anggota, $alasan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menolak pinjaman #$pinjaman_id untuk anggota $nama_anggota ($no_anggota) - Alasan: $alasan";
    return log_activity($user_id, $user_role, 'rejection', $description, 'pinjaman', $pinjaman_id);
}
function log_pelunasan_pinjaman($pinjaman_id, $no_anggota, $nama_anggota, $total_pelunasan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Melunasi pinjaman #$pinjaman_id untuk anggota $nama_anggota ($no_anggota) sebesar Rp " .
        number_format($total_pelunasan, 0, ',', '.');
    return log_activity($user_id, $user_role, 'pelunasan', $description, 'pinjaman', $pinjaman_id);
}
// ================== FUNGSI UNTUK PEMBAYARAN CICILAN ==================

// Fungsi untuk log pembayaran cicilan
function log_pembayaran_cicilan($id_cicilan, $id_anggota, $jumlah_bayar, $metode, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Melakukan pembayaran cicilan #$id_cicilan sebesar Rp " .
        number_format($jumlah_bayar, 0, ',', '.') . " via $metode";

    return log_activity($user_id, $user_role, 'pembayaran_cicilan', $description, 'cicilan', $id_cicilan);
}

// Fungsi untuk log aktivitas cicilan secara umum
function log_cicilan_activity($cicilan_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'cicilan', $cicilan_id);
}

// Fungsi untuk log pelunasan cicilan
function log_pelunasan_cicilan($cicilan_id, $id_anggota, $total_pelunasan, $metode, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Melunasi cicilan #$cicilan_id untuk anggota #$id_anggota sebesar Rp " .
        number_format($total_pelunasan, 0, ',', '.') . " via $metode";

    return log_activity($user_id, $user_role, 'pelunasan_cicilan', $description, 'cicilan', $cicilan_id);
}

// Fungsi untuk log pembayaran sebagian cicilan
function log_pembayaran_sebagian_cicilan($cicilan_id, $id_anggota, $jumlah_bayar, $sisa_cicilan, $metode, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Membayar sebagian cicilan #$cicilan_id untuk anggota #$id_anggota sebesar Rp " .
        number_format($jumlah_bayar, 0, ',', '.') . " via $metode (Sisa: Rp " .
        number_format($sisa_cicilan, 0, ',', '.') . ")";

    return log_activity($user_id, $user_role, 'pembayaran_sebagian', $description, 'cicilan', $cicilan_id);
}

// Fungsi untuk log perubahan status cicilan
function log_status_cicilan_change($cicilan_id, $old_status, $new_status, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status cicilan #$cicilan_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'cicilan', $cicilan_id);
}
?>