<?php
session_start();

// Cek role ketua
if ($_SESSION['role'] !== 'ketua') {
    header('Location: ../index.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// KUERI UNTUK DASHBOARD OVERVIEW
// Total Pemesanan
$total_pemesanan = $conn->query("SELECT COUNT(*) as total FROM pemesanan")->fetch_assoc()['total'];

// Pemesanan by Status
$status_counts = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM pemesanan 
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$status_breakdown = [];
foreach ($status_counts as $status) {
    $status_breakdown[$status['status']] = $status['count'];
}

// Omset Bulan Ini
$omset_bulan = $conn->query("
    SELECT COALESCE(SUM(pd.subtotal), 0) as omset 
    FROM pemesanan_detail pd 
    INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan 
    WHERE p.status = 'Terkirim' 
    AND MONTH(p.tanggal_pesan) = MONTH(CURRENT_DATE()) 
    AND YEAR(p.tanggal_pesan) = YEAR(CURRENT_DATE())
")->fetch_assoc()['omset'];

// Produk Terlaris
$produk_terlaris = $conn->query("
    SELECT pr.nama_produk, SUM(pd.jumlah) as total_terjual 
    FROM pemesanan_detail pd 
    INNER JOIN produk pr ON pd.id_produk = pr.id_produk 
    INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan 
    WHERE p.status = 'Terkirim' 
    GROUP BY pr.id_produk 
    ORDER BY total_terjual DESC 
    LIMIT 1
")->fetch_assoc();

// Data untuk Chart (Trend 7 hari terakhir)
// Data untuk Chart (Trend 7 hari terakhir) - FIXED
$chart_data = $conn->query("
    SELECT 
        dates.tanggal,
        COALESCE(COUNT(p.id_pemesanan), 0) as jumlah
    FROM (
        SELECT CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY as tanggal
        FROM 
            (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 
             UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) a
        CROSS JOIN 
            (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 
             UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) b
        WHERE (CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY) >= CURDATE() - INTERVAL 6 DAY
        ORDER BY tanggal
    ) dates
    LEFT JOIN pemesanan p ON DATE(p.tanggal_pesan) = dates.tanggal
    GROUP BY dates.tanggal
    ORDER BY dates.tanggal
")->fetch_all(MYSQLI_ASSOC);

// Data pemesanan untuk tabel
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.id_pemesanan LIKE ? OR a.nama LIKE ? OR pr.nama_produk LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.tanggal_pesan) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.tanggal_pesan) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$pemesanan_query = "
    SELECT 
        p.id_pemesanan,
        p.id_anggota,
        a.nama as nama_anggota,
        p.tanggal_pesan,
        p.status,
        p.metode,
        p.bank_tujuan,
        p.total_harga,
        GROUP_CONCAT(pr.nama_produk SEPARATOR ', ') as produk,
        COUNT(pd.id_produk) as jumlah_produk
    FROM pemesanan p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pemesanan_detail pd ON p.id_pemesanan = pd.id_pemesanan
    LEFT JOIN produk pr ON pd.id_produk = pr.id_produk
    $where_sql
    GROUP BY p.id_pemesanan
    ORDER BY p.tanggal_pesan DESC
";

$stmt = $conn->prepare($pemesanan_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pemesanan_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Pemesanan - KDMPGS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            background: white;
            border-radius: 8px;
            border-left: 4px solid #0d6efd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-waiting {
            border-left-color: #ffc107;
        }

        .card-processing {
            border-left-color: #0dcaf0;
        }

        .card-delivered {
            border-left-color: #198754;
        }

        .card-revenue {
            border-left-color: #6f42c1;
        }

        .card-bestseller {
            border-left-color: #fd7e14;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        .chart-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .table-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .filter-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-3">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h3>Monitoring Pemesanan</h3>
                <p class="text-muted mb-0">Pantau dan kelola semua aktivitas pemesanan anggota</p>
            </div>
            <div class="col-auto d-flex align-items-center">
                <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
            </div>
        </div>

<!-- Dashboard Stats - SIMPLE VERSION -->
<div class="row g-3 mb-4">
    <!-- Total Pemesanan -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h2 class="fw-bold text-primary mb-1"><?= $total_pemesanan ?></h2>
                <p class="text-muted small mb-0">Total Pemesanan</p>
            </div>
        </div>
    </div>

    <!-- Menunggu -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h2 class="fw-bold text-warning mb-1"><?= $status_breakdown['Menunggu'] ?? 0 ?></h2>
                <p class="text-muted small mb-0">Menunggu</p>
            </div>
        </div>
    </div>

    <!-- Diproses -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h2 class="fw-bold text-info mb-1"><?= $status_breakdown['Diproses'] ?? 0 ?></h2>
                <p class="text-muted small mb-0">Diproses</p>
            </div>
        </div>
    </div>

    <!-- Terkirim -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h2 class="fw-bold text-success mb-1"><?= $status_breakdown['Terkirim'] ?? 0 ?></h2>
                <p class="text-muted small mb-0">Terkirim</p>
            </div>
        </div>
    </div>

    <!-- Omset Bulan Ini -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h3 class="fw-bold text-dark mb-1">Rp <?= number_format($omset_bulan, 0, ',', '.') ?></h3>
                <p class="text-muted small mb-0">Omset Bulan Ini</p>
            </div>
        </div>
    </div>

    <!-- Produk Terlaris -->
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border-0 bg-light h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold text-dark mb-1">
                    <?=
                        $produk_terlaris['nama_produk']
                        ? (strlen($produk_terlaris['nama_produk']) > 8
                            ? substr($produk_terlaris['nama_produk'], 0, 8) . '...'
                            : $produk_terlaris['nama_produk'])
                        : '-'
                        ?>
                </h4>
                <p class="text-muted small mb-0">Produk Terlaris</p>
            </div>
        </div>
    </div>
</div>

        <!-- Charts Section -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="chart-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">Trend Pemesanan 7 Hari Terakhir</h6>
                        <div class="text-muted small">
                            <i class="fas fa-calendar me-1"></i>Last 7 days
                        </div>
                    </div>
                    <canvas id="trendChart" height="120"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">Distribusi Status</h6>
                        <div class="text-muted small">
                            <i class="fas fa-chart-pie me-1"></i>All orders
                        </div>
                    </div>
                    <canvas id="statusChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- Filter Section - FIXED -->
<div class="filter-card p-3 mb-3">
    <form method="GET" action="" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="monitoring_pemesanan">
        
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Pencarian</label>
            <input type="text" class="form-control form-control-sm" name="search" 
                   placeholder="No. Order, Nama, Produk..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?= $status_filter == 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="Diproses" <?= $status_filter == 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Terkirim" <?= $status_filter == 'Terkirim' ? 'selected' : '' ?>>Terkirim</option>
                        <option value="Dibatalkan" <?= $status_filter == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm me-1">
                        <i class="fas fa-search me-1"></i> Terapkan Filter
                    </button>
                    <a href="?page=monitoring_pemesanan" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-refresh me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        <div class="table-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold">Daftar Pemesanan</h6>
                <div class="d-flex align-items-center">
                    <span class="badge bg-light text-dark border me-2">
                        <i class="fas fa-database me-1"></i><?= $pemesanan_result->num_rows ?> data
                    </span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">
                            <i class="fas fa-cog me-1"></i>Aksi
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportToExcel()"><i
                                        class="fas fa-file-excel me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-print me-2"></i>Print</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="80">No. Order</th>
                            <th width="120">Tanggal</th>
                            <th>Pemesan</th>
                            <th>Produk</th>
                            <th width="80" class="text-center">Items</th>
                            <th width="120" class="text-end">Total</th>
                            <th width="100" class="text-center">Metode</th>
                            <th width="100" class="text-center">Status</th>
                            <th width="60" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pemesanan = $pemesanan_result->fetch_assoc()):
                            $status_color = [
                                'Menunggu' => 'warning',
                                'Diproses' => 'info',
                                'Terkirim' => 'success',
                                'Dibatalkan' => 'danger'
                            ][$pemesanan['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td class="fw-semibold text-primary">#<?= $pemesanan['id_pemesanan'] ?></td>
                                <td>
                                    <small
                                        class="text-muted"><?= date('d/m/Y', strtotime($pemesanan['tanggal_pesan'])) ?></small>
                                    <br>
                                    <small
                                        class="text-muted"><?= date('H:i', strtotime($pemesanan['tanggal_pesan'])) ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($pemesanan['nama_anggota']) ?></div>
                                    <small class="text-muted">ID: <?= $pemesanan['id_anggota'] ?></small>
                                </td>
                                <td>
                                    <span class="d-block text-truncate" style="max-width: 200px;"
                                        title="<?= htmlspecialchars($pemesanan['produk']) ?>">
                                        <?= $pemesanan['produk'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $pemesanan['jumlah_produk'] ?>
                                        item</span>
                                </td>
                                <td class="text-end fw-semibold">Rp
                                    <?= number_format($pemesanan['total_harga'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $pemesanan['metode'] ?></span>
                                    <?php if ($pemesanan['metode'] == 'transfer' && $pemesanan['bank_tujuan']): ?>
                                        <small class="d-block text-muted"><?= $pemesanan['bank_tujuan'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $status_color ?> status-badge">
                                        <?= $pemesanan['status'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary view-detail"
                                        data-id="<?= $pemesanan['id_pemesanan'] ?>" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if ($pemesanan_result->num_rows === 0): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p class="mb-0">Tidak ada data pemesanan</p>
                                        <small class="d-block">Coba ubah filter pencarian Anda</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pemesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content via AJAX -->
                </div>
            </div>
        </div>
    </div>
    <script>
        // Colors for charts
        const colors = {
            primary: '#0d6efd',
            warning: '#ffc107',
            info: '#0dcaf0',
            success: '#198754',
            purple: '#6f42c1',
            orange: '#fd7e14'
        };

        // Trend Chart
        const trendChart = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: [<?= implode(',', array_map(function ($item) {
                    return "'" . date('d M', strtotime($item['tanggal'])) . "'";
                }, $chart_data)) ?>],
                datasets: [{
                    label: 'Pemesanan',
                    data: [<?= implode(',', array_column($chart_data, 'jumlah')) ?>],
                    borderColor: colors.primary,
                    backgroundColor: colors.primary + '20',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // Status Chart  
        const statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Menunggu', 'Diproses', 'Terkirim', 'Dibatalkan'],
                datasets: [{
                    data: [
                        <?= $status_breakdown['Menunggu'] ?? 0 ?>,
                        <?= $status_breakdown['Diproses'] ?? 0 ?>,
                        <?= $status_breakdown['Terkirim'] ?? 0 ?>,
                        <?= $status_breakdown['Dibatalkan'] ?? 0 ?>
                    ],
                    backgroundColor: [colors.warning, colors.info, colors.success, '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                cutout: '60%'
            }
        });

        // Detail Modal
        document.querySelectorAll('.view-detail').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                fetch(`pages/ajax/get_detail_pemesanan.php?id=${id}`)
                    .then(r => r.text())
                    .then(html => {
                        document.getElementById('detailContent').innerHTML = html;
                        new bootstrap.Modal('#detailModal').show();
                    });
            });
        });

        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            window.open(`export_pemesanan_excel.php?${params}`, '_blank');
        }
    </script>
</body>

</html>

<?php $conn->close(); ?>