<?php
// Set timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');
require 'koneksi/koneksi.php';
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
    
    // Format tanggal transaksi dengan jam (format: d/m/Y, H:i:s)
    $transaksi['tanggal_bayar_format'] = date('d/m/Y, H:i:s', strtotime($transaksi['tanggal_bayar']));
    
    // Render HTML template
    ob_start();
    include 'template_bukti_transaksi.php';
    $html = ob_get_clean();
    
    // Setup dompdf
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A6', 'portrait');
    
    // Render PDF
    $dompdf->render();
    
    // Output PDF untuk di-download
    $dompdf->stream("bukti_transaksi_{$transaksi['id_transaksi']}.pdf", 
                   array("Attachment" => true));
} else {
    echo "Transaksi tidak ditemukan.";
}

$stmt->close();
$conn->close();
?>
