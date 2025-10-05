<?php
session_start();

// Include file history log
require_once 'functions/history_log.php';

// Log logout activity sebelum menghancurkan session
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role'])) {
    log_logout_activity($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
}

// Hancurkan semua data session
$_SESSION = array();

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: index.php");
exit();
?>