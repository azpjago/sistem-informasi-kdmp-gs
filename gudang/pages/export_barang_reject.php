<?php
session_start();
require_once 'functions/history_log.php';

if (!isset($_SESSION['is_logged_in']) || !in_array($_SESSION['role'], ['gudang', 'qc', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// LOG ACTIVITY: Export CSV
log_activity(
    $_SESSION['user_id'] ?? 0,
    $_SESSION['role'] ?? 'system',
    'export_csv',
    'Mengekspor data barang reject ke file CSV',
    'barang_reject',
    0
);

// Query data barang reject dengan join untuk informasi lengkap
$query = "SELECT 
            br.id_reject,
            br.kode_produk,
            br.nama_produk,
            br.jumlah_reject,
            br.satuan_kecil,
            br.alasan_reject,
            br.status_tindaklanjut,
            br.catatan_tindaklanjut,
            br.created_at,
            br.updated_at,
            s.nama_supplier,
            po.no_invoice,
            po.tanggal_order,
            u.username as nama_petugas_qc,
            qc.qty_diterima,
            qc.qty_bagus,
            qc.catatan as catatan_qc
          FROM barang_rejek br
          LEFT JOIN qc_result qc ON br.id_qc = qc.id_qc
          LEFT JOIN purchase_order_items poi ON br.id_po_item = poi.id_item
          LEFT JOIN purchase_order po ON poi.id_po = po.id_po
          LEFT JOIN supplier s ON po.id_supplier = s.id_supplier
          LEFT JOIN pengurus u ON qc.qc_by = u.id
          ORDER BY br.created_at DESC";

$result = $conn->query($query);

// Set header untuk download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data_barang_reject_' . date('Y-m-d_H-i') . '.csv"');

// Buat output file CSV
$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, [
    'ID_REJECT',
    'TANGGAL_REJECT',
    'KODE_PRODUK',
    'NAMA_PRODUK',
    'JUMLAH_REJECT',
    'SATUAN',
    'ALASAN_REJECT',
    'STATUS_TINDAKAN',
    'CATATAN_TINDAKAN',
    'SUPPLIER',
    'NO_INVOICE',
    'TANGGAL_PO',
    'PETUGAS_QC',
    'QTY_DITERIMA',
    'QTY_BAGUS',
    'CATATAN_QC',
    'DIBUAT_PADA',
    'DIUPDATE_PADA'
], ';');

// Data rows
while ($row = $result->fetch_assoc()) {
    // Format status untuk lebih readable
    $status_text = [
        'return_supplier' => 'Return Supplier',
        'diperbaiki' => 'Diperbaiki',
        'destroyed' => 'Dimusnahkan',
        'digunakan' => 'Digunakan Internal',
        'selesai' => 'Selesai'
    ];

    $status = $status_text[$row['status_tindaklanjut']] ?? $row['status_tindaklanjut'];

    fputcsv($output, [
        $row['id_reject'],
        date('d/m/Y', strtotime($row['created_at'])),
        $row['kode_produk'],
        $row['nama_produk'],
        $row['jumlah_reject'],
        $row['satuan_kecil'],
        $row['alasan_reject'],
        $status,
        $row['catatan_tindaklanjut'] ?: '',
        $row['nama_supplier'] ?: '',
        $row['no_invoice'] ?: '',
        $row['tanggal_po'] ? date('d/m/Y', strtotime($row['tanggal_po'])) : '',
        $row['nama_petugas_qc'] ?: '',
        $row['qty_diterima'] ?: '0',
        $row['qty_bagus'] ?: '0',
        $row['catatan_qc'] ?: '',
        date('d/m/Y H:i', strtotime($row['created_at'])),
        $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : ''
    ], ';');
}
fclose($output);
$conn->close();
exit;