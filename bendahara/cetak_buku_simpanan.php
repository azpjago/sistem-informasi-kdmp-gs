<?php
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) die("Connection failed: ". $conn->connect_error);
$jumlah_simpanan_wajib = 20000;

// ================== MESIN PENGOLAH DATA SEDERHANA ==================
$sql_anggota = "SELECT id, no_anggota, nama, tanggal_join FROM anggota ORDER BY CAST(SUBSTRING(no_anggota, 4) AS UNSIGNED) ASC";
$anggota_list = $conn->query($sql_anggota)->fetch_all(MYSQLI_ASSOC);
$payments_map = [];

// Kueri dioptimalkan untuk langsung menggunakan fungsi YEAR() dan MONTH()
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
while ($p = $result_payments->fetch_assoc()) {
    $payments_map[$p['anggota_id']][$p['bulan']] = $p['total_bayar'];
}
$stmt_payments->close();
// ================== AKHIR DARI MESIN PENGOLAH DATA ==================

$nama_bulan_header = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buku Simpanan Anggota - <?= $selected_year ?></title>
    <style>
        body { font-family: sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        h3, h4, h5 { margin: 0; padding: 0; }
        .table-custom { width: 100%; border-collapse: collapse; font-size: 9px; }
        .table-custom th, .table-custom td { border: 1px solid black; padding: 4px; text-align: center; }
        .table-custom .nama-anggota { text-align: left; }
        .table-custom .nominal { text-align: right; }
        .na-cell { color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h3>BUKU SIMPANAN ANGGOTA</h3>
        <h4>KOPERASI DESA MERAH PUTIH GANJAR SABAR</h4>
        <h5>TAHUN BUKU <?= $selected_year ?></h5>
    </div>
    <table class="table-custom">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%;">NO ANGGOTA</th>
                <th rowspan="2" style="width: 15%;">NAMA ANGGOTA</th>
                <th colspan="12">IURAN ANGGOTA SETIAP BULAN</th>
                <th rowspan="2">JUMLAH</th>
            </tr>
            <tr>
                <?php foreach($nama_bulan_header as $bulan): ?><th><?= $bulan ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($anggota_list)): ?>
                <tr><td colspan="15">Tidak ada data anggota.</td></tr>
            <?php else: ?>
                <?php foreach ($anggota_list as $anggota): ?>
                    <?php
                        $tgl_join = new DateTime($anggota['tanggal_join']);
                        $total_tahunan = 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
                        <td class="nama-anggota"><?= htmlspecialchars($anggota['nama']) ?></td>
                        <?php for ($bulan_idx = 1; $bulan_idx <= 12; $bulan_idx++): ?>
                            <?php
                                $tgl_awal_bulan = new DateTime("$selected_year-$bulan_idx-01");
                                $display = '';
                                $class = 'nominal';
                                if ($tgl_awal_bulan->format('Y-m') < $tgl_join->format('Y-m')) {
                                    $display = 'N/A';
                                    $class = 'na-cell';
                                } else {
                                    $jumlah_bayar = $payments_map[$anggota['id']][$bulan_idx] ?? 0;
                                    $total_tahunan += $jumlah_bayar;
                                    $display = ($jumlah_bayar > 0) ? number_format($jumlah_bayar, 0, ',', '.') : '-';
                                }
                            ?>
                            <td class="<?= $class ?>"><?= $display ?></td>
                        <?php endfor; ?>
                        <td class="nominal"><?= number_format($total_tahunan, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Buku_Simpanan_Anggota_".$selected_year.".pdf", ["Attachment" => false]);
?>