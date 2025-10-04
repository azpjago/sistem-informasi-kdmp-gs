<?php
// pages/ajax/get_detail_pinjaman.php
session_start();
if ($_SESSION['role'] !== 'ketua') {
    http_response_code(403);
    exit('Akses ditolak');
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id_pinjaman = intval($_GET['id'] ?? 0);

if ($id_pinjaman <= 0) {
    echo '<div class="alert alert-danger">ID Pinjaman tidak valid</div>';
    exit;
}

// Ambil data detail pinjaman
$stmt = $conn->prepare("
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.no_anggota,
        a.no_hp,
        a.alamat,
        a.email,
        u.nama as approved_by_name,
        p.tanggal_pengajuan,
        p.tanggal_approve
    FROM pinjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pengurus u ON p.approved_by = u.id
    WHERE p.id_pinjaman = ?
");

$stmt->bind_param("i", $id_pinjaman);
$stmt->execute();
$result = $stmt->get_result();
$pinjaman = $result->fetch_assoc();

if (!$pinjaman) {
    echo '<div class="alert alert-danger">Data pinjaman tidak ditemukan</div>';
    exit;
}

// Format status
$status_badge = [
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'active' => 'info',
    'lunas' => 'dark'
][$pinjaman['status']] ?? 'secondary';
?>

<div class="row">
    <div class="col-md-6">
        <h6>Informasi Anggota</h6>
        <table class="table table-sm">
            <tr>
                <td width="40%"><strong>Nama</strong></td>
                <td><?= htmlspecialchars($pinjaman['nama_anggota']) ?></td>
            </tr>
            <tr>
                <td><strong>No. Anggota</strong></td>
                <td><?= htmlspecialchars($pinjaman['no_anggota']) ?></td>
            </tr>
            <tr>
                <td><strong>No. HP</strong></td>
                <td><?= htmlspecialchars($pinjaman['no_hp']) ?></td>
            </tr>
            <tr>
                <td><strong>Email</strong></td>
                <td><?= htmlspecialchars($pinjaman['email'] ?? '-') ?></td>
            </tr>
            <tr>
                <td><strong>Alamat</strong></td>
                <td><?= htmlspecialchars($pinjaman['alamat'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <div class="col-md-6">
        <h6>Detail Pinjaman</h6>
        <table class="table table-sm">
            <tr>
                <td width="40%"><strong>Status</strong></td>
                <td>
                    <span class="badge bg-<?= $status_badge ?>">
                        <?= strtoupper($pinjaman['status']) ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Jumlah Pinjaman</strong></td>
                <td class="fw-bold text-primary">
                    Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?>
                </td>
            </tr>
            <tr>
                <td><strong>Tenor</strong></td>
                <td><?= $pinjaman['tenor_bulan'] ?> Bulan</td>
            </tr>
            <tr>
                <td><strong>Bunga/Bulan</strong></td>
                <td><?= $pinjaman['bunga_bulan'] ?>%</td>
            </tr>
            <tr>
                <td><strong>Cicilan/Bulan</strong></td>
                <td class="fw-bold">
                    Rp <?= number_format($pinjaman['cicilan_per_bulan'], 0, ',', '.') ?>
                </td>
            </tr>
            <tr>
                <td><strong>Sumber Dana</strong></td>
                <td><?= htmlspecialchars($pinjaman['sumber_dana']) ?></td>
            </tr>
            <tr>
                <td><strong>Tujuan Pinjaman</strong></td>
                <td><?= htmlspecialchars($pinjaman['tujuan_pinjaman']) ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <h6>Informasi Approval</h6>
        <table class="table table-sm">
            <tr>
                <td width="30%"><strong>Tanggal Pengajuan</strong></td>
                <td><?= date('d/m/Y H:i', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal Approval</strong></td>
                <td>
                    <?= $pinjaman['tanggal_approve'] ?
                        date('d/m/Y H:i', strtotime($pinjaman['tanggal_approve'])) : '-' ?>
                </td>
            </tr>
            <tr>
                <td><strong>Disetujui Oleh</strong></td>
                <td><?= $pinjaman['approved_by_name'] ?? '-' ?></td>
            </tr>
            <tr>
                <td><strong>Catatan Approval</strong></td>
                <td><?= $pinjaman['catatan_approval'] ?
                    htmlspecialchars($pinjaman['catatan_approval']) : '-' ?></td>
            </tr>
        </table>
    </div>
</div>

<?php
$conn->close();
?>