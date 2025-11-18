<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header("Location: ../dashboard.php");
    exit;
}
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
date_default_timezone_set("Asia/Jakarta");

require 'koneksi/koneksi.php';
// Ambil ID PO dari parameter URL
$id_po = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : 0;

// Query data PO
$query_po = "SELECT po.*, s.nama_supplier, s.alamat, s.no_telp, s.email 
             FROM purchase_order po 
             JOIN supplier s ON po.id_supplier = s.id_supplier 
             WHERE po.id_po = '$id_po'";
$result_po = $conn->query($query_po);
$po = $result_po->fetch_assoc();

if (!$po) {
    die("Data PO tidak ditemukan");
}

// Query items PO
$query_items = "SELECT poi.*, sp.nama_produk, sp.satuan_besar, sp.kategori
                FROM purchase_order_items poi 
                JOIN supplier_produk sp ON poi.id_supplier_produk = sp.id_supplier_produk 
                WHERE poi.id_po = '$id_po'";
$result_items = $conn->query($query_items);

// Path logo
$logo_path = '../logo/logo.png';
$logo_data = '';
if (file_exists($logo_path)) {
    $logo_type = pathinfo($logo_path, PATHINFO_EXTENSION);
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/' . $logo_type . ';base64,' . base64_encode($logo_data);
} else {
    $logo_base64 = '';
}

// Generate HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>QC Report - ' . $po['no_invoice'] . '</title>
    <style>
        @page {
            margin: 1.5cm;
        }
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif; 
            margin: 0;
            padding: 0;
            color: #000;
            font-size: 12px;
            line-height: 1.4;
        }
        .header-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #c00;
            padding-bottom: 10px;
        }
        .logo {
            width: 70px;
            margin-right: 15px;
        }
        .header-content {
            flex: 1;
            text-align: center;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            color: #900;
            margin-bottom: 3px;
        }
        .company-details {
            font-size: 10px;
            margin-bottom: 2px;
        }
        .document-title {
            text-align: center;
            margin: 20px 0;
            font-size: 16px;
            font-weight: bold;
            text-decoration: underline;
        }
        .info-section {
            margin: 15px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .qc-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .qc-table th, .qc-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        .qc-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-left {
            text-align: left;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .signature-section {
            margin-top: 50px;
            width: 100%;
        }
        .signature-area {
            text-align: center;
            width: 45%;
            float: left;
            margin-right: 10%;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto;
            padding-top: 40px;
        }
        .clear {
            clear: both;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            text-align: center;
        }
        .qc-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-lulus {
            background-color: #d4edda;
            color: #155724;
        }
        .status-tolak {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header-container">';

if (!empty($logo_base64)) {
    $html .= '<img src="' . $logo_base64 . '" class="logo" alt="Logo KDMPGS">';
}

$html .= '
        <div class="header-content">
            <div class="company-name">KOPERASI DESA MERAH PUTIH GANJAR SABAR</div>
            <div class="company-details">Akta Notaris : H. Zainur Rokhim, S.H., M.Kn | Badan Hukum : No. AHU-0060580.AH.U.29.Tahun 2025</div>
            <div class="company-details">NIK: 3204101140006 NIB: 1007250071955</div>
            <div class="company-details">Jl. Enuk Marinah RT. 01 RW. 07 Desa Ganjar Sabar Kecamatan Nagreg</div>
        </div>
    </div>
    
    <div class="document-title">LAPORAN QUALITY CONTROL (QC)</div>
    
    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="info-label">No. Invoice PO</td>
                <td>: ' . htmlspecialchars($po['no_invoice']) . '</td>
                <td class="info-label">Tanggal Pemeriksaan</td>
                <td>: ' . date('d/m/Y') . '</td>
            </tr>
            <tr>
                <td class="info-label">Supplier</td>
                <td>: ' . htmlspecialchars($po['nama_supplier']) . '</td>
                <td class="info-label">Petugas QC</td>
                <td>: ' . htmlspecialchars($_SESSION['username'] ?? 'Petugas QC') . '</td>
            </tr>
            <tr>
                <td class="info-label">Alamat Supplier</td>
                <td>: ' . htmlspecialchars($po['alamat']) . '</td>
                <td class="info-label">Tanggal Terima</td>
                <td>: ' . date('d/m/Y', strtotime($po['tanggal_pengiriman'])) . '</td>
            </tr>
        </table>
    </div>
    
    <table class="qc-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="20%">Nama Produk</th>
                <th width="10%">Kategori</th>
                <th width="10%">Quantity</th>
                <th width="10%">Satuan</th>
                <th width="10%">Bagus</th>
                <th width="10%">Jelek</th>
                <th width="20%">Keterangan</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
while ($item = $result_items->fetch_assoc()) {
    $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td class="text-left">' . htmlspecialchars($item['nama_produk']) . '</td>
                <td class="text-center">' . htmlspecialchars($item['kategori'] ?? '-') . '</td>
                <td class="text-center">' . $item['qty'] . '</td>
                <td class="text-center">' . htmlspecialchars($item['satuan']) . '</td>
                <td class="text-center"></td>
                <td class="text-center"></td>
                <td class="text-center"></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="signature-section">
        <div class="signature-area">
            <div>Petugas Quality Control</div>
            <div class="signature-line"></div>
            <br>
            <div>' . htmlspecialchars($_SESSION['username'] ?? '..............') . '</div>
        </div>
        
        <div class="signature-area">
            <div>Manager Gudang</div>
            <div class="signature-line"></div>
            <br>
            <div>..............</div>
        </div>
        <div class="clear"></div>
    </div>
    
    <div class="footer">
        <p>Dokumen ini dicetak pada: ' . date('d/m/Y H:i:s') . ' | Halaman 1 dari 1</p>
    </div>
</body>
</html>';

$dompdf = new Dompdf();
$dompdf->getOptions()->setIsRemoteEnabled(true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF untuk di-download
$dompdf->stream('QC-Report-' . $po['no_invoice'] . '.pdf', ['Attachment' => true]);

$conn->close();
?>
