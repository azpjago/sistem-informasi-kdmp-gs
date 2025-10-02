<?php
// Pastikan ID anggota ada di URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID Anggota tidak valid.");
}
$anggota_id = intval($_GET['id']);

// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) die("Connection failed: ". $conn->connect_error);

// 1. Ambil info anggota
$stmt_anggota = $conn->prepare("SELECT nama, no_anggota FROM anggota WHERE id = ?");
$stmt_anggota->bind_param("i", $anggota_id);
$stmt_anggota->execute();
$result_anggota = $stmt_anggota->get_result();
$anggota_info = $result_anggota->fetch_assoc();
$stmt_anggota->close();

if (!$anggota_info) {
    die("Anggota tidak ditemukan.");
}

// 2. Ambil riwayat pembayaran anggota tersebut
$stmt_pembayaran = $conn->prepare("SELECT * FROM pembayaran WHERE anggota_id = ? ORDER BY tanggal_bayar DESC");
$stmt_pembayaran->bind_param("i", $anggota_id);
$stmt_pembayaran->execute();
$result_pembayaran = $stmt_pembayaran->get_result();
$rekap_data = $result_pembayaran->fetch_all(MYSQLI_ASSOC);
$stmt_pembayaran->close();
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="?page=rekap" class="btn btn-secondary">‹ Kembali ke Pencarian</a>
    <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Riwayat</button>
</div>

<div id="print-area">
    <h3 class="mb-4">Riwayat Transaksi</h3>
    <p>
        <strong>Nomor Anggota:</strong> <?= htmlspecialchars($anggota_info['no_anggota']) ?><br>
        <strong>Nama Anggota:</strong> <?= htmlspecialchars($anggota_info['nama']) ?>
    </p>

    <?php if (!empty($rekap_data)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Tanggal Bayar</th>
                        <th>Jenis Transaksi</th>
                        <th>Periode</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Bukti User</th>
                        <th>Keterangan</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekap_data as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i:s', strtotime($r['tanggal_bayar'])) ?></td>
                            <td class="text-center">
                                <?php 
                                // PERBAIKAN: Handle semua jenis transaksi
                                switch ($r['jenis_transaksi']) {
                                    case 'wajib':
                                        echo '<span class="badge bg-primary">Wajib</span>';
                                        break;
                                    case 'pokok':
                                        echo '<span class="badge bg-success">Pokok</span>';
                                        break;
                                    case 'sukarela':
                                        echo '<span class="badge bg-info text-dark">Sukarela</span>';
                                        break;
                                    case 'setor':
                                        echo '<span class="badge bg-primary">Setor</span>';
                                        break;
                                    case 'tarik':
                                        echo '<span class="badge bg-warning text-dark">Tarik</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary">' . htmlspecialchars($r['jenis_transaksi']) . '</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($r['bulan_periode']) ?></td>
                            <td class="text-end"><?= "Rp. " . number_format($r['jumlah'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= ucfirst($r['metode']) ?></td>
                            <td class="text-center">
                                <?php if(!empty($r['bukti'])): ?>
                                    <a href="<?= htmlspecialchars($r['bukti']) ?>" target="_blank" class="btn btn-info btn-sm no-print">Lihat</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm upload-bukti-btn no-print" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalUploadBukti" 
                                            data-pembayaran-id="<?= $r['id'] ?>">
                                        Upload
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($r['status']) ?></td>
                            <td class="text-center no-print">
                                <a href="cetak_bukti.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Bukti Transaksi">
                                    🖨️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-3">Belum ada riwayat transaksi untuk anggota ini.</div>
    <?php endif; ?>
</div>