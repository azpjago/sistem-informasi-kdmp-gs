<?php
// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) die("Connection failed: ". $conn->connect_error);

// --- LOGIKA FILTER TAHUN ---
$current_year = date('Y');
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;
$years = range($current_year - 2, $current_year + 5);

// ================== BLOK LOGIKA PENGAMBILAN DATA BARU ==================

// 1. Ambil semua anggota
$anggota_list = $conn->query("SELECT id, no_anggota, nama FROM anggota ORDER BY no_anggota ASC")->fetch_all(MYSQLI_ASSOC);

// 2. Ambil data pembayaran WAJIB untuk tahun yang dipilih dengan cara yang efisien
$payments_map = [];

// Kueri dioptimalkan untuk langsung menggunakan fungsi YEAR() dan MONTH() pada kolom 'bulan_periode'
$sql_payments = "
    SELECT 
        anggota_id, 
        MONTH(bulan_periode) as bulan, 
        SUM(jumlah) as total_bayar
    FROM pembayaran 
    WHERE 
        jenis_simpanan = 'Simpanan Wajib' 
        AND YEAR(bulan_periode) = ?
    GROUP BY 
        anggota_id, MONTH(bulan_periode)
";
$stmt_payments = $conn->prepare($sql_payments);
$stmt_payments->bind_param("i", $selected_year);
$stmt_payments->execute();
$result_payments = $stmt_payments->get_result();

// 3. Petakan pembayaran ke dalam format yang mudah dibaca: [id_anggota][nomor_bulan] = jumlah
while ($p = $result_payments->fetch_assoc()) {
    $payments_map[$p['anggota_id']][$p['bulan']] = $p['total_bayar'];
}
$stmt_payments->close();

// ================== AKHIR DARI BLOK LOGIKA BARU ==================

// Array nama bulan untuk header tabel
$nama_bulan_header = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📚 Buku Simpanan Wajib Anggota</h3>
    <a href="cetak_buku_simpanan.php?tahun=<?= $selected_year ?>" target="_blank" class="btn btn-danger no-print">
        📄 Export ke PDF
    </a>
</div>

<div class="card mb-4 no-print">
    <div class="card-header"><strong>Pilih Tahun</strong></div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="buku_simpanan">
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
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped text-center align-middle" style="font-size: 0.9rem;">
        <thead class="table-dark">
            <tr>
                <th rowspan="2" class="align-middle">No Anggota</th>
                <th rowspan="2" class="align-middle" style="min-width: 200px;">Nama Anggota</th>
                <th rowspan="2" class="align-middle">Tahun</th>
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
                <tr><td colspan="15">Tidak ada data anggota.</td></tr>
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