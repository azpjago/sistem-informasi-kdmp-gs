<?php
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;
require 'koneksi/koneksi.php';

$selected_month = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$selected_year = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// ================== FUNGSI KONVERSI GAMBAR KE BASE64 ==================
function gambarKeBase64($path) {
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return false;
}

// Konversi logo ke base64
$logo_base64 = gambarKeBase64('assets/logo.jpeg');
if (!$logo_base64) {
    $logo_base64 = '';
}

// ================== MESIN PENGOLAH DATA ==================
$jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

$anggota_list = $conn->query("
    SELECT id, no_anggota, nama 
    FROM anggota 
    ORDER BY no_anggota ASC
")->fetch_all(MYSQLI_ASSOC);

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

// Hitung total per hari
$total_per_hari = array_fill(1, $jumlah_hari, 0);
$total_keseluruhan = 0;

foreach ($anggota_list as $anggota) {
    for ($hari = 1; $hari <= $jumlah_hari; $hari++) {
        if (isset($sukarela_map[$anggota['id']][$hari])) {
            $total_per_hari[$hari] += $sukarela_map[$anggota['id']][$hari];
            $total_keseluruhan += $sukarela_map[$anggota['id']][$hari];
        }
    }
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buku Simpanan Sukarela - <?= date('F', mktime(0, 0, 0, $selected_month, 10)) . ' ' . $selected_year ?></title>
    <style>
        body { font-family: sans-serif; }
        .header { 
            text-align: center; 
            margin-bottom: 20px;
            position: relative;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        .logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .title-text {
            padding-top: 10px;
        }
        .title-text h3,
        .title-text h4,
        .title-text h5 {
            margin: 4px 0;
            padding: 0;
        }
        h3, h4, h5 { margin: 5px 0; padding: 0; }
        .table-custom { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 8px; 
            margin-top: 15px; 
        }
        .table-custom th, 
        .table-custom td { 
            border: 1px solid black; 
            padding: 3px; 
            text-align: center; 
        }
        .table-custom .nama-anggota { 
            text-align: left; 
        }
        .table-custom .nominal { 
            text-align: right; 
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .total-row td {
            border-top: 2px solid black;
        }
        .footer {
            margin-top: 20px;
            font-size: 8px;
            text-align: right;
        }
        .page-info {
            font-size: 8px;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <?php if ($logo_base64): ?>
                <img src="<?= $logo_base64 ?>" class="logo" alt="Logo Koperasi">
            <?php else: ?>
                <div style="width:70px;height:70px;background:#ccc;display:flex;align-items:center;justify-content:center;font-size:10px;">
                    LOGO
                </div>
            <?php endif; ?>
            <div class="title-text">
                <h3>BUKU SIMPANAN SUKARELA</h3>
                <h4>KOPERASI DESA MERAH PUTIH GANJAR SABAR</h4>
                <h5>BULAN <?= strtoupper(date('F', mktime(0, 0, 0, $selected_month, 10))) . ' ' . $selected_year ?></h5>
            </div>
        </div>
    </div>
    
    <table class="table-custom">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%;">NO ANGGOTA</th>
                <th rowspan="2" style="width: 15%;">NAMA ANGGOTA</th>
                <th colspan="<?= $jumlah_hari ?>">TANGGAL DALAM BULAN</th>
            </tr>
            <tr>
                <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
                    <th style="width: 25px;"><?= $hari ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($anggota_list)): ?>
                <tr><td colspan="<?= 2 + $jumlah_hari ?>">Tidak ada data anggota.</td></tr>
            <?php else: ?>
                <?php foreach ($anggota_list as $anggota): ?>
                    <?php
                        $total_anggota = 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
                        <td class="nama-anggota"><?= htmlspecialchars($anggota['nama']) ?></td>
                        <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
                            <?php
                                $jumlah = $sukarela_map[$anggota['id']][$hari] ?? 0;
                                $total_anggota += $jumlah;
                                $display = ($jumlah > 0) ? number_format($jumlah, 0, ',', '.') : '-';
                            ?>
                            <td class="nominal"><?= $display ?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Baris Total -->
                <tr class="total-row">
                    <td colspan="2" style="text-align: center;">TOTAL</td>
                    <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
                        <td class="nominal"><?= number_format($total_per_hari[$hari], 0, ',', '.') ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Dicetak pada: <?= date('d-m-Y H:i:s') ?> | Halaman 1</p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Buku_Simpanan_Sukarela_".date('F_Y', mktime(0, 0, 0, $selected_month, 10)).".pdf", ["Attachment" => false]);
?>
