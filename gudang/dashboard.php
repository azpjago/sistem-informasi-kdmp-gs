<!DOCTYPE html>
<html>

<head>
    <title>Dashboard Gudang</title>
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
                <h4>KDMPGS</h4>
            </div>

            <ul class="nav flex-column">
                <li class="nav-item"><a href="?page=dashboard_utama" class="nav-link"><i class="fas fa-home"></i>
                        <span>Home</span></a></li>
                <li class="nav-item"><a href="?page=purchase_order" class="nav-link"><i class="fas fa-phone-square"></i>
                        <span>Purchase Order</span></a></li>
                <li class="nav-item"><a href="?page=barang_masuk" class="nav-link"><i class="fas fa-arrow-down"></i>
                        <span>Barang Masuk</span></a></li>
                <li class="nav-item"><a href="?page=gudang" class="nav-link"><i class="fas fa-boxes"></i>
                        <span>Inventory</span></a></li>
                <li class="nav-item"><a href="?page=produk" class="nav-link"><i class="fas fa-shopping-cart"></i>
                        <span>Produk</span></a></li>
                <li class="nav-item"><a href="?page=barang_reject" class="nav-link"><i class="fas fa-times-circle"></i>
                        <span>Barang Reject</span></a></li>
                <li class="nav-item"><a href="?page=stock_opname" class="nav-link"><i class="fas fa-exclamation-triangle"></i>
                        <span>Stock Opname</span></a></li>
                <li class="nav-item"><a href="?page=supplier" class="nav-link"><i class="fas fa-industry"></i>
                        <span>Supplier</span></a></li>
                <li class="nav-item logout-item"><a href="../logout.php" class="nav-link"><i
                            class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>

            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-name"><?php echo $_SESSION['nama'] ?? 'Pengurus'; ?></div>
                <div class="user-role"><?php echo $_SESSION['role'] ?? 'Gudang'; ?></div>
            </div>
        </div>

        <!-- CONTENT AREA - akan di-load dari pages -->
        <div class="content-wrapper">
            <?php
            $page = $_GET['page'] ?? "dashboard_utama";
            $allowed_pages = [
                'dashboard_utama',
                'supplier',
                'barang_masuk',
                'gudang',
                'produk',
                'barang_reject',
                'stock_opname',
                'purchase_order',
                'supplier_produk'
            ];

            if (in_array($page, $allowed_pages) && file_exists("pages/" . $page . ".php")) {
                include "pages/" . $page . ".php";
            } else {
                echo "Halaman tidak ditemukan.";
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