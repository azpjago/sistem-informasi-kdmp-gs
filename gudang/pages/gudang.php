<?php
// Include history log functions
require_once 'functions/history_log.php';

// PROSES UPDATE STATUS INVENTORY
if (isset($_POST['update_status'])) {
    $id_inventory = $conn->real_escape_string($_POST['id_inventory']);
    $status = $conn->real_escape_string($_POST['status']);

    // Ambil data lama untuk log
    $query_old = "SELECT nama_produk, status FROM inventory_ready WHERE id_inventory = '$id_inventory'";
    $result_old = $conn->query($query_old);
    $old_data = $result_old->fetch_assoc();

    $query = "UPDATE inventory_ready SET status = '$status', updated_at = NOW() WHERE id_inventory = '$id_inventory'";

    if ($conn->query($query)) {
        // Panggil fungsi history log
        log_inventory_status_change($id_inventory, $old_data['status'], $status, 'gudang');

        $_SESSION['success'] = "Status produk berhasil diubah!";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=gudang");
    exit;
}

// PROSES BUAT RENCANA PENJUALAN
if (isset($_POST['buat_rencana_penjualan'])) {
    $id_inventory = $conn->real_escape_string($_POST['id_inventory']);
    $jumlah = $conn->real_escape_string($_POST['jumlah_penjualan']);
    $tanggal_rencana = $conn->real_escape_string($_POST['tanggal_rencana']);
    $keterangan = $conn->real_escape_string($_POST['keterangan_penjualan'] ?? '');

    // Ambil data produk
    $query_produk = "SELECT * FROM inventory_ready WHERE id_inventory = '$id_inventory'";
    $result_produk = $conn->query($query_produk);

    if ($result_produk && $result_produk->num_rows > 0) {
        $produk = $result_produk->fetch_assoc();

        // Validasi jumlah
        if ($jumlah > $produk['jumlah_tersedia']) {
            $_SESSION['error'] = "Jumlah penjualan melebihi stok tersedia!";
            header("Location: dashboard.php?page=gudang");
            exit;
        }

        // Insert rencana penjualan
        $query_insert = "INSERT INTO rencana_penjualan (id_inventory, kode_produk, nama_produk, jumlah, tanggal_rencana, keterangan, created_by) 
                         VALUES ('$id_inventory', '{$produk['kode_produk']}', '{$produk['nama_produk']}', '$jumlah', '$tanggal_rencana', '$keterangan', '{$_SESSION['user_id']}')";

        if ($conn->query($query_insert)) {
            // Panggil fungsi history log
            $description = "Membuat rencana penjualan untuk produk '" . $produk['nama_produk'] . "' sebanyak " . $jumlah . " " . $produk['satuan_kecil'] . " pada tanggal " . $tanggal_rencana;
            log_sales_plan_activity($id_inventory, 'create', $description, 'gudang');

            $_SESSION['success'] = "Rencana penjualan berhasil dibuat!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Produk tidak ditemukan!";
    }

    header("Location: dashboard.php?page=gudang");
    exit;
}
?>
<style>
    :root {
        --primary: #2c3e50;
        --secondary: #7f8c8d;
        --success: #27ae60;
        --warning: #f39c12;
        --danger: #e74c3c;
        --info: #3498db;
        --light: #ecf0f1;
        --dark: #2c3e50;
    }

    /* STATS CARD FULL COLOR */
    .stats-card {
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        text-align: center;
        height: 100%;
        color: white;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
    }

    .stats-card.total {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .stats-card.available {
        background: linear-gradient(135deg, #27ae60, #219a52);
    }

    .stats-card.limited {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }

    .stats-card.out {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }

    .stats-icon {
        font-size: 24px;
        margin-bottom: 15px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }

    .stats-total {
        font-size: 32px;
        font-weight: bold;
        margin: 10px 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stats-label {
        font-size: 14px;
        font-weight: 500;
        opacity: 0.9;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* PRODUCT CARD FULL COLOR */
    .product-card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        height: 100%;
        border: none;
        overflow: hidden;
        background: white;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    /* Warna berbeda untuk setiap status produk */
    .product-card.available {
        border-top: 4px solid #27ae60;
        background: linear-gradient(to bottom, #ffffff, #f8fff9);
    }

    .product-card.low-stock {
        border-top: 4px solid #f39c12;
        background: linear-gradient(to bottom, #ffffff, #fffaf2);
    }

    .product-card.out-of-stock {
        border-top: 4px solid #e74c3c;
        background: linear-gradient(to bottom, #ffffff, #fff5f5);
    }

    .product-card.sold {
        border-top: 4px solid #7f8c8d;
        background: linear-gradient(to bottom, #ffffff, #f8f9fa);
    }

    .product-header {
        padding: 20px 20px 15px 20px;
        border-bottom: 1px solid #e9ecef;
        background: transparent;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .product-body {
        padding: 20px;
    }

    .product-footer {
        padding: 15px 20px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }

    .product-code {
        font-family: 'Courier New', monospace;
        background: rgba(52, 152, 219, 0.1);
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        color: #2c3e50;
        border: 1px solid rgba(52, 152, 219, 0.2);
        font-weight: 600;
    }

    .status-badge {
        font-size: 11px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .product-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 10px;
        color: #2c3e50;
        line-height: 1.3;
    }

    .product-unit {
        font-size: 18px !important;
        font-weight: 800;
        color: #3498db !important;
        background: rgba(52, 152, 219, 0.1);
        padding: 4px 8px;
        border-radius: 6px;
        min-width: 40px;
        text-align: center;
    }

    .product-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        padding: 8px 0;
        border-bottom: 1px solid #f8f9fa;
    }

    .product-detail:last-child {
        border-bottom: none;
    }

    .product-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .product-value {
        font-size: 14px;
        font-weight: 700;
        color: #2c3e50;
    }

    .batch-number {
        font-size: 12px;
        background: rgba(108, 117, 125, 0.1);
        padding: 6px 10px;
        border-radius: 6px;
        display: inline-block;
        color: #6c757d;
        border: 1px solid rgba(108, 117, 125, 0.2);
        margin-bottom: 15px;
        font-weight: 500;
    }

    .price-tag {
        font-weight: 800;
        color: #27ae60;
        font-size: 14px;
        background: rgba(39, 174, 96, 0.1);
        padding: 4px 8px;
        border-radius: 6px;
    }

    .stock-info {
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stock-badge {
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        transform: scale(1.1);
        border-color: currentColor;
    }

    .btn-outline-primary.action-btn:hover {
        background: #3498db;
        color: white;
    }

    .btn-outline-warning.action-btn:hover {
        background: #f39c12;
        color: white;
    }

    .btn-outline-success.action-btn:hover {
        background: #27ae60;
        color: white;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding-left: 45px;
        border-radius: 25px;
        height: 45px;
        font-size: 15px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }

    .search-box i {
        position: absolute;
        left: 18px;
        top: 13px;
        color: #6c757d;
        font-size: 16px;
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 3px solid #3498db;
        position: relative;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 80px;
        height: 3px;
        background: #27ae60;
    }

    .filter-btn {
        border-radius: 25px;
        padding: 10px 20px;
        background: white;
        border: 2px solid #e9ecef;
        color: #6c757d;
        height: 45px;
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        border-color: #3498db;
        color: #3498db;
    }

    /* Badge colors dengan variasi */
    .bg-success {
        background: linear-gradient(135deg, #27ae60, #219a52) !important;
    }

    .bg-warning {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
    }

    .bg-danger {
        background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
    }

    .bg-secondary {
        background: linear-gradient(135deg, #7f8c8d, #6c757d) !important;
    }

    /* Header styling */
    .header h2 {
        font-weight: 800;
        color: #2c3e50;
        font-size: 28px;
    }
</style>

<div class="container-fluid py-4">
    <div class="header d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">🎲 Gudang Barang</h2>
        <div class="d-flex align-items-center">
            <div class="search-box me-2">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Cari produk..." id="searchInput">
            </div>
        </div>
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Statistik Inventory -->
    <div class="row mb-5">
        <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card total">
            <div class="stats-icon">
                <i class="fas fa-cubes"></i>
            </div>
            <div class="stats-total">
                <?php
                $query_total = "SELECT COUNT(*) as total FROM inventory_ready";
                $result_total = $conn->query($query_total);
                $total = $result_total->fetch_assoc()['total'];
                echo $total;
                ?>
            </div>
            <div class="stats-label">Total Produk di Gudang</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card available">
            <div class="stats-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-total">
                <?php
                $query_available = "SELECT COUNT(*) as total FROM inventory_ready WHERE status = 'available' AND jumlah_tersedia > 0";
                $result_available = $conn->query($query_available);
                $available = $result_available->fetch_assoc()['total'] ?? 0;
                echo $available;
                ?>
            </div>
            <div class="stats-label">Produk Tersedia</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card limited">
            <div class="stats-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stats-total">
                <?php
                $query_limited = "SELECT COUNT(*) as total FROM inventory_ready WHERE jumlah_tersedia > 0 AND jumlah_tersedia <= 5";
                $result_limited = $conn->query($query_limited);
                $limited = $result_limited->fetch_assoc()['total'];
                echo $limited;
                ?>
            </div>
            <div class="stats-label">Stok Menipis</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card out">
            <div class="stats-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stats-total">
                <?php
                $query_out = "SELECT COUNT(*) as total FROM inventory_ready WHERE jumlah_tersedia = 0";
                $result_out = $conn->query($query_out);
                $out = $result_out->fetch_assoc()['total'];
                echo $out;
                ?>
            </div>
            <div class="stats-label">Stok Habis</div>
        </div>
    </div>
    </div>

    <!-- Daftar Produk -->
    <div class="section-title">📦 Daftar Produk Siap Jual</div>
    <div class="row" id="productGrid">
    <?php
    // Query data inventory
    $query = "SELECT * FROM inventory_ready ORDER BY updated_at DESC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Tentukan class berdasarkan stok
            $stock_class = '';
            if ($row['jumlah_tersedia'] == 0) {
                $stock_class = 'out-of-stock';
            } else if ($row['jumlah_tersedia'] <= 5) {
                $stock_class = 'low-stock';
            } else if ($row['status'] == 'sold') {
                $stock_class = 'sold';
            } else {
                $stock_class = 'available';
            }

            // Tentukan badge warna berdasarkan status
            $status_class = '';
            $status_text = '';
            switch ($row['status']) {
                case 'available':
                    $status_class = 'bg-success';
                    $status_text = 'Tersedia';
                    break;
                case 'sold':
                    $status_class = 'bg-secondary';
                    $status_text = 'Terjual';
                    break;
                case 'returned':
                    $status_class = 'bg-warning';
                    $status_text = 'Dikembalikan';
                    break;
                case 'damaged':
                    $status_class = 'bg-danger';
                    $status_text = 'Rusak';
                    break;
                default:
                    $status_class = 'bg-secondary';
                    $status_text = 'Unknown';
            }

            // Tentukan badge warna berdasarkan stok
            $stock_badge = '';
            $stock_text = '';
            if ($row['jumlah_tersedia'] == 0) {
                $stock_badge = 'bg-danger';
                $stock_text = 'HABIS';
            } else if ($row['jumlah_tersedia'] <= 5) {
                $stock_badge = 'bg-warning';
                $stock_text = 'RENDAH';
            } else {
                $stock_badge = 'bg-success';
                $stock_text = 'AMAN';
            }

            echo "
            <div class='col-xl-3 col-lg-4 col-md-6 mb-4 product-item'>
                <div class='product-card {$stock_class}'>
                    <div class='product-header'>
                        <span class='product-code'>{$row['kode_produk']}</span>
                        <span class='badge {$status_class} status-badge'>{$status_text}</span>
                    </div>
                    
                    <div class='product-body'>
                        <div class='d-flex justify-content-between align-items-start mb-3'>
                            <h6 class='product-title'>{$row['nama_produk']}</h6>
                            <span class='product-unit'>{$row['satuan_kecil']}</span>
                        </div>
                        
                        <div class='mb-3'>
                            <span class='batch-number'><i class='fas fa-barcode me-1'></i> {$row['no_batch']}</span>
                        </div>
                        
                        <div class='product-detail'>
                            <span class='product-label'>Stok Awal</span>
                            <span class='product-value'>{$row['jumlah_awal']}</span>
                        </div>
                        
                        <div class='product-detail'>
                            <span class='product-label'>Stok Tersedia</span>
                            <div class='stock-info'>
                                <span class='product-value'>{$row['jumlah_tersedia']}</span>
                                <span class='badge {$stock_badge} stock-badge'>{$stock_text}</span>
                            </div>
                        </div>
                        
                        <div class='product-detail'>
                            <span class='product-label'>Harga Satuan</span>
                            <span class='price-tag'>Rp " . number_format($row['harga_satuan'], 0, ',', '.') . "</span>
                        </div>
                    </div>
                    
                    <div class='product-footer'>
                        <button type='button' class='btn btn-sm btn-outline-primary action-btn' data-bs-toggle='modal' data-bs-target='#detailModal{$row['id_inventory']}' title='Lihat Detail'>
                            <i class='fas fa-eye'></i>
                        </button>
                        <button type='button' class='btn btn-sm btn-outline-warning action-btn' data-bs-toggle='modal' data-bs-target='#statusModal{$row['id_inventory']}' title='Ubah Status'>
                            <i class='fas fa-edit'></i>
                        </button>
                        <button type='button' class='btn btn-sm btn-outline-success action-btn' data-bs-toggle='modal' data-bs-target='#penjualanModal{$row['id_inventory']}' title='Buat Rencana Penjualan'>
                            <i class='fas fa-cart-plus'></i>
                        </button>
                    </div>
                </div>
            </div>";
        }
    } else {
        echo "<div class='col-12 text-center py-5'>
                <i class='fas fa-box-open fa-4x text-muted mb-3'></i>
                <h4 class='text-muted mb-2'>Tidak ada data inventory</h4>
                <p class='text-muted'>Belum ada produk yang siap dijual</p>
              </div>";
    }
    ?>
</div>
</div>

<script>
    $(document).ready(function () {
        // Pencarian produk
        $('#searchInput').on('keyup', function () {
            const searchText = $(this).val().toLowerCase();

            $('.product-item').each(function () {
                const productName = $(this).find('.product-title').text().toLowerCase();
                const productCode = $(this).find('.product-code').text().toLowerCase();
                const batchNumber = $(this).find('.batch-number').text().toLowerCase();

                if (productName.includes(searchText) || productCode.includes(searchText) || batchNumber.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Inisialisasi tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
