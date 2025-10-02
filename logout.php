<?php
// Selalu mulai sesi di awal
session_start();

// 1. Hapus semua variabel sesi
$_SESSION = array();

// 2. Hancurkan sesi secara total
session_destroy();

// 3. Arahkan pengguna kembali ke halaman login (index.php)
header("Location: index.php");

// 4. Pastikan tidak ada kode lain yang berjalan setelah redirect
exit;
?>