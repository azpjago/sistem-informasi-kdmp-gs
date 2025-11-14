<?php
// 1. TOTAL PRODUK AKTIF
$total_produk = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as total FROM produk WHERE status = 'aktif'"
));

// 2. PESANAN HARI INI (tanggal sekarang)
$pesanan_hari_ini = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as total FROM pemesanan 
     WHERE DATE(tanggal_pesan) = CURDATE()
     AND status = 'Terkirim'"
));

// 3. PENDAPATAN BULAN INI (bulan dan tahun sekarang)
$pendapatan_bulan_ini = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(total_harga), 0) as total FROM pemesanan 
     WHERE MONTH(tanggal_pesan) = MONTH(CURDATE()) 
     AND YEAR(tanggal_pesan) = YEAR(CURDATE())
     AND status = 'Terkirim'"
));

// 4. STOK MENIPIS (jumlah <= 10, asumsi stok minimum = 10)
$stok_menipis = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as total FROM produk 
     WHERE jumlah <= 10 AND status = 'aktif' AND is_paket = 0"
));

// 5. DATA UNTUK GRAFIK 30 HARI TERAKHIR
$grafik_query = mysqli_query(
    $conn,
    "SELECT 
        DATE(tanggal_pesan) as tanggal,
        COUNT(*) as jumlah_pesanan,
        COALESCE(SUM(total_harga), 0) as total_pendapatan
     FROM pemesanan 
     WHERE tanggal_pesan >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY DATE(tanggal_pesan)
     ORDER BY tanggal"
);

// 6. STATUS PESANAN TERBARU
$status_pesanan = mysqli_query(
    $conn,
    "SELECT 
        p.id_pemesanan,
        a.nama as nama_anggota,
        p.tanggal_pesan,
        p.status,
        p.total_harga
     FROM pemesanan p
     JOIN anggota a ON p.id_anggota = a.id
     ORDER BY p.tanggal_pesan DESC 
     LIMIT 5"
);

// 7. DATA UNTUK GRAFIK STATUS PESANAN (pie chart)
$status_data = mysqli_query($conn, "SELECT status, COUNT(*) AS jumlah FROM pemesanan GROUP BY status");
$status_chart = [];
while ($row = mysqli_fetch_assoc($status_data)) {
    $status_chart[$row['status']] = $row['jumlah'];
}

// 8. PRODUK TERLARIS
$produk_terlaris = mysqli_query($conn, "
    SELECT p.nama_produk, SUM(dp.jumlah) as total_terjual
    FROM pemesanan_detail dp
    JOIN produk p ON dp.id_produk = p.id_produk
    JOIN pemesanan pm ON dp.id_pemesanan = pm.id_pemesanan
    WHERE pm.status = 'Terkirim'
    GROUP BY dp.id_produk, p.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");

// 9. STOK MENIPIS DETAIL - DIUBAH: gunakan jumlah bukan stok
$stok_menipis_detail = mysqli_query($conn, "
    SELECT nama_produk, jumlah_tersedia, satuan_kecil
    FROM inventory_ready 
    WHERE jumlah_tersedia <= 5 AND status = 'available'
    ORDER BY jumlah_tersedia ASC
    LIMIT 5
");

// Prepare data untuk grafik trend
$labels = [];
$data_jumlah = [];
$data_pendapatan = [];
mysqli_data_seek($grafik_query, 0);
while ($row = mysqli_fetch_assoc($grafik_query)) {
    $labels[] = date('d M', strtotime($row['tanggal']));
    $data_jumlah[] = $row['jumlah_pesanan'];
    $data_pendapatan[] = $row['total_pendapatan'];
}
?>

<h3 class="mb-4">📊 Dashboard Usaha</h3>

<!-- Statistik Utama -->
<div class="row mb-4">
    <!-- TOTAL PRODUK -->
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body text-center">
                <div class="fs-1 mb-2">📦</div>
                <h5 class="card-title">Total Produk</h5>
                <p class="card-text fs-4 fw-bold"><?= $total_produk['total'] ?? 0 ?></p>
                <small>Item aktif</small>
            </div>
        </div>
    </div>

    <!-- PESANAN HARI INI -->
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body text-center">
                <div class="fs-1 mb-2">🛒</div>
                <h5 class="card-title">Pesanan Hari Ini</h5>
                <p class="card-text fs-4 fw-bold"><?= $pesanan_hari_ini['total'] ?? 0 ?></p>
                <small>Pesanan tanggal <?= date('d/m/Y') ?></small>
            </div>
        </div>
    </div>

    <!-- PENDAPATAN BULAN INI -->
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body text-center">
                <div class="fs-1 mb-2">💰</div>
                <h5 class="card-title">Pendapatan Bulan Ini</h5>
                <p class="card-text fs-4 fw-bold">Rp
                    <?= number_format($pendapatan_bulan_ini['total'] ?? 0, 0, ',', '.') ?></p>
                <small>Bulan <?= date('F Y') ?></small>
            </div>
        </div>
    </div>

    <!-- STOK MENIPIS - DIUBAH: jumlah <= 10 -->
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body text-center">
                <div class="fs-1 mb-2">⚠️</div>
                <h5 class="card-title">Stok Menipis</h5>
                <p class="card-text fs-4 fw-bold"><?= $stok_menipis['total'] ?? 0 ?></p>
                <small>Kurang dari 10 item</small>
            </div>
        </div>
    </div>
</div>

<!-- Grafik dan Chart -->
<div class="row mb-4">
    <!-- TREND PENJUALAN -->
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-line me-2"></i>Trend Penjualan 30 Hari Terakhir
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- STATUS PESANAN PIE CHART & PRODUK TERLARIS -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-chart-pie me-2"></i>Status Pesanan
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <!-- STATUS PESANAN TERBARU -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="fas fa-list me-2"></i>Status Pesanan Terbaru
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($status_pesanan) > 0): ?>
                    <div class="list-group">
                        <?php while ($pesanan = mysqli_fetch_assoc($status_pesanan)): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong>#<?= $pesanan['id_pemesanan'] ?></strong>
                                    <span class="badge bg-<?=
                                        ($pesanan['status'] == 'Terkirim') ? 'success' :
                                        (($pesanan['status'] == 'Dikirim') ? 'warning' : 'secondary')
                                        ?>">
                                        <?= $pesanan['status'] ?>
                                    </span>
                                </div>
                                <small><?= $pesanan['nama_anggota'] ?></small>
                                <div class="text-end">
                                    <small>Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                            Belum ada pesanan
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="fas fa-star me-2"></i>5 Produk Terlaris
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if (mysqli_num_rows($produk_terlaris) > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($produk_terlaris)): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary me-2"><?= $no++ ?></span>
                                    <?= $row['nama_produk'] ?>
                                </div>
                                <span class="badge bg-success"><?= $row['total_terjual'] ?> terjual</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            Belum ada data penjualan
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STOK MENIPIS & QUICK ACTIONS - DIUBAH: tampilkan jumlah dan satuan -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-exclamation-triangle me-2"></i>Produk Stok Menipis
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if (mysqli_num_rows($stok_menipis_detail) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($stok_menipis_detail)): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= $row['nama_produk'] ?></strong>
                                    <br>
                                    <small class="text-muted">Sisa: <?= $row['jumlah_tersedia'] ?>         <?= $row['satuan_kecil'] ?></small>
                                </div>
                                <span class="badge bg-danger">
                                    Stok Menipis
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-success py-3">
                            ✅ Semua stok aman (di atas 10 item)
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-bolt me-2"></i>Aksi Cepat
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-flex">
                    <a href="dashboard.php?page=produk" class="btn btn-primary me-2">
                        <i class="fas fa-box me-1"></i>Produk
                    </a>
                    <a href="dashboard.php?page=monitoring" class="btn btn-success me-2">
                        <i class="fas fa-shopping-cart me-1"></i>Pesanan
                    </a>
                    <a href="dashboard.php?page=pengiriman" class="btn btn-warning me-2">
                        <i class="fas fa-truck me-1"></i>Tracking
                    </a>
                    <a href="dashboard.php?page=laporan" class="btn btn-info">
                        <i class="fas fa-chart-bar me-1"></i>Laporan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Grafik Status Pesanan (Pie Chart)
    const ctxPie = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_keys($status_chart)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($status_chart)); ?>,
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Grafik Trend Penjualan (Line Chart) dengan handling data kosong
const salesChartElement = document.getElementById('salesChart');

if (salesChartElement) {
    const ctxLine = salesChartElement.getContext('2d');
    
    const labels = <?php echo isset($labels) ? json_encode($labels) : '[]'; ?>;
            const dataJumlah = <?php echo isset($data_jumlah) ? json_encode($data_jumlah) : '[]'; ?>;

            // Cek jika data kosong
            if (dataJumlah.length === 0 || labels.length === 0) {
                salesChartElement.style.position = 'relative';
                salesChartElement.innerHTML = `
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #6c757d;">
                <i class="fas fa-chart-line fa-3x mb-2"></i>
                <p>Tidak ada data penjualan untuk ditampilkan</p>
            </div>
        `;
            } else {
                const salesChart = new Chart(ctxLine, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Jumlah Pesanan',
                            data: dataJumlah,
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderColor: '#0d6efd',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#0d6efd',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function (context) {
                                        return `Pesanan: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            }
        }
</script>
