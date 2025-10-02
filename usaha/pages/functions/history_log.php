<?php
function log_activity($user_id, $user_type, $activity_type, $description, $table_affected = null, $record_id = null)
{
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error) {
        // Jangan die() di dalam function, lebih baik return false atau throw exception
        error_log("Connection failed: " . $conn->connect_error);
        return false;
    }
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $query = "INSERT INTO history_activity 
              (user_id, user_type, activity_type, description, table_affected, record_id, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        $conn->close();
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'issssis', $user_id, $user_type, $activity_type, $description, $table_affected, $record_id, $user_agent);
    $result = mysqli_stmt_execute($stmt);
    $insert_id = mysqli_insert_id($conn);

    mysqli_stmt_close($stmt);
    $conn->close();

    return $result ? $insert_id : false;
}

// Helper functions untuk activity spesifik
function log_order_activity($order_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['id'])) {
        return log_activity($_SESSION['user_id'], $user_type, $activity_type, $description, 'pemesanan', $order_id);
    }
    return false;
}

function log_product_activity($product_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        return log_activity($_SESSION['user_id'], $user_type, $activity_type, $description, 'produk', $product_id);
    }
    return false;
}

function log_user_activity($user_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        return log_activity($_SESSION['user_id'], $user_type, $activity_type, $description, 'anggota', $user_id);
    }
    return false;
}

// Function khusus untuk log aktivitas pengiriman
function log_delivery_activity($delivery_id, $activity_type, $description, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        return log_activity($_SESSION['user_id'], $user_type, $activity_type, $description, 'pengiriman', $delivery_id);
    }
    return false;
}

// Function untuk log login/logout
function log_auth_activity($user_id, $user_type, $activity_type, $description)
{
    return log_activity($user_id, $user_type, $activity_type, $description, 'usaha', $user_id);
}

// Function untuk log perubahan status pesanan
function log_order_status_change($order_id, $old_status, $new_status, $user_type = 'pengurus')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        $description = "Mengubah status pesanan #$order_id dari $old_status menjadi $new_status";
        return log_activity($_SESSION['user_id'], $user_type, 'order_status_change', $description, 'pemesanan', $order_id);
    }
    return false;
}
?>