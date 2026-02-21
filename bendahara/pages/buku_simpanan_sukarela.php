<?php
// ================== FILTER BULAN & TAHUN ==================
$current_year = date('Y');
$current_month = date('n');

$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : $current_year;
$selected_month = isset($_GET['bulan']) ? intval($_GET['bulan']) : $current_month;

$years = range($current_year - 2, $current_year + 5);

// Hitung jumlah hari dalam bulan terpilih
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
    <h3 class="mb-4">💰 Buku Simpanan Sukarela</h3>
</div>

<!-- ================= FILTER ================= -->
<div class="card mb-4">
    <div class="card-header"><strong>Filter Bulan & Tahun</strong></div>
    <div class="card-body">
        <form method="GET">
			<input type="hidden" name="page" value="buku_simpanan_sukarela">
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
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ================= TABEL ================= -->
<div class="table-responsive">
    <table class="table table-bordered table-striped text-center align-middle" style="font-size: 0.85rem;">
        <thead class="table-dark">
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
                    <td colspan="<?= 2 + $jumlah_hari ?>">Tidak ada data anggota.</td>
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
