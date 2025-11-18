<?php
function log_activity($user_id, $user_type, $activity_type, $description, $table_affected = null, $record_id = null)
{
    require 'koneksi/koneksi.php';

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
    // HAPUS session_start() di sini, karena sudah di-start di file utama
    // if (session_status() === PHP_SESSION_NONE) {
    //     session_start();
    // }

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

function log_approval_pinjaman($pinjaman_id, $no_anggota, $nama_anggota, $user_type = "Ketua")
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menyetujui pinjaman #$pinjaman_id untuk anggota $nama_anggota ($no_anggota)";
    return log_activity($user_id, $user_role, 'approval', $description, 'pinjaman', $pinjaman_id);
}

function log_rejection_pinjaman($pinjaman_id, $no_anggota, $nama_anggota, $alasan, $user_type = "Ketua")
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menolak pinjaman #$pinjaman_id untuk anggota $nama_anggota ($no_anggota) - Alasan: $alasan";
    return log_activity($user_id, $user_role, 'rejection', $description, 'pinjaman', $pinjaman_id);
}

// Fungsi untuk log pengajuan pengeluaran
function log_pengajuan_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $session_info['user_role'];

    $description = "Mengajukan pengeluaran #$pengeluaran_id - $keterangan (Rp " . number_format($jumlah, 0, ',', '.') . ") dari $sumber_dana";
    return log_activity($user_id, $user_role, 'pengajuan', $description, 'pengeluaran', $pengeluaran_id);
}

// Fungsi untuk log approval pengeluaran
function log_approval_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $session_info['user_role'];

    $description = "Menyetujui pengeluaran #$pengeluaran_id - $keterangan (Rp " . number_format($jumlah, 0, ',', '.') . ") dari $sumber_dana";
    return log_activity($user_id, $user_role, 'approval', $description, 'pengeluaran', $pengeluaran_id);
}

// Fungsi untuk log rejection pengeluaran
function log_rejection_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana, $alasan)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $session_info['user_role'];

    $description = "Menolak pengeluaran #$pengeluaran_id - $keterangan (Rp " . number_format($jumlah, 0, ',', '.') . ") dari $sumber_dana - Alasan: $alasan";
    return log_activity($user_id, $user_role, 'rejection', $description, 'pengeluaran', $pengeluaran_id);
}

// Fungsi untuk log edit pengeluaran
function log_edit_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $session_info['user_role'];

    $description = "Mengedit pengeluaran #$pengeluaran_id - $keterangan (Rp " . number_format($jumlah, 0, ',', '.') . ") dari $sumber_dana";
    return log_activity($user_id, $user_role, 'edit', $description, 'pengeluaran', $pengeluaran_id);
}

// Fungsi untuk log hapus pengeluaran
function log_hapus_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $session_info['user_role'];

    $description = "Menghapus pengeluaran #$pengeluaran_id - $keterangan (Rp " . number_format($jumlah, 0, ',', '.') . ") dari $sumber_dana";
    return log_activity($user_id, $user_role, 'hapus', $description, 'pengeluaran', $pengeluaran_id);
}
?>
