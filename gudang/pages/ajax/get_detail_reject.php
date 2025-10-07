<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
require_once 'unctions/history_log.php'; // Tambahkan include history_log

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // LOG ACTIVITY: View detail barang reject
    // Ambil info singkat untuk log
    $info_query = "SELECT nama_produk, jumlah_reject, satuan_kecil FROM barang_rejek WHERE id_reject = '$id'";
    $info_result = $conn->query($info_query);

    if ($info_result && $info_row = $info_result->fetch_assoc()) {
        $description = "Melihat detail barang reject: {$info_row['nama_produk']} ";
        $description .= "({$info_row['jumlah_reject']} {$info_row['satuan_kecil']})";

        log_activity(
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'system',
            'view_detail',
            $description,
            'barang_reject',
            $id
        );
    }

    $query = "SELECT 
                br.*,
                s.nama_supplier,
                po.no_invoice,
                po.tanggal_po,
                sp.nama_produk as nama_produk_supplier,
                u.username as nama_petugas_qc,
                qc.qty_diterima,
                qc.qty_bagus,
                qc.catatan as catatan_qc,
                qc.bukti_qc,
                br.created_at,
                br.updated_at,
                u2.username as updated_by_name
              FROM barang_rejek br
              LEFT JOIN qc_result qc ON br.id_qc = qc.id_qc
              LEFT JOIN purchase_order_items poi ON br.id_po_item = poi.id_item
              LEFT JOIN purchase_order po ON poi.id_po = po.id_po
              LEFT JOIN supplier s ON po.id_supplier = s.id_supplier
              LEFT JOIN supplier_produk sp ON br.id_supplier_produk = sp.id_supplier_produk
              LEFT JOIN pengurus u ON qc.qc_by = u.id
              LEFT JOIN pengurus u2 ON br.updated_by = u2.id
              WHERE br.id_reject = '$id'";

    $result = $conn->query($query);
    $data = $result->fetch_assoc();

    if ($data) {
        // Tentukan badge class berdasarkan status
        $status_class = '';
        $status_text = $data['status_tindaklanjut'];

        switch ($data['status_tindaklanjut']) {
            case 'return_supplier':
                $status_class = 'bg-danger';
                $status_text = 'Return Supplier';
                break;
            case 'diperbaiki':
                $status_class = 'bg-warning';
                $status_text = 'Diperbaiki';
                break;
            case 'destroyed':
                $status_class = 'bg-dark';
                $status_text = 'Dimusnahkan';
                break;
            case 'digunakan':
                $status_class = 'bg-info';
                $status_text = 'Digunakan Internal';
                break;
            case 'selesai':
                $status_class = 'bg-success';
                $status_text = 'Selesai';
                break;
            default:
                $status_class = 'bg-secondary';
        }

        echo '
        <div class="row">
            <div class="col-md-6">
                <h6>Informasi Produk</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nama Produk</strong></td><td>' . htmlspecialchars($data['nama_produk']) . '</td></tr>
                    <tr><td><strong>Kode Produk</strong></td><td>' . htmlspecialchars($data['kode_produk']) . '</td></tr>
                    <tr><td><strong>Jumlah Reject</strong></td><td><span class="badge bg-danger">' . $data['jumlah_reject'] . ' ' . $data['satuan_kecil'] . '</span></td></tr>
                    <tr><td><strong>Supplier</strong></td><td>' . htmlspecialchars($data['nama_supplier']) . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Informasi PO & QC</h6>
                <table class="table table-sm">
                    <tr><td><strong>No. Invoice</strong></td><td>' . htmlspecialchars($data['no_invoice']) . '</td></tr>
                    <tr><td><strong>Tanggal PO</strong></td><td>' . date('d/m/Y', strtotime($data['tanggal_po'])) . '</td></tr>
                    <tr><td><strong>Petugas QC</strong></td><td>' . htmlspecialchars($data['nama_petugas_qc']) . '</td></tr>
                    <tr><td><strong>Qty Diterima</strong></td><td>' . $data['qty_diterima'] . '</td></tr>
                    <tr><td><strong>Qty Bagus</strong></td><td>' . $data['qty_bagus'] . '</td></tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Alasan & Tindakan</h6>
                <table class="table table-sm">
                    <tr><td><strong>Alasan Reject</strong></td><td>' . htmlspecialchars($data['alasan_reject']) . '</td></tr>
                    <tr><td><strong>Status Tindakan</strong></td><td><span class="badge ' . $status_class . '">' . $status_text . '</span></td></tr>
                    <tr><td><strong>Catatan Tindakan</strong></td><td>' . ($data['catatan_tindaklanjut'] ? htmlspecialchars($data['catatan_tindaklanjut']) : '-') . '</td></tr>
                    <tr><td><strong>Catatan QC</strong></td><td>' . htmlspecialchars($data['catatan_qc']) . '</td></tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Timestamps</h6>
                <table class="table table-sm">
                    <tr><td><strong>Dibuat Pada</strong></td><td>' . date('d/m/Y H:i', strtotime($data['created_at'])) . '</td></tr>
                    <tr><td><strong>Diupdate Pada</strong></td><td>' . ($data['updated_at'] ? date('d/m/Y H:i', strtotime($data['updated_at'])) : '-') . '</td></tr>
                    <tr><td><strong>Diupdate Oleh</strong></td><td>' . ($data['updated_by_name'] ? htmlspecialchars($data['updated_by_name']) : '-') . '</td></tr>
                </table>
            </div>
        </div>';

        if ($data['bukti_qc']) {
            echo '
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Bukti QC</h6>
                    <img src="../' . htmlspecialchars($data['bukti_qc']) . '" class="img-fluid rounded" style="max-height: 300px;" alt="Bukti QC">
                </div>
            </div>';
        }
    } else {
        echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    }
}
?>