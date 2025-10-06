<?php
// FUNGSI UNTUK MENDAPATKAN DATA SALDO DARI get_saldo_data.php
function getSaldoData()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/get_saldo_data.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Set session cookie untuk auth
    $cookie = session_name() . '=' . session_id();
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            return $data;
        }
    }

    // Fallback: hitung manual jika API gagal
    return calculateSaldoManually();
}

// FALLBACK FUNCTION JIKA API TIDAK BERHASIL
function calculateSaldoManually()
{
    global $conn;

    // Data rekening
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // Simpanan Pokok & Wajib
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Simpanan Sukarela (Setor)
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Tarik Sukarela
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // Penjualan Sembako
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'cash'
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'transfer'
                AND p.bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Hibah
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'cash'
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Pengeluaran Approved
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = ?
        ");
        $stmt->bind_param("s", $nama_rekening);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // Simpan data rekening
        $rekening[] = [
            'nama_rekening' => $nama_rekening,
            'saldo_sekarang' => $saldo_rekening,
            'nomor_rekening' => getNomorRekening($nama_rekening),
            'jenis' => ($nama_rekening == 'Kas Tunai') ? 'kas' : 'bank'
        ];

        $saldo_utama += $saldo_rekening;
    }

    // Hitung komponen lainnya
    $result = $conn->query("SELECT COALESCE(SUM(saldo_total), 0) as total_simpanan FROM anggota WHERE status_keanggotaan = 'Aktif'");
    $total_simpanan = $result->fetch_assoc()['total_simpanan'];

    return [
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'rekening' => $rekening,
        'total_simpanan' => $total_simpanan,
        'last_update' => date('d/m/Y H:i:s')
    ];
}

function getNomorRekening($nama_rekening)
{
    $nomor_rekening = [
        'Kas Tunai' => '-',
        'Bank MANDIRI' => '1234567890',
        'Bank BRI' => '0987654321',
        'Bank BNI' => '55555555555'
    ];
    return $nomor_rekening[$nama_rekening] ?? '-';
}

// AMBIL DATA SALDO
$saldo_data = getSaldoData();

// EXTRACT DATA
$saldo_utama = $saldo_data['saldo_utama'];
$rekening_data = $saldo_data['rekening'];
$total_simpanan = $saldo_data['total_simpanan'];
$last_update = $saldo_data['last_update'];

// HITUNG TOTAL KAS & BANK
$total_kas_bank = 0;
foreach ($rekening_data as $rek) {
    $total_kas_bank += $rek['saldo_sekarang'];
}

// TOTAL ASET
$total_aset = $total_simpanan + $total_kas_bank;

// NET INCOME BULAN INI
$bulan_ini = date('Y-m');
$result_pendapatan = $conn->query("
    SELECT COALESCE(SUM(pd.subtotal), 0) as pendapatan_penjualan
    FROM pemesanan_detail pd 
    INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
    WHERE p.status = 'Terkirim' AND DATE_FORMAT(p.tanggal_pesan, '%Y-%m') = '$bulan_ini'
");
$pendapatan_penjualan = $result_pendapatan->fetch_assoc()['pendapatan_penjualan'];

$result_pengeluaran = $conn->query("
    SELECT COALESCE(SUM(jumlah), 0) as total_pengeluaran
    FROM pengeluaran 
    WHERE status = 'approved' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_ini'
");
$total_pengeluaran = $result_pengeluaran->fetch_assoc()['total_pengeluaran'];

$net_income = $pendapatan_penjualan - $total_pengeluaran;

// DATA UNTUK GRAFIK TREND 6 BULAN
$bulan_labels = [];
$data_simpanan = [];
$data_pendapatan = [];
$data_pengeluaran = [];

for ($i = 5; $i >= 0; $i--) {
    $bulan = date('Y-m', strtotime("-$i months"));
    $bulan_label = date('M Y', strtotime($bulan));
    $bulan_labels[] = $bulan_label;

    // Simpanan bulan tersebut
    $result_simpanan_bulan = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as simpanan_bulan
        FROM pembayaran 
        WHERE status_bayar = 'Lunas' AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan'
        AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
    ");
    $data_simpanan[] = $result_simpanan_bulan->fetch_assoc()['simpanan_bulan'];

    // Pendapatan bulan tersebut
    $result_pendapatan_bulan = $conn->query("
        SELECT COALESCE(SUM(pd.subtotal), 0) as pendapatan_bulan
        FROM pemesanan_detail pd 
        INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
        WHERE p.status = 'Terkirim' AND DATE_FORMAT(p.tanggal_pesan, '%Y-%m') = '$bulan'
    ");
    $data_pendapatan[] = $result_pendapatan_bulan->fetch_assoc()['pendapatan_bulan'];

    // Pengeluaran bulan tersebut
    $result_pengeluaran_bulan = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as pengeluaran_bulan
        FROM pengeluaran 
        WHERE status = 'approved' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'
    ");
    $data_pengeluaran[] = $result_pengeluaran_bulan->fetch_assoc()['pengeluaran_bulan'];
}

// TOP 5 ANGGOTA
$result_top_anggota = $conn->query("
    SELECT nama, no_anggota, saldo_total 
    FROM anggota 
    WHERE status_keanggotaan = 'Aktif'
    ORDER BY saldo_total DESC 
    LIMIT 5
");

// DATA PINJAMAN
$result_pinjaman = $conn->query("
    SELECT 
        COUNT(*) as total_pinjaman,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as pinjaman_aktif,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as pinjaman_ditolak,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as pinjaman_disetujui,
        COALESCE(SUM(jumlah_pinjaman), 0) as total_nilai_pinjaman
    FROM pinjaman
");
$data_pinjaman = $result_pinjaman->fetch_assoc();

// ALERT SYSTEM
$alert_pinjaman_telat = 0;
$saldo_kas_rendah = false;
$saldo_bank_rendah = false;

foreach ($rekening_data as $rek) {
    if ($rek['nama_rekening'] == 'Kas Tunai' && $rek['saldo_sekarang'] < 1000000) {
        $saldo_kas_rendah = true;
    }
    if ($rek['jenis'] == 'bank' && $rek['saldo_sekarang'] < 500000) {
        $saldo_bank_rendah = true;
    }
}
?>

<div class="container-fluid">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📈 Laporan Keuangan Komprehensif</h2>
        <div class="d-flex align-items-center">
            <small class="text-muted me-3">Last Update: <?= $last_update ?></small>
            <div class="btn-group">
                <button class="btn btn-outline-primary active" data-period="month">Bulan Ini</button>
                <button class="btn btn-outline-primary" data-period="quarter">Triwulan</button>
                <button class="btn btn-outline-primary" data-period="year">Tahun</button>
                <button class="btn btn-outline-primary" data-period="custom">Custom</button>
            </div>
        </div>
    </div>

    <!-- ALERT SYSTEM -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert-container">
                <?php if ($alert_pinjaman_telat > 0): ?>
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Peringatan:</strong> Terdapat <?= $alert_pinjaman_telat ?> pinjaman yang terlambat bayar
                    </div>
                <?php endif; ?>

                <?php if ($saldo_kas_rendah): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        <strong>Perhatian:</strong> Saldo Kas Tunai rendah
                    </div>
                <?php endif; ?>

                <?php if ($saldo_bank_rendah): ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-university me-2"></i>
                        <strong>Info:</strong> Saldo bank perlu diperhatikan
                    </div>
                <?php endif; ?>

                <?php if (isset($saldo_data['selisih']) && abs($saldo_data['selisih']) > 10000): ?>
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="fas fa-calculator me-2"></i>
                        <strong>Perhatian:</strong> Terdapat selisih Rp
                        <?= number_format($saldo_data['selisih'], 0, ',', '.') ?> pada perhitungan saldo
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DASHBOARD METRICS -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">TOTAL ASET</h6>
                            <h3 class="text-primary">Rp <?= number_format($total_aset, 0, ',', '.') ?></h3>
                            <small class="text-muted">Simpanan: Rp
                                <?= number_format($total_simpanan, 0, ',', '.') ?></small>
                        </div>
                        <div class="metric-icon bg-primary">
                            <i class="fas fa-landmark"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">TOTAL SIMPANAN</h6>
                            <h3 class="text-success">Rp <?= number_format($total_simpanan, 0, ',', '.') ?></h3>
                            <small
                                class="text-muted"><?= $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status_keanggotaan = 'Aktif'")->fetch_assoc()['total'] ?>
                                anggota aktif</small>
                        </div>
                        <div class="metric-icon bg-success">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">KAS & BANK</h6>
                            <h3 class="text-info">Rp <?= number_format($total_kas_bank, 0, ',', '.') ?></h3>
                            <small class="text-muted">Dana operasional tersedia</small>
                        </div>
                        <div class="metric-icon bg-info">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card metric-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">NET INCOME</h6>
                            <h3 class="<?= $net_income >= 0 ? 'text-success' : 'text-danger' ?>">
                                Rp <?= number_format($net_income, 0, ',', '.') ?>
                            </h3>
                            <small class="text-muted">Bulan <?= date('F Y') ?></small>
                        </div>
                        <div class="metric-icon <?= $net_income >= 0 ? 'bg-success' : 'bg-danger' ?>">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BREAKDOWN SALDO -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-coins me-2"></i>Breakdown Saldo per Rekening
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($rekening_data as $rek): ?>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="me-3">
                                        <i
                                            class="fas fa-<?= $rek['jenis'] == 'kas' ? 'money-bill-wave' : 'university' ?> fa-2x text-<?= $rek['saldo_sekarang'] > 0 ? 'success' : 'danger' ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= $rek['nama_rekening'] ?></h6>
                                        <small class="text-muted"><?= $rek['nomor_rekening'] ?></small>
                                        <div class="mt-1">
                                            <strong class="text-<?= $rek['saldo_sekarang'] > 0 ? 'success' : 'danger' ?>">
                                                Rp <?= number_format($rek['saldo_sekarang'], 0, ',', '.') ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART & ANALYTICS ROW -->
    <div class="row mb-4">
        <!-- TREND CHART -->
        <div class="col-xl-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Trend Keuangan 6 Bulan Terakhir
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- QUICK STATS -->
        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tachometer-alt me-2"></i>Kinerja Keuangan
                    </h5>
                </div>
                <div class="card-body">
                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Simpanan</span>
                            <strong>Rp <?= number_format($total_simpanan, 0, ',', '.') ?></strong>
                        </div>
                    </div>

                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Kas & Bank</span>
                            <strong>Rp <?= number_format($total_kas_bank, 0, ',', '.') ?></strong>
                        </div>
                    </div>

                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Pinjaman Aktif</span>
                            <strong><?= $data_pinjaman['pinjaman_aktif'] ?> pinjaman</strong>
                        </div>
                    </div>

                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Net Income</span>
                            <strong class="<?= $net_income >= 0 ? 'text-success' : 'text-danger' ?>">
                                Rp <?= number_format($net_income, 0, ',', '.') ?>
                            </strong>
                        </div>
                    </div>

                    <hr>

                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Anggota</span>
                            <strong><?= $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status_keanggotaan = 'Aktif'")->fetch_assoc()['total'] ?>
                                orang</strong>
                        </div>
                    </div>

                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Penjualan Bulan Ini</span>
                            <strong>Rp <?= number_format($pendapatan_penjualan, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILED DATA ROW -->
    <div class="row">
        <!-- TOP 5 ANGGOTA -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-trophy me-2"></i>Top 5 Anggota
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $rank = 1;
                    while ($anggota = $result_top_anggota->fetch_assoc()):
                        $badge_color = $rank == 1 ? 'bg-warning' : ($rank == 2 ? 'bg-secondary' : ($rank == 3 ? 'bg-danger' : 'bg-light text-dark'));
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <span class="badge <?= $badge_color ?> me-2"><?= $rank++ ?></span>
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($anggota['nama']) ?></h6>
                                    <small class="text-muted"><?= $anggota['no_anggota'] ?></small>
                                </div>
                            </div>
                            <span class="badge bg-primary">Rp
                                <?= number_format($anggota['saldo_total'], 0, ',', '.') ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- PINJAMAN SUMMARY -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-hand-holding-usd me-2"></i>Summary Pinjaman
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="text-primary"><?= $data_pinjaman['total_pinjaman'] ?></h4>
                            <small class="text-muted">Total Pinjaman</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-success"><?= $data_pinjaman['pinjaman_aktif'] ?></h4>
                            <small class="text-muted">Aktif</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-warning"><?= $data_pinjaman['pinjaman_disetujui'] ?></h4>
                            <small class="text-muted">Disetujui</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-danger"><?= $data_pinjaman['pinjaman_ditolak'] ?></h4>
                            <small class="text-muted">Ditolak</small>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Total Nilai: Rp
                            <?= number_format($data_pinjaman['total_nilai_pinjaman'], 0, ',', '.') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERT SYSTEM -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell me-2"></i>System Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($rekening_data as $rek): ?>
                        <div class="alert-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="alert-icon <?= $rek['saldo_sekarang'] > 0 ? 'bg-success' : 'bg-danger' ?> me-3">
                                    <i class="fas fa-<?= $rek['jenis'] == 'kas' ? 'money-bill-wave' : 'university' ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?= $rek['nama_rekening'] ?></h6>
                                    <small class="text-muted">
                                        Rp <?= number_format($rek['saldo_sekarang'], 0, ',', '.') ?> -
                                        <span class="<?= $rek['saldo_sekarang'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $rek['saldo_sekarang'] > 0 ? 'Aman' : 'Perhatian' ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILED REPORTS SECTION -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#simpanan"
                                type="button">
                                <i class="fas fa-piggy-bank me-2"></i>Simpanan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#kasbank" type="button">
                                <i class="fas fa-wallet me-2"></i>Kas & Bank
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pinjaman" type="button">
                                <i class="fas fa-hand-holding-usd me-2"></i>Pinjaman
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="reportTabsContent">
                        <!-- TAB SIMPANAN -->
                        <div class="tab-pane fade show active" id="simpanan">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Jenis Simpanan</th>
                                            <th class="text-end">Jumlah Anggota</th>
                                            <th class="text-end">Total Nilai</th>
                                            <th class="text-end">Rata-rata</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $result_breakdown = $conn->query("
                                            SELECT 
                                                'Pokok' as jenis,
                                                COUNT(*) as jumlah_anggota,
                                                SUM(simpanan_pokok) as total,
                                                AVG(simpanan_pokok) as rata_rata
                                            FROM anggota 
                                            WHERE status_keanggotaan = 'Aktif'
                                            UNION ALL
                                            SELECT 
                                                'Wajib' as jenis,
                                                COUNT(*) as jumlah_anggota,
                                                SUM(simpanan_wajib) as total,
                                                AVG(simpanan_wajib) as rata_rata
                                            FROM anggota 
                                            WHERE status_keanggotaan = 'Aktif'
                                            UNION ALL
                                            SELECT 
                                                'Sukarela' as jenis,
                                                COUNT(*) as jumlah_anggota,
                                                SUM(saldo_sukarela) as total,
                                                AVG(saldo_sukarela) as rata_rata
                                            FROM anggota 
                                            WHERE status_keanggotaan = 'Aktif'
                                        ");

                                        while ($row = $result_breakdown->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td>Simpanan <?= $row['jenis'] ?></td>
                                                <td class="text-end"><?= number_format($row['jumlah_anggota']) ?></td>
                                                <td class="text-end">Rp <?= number_format($row['total'], 0, ',', '.') ?>
                                                </td>
                                                <td class="text-end">Rp <?= number_format($row['rata_rata'], 0, ',', '.') ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB KAS & BANK -->
                        <div class="tab-pane fade" id="kasbank">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sumber Dana</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-end">% dari Total</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_semua = $total_kas_bank;

                                        foreach ($rekening_data as $rek):
                                            $persentase = $total_semua > 0 ? ($rek['saldo_sekarang'] / $total_semua) * 100 : 0;
                                            $status = $rek['saldo_sekarang'] > 0 ? 'Aman' : 'Kosong';
                                            ?>
                                            <tr>
                                                <td><?= $rek['nama_rekening'] ?></td>
                                                <td class="text-end">Rp
                                                    <?= number_format($rek['saldo_sekarang'], 0, ',', '.') ?>
                                                </td>
                                                <td class="text-end"><?= number_format($persentase, 1) ?>%</td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $rek['saldo_sekarang'] > 0 ? 'success' : 'danger' ?>">
                                                        <?= $status ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB PINJAMAN -->
                        <div class="tab-pane fade" id="pinjaman">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Nama Anggota</th>
                                            <th class="text-end">Jumlah Pinjaman</th>
                                            <th class="text-end">Tenor</th>
                                            <th>Status</th>
                                            <th class="text-end">Total Bayar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $result_detail_pinjaman = $conn->query("
                                            SELECT p.*, a.nama, a.no_anggota
                                            FROM pinjaman p
                                            JOIN anggota a ON p.id_anggota = a.id
                                            ORDER BY p.tanggal_pengajuan DESC
                                            LIMIT 10
                                        ");

                                        while ($pinjaman = $result_detail_pinjaman->fetch_assoc()):
                                            $status_badge = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'active' => 'info',
                                                'lunas' => 'primary'
                                            ][$pinjaman['status']] ?? 'secondary';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div><?= htmlspecialchars($pinjaman['nama']) ?></div>
                                                    <small class="text-muted"><?= $pinjaman['no_anggota'] ?></small>
                                                </td>
                                                <td class="text-end">Rp
                                                    <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?>
                                                </td>
                                                <td class="text-end"><?= $pinjaman['tenor_bulan'] ?> bln</td>
                                                <td>
                                                    <span class="badge bg-<?= $status_badge ?>">
                                                        <?= ucfirst($pinjaman['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">Rp
                                                    <?= number_format($pinjaman['total_pengembalian'], 0, ',', '.') ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

// Chart JS
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Trend Chart
const ctx = document.getElementById('trendChart').getContext('2d');
let trendChart;

// Initialize chart
function initializeChart() {
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($bulan_labels) ?>,
            datasets: [
                {
                    label: 'Simpanan',
                    data: <?= json_encode($data_simpanan) ?>,
                    borderColor: '#2E86AB',
                    backgroundColor: 'rgba(46, 134, 171, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Pendapatan',
                    data: <?= json_encode($data_pendapatan) ?>,
                    borderColor: '#27AE60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Pengeluaran',
                    data: <?= json_encode($data_pengeluaran) ?>,
                    borderColor: '#E74C3C',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
}

// Fungsi yang hilang - UPDATE CHARTS
// Ganti function updateCharts dengan yang REAL
function updateCharts(startDate, endDate) {
    showLoadingMessage();
    
    // Kirim request ke server untuk data periode tertentu
    fetch(`pages/proses/get_laporan_data.php?start_date=${startDate.toISOString().split('T')[0]}&end_date=${endDate.toISOString().split('T')[0]}`)
        .then(response => response.json())
        .then(data => {
            // Update chart dengan data baru
            trendChart.data.labels = data.bulan_labels;
            trendChart.data.datasets[0].data = data.data_simpanan;
            trendChart.data.datasets[1].data = data.data_pendapatan;
            trendChart.data.datasets[2].data = data.data_pengeluaran;
            trendChart.update();
            
            hideLoadingMessage();
        })
        .catch(error => {
            console.error('Error:', error);
            hideLoadingMessage();
            alert('Gagal memuat data periode tersebut');
        });
}
// Fungsi untuk update tables
function updateTables(startDate, endDate) {
    console.log('Updating tables for period:', startDate, endDate);
    // Implementasi update tabel akan ditambahkan kemudian
}

// Fungsi untuk custom date picker
function showCustomDatePicker() {
    const startDate = prompt('Masukkan Tanggal Mulai (YYYY-MM-DD):', '<?= date('Y-m-01') ?>');
    const endDate = prompt('Masukkan Tanggal Akhir (YYYY-MM-DD):', '<?= date('Y-m-d') ?>');
    
    if (startDate && endDate) {
        // Validasi tanggal
        if (new Date(startDate) > new Date(endDate)) {
            alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
            return;
        }
        
        // Update charts dengan periode custom
        updateCharts(new Date(startDate), new Date(endDate));
        
        // Update button text untuk menunjukkan periode custom
        const customBtn = document.querySelector('[data-period="custom"]');
        customBtn.innerHTML = `Custom (${formatDate(startDate)} - ${formatDate(endDate)})`;
    }
}

// Helper function untuk format tanggal
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'short' 
    });
}

// Loading message functions
function showLoadingMessage() {
    let loadingDiv = document.getElementById('loading-message');
    if (!loadingDiv) {
        loadingDiv = document.createElement('div');
        loadingDiv.id = 'loading-message';
        loadingDiv.className = 'alert alert-info text-center';
        loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memuat data...';
        document.querySelector('.container-fluid').prepend(loadingDiv);
    }
}

function hideLoadingMessage() {
    const loadingDiv = document.getElementById('loading-message');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Period Filter - VERSI YANG DIPERBAIKI
document.querySelectorAll('[data-period]').forEach(btn => {
    btn.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('[data-period]').forEach(b => {
            b.classList.remove('active');
        });
        
        // Add active to clicked button
        this.classList.add('active');
        
        const period = this.getAttribute('data-period');
        
        // Filter data based on period
        filterDataByPeriod(period);
    });
});

// Fungsi filter data - VERSI YANG DIPERBAIKI
function filterDataByPeriod(period) {
    let startDate, endDate;
    
    const today = new Date();
    
    switch(period) {
        case 'month':
            // Bulan ini
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = today;
            break;
            
        case 'quarter':
            // Triwulan (3 bulan terakhir)
            startDate = new Date(today);
            startDate.setMonth(startDate.getMonth() - 3);
            endDate = today;
            break;
            
        case 'year':
            // Tahun ini
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = today;
            break;
            
        case 'custom':
            // Tampilkan date picker
            showCustomDatePicker();
            return;
            
        default:
            // Default ke bulan ini
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = today;
    }
    
    console.log(`Filtering data: ${period}`, startDate, endDate);
    
    // Update charts and tables dengan data filtered
    updateCharts(startDate, endDate);
    updateTables(startDate, endDate);
}

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
});

// Auto refresh setiap 5 menit
setTimeout(() => {
    location.reload();
}, 300000); // 5 menit

// CSS untuk loading dan lainnya
</script>

<style>
.metric-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

/* PERBAIKAN TAB JUDUL */
.nav-tabs .nav-link {
    border: none;
    color: #6c757d !important;
    font-weight: 500;
    background-color: transparent;
}

.nav-tabs .nav-link.active {
    color: #2E86AB !important;
    border-bottom: 3px solid #2E86AB !important;
    background: none;
}

.nav-tabs .nav-link:hover {
    color: #2E86AB !important;
    background-color: rgba(46, 134, 171, 0.1) !important;
}

.alert-container .alert {
    border: none;
    border-radius: 8px;
    margin-bottom: 10px;
}
.performance-item {
    border-bottom: 1px solid #eee;
    padding-bottom: 1rem;
}
.performance-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.alert-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
</style>