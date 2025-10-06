<?php
function log_activity($user_id, $user_type, $activity_type, $description, $table_affected = null, $record_id = null)
{
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error) {
        error_log("LOG ERROR: Koneksi database gagal - " . $conn->connect_error);
        return false;
    }
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Debug information
    error_log("DEBUG LOG: Attempting to log - User: $user_id, Type: $user_type, Activity: $activity_type, Desc: $description");

    $query = "INSERT INTO history_activity 
              (user_id, user_type, activity_type, description, table_affected, record_id, user_agent, ip_address, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("LOG ERROR: Prepare failed - " . $conn->error);
        $conn->close();
        return false;
    }

    $stmt->bind_param('issssiss', $user_id, $user_type, $activity_type, $description, $table_affected, $record_id, $user_agent, $ip_address);
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("LOG ERROR: Execute failed - " . $stmt->error);
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $insert_id = $conn->insert_id;
    $stmt->close();
    $conn->close();
    
    error_log("DEBUG LOG: Successfully logged activity with ID: $insert_id");
    return $insert_id;
}

// Helper function untuk mendapatkan user info dengan debugging
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
    } elseif (isset($_SESSION['bendahara_id'])) {
        $user_id = $_SESSION['bendahara_id'];
    }
    
    if (isset($_SESSION['role'])) {
        $user_role = $_SESSION['role'];
    } elseif (isset($_SESSION['user_role'])) {
        $user_role = $_SESSION['user_role'];
    } elseif (isset($_SESSION['user_type'])) {
        $user_role = $_SESSION['user_type'];
    }
    
    error_log("DEBUG SESSION: User ID = $user_id, Role = $user_role");
    error_log("DEBUG SESSION DATA: " . print_r($_SESSION, true));
    
    return ['user_id' => $user_id, 'user_role' => $user_role];
}

// PERBAIKAN: Fungsi helper dengan debugging
function log_anggota_activity($anggota_id, $activity_type, $description, $user_type = "pengurus")
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];
    
    error_log("DEBUG ANGGOTA LOG: Anggota ID = $anggota_id, Activity = $activity_type");
    
    return log_activity($user_id, $user_role, $activity_type, $description, 'anggota', $anggota_id);
}

function log_pembayaran_activity($pembayaran_id, $activity_type, $description, $user_type = "pengurus")
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];
    
    error_log("DEBUG PEMBAYARAN LOG: Pembayaran ID = $pembayaran_id, Activity = $activity_type");
    
    return log_activity($user_id, $user_role, $activity_type, $description, 'pembayaran', $pembayaran_id);
}

// Function untuk log login/logout
function log_auth_activity($user_id, $user_type, $activity_type, $description)
{
    return log_activity($user_id, $user_type, $activity_type, $description, 'users', $user_id);
}

// Function untuk log perubahan status
function log_status_change($table_name, $record_id, $old_status, $new_status, $user_type = 'bendahara')
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
function log_create_activity($table_name, $record_id, $description, $user_type = 'bendahara')
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
function log_update_activity($table_name, $record_id, $description, $user_type = 'bendahara')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['id'], $user_type, 'update', $description, $table_name, $record_id);
    }
    return false;
}

// Di functions/history_log.php - perbaiki fungsi ini
function log_pengeluaran_activity($pengeluaran_id, $activity_type, $description, $user_type = "pengurus")
{
    error_log("=== LOG_PENGELUARAN_ACTIVITY CALLED ===");

    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    error_log("User ID from session: $user_id");
    error_log("User Role from session: $user_role");
    error_log("Pengeluaran ID: $pengeluaran_id");
    error_log("Activity Type: $activity_type");
    error_log("Description: $description");

    // Jika user_id = 0 (tidak ada session), gunakan system
    if ($user_id == 0) {
        error_log("WARNING: No user_id found, using system");
        $user_id = 0;
        $user_role = 'system';
    }

    $result = log_activity($user_id, $user_role, $activity_type, $description, 'pengeluaran', $pengeluaran_id);

    if ($result) {
        error_log("SUCCESS: Log created with ID: $result");
    } else {
        error_log("FAILED: Log creation failed");
    }

    return $result;
}
?>