<?php
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;
require 'koneksi/koneksi.php';

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
    // Fallback jika logo tidak ditemukan
    $logo_base64 = ''; // Atau bisa menggunakan teks sebagai pengganti
}
// ================== AKHIR FUNGSI ==================

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

// Hitung total keseluruhan untuk baris jumlah
$total_kolom_bulan = array_fill(1, 12, 0);
$total_keseluruhan = 0;

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buku Simpanan Anggota - <?= $selected_year ?></title>
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
        .table-custom { width: 100%; border-collapse: collapse; font-size: 9px; margin-top: 15px; }
        .table-custom th, .table-custom td { border: 1px solid black; padding: 4px; text-align: center; }
        .table-custom .nama-anggota { text-align: left; }
        .table-custom .nominal { text-align: right; }
        .na-cell { color: #888; }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .logo {
        position: absolute;
        left: 0;
        top: 0;
        width: 80px;
        height: 80px;
        object-fit: contain;
		}
        .total-row td {
            border-top: 2px solid black;
        }
        .footer {
            margin-top: 20px;
            font-size: 8px;
            text-align: right;
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
                <h3>BUKU SIMPANAN ANGGOTA</h3>
                <h4>KOPERASI DESA MERAH PUTIH GANJAR SABAR</h4>
                <h5>TAHUN BUKU <?= $selected_year ?></h5>
            </div>
        </div>
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
                                    $total_kolom_bulan[$bulan_idx] += $jumlah_bayar;
                                    $total_keseluruhan += $jumlah_bayar;
                                    $display = ($jumlah_bayar > 0) ? number_format($jumlah_bayar, 0, ',', '.') : '-';
                                }
                            ?>
                            <td class="<?= $class ?>"><?= $display ?></td>
                        <?php endfor; ?>
                        <td class="nominal"><?= number_format($total_tahunan, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Baris Jumlah/Total -->
                <tr class="total-row">
                    <td colspan="2" style="text-align: center; font-weight: bold;">TOTAL</td>
                    <?php for ($bulan_idx = 1; $bulan_idx <= 12; $bulan_idx++): ?>
                        <td class="nominal"><?= number_format($total_kolom_bulan[$bulan_idx], 0, ',', '.') ?></td>
                    <?php endfor; ?>
                    <td class="nominal"><?= number_format($total_keseluruhan, 0, ',', '.') ?></td>
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
$options->set('isPhpEnabled', true); // Tambahkan ini

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Buku_Simpanan_Anggota_".$selected_year.".pdf", ["Attachment" => false]);
?>
