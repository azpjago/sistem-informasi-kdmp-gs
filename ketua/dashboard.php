<!DOCTYPE html>
<html>
<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require 'koneksi/koneksi.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ketua') {
    header('Location: ../index.php');
    exit;
}
?>

<head>
    <title>Dashboard Ketua - Koperasi</title>
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
        <!-- SIDEBAR NAVIGATION KHUSUS KETUA -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h4>KDMPGS - KETUA</h4>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=dashboard_utama" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard Utama</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=data_anggota" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Data Anggota</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=laporan_keuangan" class="nav-link">
                        <i class="fas fa-chart-pie"></i>
                        <span>Laporan Keuangan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=pengeluaran" class="nav-link">
                        <i class="fas fa-money-bill-transfer"></i>
                        <span>App. Pengeluaran</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=monitoring_pemesanan" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>M. Pemesanan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=approval_pinjaman" class="nav-link">
                        <i class="fas fa-check-circle"></i>
                        <span>App. Pinjaman</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=laporan_strategis" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>Laporan Strategis</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=activity" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Activity</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=pengaturan_sistem" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <span>Pengaturan Sistem</span>
                    </a>
                </li>
                <li class="nav-item logout-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="user-name"><?php echo $_SESSION['username'] ?? 'Pengurus'; ?></div>
                <div class="user-role"><?php echo $_SESSION['role'] ?? 'Ketua'; ?></div>
            </div>
        </div>

        <!-- CONTENT AREA - akan di-load dari pages/ -->
        <div class="content-wrapper">
            <?php
            $page = $_GET['page'] ?? "dashboard_utama";
            $allowed_pages = [
                'dashboard_utama',
                'data_anggota',
                'laporan_keuangan',
                'pengeluaran',
                'activity',
                'monitoring_pemesanan',
                'approval_pinjaman',
                'laporan_strategis',
                'pengaturan_sistem'
            ];

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
