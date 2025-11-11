<?php
// Include file history log
require_once 'functions/history_log.php';
// Handle actions (update status, hapus, dll)
// Handle actions (update status, hapus, dll)
if (isset($_POST['update_status'])) {
    $id_reject = intval($_POST['id_reject']);
    $status_tindaklanjut = $conn->real_escape_string($_POST['status_tindaklanjut']);
    $catatan_tindaklanjut = $conn->real_escape_string($_POST['catatan_tindaklanjut']);

    // Ambil info barang reject sebelum update untuk log
    $reject_info = $conn->query("SELECT nama_produk, jumlah_reject, satuan_kecil, status_tindaklanjut FROM barang_rejek WHERE id_reject = '$id_reject'")->fetch_assoc();

    $sql = "UPDATE barang_rejek 
            SET status_tindaklanjut = '$status_tindaklanjut', 
                catatan_tindaklanjut = '$catatan_tindaklanjut',
                updated_at = NOW(),
                updated_by = '{$_SESSION['username']}'
            WHERE id_reject = '$id_reject'";

    if ($conn->query($sql)) {
        // LOG ACTIVITY: Update status barang reject
        $description = "Mengupdate status barang reject: {$reject_info['nama_produk']} ";
        $description .= "({$reject_info['jumlah_reject']} {$reject_info['satuan_kecil']}) ";
        $description .= "dari {$reject_info['status_tindaklanjut']} menjadi $status_tindaklanjut";

        if ($catatan_tindaklanjut) {
            $description .= " - Catatan: $catatan_tindaklanjut";
        }

        log_activity(
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'system',
            'update_status',
            $description,
            'barang_reject',
            $id_reject
        );

        $_SESSION['success'] = "Status barang reject berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal update status: " . $conn->error;
    }
    header("Location: dashboard.php?page=stock_opname");
    exit;
}

if (isset($_GET['delete'])) {
    $id_reject = intval($_GET['delete']);

    // Ambil info barang reject sebelum dihapus untuk log
    $reject_info = $conn->query("SELECT br.nama_produk, br.jumlah_reject, br.satuan_kecil, s.nama_supplier 
                                FROM barang_rejek br
                                LEFT JOIN purchase_order_items poi ON br.id_po_item = poi.id_item
                                LEFT JOIN purchase_order po ON poi.id_po = po.id_po
                                LEFT JOIN supplier s ON po.id_supplier = s.id_supplier
                                WHERE br.id_reject = '$id_reject'")->fetch_assoc();

    $sql = "DELETE FROM barang_rejek WHERE id_reject = '$id_reject'";
    if ($conn->query($sql)) {
        // LOG ACTIVITY: Hapus barang reject
        $description = "Menghapus data barang reject: {$reject_info['nama_produk']} ";
        $description .= "({$reject_info['jumlah_reject']} {$reject_info['satuan_kecil']}) ";
        $description .= "dari supplier {$reject_info['nama_supplier']}";

        log_activity(
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'system',
            'delete',
            $description,
            'barang_reject',
            $id_reject
        );

        $_SESSION['success'] = "Data barang reject berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus data: " . $conn->error;
    }
    header("Location: dashboard.php?page=stock_opname");
    exit;
}

// Query data barang reject dengan join untuk informasi lengkap
$query = "SELECT 
            br.id_reject,
            br.created_at,
            br.nama_produk,
            br.kode_produk,
            br.jumlah_reject,
            br.satuan_kecil,
            br.alasan_reject,
            br.status_tindaklanjut,
            br.catatan_tindaklanjut,
            po.no_invoice,
            s.nama_supplier,
            u.username as nama_petugas_qc
          FROM barang_rejek br
          LEFT JOIN qc_result qc ON br.id_qc = qc.id_qc
          LEFT JOIN purchase_order_items poi ON br.id_po_item = poi.id_item
          LEFT JOIN purchase_order po ON poi.id_po = po.id_po
          LEFT JOIN supplier s ON po.id_supplier = s.id_supplier
          LEFT JOIN pengurus u ON qc.qc_by = u.id
          ORDER BY br.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang Reject - Sistem Inventory</title>
    <style>
        .badge-return_supplier { background-color: #dc3545; }
        .badge-diperbaiki { background-color: #fd7e14; }
        .badge-destroyed { background-color: #6c757d; }
        .badge-digunakan { background-color: #20c997; }
        .badge-selesai { background-color: #198754; }
        .table th { background-color: #f8f9fa; }
        .card-header { background-color: #f8f9fa; border-bottom: 1px solid #e3e6f0; }
        .action-buttons .btn { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-times-circle text-danger me-2"></i>Data Barang Reject</h2>
            <div>
                <a href="pages/export_barang_reject.php" class="btn btn-success">
                    <i class="fas fa-file-csv me-2"></i>Export Csv
                </a>
            </div>
        </div>

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php
                                    $total_reject = $conn->query("SELECT SUM(jumlah_reject) as total FROM barang_rejek")->fetch_assoc()['total'];
                                    echo $total_reject ?: '0';
                                    ?>
                                </h4>
                                <span>Total Barang Reject</span>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-times-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php
                                    $pending = $conn->query("SELECT COUNT(*) as total FROM barang_rejek WHERE status_tindaklanjut = 'return_supplier'")->fetch_assoc()['total'];
                                    echo $pending;
                                    ?>
                                </h4>
                                <span>Pending Tindakan</span>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php
                                    $selesai = $conn->query("SELECT COUNT(*) as total FROM barang_rejek WHERE status_tindaklanjut = 'selesai'")->fetch_assoc()['total'];
                                    echo $selesai;
                                    ?>
                                </h4>
                                <span>Telah Diselesaikan</span>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0">
                                    <?php
                                    $diperbaiki = $conn->query("SELECT COUNT(*) as total FROM barang_rejek WHERE status_tindaklanjut = 'diperbaiki'")->fetch_assoc()['total'];
                                    echo $diperbaiki;
                                    ?>
                                </h4>
                                <span>Dalam Perbaikan</span>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tools fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <!-- Tabel Data -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Daftar Barang Reject</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabelBarangReject" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>No. Invoice</th>
                        <th>Supplier</th>
                        <th>Nama Produk</th>
                        <th>Jumlah Reject</th>
                        <th>Alasan Reject</th>
                        <th>Status Tindakan</th>
                        <th>Petugas QC</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        $no = 1;
                        while ($row = $result->fetch_assoc()) {
                            $status_class = '';
                            $status_text = '';
                            
                            switch ($row['status_tindaklanjut']) {
                                case 'return_supplier':
                                    $status_class = 'badge-return_supplier';
                                    $status_text = 'Return Supplier';
                                    break;
                                case 'diperbaiki':
                                    $status_class = 'badge-diperbaiki';
                                    $status_text = 'Diperbaiki';
                                    break;
                                case 'destroyed':
                                    $status_class = 'badge-destroyed';
                                    $status_text = 'Dimusnahkan';
                                    break;
                                case 'digunakan':
                                    $status_class = 'badge-digunakan';
                                    $status_text = 'Digunakan Internal';
                                    break;
                                case 'selesai':
                                    $status_class = 'badge-selesai';
                                    $status_text = 'Selesai';
                                    break;
                                default:
                                    $status_class = 'badge-return_supplier';
                                    $status_text = 'Return Supplier';
                            }

                            echo "
                            <tr>
                                <td>{$no}</td>
                                <td>" . date('d/m/Y', strtotime($row['created_at'])) . "</td>
                                <td>" . ($row['no_invoice'] ?? '-') . "</td>
                                <td>" . ($row['nama_supplier'] ?? '-') . "</td>
                                <td>
                                    <strong>{$row['nama_produk']}</strong>
                                    " . (!empty($row['kode_produk']) ? "<br><small class='text-muted'>Kode: {$row['kode_produk']}</small>" : "") . "
                                </td>
                                <td>
                                    <span class='badge bg-danger'>{$row['jumlah_reject']} {$row['satuan_kecil']}</span>
                                </td>
                                <td>{$row['alasan_reject']}</td>
                                <td><span class='badge {$status_class}'>{$status_text}</span></td>
                                <td>" . ($row['nama_petugas_qc'] ?? '-') . "</td>
                                <td class='action-buttons'>
                                    <button class='btn btn-sm btn-warning btn-edit' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#editModal'
                                            data-id='{$row['id_reject']}'
                                            data-status='{$row['status_tindaklanjut']}'
                                            data-catatan='" . ($row['catatan_tindaklanjut'] ?? '') . "'>
                                        <i class='fas fa-edit'></i>
                                    </button>
                                    <button class='btn btn-sm btn-info btn-detail' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#detailModal'
                                            data-id='{$row['id_reject']}'>
                                        <i class='fas fa-eye'></i>
                                    </button>
                                    <a href='?delete={$row['id_reject']}' 
                                       class='btn btn-sm btn-danger btn-delete' 
                                       onclick=\"return confirm('Yakin hapus data ini?')\">
                                        <i class='fas fa-trash'></i>
                                    </a>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        // ✅ PASTIKAN COLSPAN SESUAI DENGAN JUMLAH KOLOM (10)
                        echo "<tr><td colspan='10' class='text-center py-4'>
                                <i class='fas fa-box-open fa-2x text-muted mb-2'></i><br>
                                <span class='text-muted'>Tidak ada data barang reject</span>
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Modal Edit Status -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status Tindakan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="id_reject" id="edit_id_reject">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Status Tindakan</label>
                            <select name="status_tindaklanjut" id="edit_status" class="form-select" required>
                                <option value="return_supplier">Return ke Supplier</option>
                                <option value="diperbaiki">Diperbaiki</option>
                                <option value="destroyed">Dimusnahkan</option>
                                <option value="digunakan">Digunakan Internal</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Catatan Tindakan</label>
                            <textarea name="catatan_tindaklanjut" id="edit_catatan" class="form-control" rows="3" placeholder="Keterangan tindakan yang dilakukan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Barang Reject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Detail akan di-load via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        if ($('#tabelBarangReject').length && !$.fn.DataTable.isDataTable('#tabelBarangReject')) {
            $('#tabelBarangReject').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: "Cari Barang Rejek : ",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    zeroRecords: "Tidak ada data yang cocok",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
                }
                columnDefs: [
                { targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], orderable: true }
            ]
            });
        }

        // Edit Modal Handler
        $('.btn-edit').on('click', function() {
            const id = $(this).data('id');
            const status = $(this).data('status');
            const catatan = $(this).data('catatan');
            
            $('#edit_id_reject').val(id);
            $('#edit_status').val(status);
            $('#edit_catatan').val(catatan || '');
        });

        // Detail Modal Handler
        $('.btn-detail').on('click', function() {
            const id = $(this).data('id');
            
            $('#detailContent').html(`
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Memuat data...</p>
                </div>
            `);
            
            $.ajax({
                url: 'ajax/get_detail_reject.php',
                method: 'GET',
                data: { id: id },
                success: function(response) {
                    $('#detailContent').html(response);
                },
                error: function() {
                    $('#detailContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Gagal memuat data detail
                        </div>
                    `);
                }
            });
        });

        // Confirm delete
        $('.btn-danger').on('click', function(e) {
            if (!confirm('Yakin ingin menghapus data ini?')) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>

<?php $conn->close(); ?>
