<?php
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;
require 'koneksi/koneksi.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID Anggota tidak valid.");
}
$anggota_id = intval($_GET['id']);

date_default_timezone_set('Asia/Jakarta');
// 1. Ambil data profil anggota (hanya untuk info, bukan saldo)
$stmt_anggota = $conn->prepare("SELECT no_anggota, nama, alamat FROM anggota WHERE id = ?");
$stmt_anggota->bind_param("i", $anggota_id);
$stmt_anggota->execute();
$anggota = $stmt_anggota->get_result()->fetch_assoc();
$stmt_anggota->close();
if (!$anggota) { die("Anggota tidak ditemukan."); }

// ================== PERUBAHAN UTAMA ==================
// 2. Ambil SEMUA transaksi dari tabel pembayaran untuk anggota ini
$stmt_transaksi = $conn->prepare("SELECT * FROM pembayaran WHERE anggota_id = ? ORDER BY tanggal_bayar ASC");
$stmt_transaksi->bind_param("i", $anggota_id);
$stmt_transaksi->execute();
$transaksi_list = $stmt_transaksi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_transaksi->close();
// ================== AKHIR PERUBAHAN ==================


ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kartu Nota - <?= $anggota['nama'] ?></title>
    <style>
        /* CSS Anda tidak berubah */
        body { font-family: sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info { margin-bottom: 20px; }
        .info-table { width: 50%; }
        .transaction-table { width: 100%; border-collapse: collapse; }
        .transaction-table th, .transaction-table td { border: 1px solid black; padding: 5px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h3>KARTU NOTA SIMPANAN ANGGOTA</h3>
        <h4>Koperasi Desa Merah Putih Ganjar Sabar</h4>
    </div>

    <div class="info">
        <table class="info-table">
            <tr><td>Nomor Anggota</td><td>: <?= htmlspecialchars($anggota['no_anggota']) ?></td></tr>
            <tr><td>Nama Anggota</td><td>: <?= htmlspecialchars($anggota['nama']) ?></td></tr>
            <tr><td>Alamat</td><td>: <?= htmlspecialchars($anggota['alamat']) ?></td></tr>
            <tr><td>Dicetak Tanggal</td><td>: <?= date('d/m/Y H:i:s') ?></td></tr>
        </table>
    </div>

    <table class="transaction-table">
        <thead class="text-center">
            <tr>
                <th rowspan="2">Tanggal</th>
                <th rowspan="2">ID Transaksi</th>
                <th rowspan="2">Keterangan</th>
                <th colspan="2">Mutasi</th>
                <th rowspan="2">Saldo</th>
                <th rowspan="2">Paraf</th>
            </tr>
            <tr>
                <th>Debet</th>
                <th>Credit</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // ================== PERUBAHAN UTAMA ==================
            $saldo = 0; // Saldo awal selalu dimulai dari 0
            
            foreach ($transaksi_list as $trx):
                $debet = 0;
                $credit = 0;
                
                // Cek jenis transaksi untuk menentukan masuk ke debet atau credit
                // Asumsi: 'tarik' adalah debet, sisanya (setor/pokok/wajib) adalah credit
                if (strpos(strtolower($trx['jenis_transaksi']), 'tarik') !== false) {
                    $debet = (float)$trx['jumlah'];
                } else {
                    $credit = (float)$trx['jumlah'];
                }
                
                $saldo += $credit - $debet;
            ?>
            <tr>
                <td class="text-center"><?= date('d/m/Y H:i:s', strtotime($trx['tanggal_bayar'])) ?></td>
                <td class="text-center"><?= htmlspecialchars($trx['id_transaksi']) ?></td>
                <td><?= htmlspecialchars($trx['status']) ?></td>
                <td class="text-right"><?= ($debet > 0) ? number_format($debet, 0, ',', '.') : '-' ?></td>
                <td class="text-right"><?= ($credit > 0) ? number_format($credit, 0, ',', '.') : '-' ?></td>
                <td class="text-right font-bold"><?= number_format($saldo, 0, ',', '.') ?></td>
                <td></td>
            </tr>
            <?php endforeach; 
            // ================== AKHIR PERUBAHAN ==================
            ?>

            <tr>
                <td colspan="5" class="text-right font-bold">Saldo Akhir</td>
                <td class="text-right font-bold"><?= number_format($saldo, 0, ',', '.') ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Konfigurasi Dompdf (Tidak ada perubahan)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Kartu_Nota_".$anggota['nama'].".pdf", ["Attachment" => false]);
?>
