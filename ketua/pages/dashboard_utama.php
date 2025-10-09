<?php
// Query untuk statistics
$result = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status_keanggotaan = 'Aktif'");
$total_anggota = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT SUM(saldo_total) as total FROM anggota");
$total_simpanan = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as total FROM pemesanan WHERE MONTH(tanggal_pesan) = MONTH(CURRENT_DATE())");
$pemesanan_bulan_ini = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM pinjaman WHERE status = 'Menunggu'");
$pending_approval = $result->fetch_assoc()['total'];

// Data untuk chart (contoh sederhana)
$result = $conn->query("SELECT COUNT(*) as total FROM inventory_ready WHERE jumlah_tersedia < 10 AND status = 'available'");
$stok_menipis = $result->fetch_assoc()['total'];

// Pendapatan bulan ini
$result = $conn->query("SELECT SUM(total_harga) as total FROM pemesanan WHERE MONTH(tanggal_pesan) = MONTH(CURRENT_DATE()) AND status IN ('Terkirim', 'Selesai')");
$pendapatan_bulan_ini = $result->fetch_assoc()['total'] ?? 0;
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-4">Dashboard Ketua 📊</h3>
        <div class="btn-toolbar mb-2 mb-md-0">
            <span class="text-muted me-3"><?php echo date('d F Y'); ?></span>
        </div>
    </div>

    <!-- Statistics Cards - Dipercantik -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Anggota</h6>
                            <h3 class="fw-bold text-primary mb-0"><?php echo $total_anggota; ?></h3>
                            <small class="text-muted">Anggota aktif</small>
                        </div>
                        <div class="statistic-icon bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-users fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Simpanan</h6>
                            <h3 class="fw-bold text-success mb-0">
                                Rp <?php
                                // KUERI PERBAIKAN: Hitung dari pembayaran, bukan field saldo_total
                                $simpanan_result = $conn->query("
                                    SELECT COALESCE(SUM(p.jumlah), 0) as total 
                                    FROM pembayaran p 
                                    INNER JOIN anggota a ON p.anggota_id = a.id 
                                    WHERE (p.status_bayar = 'Lunas' OR p.status = 'Lunas')
                                    AND p.jenis_transaksi = 'setor'
                                    AND p.jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela')
                                    AND a.status_keanggotaan = 'Aktif'
                                ");
                                echo number_format($simpanan_result->fetch_assoc()['total'] ?? 0, 0, ',', '.');
                                ?>
                            </h3>
                            <small class="text-muted">Saldo total anggota</small>
                        </div>
                        <div class="statistic-icon bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-piggy-bank fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Pemesanan Bulan Ini</h6>
                            <h3 class="fw-bold text-info mb-0"><?php echo $pemesanan_bulan_ini; ?></h3>
                            <small class="text-muted">Bulan <?php echo date('F Y'); ?></small>
                        </div>
                        <div class="statistic-icon bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-shopping-cart fa-lg text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Pending Approval</h6>
                            <h3 class="fw-bold text-warning mb-0"><?php echo $pending_approval; ?></h3>
                            <small class="text-muted">Menunggu persetujuan</small>
                        </div>
                        <div class="statistic-icon bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-clock fa-lg text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Pendapatan Bulan Ini</h6>
                            <h4 class="fw-bold text-success mb-0">Rp
                                <?php echo number_format($pendapatan_bulan_ini, 0, ',', '.'); ?></h4>
                            <small class="text-muted">Bulan <?php echo date('F Y'); ?></small>
                        </div>
                        <div class="statistic-icon bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-money-bill-wave fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Stok Menipis</h6>
                            <h4 class="fw-bold text-danger mb-0"><?php echo $stok_menipis; ?></h4>
                            <small class="text-muted">Perlu restock</small>
                        </div>
                        <div class="statistic-icon bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-exclamation-triangle fa-lg text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Produk Tersedia</h6>
                            <?php
                            $result = $conn->query("SELECT COUNT(*) as total FROM produk WHERE status = 'aktif'");
                            $total_produk = $result->fetch_assoc()['total'];
                            ?>
                            <h4 class="fw-bold text-primary mb-0"><?php echo $total_produk; ?></h4>
                            <small class="text-muted">Item aktif</small>
                        </div>
                        <div class="statistic-icon bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-boxes fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Additional Info -->
    <div class="row">
        <!-- Status Pesanan -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i>Status Pesanan</h6>
                </div>
                <div class="card-body">
                    <?php
                    $statuses = ['Menunggu', 'Disiapkan', 'Dalam Perjalanan', 'Terkirim', 'Selesai'];
                    foreach ($statuses as $status) {
                        $result = $conn->query("SELECT COUNT(*) as total FROM pemesanan WHERE status = '$status'");
                        $count = $result->fetch_assoc()['total'];
                        $percentage = $pemesanan_bulan_ini > 0 ? ($count / $pemesanan_bulan_ini) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small"><?php echo $status; ?></span>
                                <span class="small fw-bold"><?php echo $count; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar"
                                    style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Aktivitas Terbaru -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-info"></i>Aktivitas Terbaru</h6>
                </div>
                <div class="card-body">
                    <?php
                    $result = $conn->query("SELECT * FROM history_activity ORDER BY created_at DESC LIMIT 5");
                    while ($row = $result->fetch_assoc()) {
                        ?>
                        <div class="d-flex align-items-start mb-3">
                            <div class="activity-icon bg-light rounded-circle p-2 me-3">
                                <i class="fas fa-<?php
                                switch ($row['activity_type']) {
                                    case 'login':
                                        echo 'sign-in-alt text-success';
                                        break;
                                    case 'order_create':
                                        echo 'plus-circle text-primary';
                                        break;
                                    case 'product_update':
                                        echo 'edit text-warning';
                                        break;
                                    default:
                                        echo 'circle text-secondary';
                                }
                                ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?>
                                </div>
                                <div class="fw-medium"><?php echo $row['description']; ?></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-2">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-warning"></i>Aksi Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="?page=laporan_keuangan" class="btn btn-outline-primary w-100 h-100 py-3">
                                <i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i>
                                <span>Laporan Keuangan</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="?page=approval_pinjaman" class="btn btn-outline-warning w-100 h-100 py-3">
                                <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                                <span>Approval</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="?page=data_anggota" class="btn btn-outline-info w-100 h-100 py-3">
                                <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                <span>Data Anggota</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="?page=monitoring_pemesanan" class="btn btn-outline-success w-100 h-100 py-3">
                                <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                                <span>Monitoring</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $conn->close(); ?>