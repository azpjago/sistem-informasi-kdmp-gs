<?php
// Include history log functions
require_once 'functions/history_log.php';

if (isset($_POST['submit_qc'])) {
    $id_barang_masuk = intval($_POST['id_barang_masuk']);
    $id_items = $_POST['id_item'];
    $qty_diterima = $_POST['qty_diterima'];
    $qty_bagus = $_POST['qty_bagus'];
    $qty_reject = $_POST['qty_reject'];
    $status_qc = $_POST['status_qc'];
    $catatan = $_POST['catatan'];

    // Ambil data barang masuk untuk log
    $query_barang_masuk = "SELECT bm.*, po.no_invoice, s.nama_supplier 
                           FROM barang_masuk bm
                           JOIN purchase_order po ON bm.id_po = po.id_po 
                           JOIN supplier s ON po.id_supplier = s.id_supplier 
                           WHERE bm.id_barang_masuk = '$id_barang_masuk'";
    $result_barang_masuk = $conn->query($query_barang_masuk);
    $barang_masuk_data = $result_barang_masuk->fetch_assoc();

    // upload file bukti QC
    $bukti_qc = null;
    if (!empty($_FILES['bukti_qc']['name'])) {
        $uploadDir = __DIR__ . "/uploads/qc/"; // simpan di /uploads/qc/
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // buat folder jika belum ada
        }

        $fileName = "qc_" . time() . "_" . basename($_FILES['bukti_qc']['name']);
        $target = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['bukti_qc']['tmp_name'], $target)) {
            // simpan path relatif biar bisa dipanggil dari web
            $bukti_qc = "uploads/qc/" . $fileName;
        }
    }

    foreach ($id_items as $i => $id_item) {
        $id_item = intval($id_item);
        $q_diterima = intval($qty_diterima[$i]);
        $q_bagus = intval($qty_bagus[$i]);
        $q_reject = intval($qty_reject[$i]);
        $st_qc = $conn->real_escape_string($status_qc[$i]);
        $ctt = $conn->real_escape_string($catatan[$i]);

        // 2. Ambil data dari purchase_order_items dengan JOIN ke supplier_produk
        $query_item_data = "SELECT 
                    poi.id_supplier_produk,
                    poi.satuan,                    
                    poi.qty_kecil,                 
                    poi.satuan_kecil,              
                    poi.harga_satuan,
                    sp.nama_produk
                FROM purchase_order_items poi 
                LEFT JOIN supplier_produk sp ON poi.id_supplier_produk = sp.id_supplier_produk
                WHERE poi.id_item = '$id_item'";

        $result_item = $conn->query($query_item_data);

        if ($result_item && $result_item->num_rows > 0) {
            $item_data = $result_item->fetch_assoc();
            $id_supplier_produk = $item_data['id_supplier_produk'];
            $satuan = $conn->real_escape_string($item_data['satuan']);
            $harga_satuan = $item_data['harga_satuan'];
            $satuan_kecil = $item_data['satuan_kecil'];
            $nama_produk = $conn->real_escape_string($item_data['nama_produk']);

            // 1. Insert ke qc_result
            $sql = "INSERT INTO qc_result (id_barang_masuk, id_item, id_supplier_produk, qty_diterima, qty_bagus, qty_reject, status_qc, catatan, bukti_qc, qc_by)
                VALUES ('$id_barang_masuk', '$id_item', '$id_supplier_produk', '$q_diterima', '$q_bagus', '$q_reject', '$st_qc', '$ctt', '$bukti_qc', '{$_SESSION['user_id']}')";
            $conn->query($sql);
            $id_qc = $conn->insert_id;

            // Panggil fungsi history log untuk QC
            $description_qc = "Input QC untuk produk " . $nama_produk . ": Diterima $q_diterima, Bagus $q_bagus, Reject $q_reject, Status: $st_qc";
            log_barang_masuk_qc($id_barang_masuk, 'qc_input', $description_qc, 'gudang');

            // Generate kode_produk dari prefix + id_supplier_produk
            $kode_produk = 'SP' . str_pad($id_supplier_produk, 6, '0', STR_PAD_LEFT);

            // 3. Distribusi ke inventory_ready jika ada barang bagus
            if ($q_bagus > 0) {
                // Generate no_batch (contoh: QC2025090001)
                $no_batch = "QC" . date('Ymd') . str_pad($id_qc, 4, '0', STR_PAD_LEFT);

                // Cek apakah produk sudah ada di inventory_ready berdasarkan id_supplier_produk
                $check_product = "SELECT id_inventory, jumlah_tersedia 
                     FROM inventory_ready 
                     WHERE id_supplier_produk = '$id_supplier_produk' 
                     AND status = 'available' 
                     LIMIT 1";

                $result_check = $conn->query($check_product);

                if ($result_check && $result_check->num_rows > 0) {
                    // Produk sudah ada, update stok
                    $existing_product = $result_check->fetch_assoc();
                    $id_inventory = $existing_product['id_inventory'];
                    $new_stock = $existing_product['jumlah_tersedia'] + $q_bagus;

                    $sql_inventory = "UPDATE inventory_ready 
                         SET jumlah_awal = jumlah_awal + '$q_bagus',
                             jumlah_tersedia = jumlah_tersedia + '$q_bagus',
                             updated_at = NOW()
                         WHERE id_inventory = '$id_inventory'";

                    if (!$conn->query($sql_inventory)) {
                        error_log("Gagal update inventory_ready: " . $conn->error);
                        $_SESSION['error'] = "Gagal update stok inventory";
                    } else {
                        error_log("Berhasil update stok produk: $nama_produk, stok baru: $new_stock");
                        
                        // Panggil fungsi history log untuk update stok
                        $description_stock = "Update stok produk " . $nama_produk . ": +$q_bagus $satuan_kecil, stok baru: $new_stock $satuan_kecil";
                        log_inventory_activity($id_inventory, 'stock_update', $description_stock, 'gudang');
                    }
                } else {
                    // Produk belum ada, insert baru ke inventory_ready
                    $sql_inventory = "INSERT INTO inventory_ready 
                        (id_qc, id_po_item, id_supplier_produk, nama_produk, kode_produk, no_batch, 
                        jumlah_awal, jumlah_tersedia, satuan_kecil, harga_satuan, status, created_at)
                        VALUES 
                        ('$id_qc', '$id_item', '$id_supplier_produk', '$nama_produk', '$kode_produk', '$no_batch',
                        '$q_bagus', '$q_bagus', '$satuan_kecil', '$harga_satuan', 'available', NOW())";

                    if ($conn->query($sql_inventory)) {
                        $id_inventory_baru = $conn->insert_id;
                        error_log("Berhasil insert produk baru: $nama_produk, stok: $q_bagus, id_inventory: $id_inventory_baru");

                        // Panggil fungsi history log untuk produk baru
                        $description_new_product = "Menambahkan produk baru ke inventory: " . $nama_produk . " (" . $kode_produk . ") dengan stok awal: $q_bagus $satuan_kecil";
                        log_inventory_activity($id_inventory_baru, 'create', $description_new_product, 'gudang');
                    } else {
                        error_log("Gagal insert inventory_ready: " . $conn->error);
                        $_SESSION['error'] = "Gagal menyimpan data inventory";
                    }
                }
            }
            // 4. Distribusi ke barang_rejek jika ada reject
            if ($q_reject > 0) {
                $sql_reject = "INSERT INTO barang_rejek 
                  (id_qc, id_po_item, id_supplier_produk, nama_produk, kode_produk, 
                   jumlah_reject, satuan_kecil, alasan_reject, status_tindaklanjut)
                  VALUES 
                  ('$id_qc', '$id_item', '$id_supplier_produk', '$nama_produk', '$kode_produk',
                   '$q_reject', '$satuan_kecil', '$ctt', 'return_supplier')";

                if (!$conn->query($sql_reject)) {
                    error_log("Gagal insert barang_rejek: " . $conn->error);
                    $_SESSION['error'] = "Gagal menyimpan data barang reject";
                } else {
                    // Panggil fungsi history log untuk barang reject
                    $description_reject = "Barang reject: " . $nama_produk . " sebanyak $q_reject $satuan_kecil, alasan: $ctt";
                    log_barang_masuk_qc($id_barang_masuk, 'reject', $description_reject, 'gudang');
                }
            }
        } else {
            error_log("Data purchase_order_items tidak ditemukan untuk id_item: $id_item");
            $_SESSION['error'] = "Data produk tidak ditemukan untuk item ID: $id_item";
        }
    }

    // 5. Update status barang_masuk menjadi selesai QC
    $old_status = $barang_masuk_data['status'];
    $new_status = 'done';
    
    $update_status = "UPDATE barang_masuk SET status = '$new_status' WHERE id_barang_masuk = '$id_barang_masuk'";
    if (!$conn->query($update_status)) {
        error_log("Gagal update status barang_masuk: " . $conn->error);
    } else {
        // Panggil fungsi history log untuk perubahan status barang masuk
        $description_status = "Mengubah status barang masuk #$id_barang_masuk dari $old_status menjadi $new_status setelah proses QC selesai";
        log_barang_masuk_status_change($id_barang_masuk, $old_status, $new_status, 'gudang');
    }

    // Panggil fungsi history log untuk penyelesaian QC
    $description_complete = "Proses QC selesai untuk barang masuk #$id_barang_masuk (PO: " . $barang_masuk_data['no_invoice'] . " - " . $barang_masuk_data['nama_supplier'] . ")";
    log_barang_masuk_activity($id_barang_masuk, 'qc_complete', $description_complete, 'gudang');

    $_SESSION['success'] = "Hasil QC berhasil disimpan!";
    header("Location: dashboard.php?page=barang_masuk");
    exit;
}

// Query untuk mengambil data barang masuk
$query_barang_masuk = "SELECT 
                        bm.id_barang_masuk,
                        bm.id_po,
                        bm.tanggal_terima,
                        bm.status,
                        bm.penerima,
                        po.no_invoice, 
                        s.nama_supplier,
                        u.username as nama_petugas
                       FROM barang_masuk bm
                       JOIN purchase_order po ON bm.id_po = po.id_po
                       JOIN supplier s ON po.id_supplier = s.id_supplier
                       LEFT JOIN pengurus u ON bm.penerima = u.id
                        WHERE bm.status = 'draft'
                       ORDER BY bm.tanggal_terima DESC";
$result_barang_masuk = $conn->query($query_barang_masuk);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Sistem QC Barang</title>
    <style>
        .badge-draft {
            background-color: #6c757d;
        }

        .badge-qc {
            background-color: #17a2b8;
        }

        .badge-lulus {
            background-color: #28a745;
        }

        .badge-ditolak {
            background-color: #dc3545;
        }

        .modal-xl {
            max-width: 95%;
        }

        .table th {
            background-color: #f8f9fa;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">📦 Barang Masuk & QC</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahBarangMasukModal">
                <i class="fas fa-plus me-2"></i>Tambah Penerimaan Barang
            </button>
        </div>

        <!-- Tabel Daftar Barang Masuk -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Barang Masuk</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tabelBarangMasuk" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>No. Invoice / PO</th>
                                <th>Supplier</th>
                                <th>Tanggal Terima</th>
                                <th>Status QC</th>
                                <th>Petugas QC</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result_barang_masuk && $result_barang_masuk->num_rows > 0) {
                                $no = 1;
                                while ($row = $result_barang_masuk->fetch_assoc()) {
                                    $status_class = '';
                                    $status_text = '';

                                    if ($row['status'] == 'draft') {
                                        $status_class = 'badge-draft';
                                        $status_text = 'Draft';
                                    } elseif ($row['status'] == 'qc') {
                                        $status_class = 'badge-qc';
                                        $status_text = 'QC';
                                    } elseif ($row['status'] == 'lulus') {
                                        $status_class = 'badge-lulus';
                                        $status_text = 'Lulus';
                                    } else {
                                        $status_class = 'badge-ditolak';
                                        $status_text = 'Ditolak';
                                    }

                                    $petugas_qc = isset($row['penerima']) ? $row['penerima'] : '-';

                                    echo "
                                <tr>
                                    <td>{$no}</td>
                                    <td>{$row['no_invoice']}</td>
                                    <td>{$row['nama_supplier']}</td>
                                    <td>{$row['tanggal_terima']}</td>
                                    <td><span class='badge {$status_class}'>{$status_text}</span></td>
                                    <td>{$petugas_qc}</td>
                                    <td>
                                        <button class='btn btn-sm btn-success btn-input-qc' data-bs-toggle='modal' data-bs-target='#qcModal' data-id='{$row['id_barang_masuk']}'>
                                            <i class='fas fa-check'></i> Input QC
                                        </button>
                                        <a href='pages/print_qc.php?id={$row['id_po']}' class='btn btn-sm btn-secondary' target='_blank'>
                                            <i class='fas fa-file-pdf'></i> Cetak QC
                                        </a>
                                    </td>
                                </tr>";
                                    $no++;
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center'>Tidak ada data barang masuk</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Barang Masuk -->
    <div class="modal fade" id="tambahBarangMasukModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="pages/proses_barang_masuk.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Penerimaan Barang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>No. PO</label>
                            <?php
                            $query_po = "SELECT po.id_po, po.no_invoice, s.nama_supplier
                                     FROM purchase_order po
                                     JOIN supplier s ON po.id_supplier = s.id_supplier
                                     WHERE po.status IN ('disetujui') AND po.id_po NOT IN (
                                            SELECT DISTINCT id_po FROM barang_masuk
                                        )";
                            $result_po = $conn->query($query_po);
                            ?>
                            <select name="id_po" id="select_po" class="form-select" required>
                                <option value="">-- Pilih PO --</option>
                                <?php while ($row = $result_po->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id_po']; ?>">
                                        <?php echo $row['no_invoice']; ?> -
                                        <?php echo $row['nama_supplier']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>No. Surat Jalan</label>
                            <input type="text" name="no_surat_jalan" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Tanggal Terima</label>
                            <input type="date" name="tanggal_terima" class="form-control"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label>Penerima</label>
                            <input type="text" name="penerima" class="form-control"
                                value="<?php echo isset($_SESSION['nama']) ? $_SESSION['nama'] : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="simpan_barang_masuk" class="btn btn-primary">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal QC -->
    <div class="modal fade" id="qcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Input Hasil Quality Control</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_barang_masuk" id="id_barang_masuk_qc">
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Qty Dipesan</th>
                                        <th>Qty Diterima</th>
                                        <th>Qty Bagus</th>
                                        <th>Qty Reject</th>
                                        <th>Status QC</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyQC">
                                    <!-- hasil AJAX load produk -->
                                    <tr>
                                        <td colspan="7" class="text-center">Pilih barang masuk terlebih dahulu</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mb-3">
                            <label>Upload Bukti QC</label>
                            <input type="file" name="bukti_qc" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="submit_qc" class="btn btn-primary">Simpan Hasil QC</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Tambahkan di header -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).on('click', '.btn-input-qc', function () {
            const id_barang_masuk = $(this).data('id');
            $('#id_barang_masuk_qc').val(id_barang_masuk);

            // Tampilkan loading state
            $('#tbodyQC').html('<tr><td colspan="7" class="text-center">Loading data barang...</td></tr>');

            $.ajax({
                url: 'pages/ajax/get_barang_qc.php',
                method: 'GET',
                data: { id_barang_masuk: id_barang_masuk },
                dataType: 'json',
                timeout: 10000,
                success: function (response) {
                    console.log('QC Data Response:', response);

                    let html = '';

                    // Handle error response
                    if (response && response.error) {
                        html = `<tr><td colspan="7" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error: ${response.error}
                </td></tr>`;
                    }
                    // Handle success - array of items
                    // Handle success - array of items - MODIFIKASI
                    else if (Array.isArray(response) && response.length > 0) {
                        response.forEach((item, index) => {
                            // GUNAKAN qty_kecil sebagai acuan maksimum, bukan qty besar
                            const maxQty = item.qty_kecil || item.qty || 0;
                            const satuanDisplay = item.satuan_kecil || item.satuan || '';

                            html += `
                <tr>
                    <td>
                        <strong>${item.nama_produk || 'Unknown Product'}</strong>
                        ${item.kode_produk ? `<br><small class="text-muted">Kode: ${item.kode_produk}</small>` : ''}
                        <!-- TAMBAHAN: Tampilkan informasi konversi -->
                        <br><small class="text-info">
                            PO: ${item.qty || 0} ${item.satuan || ''} 
                            (${item.qty_kecil || 0} ${item.satuan_kecil || ''})
                        </small>
                        <input type="hidden" name="id_item[]" value="${item.id_item || ''}">
                    </td>
                    <td>
                        ${item.qty_kecil || 0} ${item.satuan_kecil || ''} 
                        <br><small class="text-muted">${item.qty || 0} ${item.satuan || ''}</small>
                    </td>
                    <td>
                        <input type="number" class="form-control" name="qty_diterima[]" 
                            value="${item.qty_kecil || 0}" min="0" max="${maxQty}" 
                            onchange="calculateQty(${index})">
                    </td>
                    <td>
                        <input type="number" class="form-control qty-bagus" name="qty_bagus[]" 
                            value="${item.qty_kecil || 0}" min="0" max="${maxQty}"
                            onchange="calculateReject(${index})">
                    </td>
                    <td>
                        <input type="number" class="form-control qty-reject" name="qty_reject[]" 
                            value="0" min="0" max="${maxQty}"
                            onchange="calculateBagus(${index})">
                    </td>
                    <td>
                        <select name="status_qc[]" class="form-select" onchange="updateStatus(${index})">
                            <option value="lulus">Lulus</option>
                            <option value="ditolak_sebagian">Ditolak Sebagian</option>
                            <option value="ditolak_semua">Ditolak Semua</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="form-control" name="catatan[]" placeholder="Catatan" required>
                    </td>
                </tr>`;
                        });
                    } else {
                        html = '<tr><td colspan="7" class="text-center">Tidak ada data barang untuk PO ini</td></tr>';
                    }

                    $('#tbodyQC').html(html);
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    let errorMsg = 'Gagal memuat data dari server';

                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMsg = response.error || errorMsg;
                        } catch (e) {
                            errorMsg = 'Server error: ' + xhr.status;
                        }
                    }

                    $('#tbodyQC').html(`
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle"></i> ${errorMsg}
                    </td>
                </tr>
            `);
                }
            });

            if ($('#tabelBarangMasuk').length && !$.fn.DataTable.isDataTable('#tabelBarangMasuk')) {
                $('#tabelBarangMasuk').DataTable({
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: {
                        search: "Cari Barang Masuk : ",
                        lengthMenu: "Tampilkan _MENU_ data per halaman",
                        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                        zeroRecords: "Tidak ada data yang cocok",
                        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                        infoFiltered: "(disaring dari _MAX_ total data)",
                        paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
                    }
                });
            }

            // Fungsi helper untuk kalkulasi quantity
            function calculateReject(index) {
                const row = $('tr').eq(index + 1); // +1 karena thead
                const qtyDiterima = parseInt(row.find('input[name="qty_diterima[]"]').val()) || 0;
                const qtyBagus = parseInt(row.find('.qty-bagus').val()) || 0;

                const qtyReject = qtyDiterima - qtyBagus;
                row.find('.qty-reject').val(qtyReject >= 0 ? qtyReject : 0);

                updateStatus(index);
            }

            function calculateBagus(index) {
                const row = $('tr').eq(index + 1);
                const qtyDiterima = parseInt(row.find('input[name="qty_diterima[]"]').val()) || 0;
                const qtyReject = parseInt(row.find('.qty-reject').val()) || 0;

                const qtyBagus = qtyDiterima - qtyReject;
                row.find('.qty-bagus').val(qtyBagus >= 0 ? qtyBagus : 0);

                updateStatus(index);
            }

            function updateStatus(index) {
                const row = $('tr').eq(index + 1);
                const qtyDiterima = parseInt(row.find('input[name="qty_diterima[]"]').val()) || 0;
                const qtyBagus = parseInt(row.find('.qty-bagus').val()) || 0;
                const qtyReject = parseInt(row.find('.qty-reject').val()) || 0;
                const select = row.find('select[name="status_qc[]"]');

                // Auto update status berdasarkan quantity
                if (qtyReject === 0) {
                    select.val('lulus');
                } else if (qtyReject === qtyDiterima) {
                    select.val('ditolak_semua');
                } else if (qtyReject > 0) {
                    select.val('ditolak_sebagian');
                }
            }

            // Fungsi validasi sebelum submit form QC
            function validateQCForm() {
                let isValid = true;
                const errorMessages = [];

                $('tr').each(function (index) {
                    if (index > 0) { // Skip header row
                        const qtyDiterima = parseInt($(this).find('input[name="qty_diterima[]"]').val()) || 0;
                        const qtyBagus = parseInt($(this).find('input[name="qty_bagus[]"]').val()) || 0;
                        const qtyReject = parseInt($(this).find('input[name="qty_reject[]"]').val()) || 0;
                        const produk = $(this).find('td:first').text().trim();

                        // Validasi: Qty Bagus + Qty Reject harus sama dengan Qty Diterima
                        if (qtyBagus + qtyReject !== qtyDiterima) {
                            isValid = false;
                            errorMessages.push(`<b>${produk}</b>: Total bagus (${qtyBagus}) + reject (${qtyReject}) harus sama dengan diterima (${qtyDiterima})`);
                        }

                        // Validasi: Tidak boleh minus
                        if (qtyBagus < 0 || qtyReject < 0 || qtyDiterima < 0) {
                            isValid = false;
                            errorMessages.push(`<b>${produk}</b>: Quantity tidak boleh minus`);
                        }

                        // Validasi: Tidak boleh lebih dari diterima
                        if (qtyBagus > qtyDiterima || qtyReject > qtyDiterima) {
                            isValid = false;
                            errorMessages.push(`<b>${produk}</b>: Quantity bagus/reject tidak boleh lebih dari diterima`);
                        }
                    }
                });

                if (!isValid) {
                    const errorHtml = errorMessages.join('<br>');
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi Gagal',
                        html: errorHtml,
                        confirmButtonText: 'Mengerti'
                    });
                }

                return isValid;
            }

            // Event handler untuk form submission
            $(document).on('submit', 'form', function (e) {
                if (!validateQCForm()) {
                    e.preventDefault(); // Stop form submission
                }
            });

            // Fungsi auto-calculate untuk memastikan total selalu match
            function calculateQty(index) {
                const row = $('tr').eq(index + 1);
                const qtyDiterima = parseInt(row.find('input[name="qty_diterima[]"]').val()) || 0;
                const qtyBagus = parseInt(row.find('input[name="qty_bagus[]"]').val()) || 0;
                const qtyReject = parseInt(row.find('input[name="qty_reject[]"]').val()) || 0;

                // Auto-adjust reject jika bagus diubah
                const newReject = qtyDiterima - qtyBagus;
                if (newReject >= 0) {
                    row.find('input[name="qty_reject[]"]').val(newReject);
                }

                updateStatus(index);
            }

            function calculateReject(index) {
                const row = $('tr').eq(index + 1);
                const qtyDiterima = parseInt(row.find('input[name="qty_diterima[]"]').val()) || 0;
                const qtyReject = parseInt(row.find('input[name="qty_reject[]"]').val()) || 0;

                // Auto-adjust bagus jika reject diubah
                const newBagus = qtyDiterima - qtyReject;
                if (newBagus >= 0) {
                    row.find('input[name="qty_bagus[]"]').val(newBagus);
                }

                updateStatus(index);
            }
        });
    </script>
</body>

</html>
