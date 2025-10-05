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
// ================== FUNGSI UNTUK MODUL LOGIN PENGURUS ==================

// Fungsi untuk log aktivitas login
function log_login_activity($user_id, $username, $role, $status, $description, $ip_address = null)
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip_address = $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    $activity_type = ($status === 'success') ? 'login_success' : 'login_failed';

    return log_activity($user_id, $role, $activity_type, $description, 'pengurus', $user_id);
}

// Fungsi untuk log login berhasil
function log_login_success($user_id, $username, $role)
{
    $description = "Login berhasil sebagai $role - Username: $username";
    return log_login_activity($user_id, $username, $role, 'success', $description);
}

// Fungsi untuk log login gagal - username tidak ditemukan
function log_login_failed_username($username, $ip_address = null)
{
    $description = "Login gagal - Username tidak ditemukan: $username";
    return log_login_activity(0, $username, 'unknown', 'failed', $description, $ip_address);
}

// Fungsi untuk log login gagal - password salah
function log_login_failed_password($user_id, $username, $role)
{
    $description = "Login gagal - Password salah untuk username: $username";
    return log_login_activity($user_id, $username, $role, 'failed', $description);
}

// Fungsi untuk log logout
function log_logout_activity($user_id, $username, $role)
{
    $description = "Logout berhasil - Username: $username";
    return log_activity($user_id, $role, 'logout', $description, 'pengurus', $user_id);
}

// Fungsi untuk log attempt login dengan data kosong
function log_login_empty_fields()
{
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $description = "Attempt login dengan field kosong";
    return log_activity(0, 'unknown', 'login_failed', $description, 'pengurus', 0);
}
?>