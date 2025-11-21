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

if ($id == 0) {
    die("ID transaksi tidak valid.");
}

// Query untuk mengambil data transaksi dengan join yang lebih lengkap
$sql = "
    SELECT 
        p.*, 
        a.no_anggota, 
        a.nama,
        a.alamat
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
    
    // Format data untuk tampilan
    $data = [
        'id_transaksi' => $transaksi['id_transaksi'] ?? '-',
        'no_anggota' => $transaksi['no_anggota'] ?? '-',
        'nama' => $transaksi['nama'] ?? '-',
        'alamat' => $transaksi['alamat'] ?? '-',
        'jumlah_bayar' => number_format($transaksi['jumlah_bayar'] ?? 0, 0, ',', '.'),
        'jenis_simpanan' => $transaksi['jenis_simpanan'] ?? '-',
        'keterangan' => $transaksi['keterangan'] ?? '-',
        'tanggal_bayar' => date('d/m/Y H:i:s', strtotime($transaksi['tanggal_bayar'])),
        'waktu_cetak' => date('d/m/Y H:i:s'),
        'metode_bayar' => $transaksi['metode_bayar'] ?? 'Tunai',
        'status' => $transaksi['status'] ?? 'Sukses'
    ];
    
    // Render HTML template
    ob_start();
    include 'template_bukti_transaksi.php';
    $html = ob_get_clean();
    
    // Setup dompdf dengan options
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A6', 'portrait');
    $dompdf->set_option('isHtml5ParserEnabled', true);
    
    // Render PDF
    $dompdf->render();
    
    // Output PDF untuk di-download
    $filename = "bukti_transaksi_{$data['id_transaksi']}.pdf";
    $dompdf->stream($filename, array("Attachment" => true));
    
} else {
    echo "<script>alert('Transaksi tidak ditemukan.'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
