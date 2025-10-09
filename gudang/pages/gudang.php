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

    .stats-card {
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        text-align: center;
        height: 100%;
        border-left: 4px solid var(--primary);
    }

    .stats-card.total {
        border-left-color: #3498db;
    }

    .stats-card.available {
        border-left-color: #27ae60;
    }

    .stats-card.limited {
        border-left-color: #f39c12;
    }

    .stats-card.out {
        border-left-color: #e74c3c;
    }

    .stats-icon {
        font-size: 20px;
        margin-bottom: 10px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: white;
    }

    .stats-icon.total {
        background: #3498db;
    }

    .stats-icon.available {
        background: #27ae60;
    }

    .stats-icon.limited {
        background: #f39c12;
    }

    .stats-icon.out {
        background: #e74c3c;
    }

    .stats-total {
        font-size: 24px;
        font-weight: bold;
        margin: 5px 0;
    }

    .stats-label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .product-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease;
        height: 100%;
        border: 1px solid #e9ecef;
        overflow: hidden;
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .product-card.low-stock {
        border-left: 4px solid var(--warning);
    }

    .product-card.out-of-stock {
        border-left: 4px solid var(--danger);
    }

    .product-card.sold {
        border-left: 4px solid var(--secondary);
    }

    .product-card.available {
        border-left: 4px solid var(--success);
    }

    .product-header {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        background: #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .product-body {
        padding: 15px;
    }

    .product-footer {
        padding: 12px 15px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
    }

    .product-code {
        font-family: 'Courier New', monospace;
        background: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: var(--dark);
        border: 1px solid #e9ecef;
    }

    .status-badge {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: 500;
    }

    .product-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--dark);
    }

    .product-detail {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
    }

    .product-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 500;
    }

    .product-value {
        font-size: 13px;
        font-weight: 600;
        color: var(--dark);
    }

    .batch-number {
        font-size: 12px;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
        color: #6c757d;
        border: 1px solid #e9ecef;
        margin-bottom: 10px;
    }

    .price-tag {
        font-weight: bold;
        color: var(--success);
        font-size: 14px;
    }

    .stock-info {
        font-size: 13px;
        display: flex;
        align-items: center;
    }

    .stock-badge {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 500;
        margin-left: 8px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding-left: 40px;
        border-radius: 20px;
        height: 38px;
        font-size: 14px;
    }

    .search-box i {
        position: absolute;
        left: 15px;
        top: 10px;
        color: #6c757d;
        font-size: 14px;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--black);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .filter-btn {
        border-radius: 20px;
        padding: 8px 16px;
        background: white;
        border: 1px solid #ddd;
        color: #6c757d;
        height: 38px;
        font-size: 14px;
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
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card total">
                <div class="stats-icon total">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stats-total text-primary">
                    <?php
                    $query_total = "SELECT COUNT(*) as total FROM inventory_ready";
                    $result_total = $conn->query($query_total);
                    $total = $result_total->fetch_assoc()['total'];
                    echo $total;
                    ?>
                </div>
                <div class="stats-label">Total Produk</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card available">
                <div class="stats-icon available">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-total text-success">
                    <?php
                    $query_available = "SELECT SUM(jumlah_tersedia) as total FROM inventory_ready WHERE status = 'available'";
                    $result_available = $conn->query($query_available);
                    $available = $result_available->fetch_assoc()['total'] ?? 0;
                    echo $available;
                    ?>
                </div>
                <div class="stats-label">Stok Produk Tersedia</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card limited">
                <div class="stats-icon limited">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stats-total text-warning">
                    <?php
                    $query_limited = "SELECT COUNT(*) as total FROM inventory_ready WHERE jumlah_tersedia > 0 AND jumlah_tersedia <= 5";
                    $result_limited = $conn->query($query_limited);
                    $limited = $result_limited->fetch_assoc()['total'];
                    echo $limited;
                    ?>
                </div>
                <div class="stats-label">Stok Terbatas</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stats-card out">
                <div class="stats-icon out">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stats-total text-danger">
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
    <div class="section-title">Daftar Produk Siap Jual</div>
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
                    $stock_text = 'Habis';
                } else if ($row['jumlah_tersedia'] <= 5) {
                    $stock_badge = 'bg-warning';
                    $stock_text = 'Rendah';
                } else {
                    $stock_badge = 'bg-success';
                    $stock_text = 'Aman';
                }

                echo "
                <div class='col-xl-3 col-lg-4 col-md-6 mb-4 product-item'>
    <div class='product-card {$stock_class}'>
        <div class='product-header'>
            <span class='product-code'>{$row['kode_produk']}</span>
            <span class='badge {$status_class} status-badge'>{$status_text}</span>
        </div>
        
        <div class='product-body'>
            <div class='d-flex justify-content-between align-items-center mb-2'>
                <h6 class='product-title mb-0'>{$row['nama_produk']}</h6>
                <span class='product-unit text-uppercase fw-bold fs-5 text-primary'>{$row['satuan_kecil']}</span>
            </div>
            
            <div class='mb-3'>
                <span class='batch-number'><i class='fas fa-barcode me-1'></i> {$row['no_batch']}</span>
            </div>
            
            <div class='product-detail'>
                <span class='product-label'>Stok Awal:</span>
                <span class='product-value'>{$row['jumlah_awal']} {$row['satuan_kecil']}</span>
            </div>
            
            <div class='product-detail'>
                <span class='product-label'>Stok Tersedia:</span>
                <div class='stock-info'>
                    <span class='product-value'>{$row['jumlah_tersedia']} {$row['satuan_kecil']}</span>
                    <span class='badge {$stock_badge} stock-badge'>{$stock_text}</span>
                </div>
            </div>
            
            <div class='product-detail'>
                <span class='product-label'>Harga/Satuan Besar:</span>
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
</div>

<!-- Modal Detail -->
<div class='modal fade' id='detailModal{$row['id_inventory']}' tabindex='-1' aria-hidden='true'>
    <div class='modal-dialog'>
        <div class='modal-content'>
            <div class='modal-header'>
                <h5 class='modal-title'>Detail Produk</h5>
                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class='modal-body'>
                <div class='row'>
                    <div class='col-md-6'>
                        <p><strong>Kode Produk:</strong><br>{$row['kode_produk']}</p>
                        <p><strong>Nama Produk:</strong><br>{$row['nama_produk']}</p>
                        <p><strong>Satuan:</strong><br><span class='text-uppercase fw-bold'>{$row['satuan_kecil']}</span></p>
                        <p><strong>No. Batch:</strong><br>{$row['no_batch']}</p>
                    </div>
                    <div class='col-md-6'>
                        <p><strong>Stok Awal:</strong><br>{$row['jumlah_awal']} {$row['satuan_kecil']}</p>
                        <p><strong>Stok Tersedia:</strong><br>{$row['jumlah_tersedia']} {$row['satuan_kecil']}</p>
                        <p><strong>Harga Satuan:</strong><br>Rp " . number_format($row['harga_satuan'], 0, ',', '.') . "</p>
                    </div>
                </div>
                <p><strong>Status:</strong> <span class='badge {$status_class}'>" . ucfirst($row['status']) . "</span></p>
                <p><strong>Update Terakhir:</strong><br>" . date('d/m/Y H:i', strtotime($row['updated_at'])) . "</p>
            </div>
            <div class='modal-footer'>
                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Tutup</button>
            </div>
        </div>
    </div>
</div>
                
                <!-- Modal Ubah Status -->
                <div class='modal fade' id='statusModal{$row['id_inventory']}' tabindex='-1' aria-hidden='true'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Ubah Status Produk</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <form method='POST'>
                                <div class='modal-body'>
                                    <input type='hidden' name='id_inventory' value='{$row['id_inventory']}'>
                                    <div class='mb-3'>
                                        <label class='form-label'>Produk</label>
                                        <input type='text' class='form-control' value='{$row['nama_produk']}' readonly>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Status Saat Ini</label>
                                        <input type='text' class='form-control' value='" . ucfirst($row['status']) . "' readonly>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Status Baru</label>
                                        <select class='form-select' name='status' required>
                                            <option value='available'" . ($row['status'] == 'available' ? ' selected' : '') . ">Available</option>
                                            <option value='sold'" . ($row['status'] == 'sold' ? ' selected' : '') . ">Sold</option>
                                            <option value='returned'" . ($row['status'] == 'returned' ? ' selected' : '') . ">Returned</option>
                                            <option value='damaged'" . ($row['status'] == 'damaged' ? ' selected' : '') . ">Damaged</option>
                                        </select>
                                    </div>
                                </div>
                                <div class='modal-footer'>
                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Batal</button>
                                    <button type='submit' class='btn btn-primary' name='update_status'>Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Rencana Penjualan -->
                <div class='modal fade' id='penjualanModal{$row['id_inventory']}' tabindex='-1' aria-hidden='true'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Buat Rencana Penjualan</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <form method='POST'>
                                <input type='hidden' name='id_inventory' value='{$row['id_inventory']}'>
                                <div class='modal-body'>
                                    <div class='mb-3'>
                                        <label class='form-label'>Produk</label>
                                        <input type='text' class='form-control' value='{$row['nama_produk']}' readonly>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Stok Tersedia</label>
                                        <input type='text' class='form-control' value='{$row['jumlah_tersedia']} {$row['satuan_kecil']}' readonly>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Jumlah Penjualan</label>
                                        <input type='number' class='form-control' name='jumlah_penjualan' required min='1' max='{$row['jumlah_tersedia']}'>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Tanggal Rencana Penjualan</label>
                                        <input type='date' class='form-control' name='tanggal_rencana' required min='" . date('Y-m-d') . "'>
                                    </div>
                                    <div class='mb-3'>
                                        <label class='form-label'>Keterangan (Opsional)</label>
                                        <textarea class='form-control' name='keterangan_penjualan' rows='2'></textarea>
                                    </div>
                                </div>
                                <div class='modal-footer'>
                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Batal</button>
                                    <button type='submit' class='btn btn-primary' name='buat_rencana_penjualan'>Buat Rencana</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>";
            }
        } else {
            echo "<div class='col-12 text-center py-5'>
                    <i class='fas fa-box-open fa-3x text-muted mb-3'></i>
                    <h4 class='text-muted'>Tidak ada data inventory</h4>
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