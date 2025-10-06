<?php
session_start();

// Include file history log
require_once 'functions/history_log.php';

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Pastikan hanya metode POST yang diproses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$username = $_POST['username'];
$password = $_POST['password'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (empty($username) || empty($password)) {
    // LOG: Attempt login dengan field kosong
    log_login_empty_fields();

    header("Location: index.php?error=empty");
    exit();
}

// Gunakan prepared statements untuk keamanan dari SQL Injection
$sql = "SELECT id, username, password, role FROM pengurus WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    // Verifikasi password yang di-hash
    if (password_verify($password, $user['password'])) {
        // Login sukses, simpan data ke session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_logged_in'] = true;

        // LOG: Login berhasil
        log_login_success($user['id'], $user['username'], $user['role']);

        // Tutup statement karena sudah tidak digunakan lagi
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        // Redirect berdasarkan role
        $role = $user['role'];
        switch ($role) {
            case 'bendahara':
                header("Location: bendahara/dashboard.php");
                break;
            case 'ketua':
                header("Location: ketua/dashboard.php");
                break;
            case 'usaha':
                // PENTING: Pastikan nama folder Anda adalah 'wk_bid_usaha' (menggunakan underscore)
                header("Location: usaha/dashboard.php");
                break;
            case 'sekretaris':
                header("Location: sekretaris/dashboard.php");
                break;
            case 'gudang':
                header("Location: gudang/dashboard.php");
                break;
            default:
                // Jika role tidak dikenal, arahkan ke halaman login
                header("Location: index.php");
                break;
        }
        exit(); // Hentikan skrip SETELAH redirect

    } else {
        // LOG: Login gagal - password salah
        log_login_failed_password($user['id'], $user['username'], $user['role']);
    }
} else {
    // LOG: Login gagal - username tidak ditemukan
    log_login_failed_username($username, $ip_address);
}

// Jika sampai di sini, artinya username tidak ditemukan atau password salah
// Tutup statement dan koneksi sebelum redirect ke halaman error
mysqli_stmt_close($stmt);
mysqli_close($conn);

header("Location: index.php?error=1");
exit();
?>