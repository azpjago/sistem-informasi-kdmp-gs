<!DOCTYPE html>
<html>
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'usaha') {
    header('Location: ../index.php');
    exit;
}
?>

<head>
    <title>Dashboard Usaha</title>
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

</head>

<body>
    <div class="d-flex main-container">
        <!-- SIDEBAR NAVIGATION -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h4>KDMPGS - U</h4>
            </div>

            <ul class="nav flex-column">
                <li class="nav-item"><a href="?page=dashboard_utama" class="nav-link"><i class="fas fa-home"></i>
                        <span>Home</span></a></li>
                <li class="nav-item"><a href="?page=produk" class="nav-link"><i class="fas fa-plus-square"></i>
                        <span>Produk</span></a></li>
                <li class="nav-item"><a href="?page=monitoring" class="nav-link"><i class="fas fa-shopping-cart"></i>
                        <span>Monitoring</span></a></li>
                <li class="nav-item"><a href="?page=pengiriman" class="nav-link"><i class="fas fa-truck"></i>
                        <span>Pengiriman</span></a></li>
                <li class="nav-item"><a href="?page=selesai" class="nav-link"><i class="fas fa-history"></i>
                        <span>History Pesanan</span></a></li>
                <li class="nav-item"><a href="?page=kurir" class="nav-link"><i class="fas fa-users"></i>
                        <span>Kurir</span></a></li>
                <li class="nav-item"><a href="?page=pinjaman" class="nav-link"><i class="fas fa-edit"></i>
                        <span>Ajukan Pinjaman</span></a></li>
                <li class="nav-item"><a href="?page=pembayaran_cicilan" class="nav-link"><i class="fas fa-book"></i>
                        <span>Pembayaran Cicilan</span></a></li>
                <li class="nav-item"><a href="?page=laporan" class="nav-link"><i class="fas fa-file-text"></i>
                        <span>Laporan</span></a></li>
                <li class="nav-item"><a href="?page=broadcast_wa" class="nav-link"><i class="fas fa-whatsapp"></i>
                        <span>Broadcast WA</span></a></li>
                <li class="nav-item logout-item"><a href="../logout.php" class="nav-link"><i
                            class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>            
            </ul>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-name"><?php echo $_SESSION['username'] ?? 'Pengurus'; ?></div>
                <div class="user-role"><?php echo $_SESSION['role'] ?? 'Usaha'; ?></div>
            </div>
        </div>

        <!-- CONTENT AREA - akan di-load dari pages -->
        <div class="content-wrapper">
            <?php
            $page = $_GET['page'] ?? "dashboard_utama";
            $allowed_pages = ['dashboard_utama', 'monitoring', 'produk', 'history', 'pengiriman', 'laporan', 'kurir', 'selesai', 'pinjaman', 'pembayaran_cicilan', 'broadcast_wa'];

            if (in_array($page, $allowed_pages) && file_exists("pages/" . $page . ".php")) {
                include "pages/" . $page . ".php";
            } else {
                echo '
    <div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: 80vh;">
        <div class="text-center">
            <div class="error-code display-1 fw-bold text-muted mb-2">404</div>
            <h2 class="h3 text-muted mb-3">Halaman Tidak Ditemukan</h2>
            <p class="text-muted mb-4">Halaman yang Anda cari tidak tersedia dalam sistem.</p>
            <a href="dashboard.php?page=dashboard_utama" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Aplikasi
            </a>
        </div>
    </div>';
            }
            ?>
        </div>
    </div>

    <script>
        // Menandai menu aktif berdasarkan parameter URL
        $(document).ready(function () {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'dashboard_utama';

            $('.nav-item').removeClass('active');
            $(`.nav-item a[href="?page=${currentPage}"]`).parent().addClass('active');
        });
    </script>
</body>

</html>