<?php
require_once 'dompdf/autoload.inc.php'; // Pastikan path ke autoload DOMPDF benar

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
date_default_timezone_set("Asia/Jakarta");
// Ambil parameter filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query dengan filter
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.id_pemesanan LIKE ? OR a.nama LIKE ? OR pr.nama_produk LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.tanggal_pesan) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.tanggal_pesan) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query data pemesanan
$pemesanan_query = "
    SELECT 
        p.id_pemesanan,
        p.id_anggota,
        a.nama as nama_anggota,
        a.no_anggota,
        p.tanggal_pesan,
        p.status,
        p.metode,
        p.bank_tujuan,
        p.total_harga,
        p.alamat_pemesan,
        GROUP_CONCAT(CONCAT(pr.nama_produk, ' (', pd.jumlah, ' pcs)') SEPARATOR '; ') as produk,
        COUNT(pd.id_produk) as jumlah_produk
    FROM pemesanan p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pemesanan_detail pd ON p.id_pemesanan = pd.id_pemesanan
    LEFT JOIN produk pr ON pd.id_produk = pr.id_produk
    $where_sql
    GROUP BY p.id_pemesanan
    ORDER BY p.tanggal_pesan DESC
";

$stmt = $conn->prepare($pemesanan_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pemesanan_result = $stmt->get_result();

// Hitung statistik
$total_pemesanan = $pemesanan_result->num_rows;
$total_nilai = 0;
$status_count = ['Menunggu' => 0, 'Diproses' => 0, 'Terkirim' => 0, 'Dibatalkan' => 0];

while ($row = $pemesanan_result->fetch_assoc()) {
    $total_nilai += $row['total_harga'];
    $status_count[$row['status']]++;
}
mysqli_data_seek($pemesanan_result, 0); // Reset pointer

// Buat HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pemesanan - KDMPGS</title>
    <style>
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 { 
            margin: 0; 
            color: #000000ff;
            font-size: 20px;
        }
        .header .subtitle { 
            color: #7f8c8d; 
            margin: 5px 0;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #000000ff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background-color: #da0707ff;
            color: white;
            padding: 8px;
            text-align: left;
            border: 1px solid #ffffffff;
            font-size: 11px;
        }
        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .status-waiting { color: #e67e22; font-weight: bold; }
        .status-processing { color: #3498db; font-weight: bold; }
        .status-delivered { color: #27ae60; font-weight: bold; }
        .status-cancelled { color: #e74c3c; font-weight: bold; }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            border: 1px solid #bdc3c7;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
            border-top: 1px solid #bdc3c7;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN DATA PEMESANAN</h1>
        <div class="subtitle">KOPERASI Koperasi Desa Merah Putih Ganjar Sabar</div>
        <div class="subtitle">Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Periode:</span>
            <span>' . (!empty($date_from) ? date('d/m/Y', strtotime($date_from)) : 'Semua Tanggal') . ' - ' . (!empty($date_to) ? date('d/m/Y', strtotime($date_to)) : 'Semua Tanggal') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status Filter:</span>
            <span>' . (!empty($status_filter) ? $status_filter : 'Semua Status') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Kata Kunci:</span>
            <span>' . (!empty($search) ? htmlspecialchars($search) : '-') . '</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="40">No. Order</th>
                <th width="100">Tanggal</th>
                <th width="120">Pemesan</th>
                <th width="150">Produk</th>
                <th width="20" class="text-center">Items</th>
                <th width="60" class="text-right">Total</th>
                <th width="40" class="text-center">Metode</th>
                <th width="40" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>';

$counter = 0;
while ($pemesanan = $pemesanan_result->fetch_assoc()) {
    $counter++;
    $status_class = 'status-' . strtolower($pemesanan['status']);

    $html .= '
            <tr>
                <td>#' . $pemesanan['id_pemesanan'] . '</td>
                <td>' . date('d/m/Y H:i', strtotime($pemesanan['tanggal_pesan'])) . '</td>
                <td>' . htmlspecialchars($pemesanan['nama_anggota']) . '<br><small>ID: ' . $pemesanan['id_anggota'] . '</small></td>
                <td>' . htmlspecialchars($pemesanan['produk']) . '</td>
                <td class="text-center">' . $pemesanan['jumlah_produk'] . '</td>
                <td class="text-right">Rp ' . number_format($pemesanan['total_harga'], 0, ',', '.') . '</td>
                <td class="text-center">' . $pemesanan['metode'] . '</td>
                <td class="text-center ' . $status_class . '">' . $pemesanan['status'] . '</td>
            </tr>';
}

if ($counter === 0) {
    $html .= '
            <tr>
                <td colspan="8" class="text-center" style="padding: 20px; color: #7f8c8d;">
                    Tidak ada data pemesanan
                </td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="summary">
        <div class="info-row">
            <span class="info-label">Total Pemesanan:</span>
            <span>' . $total_pemesanan . ' order</span>
        </div>
        <div class="info-row">
            <span class="info-label">Total Nilai:</span>
            <span>Rp ' . number_format($total_nilai, 0, ',', '.') . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status Breakdown:</span>
            <span>
                Menunggu: ' . $status_count['Menunggu'] . ' | 
                Diproses: ' . $status_count['Diproses'] . ' | 
                Terkirim: ' . $status_count['Terkirim'] . ' | 
                Dibatalkan: ' . $status_count['Dibatalkan'] . '
            </span>
        </div>
    </div>

    <div class="footer">
        Laporan ini dicetak secara otomatis dari Sistem Koperasi Desa Merah Putih Ganjar Sabar<br>
        Hak Cipta © ' . date('Y') . ' KDMPGS - All rights reserved
    </div>
</body>
</html>';

// Konfigurasi DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Output PDF
$dompdf->stream('laporan_pemesanan_' . date('Y-m-d') . '.pdf', [
    'Attachment' => true
]);

$stmt->close();
$conn->close();
exit;
?>