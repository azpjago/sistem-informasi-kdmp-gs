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
              (user_id, user_type, activity_type, description, table_affected, record_id, user_agent, ip_address) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

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

// Helper functions untuk activity spesifik
function log_pengeluaran_activity($pengeluaran_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, $activity_type, $description, 'pengeluaran', $pengeluaran_id);
    }
    return false;
}

function log_pinjaman_activity($pinjaman_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, $activity_type, $description, 'pinjaman', $pinjaman_id);
    }
    return false;
}

function log_anggota_activity($anggota_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, $activity_type, $description, 'anggota', $anggota_id);
    }
    return false;
}

function log_pembayaran_activity($pembayaran_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, $activity_type, $description, 'pembayaran', $pembayaran_id);
    }
    return false;
}

// Function untuk log login/logout
function log_auth_activity($user_id, $user_type, $activity_type, $description)
{
    return log_activity($user_id, $user_type, $activity_type, $description, 'users', $user_id);
}

// Function untuk log perubahan status
function log_status_change($table_name, $record_id, $old_status, $new_status, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        $description = "Mengubah status $table_name #$record_id dari $old_status menjadi $new_status";
        return log_activity($_SESSION['id'], $user_type, 'status_change', $description, $table_name, $record_id);
    }
    return false;
}

// Function untuk log pembuatan data baru
function log_create_activity($table_name, $record_id, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, 'create', $description, $table_name, $record_id);
    }
    return false;
}

// Function untuk log update data
function log_update_activity($table_name, $record_id, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, 'update', $description, $table_name, $record_id);
    }
    return false;
}
?>