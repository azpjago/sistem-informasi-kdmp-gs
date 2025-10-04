<?php
session_start();
if ($_SESSION['role'] !== 'usaha')
    exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
$id_pinjaman = intval($_GET['id'] ?? 0);

// Ambil data pinjaman
$pinjaman = $conn->query("
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.no_anggota,
        a.no_hp,
        a.alamat,
        u.nama as approved_by_name
    FROM pinjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pengurus u ON p.approved_by = u.id
    WHERE p.id_pinjaman = $id_pinjaman
")->fetch_assoc();

// Ambil data cicilan jika ada
$cicilan_result = $conn->query("
    SELECT * FROM cicilan 
    WHERE id_pinjaman = $id_pinjaman 
    ORDER BY angsuran_ke
");

$status_color = [
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'active' => 'info',
    'lunas' => 'dark'
][$pinjaman['status']] ?? 'secondary';
?>

<div class="row">
    <div class="col-md-6">
        <h6>Info Pemohon</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>Nama</strong></td>
                <td><?= $pinjaman['nama_anggota'] ?></td>
            </tr>
            <tr>
                <td><strong>No. Anggota</strong></td>
                <td><?= $pinjaman['no_anggota'] ?></td>
            </tr>
            <tr>
                <td><strong>No. HP</strong></td>
                <td><?= $pinjaman['no_hp'] ?></td>
            </tr>
            <tr>
                <td><strong>Alamat</strong></td>
                <td><?= $pinjaman['alamat'] ?></td>
            </tr>
        </table>
    </div>

    <div class="col-md-6">
        <h6>Info Pinjaman</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>No. Pinjaman</strong></td>
                <td>#<?= $pinjaman['id_pinjaman'] ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal Pengajuan</strong></td>
                <td><?= date('d/m/Y H:i', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>
                    <span class="badge bg-<?= $status_color ?>"><?= ucfirst($pinjaman['status']) ?></span>
                </td>
            </tr>
            <tr>
                <td><strong>Disetujui Oleh</strong></td>
                <td><?= $pinjaman['approved_by_name'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal Approve</strong></td>
                <td><?= $pinjaman['tanggal_approve'] ? date('d/m/Y H:i', strtotime($pinjaman['tanggal_approve'])) : '-' ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6>Detail Pinjaman</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>Jumlah Pinjaman</strong></td>
                <td class="fw-bold">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td><strong>Bunga per Bulan</strong></td>
                <td><?= $pinjaman['bunga_bulan'] ?>%</td>
            </tr>
            <tr>
                <td><strong>Tenor</strong></td>
                <td><?= $pinjaman['tenor_bulan'] ?> Bulan</td>
            </tr>
            <tr>
                <td><strong>Sumber Dana</strong></td>
                <td><?= $pinjaman['sumber_dana'] ?></td>
            </tr>
            <tr>
                <td><strong>Tujuan Pinjaman</strong></td>
                <td><?= $pinjaman['tujuan_pinjaman'] ?></td>
            </tr>
        </table>
    </div>

    <div class="col-md-6">
        <h6>Perhitungan</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>Cicilan per Bulan</strong></td>
                <td class="fw-bold">Rp <?= number_format($pinjaman['cicilan_per_bulan'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td><strong>Total Bunga</strong></td>
                <td>Rp <?= number_format($pinjaman['total_bunga'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td><strong>Total Pengembalian</strong></td>
                <td class="fw-bold text-success">Rp <?= number_format($pinjaman['total_pengembalian'], 0, ',', '.') ?>
                </td>
            </tr>
            <tr>
                <td><strong>Simpanan Saat Ajukan</strong></td>
                <td>Rp <?= number_format($pinjaman['total_simpanan'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td><strong>Max Pinjaman Dihitung</strong></td>
                <td>Rp <?= number_format($pinjaman['max_pinjaman_dihitung'], 0, ',', '.') ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if ($cicilan_result->num_rows > 0): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6>Jadwal Cicilan</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Angsuran</th>
                            <th>Jatuh Tempo</th>
                            <th>Pokok</th>
                            <th>Bunga</th>
                            <th>Total</th>
                            <th>Tanggal Bayar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cicilan = $cicilan_result->fetch_assoc()):
                            $status_cicilan = [
                                'pending' => 'secondary',
                                'lunas' => 'success',
                                'telat' => 'danger'
                            ][$cicilan['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>#<?= $cicilan['angsuran_ke'] ?></td>
                                <td><?= date('d/m/Y', strtotime($cicilan['jatuh_tempo'])) ?></td>
                                <td>Rp <?= number_format($cicilan['jumlah_pokok'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($cicilan['jumlah_bunga'], 0, ',', '.') ?></td>
                                <td class="fw-bold">Rp <?= number_format($cicilan['total_cicilan'], 0, ',', '.') ?></td>
                                <td><?= $cicilan['tanggal_bayar'] ? date('d/m/Y', strtotime($cicilan['tanggal_bayar'])) : '-' ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $status_cicilan ?>">
                                        <?= ucfirst($cicilan['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>