<?php
// Set timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');
require 'koneksi/koneksi.php';
require 'functions/terbilang.php';

// Load library DOMPDF
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil ID transaksi dari parameter URL
$id = $_GET['id'] ?? 0;

// Query untuk mengambil data transaksi
$sql = "
    SELECT 
        p.*, 
        a.no_anggota, 
        a.nama 
    FROM 
        pembayaran p 
    JOIN 
        anggota a ON p.anggota_id = a.id 
    WHERE 
        p.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $transaksi = $result->fetch_assoc();
    $anggota = [
        'no_anggota' => $transaksi['no_anggota'],
        'nama' => $transaksi['nama']
    ];
    
    // Tambahkan waktu server saat ini ke data transaksi
    $transaksi['waktu_cetak'] = date('d/m/Y, H:i:s');
    
    // Format tanggal transaksi dengan jam
    $transaksi['tanggal_bayar_format'] = date('d/m/Y, H:i:s', strtotime($transaksi['tanggal_bayar']));
    
    // Format jumlah bayar
    $transaksi['jumlah_bayar_format'] = 'Rp ' . number_format($transaksi['jumlah_bayar'], 0, ',', '.');
    
    // Render HTML template
    ob_start();
    include 'template_bukti_transaksi.php';
    $html = ob_get_clean();
    
    // Setup dompdf untuk thermal printer (lebar 80mm)
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Courier'); // Font monospace untuk thermal
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    
    // Ukuran untuk thermal printer (80mm width)
    $dompdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm x 297mm
    
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream("bukti_transaksi_{$transaksi['id_transaksi']}.pdf", 
                   array("Attachment" => true));
} else {
    echo "Transaksi tidak ditemukan.";
}

$stmt->close();
$conn->close();
?>
