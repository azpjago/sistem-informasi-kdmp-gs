<?php
// ================== FILE: buku_besar.php ==================
// Menggabungkan fungsi dari buku_simpanan.php dan buku_simpanan_sukarela.php
// dengan sistem tab yang lebih menarik

// ================== KONEKSI DATABASE ==================
// Pastikan koneksi database sudah tersedia di sini
// Contoh: include 'koneksi.php';

// ================== CEK TAB AKTIF ==================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'wajib';

// ================== FILTER TAHUN (untuk kedua tab) ==================
$current_year = date('Y');
$current_month = date('n');
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;
$selected_month = isset($_GET['bulan']) ? intval($_GET['bulan']) : $current_month;
$years = range($current_year - 2, $current_year + 5);
?>

<style>
/* ================== DESAIN TAB YANG LEBIH MENARIK ================== */
.nav-tabs-custom {
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 25px;
    padding-left: 10px;
}

.nav-tabs-custom .nav-item {
    margin-bottom: -2px;
}

.nav-tabs-custom .nav-link {
    border: none;
    padding: 12px 25px;
    font-weight: 600;
    color: #ffffff;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
    margin-right: 5px;
    transition: all 0.3s ease;
    position: relative;
    border: 1px solid transparent;
    border-bottom: none;
}

.nav-tabs-custom .nav-link:hover {
    background-color: #e9ecef;
    color: #0d6efd;
    border-color: #dee2e6;
    border-bottom: none;
}

.nav-tabs-custom .nav-link.active {
    background-color: #0059ff;
    color: #0d6efd;
    border: 1px solid #dee2e6;
    border-bottom: 2px solid #ffffff;
    margin-bottom: -1px;
    box-shadow: 0 -3px 5px rgba(0,0,0,0.05);
}

.nav-tabs-custom .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 3px;
    background-color: #0d6efd;
    border-radius: 3px 3px 0 0;
}

.nav-tabs-custom .nav-link i {
    margin-right: 8px;
    font-size: 1.1em;
}

/* ================== DESAIN CARD UNTUK RINGKASAN ================== */
.stat-card {
    border-radius: 10px;
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card .card-body {
    padding: 20px;
}

.stat-card .card-title {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.stat-card .card-text {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 10px 0;
}

.stat-card .card-footer {
    background: rgba(255,255,255,0.1);
    border-top: 1px solid rgba(255,255,255,0.2);
    font-size: 0.85rem;
}

/* ================== DESAIN TABEL ================== */
.table-custom {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.table-custom thead th {
    background-color: #343a40;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    vertical-align: middle;
    border-bottom: none;
}

.table-custom tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

/* ================== DESAIN FILTER CARD ================== */
.filter-card {
    border-radius: 10px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.filter-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
    border-radius: 10px 10px 0 0 !important;
}
</style>

<div class="container-fluid mt-3">
    <!-- ================== TAB NAVIGASI YANG LEBIH MENARIK ================== -->
    <ul class="nav nav-tabs-custom nav-tabs">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'wajib' ? 'active' : '' ?>" href="?page=buku_besar&tab=wajib&tahun=<?= $selected_year ?>">
                <i class="fas fa-book"></i> Simpanan Wajib
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab == 'sukarela' ? 'active' : '' ?>" href="?page=buku_besar&tab=sukarela&tahun=<?= $selected_year ?>&bulan=<?= $selected_month ?>">
                <i class="fas fa-hand-holding-usd"></i> Simpanan Sukarela
            </a>
        </li>
    </ul>

    <?php if ($active_tab == 'wajib'): ?>
        <!-- ================== TAB SIMPANAN WAJIB ================== -->
        <?php
        // --- LOGIKA FILTER TAHUN ---
        // ================== BLOK RINGKASAN ==================
        $summary_data = [
            'Simpanan Wajib' => ['total' => 0, 'anggota' => 0],
            'Simpanan Pokok' => ['total' => 0, 'anggota' => 0],
            'Simpanan Sukarela' => ['total' => 0, 'anggota' => 0]
        ];

        /* =========================
           1️⃣ SIMPANAN WAJIB (bulan_periode)
           ========================= */
        $sql_wajib = "
            SELECT 
                SUM(jumlah) as total,
                COUNT(DISTINCT anggota_id) as jumlah_anggota
            FROM pembayaran
            WHERE jenis_simpanan = 'Simpanan Wajib'
            AND YEAR(bulan_periode) = ?
        ";
        $stmt_wajib = $conn->prepare($sql_wajib);
        $stmt_wajib->bind_param("i", $selected_year);
        $stmt_wajib->execute();
        $result_wajib = $stmt_wajib->get_result()->fetch_assoc();

        $summary_data['Simpanan Wajib']['total'] = $result_wajib['total'] ?? 0;
        $summary_data['Simpanan Wajib']['anggota'] = $result_wajib['jumlah_anggota'] ?? 0;

        $stmt_wajib->close();

        /* =========================
           2️⃣ SIMPANAN POKOK (bulan_periode)
           ========================= */
        $sql_pokok = "
            SELECT 
                SUM(jumlah) as total,
                COUNT(DISTINCT anggota_id) as jumlah_anggota
            FROM pembayaran
            WHERE jenis_simpanan = 'Simpanan Pokok'
            AND YEAR(bulan_periode) = ?
        ";
        $stmt_pokok = $conn->prepare($sql_pokok);
        $stmt_pokok->bind_param("i", $selected_year);
        $stmt_pokok->execute();
        $result_pokok = $stmt_pokok->get_result()->fetch_assoc();

        $summary_data['Simpanan Pokok']['total'] = $result_pokok['total'] ?? 0;
        $summary_data['Simpanan Pokok']['anggota'] = $result_pokok['jumlah_anggota'] ?? 0;

        $stmt_pokok->close();

        /* =========================
           3️⃣ SIMPANAN SUKARELA (tanggal_bayar)
           ========================= */
        $sql_sukarela_sum = "
            SELECT 
                SUM(jumlah) as total,
                COUNT(DISTINCT anggota_id) as jumlah_anggota
            FROM pembayaran
            WHERE jenis_simpanan = 'Simpanan Sukarela'
            AND YEAR(tanggal_bayar) = ?
        ";
        $stmt_sukarela_sum = $conn->prepare($sql_sukarela_sum);
        $stmt_sukarela_sum->bind_param("i", $selected_year);
        $stmt_sukarela_sum->execute();
        $result_sukarela_sum = $stmt_sukarela_sum->get_result()->fetch_assoc();

        $summary_data['Simpanan Sukarela']['total'] = $result_sukarela_sum['total'] ?? 0;
        $summary_data['Simpanan Sukarela']['anggota'] = $result_sukarela_sum['jumlah_anggota'] ?? 0;

        $stmt_sukarela_sum->close();

        // ================== AMBIL DATA ANGGOTA ==================
        $anggota_list = $conn->query("
            SELECT id, no_anggota, nama 
            FROM anggota 
            ORDER BY no_anggota ASC
        ")->fetch_all(MYSQLI_ASSOC);

        // ================== DATA SIMPANAN WAJIB PER BULAN ==================
        $payments_map = [];

        $sql_payments = "
            SELECT 
                anggota_id,
                MONTH(bulan_periode) as bulan,
                SUM(jumlah) as total_bayar
            FROM pembayaran
            WHERE jenis_simpanan = 'Simpanan Wajib'
            AND YEAR(bulan_periode) = ?
            GROUP BY anggota_id, MONTH(bulan_periode)
        ";
        $stmt_payments = $conn->prepare($sql_payments);
        $stmt_payments->bind_param("i", $selected_year);
        $stmt_payments->execute();
        $result_payments = $stmt_payments->get_result();

        while ($p = $result_payments->fetch_assoc()) {
            $payments_map[$p['anggota_id']][$p['bulan']] = $p['total_bayar'];
        }

        $stmt_payments->close();

        // ================== NAMA BULAN ==================
        $nama_bulan_header = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-book text-primary me-2"></i>Buku Simpanan Wajib Anggota</h3>
            <a href="cetak_buku_simpanan.php?tahun=<?= $selected_year ?>" target="_blank" class="btn btn-danger">
                <i class="fas fa-file-pdf me-2"></i>Export ke PDF
            </a>
        </div>

        <!-- ========== BLOK RINGKASAN DATA DENGAN DESAIN BARU ========== -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-book me-2"></i>Simpanan Wajib</h6>
                        <div class="card-text">Rp <?= number_format($summary_data['Simpanan Wajib']['total'], 2, ',', '.') ?></div>
                    </div>
                    <div class="card-footer">
                        <i class="fas fa-users me-1"></i> <?= $summary_data['Simpanan Wajib']['anggota'] ?> Anggota
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-hand-holding-heart me-2"></i>Simpanan Pokok</h6>
                        <div class="card-text">Rp <?= number_format($summary_data['Simpanan Pokok']['total'], 2, ',', '.') ?></div>
                    </div>
                    <div class="card-footer">
                        <i class="fas fa-users me-1"></i> <?= $summary_data['Simpanan Pokok']['anggota'] ?> Anggota
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-piggy-bank me-2"></i>Simpanan Sukarela</h6>
                        <div class="card-text">Rp <?= number_format($summary_data['Simpanan Sukarela']['total'], 2, ',', '.') ?></div>
                    </div>
                    <div class="card-footer">
                        <i class="fas fa-users me-1"></i> <?= $summary_data['Simpanan Sukarela']['anggota'] ?> Anggota
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-user-friends me-2"></i>Total Anggota</h6>
                        <div class="card-text"><?= count($anggota_list) ?> Orang</div>
                    </div>
                    <div class="card-footer">
                        <i class="fas fa-check-circle me-1"></i> Aktif
                    </div>
                </div>
            </div>
        </div>

        <div class="card filter-card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>Pilih Tahun
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="buku_besar">
                    <input type="hidden" name="tab" value="wajib">
                    <div class="row">
                        <div class="col-md-4">
                            <select name="tahun" class="form-select">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year ?>" <?= ($selected_year == $year) ? 'selected' : '' ?>>
                                        Tahun <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>Tampilkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-custom">
                <thead>
                    <tr>
                        <th rowspan="2">No Anggota</th>
                        <th rowspan="2" style="min-width: 200px;">Nama Anggota</th>
                        <th rowspan="2">Tahun</th>
                        <th colspan="12">Iuran Anggota Setiap Bulan</th>
                    </tr>
                    <tr>
                        <?php foreach ($nama_bulan_header as $bulan): ?>
                            <th><?= $bulan ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($anggota_list)): ?>
                        <tr><td colspan="15" class="text-center py-4">Tidak ada data anggota.</td></tr>
                    <?php else: ?>
                        <?php foreach ($anggota_list as $anggota): ?>
                            <tr>
                                <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
                                <td class="text-start"><?= htmlspecialchars($anggota['nama']) ?></td>
                                <td><?= $selected_year ?></td>
                                <?php for ($bulan_idx = 1; $bulan_idx <= 12; $bulan_idx++): ?>
                                    <td class="text-end">
                                        <?php
                                        $jumlah_bayar = $payments_map[$anggota['id']][$bulan_idx] ?? '-';
                                        echo ($jumlah_bayar !== '-') ? number_format($jumlah_bayar, 0, ',', '.') : '-';
                                        ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($active_tab == 'sukarela'): ?>
        <!-- ================== TAB SIMPANAN SUKARELA ================== -->
        <?php
        // ================== FILTER BULAN & TAHUN ==================
        $jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

        // ================== AMBIL DATA ANGGOTA ==================
        $anggota_list = $conn->query("
            SELECT id, no_anggota, nama 
            FROM anggota 
            ORDER BY no_anggota ASC
        ")->fetch_all(MYSQLI_ASSOC);

        // ================== AMBIL DATA SIMPANAN SUKARELA ==================
        $sukarela_map = [];

        $sql_sukarela = "
            SELECT 
                anggota_id,
                DAY(tanggal_bayar) as tanggal,
                SUM(jumlah) as total_bayar
            FROM pembayaran
            WHERE jenis_simpanan = 'Simpanan Sukarela'
            AND MONTH(tanggal_bayar) = ?
            AND YEAR(tanggal_bayar) = ?
            GROUP BY anggota_id, DAY(tanggal_bayar)
        ";

        $stmt = $conn->prepare($sql_sukarela);
        $stmt->bind_param("ii", $selected_month, $selected_year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $sukarela_map[$row['anggota_id']][$row['tanggal']] = $row['total_bayar'];
        }

        $stmt->close();
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-hand-holding-usd text-warning me-2"></i>Buku Simpanan Sukarela</h3>
            <a href="cetak_buku_simpanan_sukarela.php?bulan=<?= $selected_month ?>&tahun=<?= $selected_year ?>" target="_blank" class="btn btn-danger">
                <i class="fas fa-file-pdf me-2"></i>Export ke PDF
            </a>
        </div>

        <!-- ================= FILTER ================= -->
        <div class="card filter-card">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>Filter Bulan & Tahun
            </div>
            <div class="card-body">
                <form method="GET">
                    <input type="hidden" name="page" value="buku_besar">
                    <input type="hidden" name="tab" value="sukarela">
                    <div class="row">
                        <div class="col-md-4">
                            <select name="bulan" class="form-select">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <select name="tahun" class="form-select">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year ?>" <?= ($selected_year == $year) ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>Tampilkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= TABEL ================= -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-custom">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:120px;">No Anggota</th>
                        <th rowspan="2" style="width:200px;">Nama Anggota</th>
                        <th colspan="<?= $jumlah_hari ?>">
                            <?= date('F', mktime(0, 0, 0, $selected_month, 10)) . ' ' . $selected_year ?>
                        </th>
                    </tr>
                    <tr>
                        <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
                            <th style="width:90px;"><?= $hari ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($anggota_list)): ?>
                        <tr>
                            <td colspan="<?= 2 + $jumlah_hari ?>" class="text-center py-4">Tidak ada data anggota.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($anggota_list as $anggota): ?>
                            <tr>
                                <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
                                <td class="text-start"><?= htmlspecialchars($anggota['nama']) ?></td>

                                <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
                                    <td class="text-end" style="min-width:90px; max-width:100px;">
                                        <?php
                                        $jumlah = $sukarela_map[$anggota['id']][$hari] ?? '-';
                                        echo ($jumlah !== '-') ? number_format($jumlah, 0, ',', '.') : '-';
                                        ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
