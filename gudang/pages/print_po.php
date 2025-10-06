<?php
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
date_default_timezone_set("Asia/Jakarta");
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$id_po = $_GET['id'];
$query_po = "SELECT po.*, s.nama_supplier, s.alamat, s.no_telp, s.email 
             FROM purchase_order po 
             JOIN supplier s ON po.id_supplier = s.id_supplier 
             WHERE po.id_po = '$id_po'";
$result_po = $conn->query($query_po);
$po = $result_po->fetch_assoc();

// Path logo - konversi ke base64
$logo_path = 'logo/logo.png';

// Cek jika file logo exists dan convert ke base64
$logo_data = '';
if (file_exists($logo_path)) {
    $logo_type = pathinfo($logo_path, PATHINFO_EXTENSION);
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/' . $logo_type . ';base64,' . base64_encode($logo_data);
} else {
    // Fallback jika logo tidak ditemukan
    $logo_base64 = '';
}

// Fungsi untuk generate nomor surat sesuai format
// Fungsi untuk generate nomor surat sesuai format
function generateNomorSurat($id_po, $conn)
{
    $current_month = date('n');
    $roman_numerals = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII'
    ];

    // Query untuk mendapatkan jumlah PO berdasarkan bulan dan tahun
    $month = date('m');
    $year = date('Y');

    $query = "SELECT COUNT(*) as total FROM purchase_order 
              WHERE MONTH(tanggal_order) = '$month' 
              AND YEAR(tanggal_order) = '$year' 
              AND id_po <= '$id_po'";

    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    $total = $data['total'];

    // Format nomor urut dengan leading zeros
    $nomor_urut = str_pad($total, 3, '0', STR_PAD_LEFT);

    return $nomor_urut . '/KDMPGS/' . $roman_numerals[$current_month] . '/' . date('Y');
}

// Sekarang buat variabel untuk nomor surat
$nomor_surat = generateNomorSurat($po['id_po'], $conn);

$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order - ' . $po['no_invoice'] . '</title>
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
            align-items: flex-start;
            margin-bottom: 10px;
            border-bottom: 2px solid #c00;
            padding-bottom: 10px;
        }
        .logo {
            width: 70px;
            margin-right: 15px;
        }
        .header-content {
            flex: 1;
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
            line-height: 1.3;
        }
        .contact-info {
            font-size: 10px;
            margin-top: 3px;
        }
        .surat-info {
            margin: 15px 0;
            font-size: 11px;
        }
        .nomor-surat {
            font-weight: bold;
            margin-bottom: 3px;
        }
        .recipient-table {
            width: 100%;
            margin: 10px 0;
        }
        .info-label {
            font-weight: bold;
            width: 90px;
            vertical-align: top;
            padding: 3px 0;
        }
        .recipient-info {
            padding: 3px 0;
        }
        .content {
            text-align: justify;
            margin: 10px 0;
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
            font-size: 10px;
        }
        .table th { 
            background-color: #ffffffff;
            padding: 6px;
            text-align: center;
            border: 1px solid #000;
            font-weight: bold;
        }
        .table td { 
            padding: 5px;
            border: 1px solid #000;
        }
        .text-right { 
            text-align: right; 
        }
        .text-center { 
            text-align: center; 
        }
        .text-left { 
            text-align: left; 
        }
        .total-section { 
            margin-top: 15px; 
            padding: 8px;
            border: 1px solid #000;
            width: 50%;
            margin-left: auto;
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .total-label {
            font-weight: bold;
        }
        .discount-line {
            color: #d32f2f;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
        }
        .ttd-section {
            margin-top: 40px;
            width: 100%;
        }
        .ttd-area {
            text-align: center;
            width: 50%;
            float: right;
        }
        .ttd-name {
            font-weight: bold;
            margin-top: 50px;
        }
        .clear {
            clear: both;
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
            <div class="company-details">NIK: 3204101140006 | NIB: 1007250071955</div>
            <div class="company-details">Jl. Enuk Marinah RT. 01 RW. 07 Desa Ganjar Sabar Kecamatan Nagreg</div>
            <div class="contact-info">Telp: +6282117587151 / +62882002354299 | Email: kdmpanjarsabar@gmail.com</div>
        </div>
    </div>
    
    <div class="surat-info">
        <div class="nomor-surat">Nomor: ' . $nomor_surat . '</div>
        <div class="nomor-surat">Perihal: Permintaan Barang untuk Koperasi Desa Merah Putih Ganjar Sabar</div>
    </div>
    
    <table class="recipient-table">
        <tr>
            <td class="info-label">Kepada Yth</td>
            <td>:</td>
            <td class="recipient-info">' . $po['nama_supplier'] . '</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td>:</td>
            <td class="recipient-info">' . $po['alamat'] . '</td>
        </tr>
    </table>
    
    <div class="content">
        <p>Assalamu\'alaikum Wr. Wb.</p>
        <p>Sehubungan dengan kebutuhan penyediaan barang sembako bagi anggota KDMP Ganjar Sabar, bersama ini kami bermaksud mengajukan permintaan pembelian dengan rincian sebagai berikut:</p>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th width="5%">NO</th>
                <th width="45%">NAMA BARANG</th>
                <th width="15%">QUANTITY</th>
                <th width="15%">HARGA (Rp)</th>
                <th width="20%">TOTAL</th>
            </tr>
        </thead>
        <tbody>';

$query_items = "SELECT poi.*, sp.nama_produk, sp.satuan_besar
                FROM purchase_order_items poi 
                JOIN supplier_produk sp ON poi.id_supplier_produk = sp.id_supplier_produk 
                WHERE poi.id_po = '$id_po'";
$result_items = $conn->query($query_items);
$no = 1;
$subtotal = 0;

while ($item = $result_items->fetch_assoc()) {
    $total_harga = $item['qty'] * $item['harga_satuan'];
    $subtotal += $total_harga;

    // Format quantity dengan satuan
    $quantity = $item['qty'] . ' ' . $item['satuan'];

    $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td class="text-left">' . $item['nama_produk'] . '</td>
                <td class="text-center">' . $quantity . '</td>
                <td class="text-right">' . number_format($item['harga_satuan'], 0, ',', '.') . '</td>
                <td class="text-right">' . number_format($total_harga, 0, ',', '.') . '</td>
            </tr>';
}

// Hitung discount dan total
$discount_amount = $subtotal * ($po['discount'] / 100);
$total_after_discount = $subtotal - $discount_amount;

$html .= '
        </tbody>
    </table>
    
    <div class="total-section">
        <div class="total-line">
            <span class="total-label">Subtotal:</span>
            <span>Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
        </div>';

if ($po['discount'] > 0) {
    $html .= '
        <div class="total-line discount-line">
            <span class="total-label">Diskon (' . $po['discount'] . '%):</span>
            <span>- Rp ' . number_format($discount_amount, 0, ',', '.') . '</span>
        </div>';
}

$html .= '
        <div class="total-line">
            <span class="total-label">TOTAL:</span>
            <span><strong>Rp ' . number_format($total_after_discount, 0, ',', '.') . '</strong></span>
        </div>
    </div>
    
    <div class="content">
        <p>Demikian surat permohonan pemesanan ini kami sampaikan. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.</p>
        <p>Wassalamu\'alaikum Wr. Wb.</p>
    </div>
    
    <div class="ttd-section">
        <div class="ttd-area">
            Ganjar Sabar, ' . date('d F Y', strtotime($po['tanggal_order'])) . '<br>
            Ketua KDMP Ganjar Sabar
            <div class="ttd-name">Purnama, S.E.,</div>
        </div>
        <div class="clear"></div>
    </div>
    
    <div class="footer">
        <p>Dokumen ini dicetak pada: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

$dompdf = new Dompdf();

// Enable remote images (untuk logo)
$dompdf->getOptions()->setIsRemoteEnabled(true);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('po-' . $po['no_invoice'] . '.pdf', ['Attachment' => true]);
?>