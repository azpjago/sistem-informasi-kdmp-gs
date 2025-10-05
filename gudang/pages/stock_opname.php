<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header("Location: ../dashboard.php");
    exit;
}

// Include file history log
require_once 'functions/history_log.php';

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// PROSES TAMBAH STOCK OPNAME
if (isset($_POST['tambah_so'])) {
    $tanggal_so = $conn->real_escape_string($_POST['tanggal_so']);
    $periode_so = $conn->real_escape_string($_POST['periode_so']);
    $catatan = $conn->real_escape_string($_POST['catatan'] ?? '');
    $id_petugas = $_SESSION['user_id'];
    
    // Generate nomor SO
    $no_so = "SO/" . date('Ymd') . "/" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $conn->begin_transaction();
    
    try {
        // Insert header SO
        $query_header = "INSERT INTO stock_opname_header (no_so, tanggal_so, periode_so, id_petugas, catatan, status) 
                        VALUES ('$no_so', '$tanggal_so', '$periode_so', '$id_petugas', '$catatan', 'waiting_approval')";
        
        if ($conn->query($query_header)) {
            $id_so = $conn->insert_id;
            
            // LOG: Pembuatan Stock Opname
            log_so_creation($id_so, $no_so, $periode_so, 'gudang');
            
            // Insert detail SO
            if (isset($_POST['id_inventory']) && is_array($_POST['id_inventory'])) {
                foreach ($_POST['id_inventory'] as $index => $id_inventory) {
                    $id_inventory_clean = intval($id_inventory);
                    $stok_fisik = floatval($_POST['stok_fisik'][$index]);
                    $status_kondisi = $conn->real_escape_string($_POST['status_kondisi'][$index]);
                    $analisis_penyebab = $conn->real_escape_string($_POST['analisis_penyebab'][$index] ?? '');
                    
                    // Get stok sistem
                    $query_stok = "SELECT jumlah_tersedia FROM inventory_ready WHERE id_inventory = '$id_inventory_clean'";
                    $result_stok = $conn->query($query_stok);
                    $stok_sistem = 0;
                    
                    if ($result_stok && $result_stok->num_rows > 0) {
                        $row_stok = $result_stok->fetch_assoc();
                        $stok_sistem = $row_stok['jumlah_tersedia'];
                    }
                    
                    $selisih = $stok_fisik - $stok_sistem;
                    
                    // Handle file upload untuk foto bukti
                    $foto_bukti = null;
                    if (!empty($_FILES['foto_bukti']['name'][$index])) {
                        $uploadDir = __DIR__ . "/../uploads/so/";
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileName = "so_" . time() . "_" . $index . "_" . basename($_FILES['foto_bukti']['name'][$index]);
                        $target = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'][$index], $target)) {
                            $foto_bukti = "uploads/so/" . $fileName;
                        }
                    }
                    
                    $query_detail = "INSERT INTO stock_opname_detail 
                                    (id_so, id_inventory, stok_sistem, stok_fisik, selisih, 
                                     status_kondisi, foto_bukti, analisis_penyebab) 
                                    VALUES ('$id_so', '$id_inventory_clean', '$stok_sistem', 
                                            '$stok_fisik', '$selisih', '$status_kondisi', 
                                            '$foto_bukti', '$analisis_penyebab')";
                    
                    if (!$conn->query($query_detail)) {
                        throw new Exception("Gagal menyimpan detail SO: " . $conn->error);
                    }
                    
                    $id_so_detail = $conn->insert_id;
                    
                    // Jika status kondisi rusak/busuk, tambahkan ke barang_rusak_so
                    if (in_array($status_kondisi, ['Rusak Ringan', 'Rusak Berat', 'Busuk', 'Expired'])) {
                        $jumlah_rusak = $stok_sistem - $stok_fisik; // Asumsi selisih negatif = rusak
                        if ($jumlah_rusak > 0) {
                            $query_rusak = "INSERT INTO barang_rusak_so 
                                           (id_so_detail, id_inventory, jumlah_rusak, 
                                            jenis_kerusakan, foto_bukti) 
                                           VALUES ('$id_so_detail', '$id_inventory_clean', 
                                                   '$jumlah_rusak', '$status_kondisi', '$foto_bukti')";
                            
                            if (!$conn->query($query_rusak)) {
                                throw new Exception("Gagal menyimpan data barang rusak: " . $conn->error);
                            }
                        }
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['success'] = "Stock Opname berhasil dibuat dengan nomor: $no_so";
        } else {
            throw new Exception("Gagal membuat header SO: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: dashboard.php?page=stock_opname");
    exit;
}

// PROSES APPROVAL SO - VERSI DIPERBAIKI
if (isset($_POST['approve_so'])) {
    $id_so = intval($_POST['id_so']);
    $approved_by = $_SESSION['user_id'];

    $conn->begin_transaction();

    try {
        // Update status SO
        $query_approve = "UPDATE stock_opname_header 
                         SET status = 'approved', approved_by = '$approved_by', approved_at = NOW() 
                         WHERE id_so = '$id_so' AND status = 'waiting_approval'";

        if ($conn->query($query_approve) && $conn->affected_rows > 0) {
            // Get SO info untuk log
            $query_so_info = "SELECT no_so FROM stock_opname_header WHERE id_so = '$id_so'";
            $result_so_info = $conn->query($query_so_info);
            $so_info = $result_so_info->fetch_assoc();
            $no_so = $so_info['no_so'];
            
            // LOG: Approval Stock Opname
            log_so_approval($id_so, $no_so, $_SESSION['role']);
            
            // Update inventory berdasarkan hasil SO yang approved
            $query_detail = "SELECT sod.*, so.no_so 
                           FROM stock_opname_detail sod
                           JOIN stock_opname_header so ON sod.id_so = so.id_so
                           WHERE sod.id_so = '$id_so'";
            $result_detail = $conn->query($query_detail);

            if ($result_detail && $result_detail->num_rows > 0) {
                while ($row = $result_detail->fetch_assoc()) {
                    // PERBAIKAN: gunakan nama kolom yang benar
                    $id_inventory = $row['id_inventory'];
                    $stok_fisik = $row['stok_fisik'];
                    $stok_sistem = $row['stok_sistem'];
                    $selisih = $row['selisih'];
                    $id_so_detail = $row['id_so_detail'];
                    $status_kondisi = $row['status_kondisi'];

                    // Tentukan jenis pergerakan stok
                    if ($selisih > 0) {
                        // SURPLUS: Stok fisik > Stok sistem (Perlu PENAMBAHAN)
                        $jenis_movement = 'penambahan';
                        $alasan = "Stock Opname Surplus - SO: {$row['no_so']}";

                        // Update inventory_ready dengan PENAMBAHAN
                        $query_update = "UPDATE inventory_ready 
                                        SET jumlah_tersedia = jumlah_tersedia + $selisih,
                                            updated_at = NOW() 
                                        WHERE id_inventory = '$id_inventory'";

                    } elseif ($selisih < 0) {
                        // MINUS: Stok fisik < Stok sistem (Perlu PENGURANGAN)
                        $jenis_movement = 'pengurangan';
                        $alasan = "Stock Opname Minus - SO: {$row['no_so']}";
                        $jumlah_pengurangan = abs($selisih); // Konversi ke positif

                        // Update inventory_ready dengan PENGURANGAN
                        $query_update = "UPDATE inventory_ready 
                                        SET jumlah_tersedia = jumlah_tersedia - $jumlah_pengurangan,
                                            updated_at = NOW() 
                                        WHERE id_inventory = '$id_inventory'";

                    } else {
                        // Tidak ada perubahan
                        continue;
                    }

                    // Eksekusi update inventory
                    if (!$conn->query($query_update)) {
                        throw new Exception("Gagal update inventory: " . $conn->error);
                    }

                    // LOG: Pergerakan Inventory dari SO
                    log_so_inventory_movement($id_so, $id_inventory, $jenis_movement, abs($selisih), $alasan, $_SESSION['role']);

                    // Catat pergerakan stok di audit trail
                    $query_movement = "INSERT INTO so_inventory_movement 
                                      (id_so_detail, id_inventory, jenis_movement, jumlah, alasan) 
                                      VALUES ('$id_so_detail', '$id_inventory', '$jenis_movement', 
                                              '" . abs($selisih) . "', '$alasan')";

                    if (!$conn->query($query_movement)) {
                        throw new Exception("Gagal mencatat movement: " . $conn->error);
                    }

                    // Jika ada barang rusak/busuk/hilang, pindahkan ke tabel barang_rusak
                    if (in_array($status_kondisi, ['Rusak Ringan', 'Rusak Berat', 'Busuk', 'Hilang', 'Expired'])) {
                        $jumlah_rusak = $stok_sistem - $stok_fisik; // Selisih negatif = rusak

                        if ($jumlah_rusak > 0) {
                            // Insert ke tabel barang_rusak (tabel utama)
                            $foto_bukti = $conn->real_escape_string($row['foto_bukti'] ?? '');
                            $query_rusak = "INSERT INTO barang_rusak 
                                           (id_inventory, jumlah_rusak, jenis_kerusakan, 
                                            foto_bukti, tanggal_rusak, status, sumber_rusak) 
                                           VALUES ('$id_inventory', '$jumlah_rusak', 
                                                   '$status_kondisi', '$foto_bukti', 
                                                   NOW(), 'tercatat', 'stock_opname')";

                            if (!$conn->query($query_rusak)) {
                                throw new Exception("Gagal memindahkan barang rusak: " . $conn->error);
                            }

                            // LOG: Barang Rusak dari SO
                            log_so_barang_rusak($id_so, $id_inventory, $jumlah_rusak, $status_kondisi, $_SESSION['role']);
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Stock Opname berhasil disetujui dan inventory telah diperbarui";
        } else {
            throw new Exception("Gagal approve SO: SO tidak ditemukan atau sudah di-approve");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: dashboard.php?page=stock_opname");
    exit;
}

// PROSES REJECT SO
if (isset($_POST['reject_so'])) {
    $id_so = intval($_POST['id_so']);
    $catatan_reject = $conn->real_escape_string($_POST['catatan_reject'] ?? '');
    
    // Get SO info untuk log
    $query_so_info = "SELECT no_so FROM stock_opname_header WHERE id_so = '$id_so'";
    $result_so_info = $conn->query($query_so_info);
    $so_info = $result_so_info->fetch_assoc();
    $no_so = $so_info['no_so'];
    
    $query = "UPDATE stock_opname_header 
             SET status = 'rejected', catatan = CONCAT(catatan, ' - REJECTED: $catatan_reject') 
             WHERE id_so = '$id_so'";
    
    if ($conn->query($query)) {
        // LOG: Rejection Stock Opname
        log_so_rejection($id_so, $no_so, $catatan_reject, $_SESSION['role']);
        
        $_SESSION['success'] = "Stock Opname telah ditolak";
    } else {
        $_SESSION['error'] = "Gagal menolak SO: " . $conn->error;
    }
    
    header("Location: dashboard.php?page=stock_opname");
    exit;
}

// Query data SO
$query_so = "SELECT so.*, p.username as nama_petugas, ap.username as nama_approver
             FROM stock_opname_header so
             LEFT JOIN pengurus p ON so.id_petugas = p.id
             LEFT JOIN pengurus ap ON so.approved_by = ap.id
             ORDER BY so.tanggal_so DESC, so.created_at DESC";
$result_so = $conn->query($query_so);

// Query inventory untuk form tambah
$query_inventory = "SELECT ir.*, sp.nama_produk 
                   FROM inventory_ready ir
                   LEFT JOIN supplier_produk sp ON ir.id_supplier_produk = sp.id_supplier_produk
                   WHERE ir.jumlah_tersedia > 0 
                   ORDER BY sp.nama_produk";
$result_inventory = $conn->query($query_inventory);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Opname</title>
    <style>
        .badge-draft { background-color: #6c757d; }
        .badge-waiting { background-color: #ffc107; color: #000; }
        .badge-approved { background-color: #28a745; }
        .badge-rejected { background-color: #dc3545; }
        .table th { background-color: #f8f9fa; }
        .so-item { border-left: 4px solid #007bff; }
        .selisih-positif { color: #28a745; font-weight: bold; }
        .selisih-negatif { color: #dc3545; font-weight: bold; }
        .foto-bukti { max-width: 100px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">📊 Stock Opname</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahSOModal">
            <i class="fas fa-plus me-2"></i>Buat Stock Opname
        </button>
    </div>

    <!-- Tabel Daftar Stock Opname -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daftar Stock Opname</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelStockOpname" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No. SO</th>
                            <th>Tanggal SO</th>
                            <th>Periode</th>
                            <th>Petugas</th>
                            <th>Jumlah Item</th>
                            <th>Status</th>
                            <th>Approver</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_so && $result_so->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $result_so->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no ?></td>
                                    <td><strong><?= $row['no_so'] ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_so'])) ?></td>
                                    <td><?= ucfirst($row['periode_so']) ?></td>
                                    <td><?= $row['nama_petugas'] ?></td>
                                    <td>
                                        <?php 
                                        $count_query = "SELECT COUNT(*) as total FROM stock_opname_detail WHERE id_so = '{$row['id_so']}'";
                                        $count_result = $conn->query($count_query);
                                        $count = $count_result->fetch_assoc()['total'];
                                        echo $count;
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($row['status']) {
                                            case 'draft': $status_class = 'badge-draft'; break;
                                            case 'waiting_approval': $status_class = 'badge-waiting'; break;
                                            case 'approved': $status_class = 'badge-approved'; break;
                                            case 'rejected': $status_class = 'badge-rejected'; break;
                                        }
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?= strtoupper(str_replace('_', ' ', $row['status'])) ?></span>
                                    </td>
                                    <td><?= $row['nama_approver'] ?? '-' ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-detail-so" data-id="<?= $row['id_so'] ?>">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <?php if (in_array($_SESSION['role'], ['ketua','gudang']) && $row['status'] == 'waiting_approval'): ?>
                                            <button class="btn btn-sm btn-success btn-approve-so" data-id="<?= $row['id_so'] ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-reject-so" data-id="<?= $row['id_so'] ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                        <a href="pages/print_so.php?id=<?= $row['id_so'] ?>" 
                                            class="btn btn-sm btn-secondary" 
                                            target="_blank"
                                            title="Print PDF">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    </td>
                                </tr>
                            <?php $no++; endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center">Belum ada data Stock Opname</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Stock Opname -->
<div class="modal fade" id="tambahSOModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Stock Opname Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formTambahSO">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Stock Opname</label>
                            <input type="date" name="tanggal_so" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Periode</label>
                            <select name="periode_so" class="form-select" required>
                                <option value="mingguan">Mingguan</option>
                                <option value="bulanan" selected>Bulanan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Petugas</label>
                            <input type="text" class="form-control" value="<?= $_SESSION['username'] ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                    
                    <h6 class="mt-4 mb-3">Daftar Produk untuk Stock Opname</h6>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="25%">Nama Produk</th>
                                    <th width="10%">Stok Sistem</th>
                                    <th width="10%">Stok Fisik</th>
                                    <th width="10%">Selisih</th>
                                    <th width="15%">Status Kondisi</th>
                                    <th width="15%">Foto Bukti</th>
                                    <th width="20%">Analisis Penyebab</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyProdukSO">
                                <?php if ($result_inventory && $result_inventory->num_rows > 0): ?>
                                    <?php $index = 0; while ($produk = $result_inventory->fetch_assoc()): ?>
                                        <tr class="so-item">
                                            <td>
                                                <input type="checkbox" name="pilih_produk[]" value="<?= $produk['id_inventory'] ?>" 
                                                       checked onchange="toggleRow(this)">
                                                <input type="hidden" name="id_inventory[]" value="<?= $produk['id_inventory'] ?>">
                                            </td>
                                            <td>
                                                <strong><?= $produk['nama_produk'] ?></strong>
                                                <br><small class="text-muted">Batch: <?= $produk['no_batch'] ?></small>
                                            </td>
                                            <td>
                                                <span class="stok-sistem"><?= $produk['jumlah_tersedia'] ?></span> <?= $produk['satuan_kecil'] ?>
                                            </td>
                                            <td>
                                                <input type="number" name="stok_fisik[]" class="form-control stok-fisik" 
                                                       value="<?= $produk['jumlah_tersedia'] ?>" min="0" step="0.001"
                                                       onchange="hitungSelisih(<?= $index ?>)">
                                            </td>
                                            <td>
                                                <span class="selisih">0</span>
                                            </td>
                                            <td>
                                                <select name="status_kondisi[]" class="form-select" onchange="toggleFoto(this)">
                                                    <option value="Baik">Baik</option>
                                                    <option value="Rusak Ringan">Rusak Ringan</option>
                                                    <option value="Rusak Berat">Rusak Berat</option>
                                                    <option value="Busuk">Busuk</option>
                                                    <option value="Hilang">Hilang</option>
                                                    <option value="Expired">Expired</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="file" name="foto_bukti[]" class="form-control foto-bukti-input" 
                                                       style="display: none;" accept="image/*">
                                                <small class="text-muted foto-info">Tidak perlu</small>
                                            </td>
                                            <td>
                                                <input type="text" name="analisis_penyebab[]" class="form-control" 
                                                       placeholder="Analisis jika ada selisih">
                                            </td>
                                        </tr>
                                    <?php $index++; endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center">Tidak ada produk dalam inventory</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_so" class="btn btn-primary">Simpan Stock Opname</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail SO -->
<div class="modal fade" id="detailSOModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Stock Opname</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailSOContent">
                <!-- Content akan diisi oleh AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Approve SO -->
<div class="modal fade" id="approveSOModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Stock Opname</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_so" id="approve_so_id">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menyetujui Stock Opname ini?</p>
            <div class="alert alert-warning">
                <strong>Perhatian:</strong> Setelah disetujui:
                <ul>
                    <li>Stok <strong>surplus</strong> akan <span class="text-success">ditambahkan</span> ke inventory</li>
                    <li>Stok <strong>minus</strong> akan <span class="text-danger">dikurangi</span> dari inventory</li>
                    <li>Barang rusak akan dipindahkan ke laporan barang rusak</li>
                </ul>
            </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="approve_so" class="btn btn-success">Ya, Setujui</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reject SO -->
<div class="modal fade" id="rejectSOModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Stock Opname</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="id_so" id="reject_so_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Alasan Penolakan</label>
                        <textarea name="catatan_reject" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="reject_so" class="btn btn-danger">Tolak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fungsi untuk menghitung selisih
// Tambahkan validasi di fungsi hitungSelisih
function hitungSelisih(index) {
    const rows = document.querySelectorAll('#tbodyProdukSO tr');
    const row = rows[index];
    
    const stokSistem = parseFloat(row.querySelector('.stok-sistem').textContent);
    let stokFisik = parseFloat(row.querySelector('.stok-fisik').value);
    
    // Validasi: stok fisik tidak boleh minus
    if (stokFisik < 0) {
        stokFisik = 0;
        row.querySelector('.stok-fisik').value = 0;
        alert('Stok fisik tidak boleh minus!');
    }
    
    const selisihSpan = row.querySelector('.selisih');
    const selisih = stokFisik - stokSistem;
    selisihSpan.textContent = selisih;
    
    if (selisih > 0) {
        selisihSpan.className = 'selisih selisih-positif';
        selisihSpan.innerHTML = '<span class="badge bg-success">+' + selisih + '</span>';
    } else if (selisih < 0) {
        selisihSpan.className = 'selisih selisih-negatif';
        selisihSpan.innerHTML = '<span class="badge bg-danger">' + selisih + '</span>';
    } else {
        selisihSpan.className = 'selisih';
        selisihSpan.innerHTML = '<span class="badge bg-secondary">0</span>';
    }
}

// Fungsi untuk toggle foto bukti
function toggleFoto(select) {
    const row = select.closest('tr');
    const fotoInput = row.querySelector('.foto-bukti-input');
    const fotoInfo = row.querySelector('.foto-info');
    
    const status = select.value;
    const needFoto = ['Rusak Ringan', 'Rusak Berat', 'Busuk', 'Expired'].includes(status);
    
    if (needFoto) {
        fotoInput.style.display = 'block';
        fotoInput.required = true;
        fotoInfo.textContent = 'Wajib diisi';
        fotoInfo.className = 'text-danger foto-info';
    } else {
        fotoInput.style.display = 'none';
        fotoInput.required = false;
        fotoInfo.textContent = 'Tidak perlu';
        fotoInfo.className = 'text-muted foto-info';
    }
}

// Fungsi untuk toggle row
function toggleRow(checkbox) {
    const row = checkbox.closest('tr');
    const inputs = row.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        if (input !== checkbox) {
            input.disabled = !checkbox.checked;
        }
    });
    
    row.style.opacity = checkbox.checked ? '1' : '0.5';
}

// Inisialisasi DataTables
$(document).ready(function() {
    $('#tabelStockOpname').DataTable({
        pageLength: 10,
        language: {
            search: "Cari SO: ",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ SO",
            zeroRecords: "Tidak ada data yang cocok",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            paginate: { 
                first: "Awal", 
                last: "Akhir", 
                next: "Berikutnya", 
                previous: "Sebelumnya" 
            }
        }
    });
    
    // Event handler untuk detail SO
    $('.btn-detail-so').click(function() {
        const id_so = $(this).data('id');
        
        $.ajax({
            url: 'pages/ajax/get_detail_so.php',
            method: 'GET',
            data: { id_so: id_so },
            success: function(response) {
                $('#detailSOContent').html(response);
                $('#detailSOModal').modal('show');
            }
        });
    });
    
    // Event handler untuk approve SO
    $('.btn-approve-so').click(function() {
        const id_so = $(this).data('id');
        $('#approve_so_id').val(id_so);
        $('#approveSOModal').modal('show');
    });
    
    // Event handler untuk reject SO
    $('.btn-reject-so').click(function() {
        const id_so = $(this).data('id');
        $('#reject_so_id').val(id_so);
        $('#rejectSOModal').modal('show');
    });
    
    // Inisialisasi hitung selisih untuk semua row
    document.querySelectorAll('.stok-fisik').forEach((input, index) => {
        hitungSelisih(index);
        toggleFoto(input.closest('tr').querySelector('select[name="status_kondisi[]"]'));
    });
});
</script>
</body>
</html>