<?php
// Handle Export CSV - PASTIKAN INI DI AWAL SEBELUM OUTPUT APAPUN
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Pastikan tidak ada output sebelumnya dengan buffer output
    if (ob_get_length()) ob_clean();
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $kategori = $_GET['kategori'] ?? '';

    // Validasi format tanggal
    if (!DateTime::createFromFormat('Y-m-d', $start_date) || !DateTime::createFromFormat('Y-m-d', $end_date)) {
        die("Format tanggal tidak valid");
    }

    // Gunakan prepared statement untuk keamanan
    $query = "
        SELECT 
            p.nama_produk,
            p.kategori,
            SUM(dp.jumlah) as total_terjual,
            SUM(dp.subtotal) as total_pendapatan,
            MAX(pm.tanggal_pesan) as tanggal_terakhir
        FROM pemesanan_detail dp
        JOIN produk p ON dp.id_produk = p.id_produk
        JOIN pemesanan pm ON dp.id_pemesanan = pm.id_pemesanan
        WHERE DATE(pm.tanggal_pesan) BETWEEN ? AND ?
        " . ($kategori ? "AND p.kategori = ?" : "") . "
        GROUP BY dp.id_produk
        ORDER BY total_terjual DESC
    ";

    $stmt = $conn->prepare($query);
    
    if ($kategori) {
        $stmt->bind_param("sss", $start_date, $end_date, $kategori);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    $export_result = $stmt->get_result();

    if (!$export_result) {
        // Log error instead of showing to user in production
        error_log("Export CSV Error: " . $conn->error);
        die("Terjadi kesalahan saat mengekspor data. Silakan coba lagi.");
    }

    // HEADER CSV - pastikan tidak ada output sebelumnya
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output BOM untuk UTF-8
    echo "\xEF\xBB\xBF";

    // Buat output stream langsung ke browser
    $output = fopen('php://output', 'w');
    
    // Header laporan
    fputcsv($output, ['LAPORAN PENJUALAN'], ',');
    fputcsv($output, ['Periode:', $start_date . ' s/d ' . $end_date], ',');
    if ($kategori) {
        fputcsv($output, ['Kategori:', $kategori], ',');
    }
    fputcsv($output, [], ','); // Baris kosong
    
    // Header kolom
    fputcsv($output, [
        'No', 
        'Nama Produk', 
        'Kategori', 
        'Jumlah Terjual', 
        'Total Pendapatan', 
        'Tanggal Terakhir'
    ], ',');
    
    // Data
    $no = 1;
    while ($row = mysqli_fetch_assoc($export_result)) {
        fputcsv($output, [
            $no++,
            $row['nama_produk'],
            $row['kategori'],
            $row['total_terjual'],
            'Rp ' . number_format($row['total_pendapatan'], 0, ',', '.'),
            date('d/m/Y', strtotime($row['tanggal_terakhir']))
        ], ',');
    }
    
    fclose($output);
    $stmt->close();
    exit(); // KELUAR DARI SCRIPT SETELAH EXPORT CSV
}

// Filter tanggal
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_kategori = $_GET['kategori'] ?? '';

// Escape input untuk mencegah SQL injection
$start_date_esc = $conn->real_escape_string($start_date);
$end_date_esc = $conn->real_escape_string($end_date);
$filter_kategori_esc = $conn->real_escape_string($filter_kategori);

// Laporan Penjualan
$penjualan_query = "
    SELECT 
        DATE(tanggal_pesan) as tanggal,
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_penjualan
    FROM pemesanan 
    WHERE DATE(tanggal_pesan) BETWEEN '$start_date_esc' AND '$end_date_esc' AND status = 'Terkirim'
    GROUP BY DATE(tanggal_pesan)
    ORDER BY tanggal
";

// Produk Terlaris - PERBAIKAN: gunakan subtotal bukan harga
$produk_terlaris_query = "
    SELECT 
        p.nama_produk,
        p.kategori,
        SUM(dp.jumlah) as total_terjual,
        SUM(dp.subtotal) as total_pendapatan
    FROM pemesanan_detail dp
    JOIN produk p ON dp.id_produk = p.id_produk
    JOIN pemesanan pm ON dp.id_pemesanan = pm.id_pemesanan
    WHERE DATE(pm.tanggal_pesan) BETWEEN '$start_date_esc' AND '$end_date_esc'
    AND pm.status = 'Terkirim' -- FILTER STATUS DITAMBAHKAN
    " . ($filter_kategori ? "AND p.kategori = '$filter_kategori_esc'" : "") . "
    GROUP BY dp.id_produk
    ORDER BY total_terjual DESC
    LIMIT 10
";

// Kategori Performance - PERBAIKAN: gunakan subtotal bukan harga
$kategori_query = "
    SELECT 
        p.kategori,
        COUNT(dp.id_detail) as total_transaksi,
        SUM(dp.jumlah) as total_terjual,
        SUM(dp.subtotal) as total_pendapatan
    FROM pemesanan_detail dp
    JOIN produk p ON dp.id_produk = p.id_produk
    JOIN pemesanan pm ON dp.id_pemesanan = pm.id_pemesanan
    WHERE DATE(pm.tanggal_pesan) BETWEEN '$start_date_esc' AND '$end_date_esc'
    AND pm.status = 'Terkirim' -- FILTER STATUS DITAMBAHKAN
    GROUP BY p.kategori
    ORDER BY total_pendapatan DESC
";

// Execute queries
$penjualan = mysqli_query($conn, $penjualan_query);
$produk_terlaris = mysqli_query($conn, $produk_terlaris_query);
$kategori = mysqli_query($conn, $kategori_query);

// Total summary
$total_summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_penjualan,
        AVG(total_harga) as rata_rata_transaksi
    FROM pemesanan 
    WHERE DATE(tanggal_pesan) BETWEEN '$start_date_esc' AND '$end_date_esc' AND status = 'Terkirim'
"));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Usaka - Laporan Penjualan</title>
    <style>
        .card-header {
            font-weight: 600;
        }

        .progress {
            background-color: #e9ecef;
        }

        .table th {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <h3 class="mb-4">📊 Laporan Usaha</h3>
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-filter me-2"></i>Filter Laporan
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="page" value="laporan">

                    <div class="col-md-3">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control"
                            value="<?= htmlspecialchars($start_date) ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control"
                            value="<?= htmlspecialchars($end_date) ?>">
                    </div>

                    <div class="col-md-3">
                        <label>Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="">Semua Kategori</option>
                            <option value="Sembako" <?= $filter_kategori == 'Sembako' ? 'selected' : '' ?>>Sembako</option>
                            <option value="LPG" <?= $filter_kategori == 'LPG' ? 'selected' : '' ?>>LPG</option>
                            <option value="Pupuk" <?= $filter_kategori == 'Pupuk' ? 'selected' : '' ?>>Pupuk</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                            <a href="dashboard.php?page=laporan" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Pesanan</h5>
                        <p class="card-text fs-2 fw-bold"><?= $total_summary['total_pesanan'] ?? 0 ?></p>
                        <small>Periode <?= date('d M Y', strtotime($start_date)) ?> -
                            <?= date('d M Y', strtotime($end_date)) ?></small>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Penjualan</h5>
                        <p class="card-text fs-2 fw-bold">Rp
                            <?= number_format($total_summary['total_penjualan'] ?? 0, 0, ',', '.') ?>
                        </p>
                        <small>Pendapatan Kotor</small>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title">Rata-Rata Transaksi</h5>
                        <p class="card-text fs-2 fw-bold">Rp
                            <?= number_format($total_summary['rata_rata_transaksi'] ?? 0, 0, ',', '.') ?>
                        </p>
                        <small>Per Pesanan</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik Penjualan -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-line me-2"></i>Grafik Trend Penjualan
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>

        <!-- Produk Terlaris -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-trophy me-2"></i>10 Produk Terlaris
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Terjual</th>
                                <th>Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; ?>
                            <?php while ($row = mysqli_fetch_assoc($produk_terlaris)): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?= $rank++ ?></span></td>
                                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kategori']) ?></span>
                                    </td>
                                    <td><?= $row['total_terjual'] ?> pcs</td>
                                    <td>Rp <?= number_format($row['total_pendapatan'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (mysqli_num_rows($produk_terlaris) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Tidak ada data penjualan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Performance by Category -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="fas fa-chart-pie me-2"></i>Performance per Kategori
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Transaksi</th>
                                <th>Terjual</th>
                                <th>Pendapatan</th>
                                <th>% Contribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_pendapatan = $total_summary['total_penjualan'] ?? 1; // Avoid division by zero
                            while ($row = mysqli_fetch_assoc($kategori)):
                                $percentage = ($row['total_pendapatan'] / $total_pendapatan) * 100;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['kategori']) ?></strong></td>
                                    <td><?= $row['total_transaksi'] ?></td>
                                    <td><?= $row['total_terjual'] ?> pcs</td>
                                    <td>Rp <?= number_format($row['total_pendapatan'], 0, ',', '.') ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%;"
                                                aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (mysqli_num_rows($kategori) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Tidak ada data penjualan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export Button (CSV only) -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-download me-2"></i>Export Laporan
            </div>
            <div class="card-body text-center">
                <a href="dashboard.php?page=laporan&export=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&kategori=<?= $filter_kategori ?>"
                    class="btn btn-success">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Script Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare data for sales chart
        const salesData = {
            labels: [<?php
            mysqli_data_seek($penjualan, 0);
            $labels = [];
            $data = [];
            while ($row = mysqli_fetch_assoc($penjualan)) {
                $labels[] = "'" . date('d M', strtotime($row['tanggal'])) . "'";
                $data[] = $row['total_penjualan'];
            }
            echo implode(', ', $labels);
            ?>],
            data: [<?php echo implode(', ', $data); ?>]
        };

        // Sales Trend Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Penjualan Harian',
                    data: salesData.data,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderColor: '#0d6efd',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return 'Rp ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>