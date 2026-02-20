<?php
// Include history log functions
require_once 'functions/history_log.php';

// FUNGSI GENERATE INVOICE
function generateInvoiceNumber($conn)
{
    $prefix = "INV-PO-";
    $year = date('Y');
    $month = date('m');

    $query = "SELECT COUNT(*) as total FROM purchase_order 
              WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();

    $sequence = $data['total'] + 1;
    return $prefix . $year . $month . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// FUNGSI VALIDASI PERUBAHAN STATUS
function validateStatusChange($current_status, $new_status)
{
    // Jika status saat ini adalah "disetujui", hanya boleh diubah ke "selesai"
    if ($current_status == 'disetujui' && $new_status != 'selesai') {
        return false;
    }

    // Jika status saat ini adalah "selesai", tidak boleh diubah ke status apapun
    if ($current_status == 'selesai') {
        return false;
    }

    // Jika status saat ini adalah "ditolak", tidak boleh diubah ke status apapun
    if ($current_status == 'ditolak') {
        return false;
    }

    return true;
}

// PROSES ORDER BARU
if (isset($_POST['buat_order'])) {
    error_log("POST data: " . print_r($_POST, true));

    $conn->begin_transaction();

    try {
        $id_supplier = $conn->real_escape_string($_POST['id_supplier']);
        $tanggal_order = $conn->real_escape_string($_POST['tanggal_order']);
        $tanggal_pengiriman = $conn->real_escape_string($_POST['tanggal_pengiriman']);
        $status_po = $conn->real_escape_string($_POST['status_po'] ?? '');
        $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');
        $discount = $conn->real_escape_string($_POST['discount'] ?? 0);

        // Generate nomor invoice
        $no_invoice = generateInvoiceNumber($conn);

        // Ambil nama supplier untuk log
        $query_supplier = "SELECT nama_supplier FROM supplier WHERE id_supplier = '$id_supplier'";
        $result_supplier = $conn->query($query_supplier);
        $supplier_data = $result_supplier->fetch_assoc();
        $nama_supplier = $supplier_data['nama_supplier'];

        // Insert order
        $query_order = "INSERT INTO purchase_order (no_invoice, id_supplier, tanggal_order, tanggal_pengiriman, discount, status, keterangan, created_by) 
                        VALUES ('$no_invoice', '$id_supplier', NOW(), '$tanggal_pengiriman', '$discount', '$status_po', '$keterangan', '{$_SESSION['user_id']}')";

        if ($conn->query($query_order)) {
            $id_po = $conn->insert_id;
            $total_po = 0;

            // Panggil fungsi history log untuk pembuatan PO
            log_po_creation($id_po, $no_invoice, $nama_supplier, 'gudang');

            // Insert detail order
            if (isset($_POST['produk']) && is_array($_POST['produk'])) {
                $produk = $_POST['produk'];
                $jumlah = $_POST['jumlah'];
                $sat = $_POST['satuan'];
                $harga_satuan = $_POST['harga_satuan'];
                $qty_kecil = $_POST['qty_kecil'];
                $satuan_kecil = $_POST['satuan_kecil'];

                for ($i = 0; $i < count($produk); $i++) {
                    if (!empty($produk[$i]) && !empty($jumlah[$i]) && !empty($harga_satuan[$i])) {
                        $id_produk = $conn->real_escape_string($produk[$i]);
                        $qty = $conn->real_escape_string($jumlah[$i]);
                        $satuan = $conn->real_escape_string($sat[$i]);
                        $harga = $conn->real_escape_string($harga_satuan[$i]);
                        $total_harga = $qty * $harga;

                        // Validasi qty_kecil
                        // ambil posted values
                        $posted_qty_kecil = isset($qty_kecil[$i]) ? floatval($qty_kecil[$i]) : 0;
                        $posted_isi = isset($_POST['isi'][$i]) ? floatval($_POST['isi'][$i]) : 1;
                        $qty = isset($jumlah[$i]) ? floatval($jumlah[$i]) : 0;

                        // fallback: hitung di server jika client tidak mengirim atau nilai invalid
                        $qty_kecil_val = ($posted_qty_kecil > 0) ? $posted_qty_kecil : ($qty * max(1, $posted_isi));
                        $satuan_kecil_val = $conn->real_escape_string($satuan_kecil[$i] ?? 'Pcs');

                        // validasi
                        if (empty($qty_kecil_val) || $qty_kecil_val <= 0) {
                            throw new Exception("Qty kecil harus diisi dan lebih besar dari 0 untuk produk baris " . ($i + 1));
                        }

                        // kemudian insert seperti biasa (sebaiknya pakai prepared statement untuk keamanan)
                        $query_detail = "INSERT INTO purchase_order_items 
            (id_po, id_supplier_produk, qty, satuan, qty_kecil, satuan_kecil, harga_satuan, total_harga) 
            VALUES ('$id_po', '$id_produk', '$qty', '$satuan', '$qty_kecil_val', '$satuan_kecil_val', '$harga', '$total_harga')";

                        if ($conn->query($query_detail)) {
                            $total_po += $total_harga;
                        } else {
                            throw new Exception("Error detail: " . $conn->error);
                        }
                    }
                }
            }

            // Update total PO setelah discount
            $total_setelah_discount = $total_po - ($total_po * ($discount / 100));
            $query_update = "UPDATE purchase_order SET total_po = '$total_setelah_discount' WHERE id_po = '$id_po'";
            $conn->query($query_update);

            $conn->commit();
            $_SESSION['success'] = "Purchase Order berhasil dibuat dengan invoice: $no_invoice";

            // Redirect ke halaman print
            header("Location: dashboard.php?page=purchase_order");
            exit;

        } else {
            throw new Exception("Error order: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: dashboard.php?page=purchase_order");
        exit;
    }
}

// UBAH STATUS ORDER
if (isset($_GET['ubah_status'])) {
    $id_order = $conn->real_escape_string($_GET['ubah_status']);
    $status = $conn->real_escape_string($_GET['status']);

    // Ambil data PO untuk log
    $query_po = "SELECT po.*, s.nama_supplier FROM purchase_order po 
                 LEFT JOIN supplier s ON po.id_supplier = s.id_supplier 
                 WHERE po.id_po = '$id_order'";
    $result_po = $conn->query($query_po);
    $po_data = $result_po->fetch_assoc();

    // Ambil status saat ini dari database
    $query_current = "SELECT status FROM purchase_order WHERE id_po = '$id_order'";
    $result_current = $conn->query($query_current);
    $current_data = $result_current->fetch_assoc();
    $current_status = $current_data['status'];

    // Validasi perubahan status
    if (validateStatusChange($current_status, $status)) {
        $query = "UPDATE purchase_order SET status = '$status' WHERE id_po = '$id_order'";

        if ($conn->query($query)) {
            // Panggil fungsi history log untuk perubahan status
            log_po_status_change($id_order, $current_status, $status, 'gudang');

            $_SESSION['success'] = "Status order berhasil diubah dari " . ucfirst($current_status) . " ke " . ucfirst($status) . "!";
        } else {
            $_SESSION['error'] = "Error: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Tidak dapat mengubah status dari " . ucfirst($current_status) . " ke " . ucfirst($status);
    }

    header("Location: dashboard.php?page=purchase_order");
    exit;
}
?>
<!-- HTML CONTENT - TAMPILAN UTAMA -->
<div class="container-fluid py-4">
    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">📞 Purchasing Order</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahOrderModal">
            <i class="fas fa-plus me-2"></i>Buat Order Baru
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelOrder" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="12%">No. Invoice</th>
                            <th width="15%">Supplier</th>
                            <th width="10%">Tanggal Order</th>
                            <th width="10%">Pengiriman</th>
                            <th width="10%">Total PO</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Mengurutkan data dari terbaru ke terlama
                        $query = "SELECT 
                                    po.*,
                                    s.nama_supplier,
                                    COUNT(poi.id_item) as jumlah_item
                                FROM purchase_order po 
                                LEFT JOIN supplier s ON po.id_supplier = s.id_supplier 
                                LEFT JOIN purchase_order_items poi ON po.id_po = poi.id_po 
                                GROUP BY po.id_po
                                ORDER BY po.tanggal_order DESC";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $badge_color = '';
                                switch ($row['status']) {
                                    case 'draft':
                                        $badge_color = 'bg-secondary';
                                        break;
                                    case 'diajukan':
                                        $badge_color = 'bg-warning';
                                        break;
                                    case 'disetujui':
                                        $badge_color = 'bg-info';
                                        break;
                                    case 'ditolak':
                                        $badge_color = 'bg-danger';
                                        break;
                                    case 'selesai':
                                        $badge_color = 'bg-success';
                                        break;
                                    default:
                                        $badge_color = 'bg-secondary';
                                }

                                // Tentukan opsi status yang tersedia berdasarkan status saat ini
                                $available_statuses = [];

                                switch ($row['status']) {
                                    case 'draft':
                                        $available_statuses = ['diajukan', 'ditolak'];
                                        break;
                                    case 'diajukan':
                                        $available_statuses = ['disetujui', 'ditolak'];
                                        break;
                                    case 'disetujui':
                                        $available_statuses = ['selesai'];
                                        break;
                                    case 'ditolak':
                                    case 'selesai':
                                        // Tidak ada opsi untuk status yang sudah ditolak atau selesai
                                        $available_statuses = [];
                                        break;
                                }

                                echo "
                                <tr>
                                    <td>{$row['no_invoice']}</td>
                                    <td>{$row['nama_supplier']}</td>
                                    <td>" . date('d/m/Y H:i:s', strtotime($row['tanggal_order'])) . "</td>
                                    <td>" . date('d/m/Y', strtotime($row['tanggal_pengiriman'])) . "</td>
                                    <td>Rp " . number_format($row['total_po'], 0, ',', '.') . "</td>
                                    <td><span class='badge $badge_color'>" . ucfirst($row['status']) . "</span></td>
                                    <td>
                                        <a href='pages/print_po.php?id={$row['id_po']}' class='btn btn-sm btn-info' target='_blank'>
                                            <i class='fas fa-print'></i> Print
                                        </a>";

                                if (!empty($available_statuses)) {
                                    echo "<div class='btn-group'>
                                            <button type='button' class='btn btn-sm btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>
                                                Status
                                            </button>
                                            <ul class='dropdown-menu'>";

                                    foreach ($available_statuses as $status_option) {
                                        $status_label = ucfirst($status_option);
                                        echo "<li><a class='dropdown-item' href='dashboard.php?page=purchase_order&ubah_status={$row['id_po']}&status=$status_option'>$status_label</a></li>";
                                    }

                                    echo "</ul>
                                        </div>";
                                } else {
                                    echo "<button type='button' class='btn btn-sm btn-secondary' disabled>
                                            Status
                                        </button>";
                                }

                                echo "
                                </tr>";
                            }
                        } else {
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Order -->
<div class="modal fade" id="tambahOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Purchase Order Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formOrder">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_supplier" id="selectSupplier" required>
                                <option value="">Pilih Supplier</option>
                                <?php
                                $query_supplier = "SELECT * FROM supplier WHERE status_supplier = 'active' ORDER BY nama_supplier";
                                $result_supplier = $conn->query($query_supplier);
                                if ($result_supplier && $result_supplier->num_rows > 0) {
                                    while ($supplier = $result_supplier->fetch_assoc()) {
                                        echo "<option value='{$supplier['id_supplier']}'>{$supplier['nama_supplier']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_order" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Tanggal Pengiriman <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_pengiriman" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Discount (%)</label>
                            <input type="number" class="form-control" name="discount" value="0" min="0" max="100"
                                step="0.01">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Status PO</label>
                            <select class="form-select" name="status_po" required>
                                <option value="draft">Draft</option>
                                <option value="diajukan" selected>Diajukan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>

                    <!-- Informasi Supplier -->
                    <div id="supplierInfo" class="mt-3 p-3 bg-light rounded" style="display: none;">
                        <h5>Informasi Supplier</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Alamat:</strong> <span id="infoAlamat"></span></p>
                                <p><strong>Telp:</strong> <span id="infoTelp"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <span id="infoEmail"></span></p>
                                <p><strong>Invoice:</strong> <span
                                        class="text-primary"><?= generateInvoiceNumber($conn) ?></span></p>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Daftar Produk</h6>

                    <div id="produk-container">
                        <div class="row produk-item mb-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Produk <span class="text-danger">*</span></label>
                                <select class="form-select select-produk" name="produk[]" required>
                                    <option value="">Pilih Produk</option>
                                    <!-- Produk akan di-load via AJAX -->
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Qty <span class="text-danger">*</span></label>
                                <input type="number" class="form-control qty" name="jumlah[]" placeholder="Qty" required
                                    min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Satuan Besar (Auto)</label>
                                <input type="text" name="satuan[]" class="form-control satuan" readonly>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Qty Kecil <span class="text-danger">*</span></label>
                                <input type="number" class="form-control qty-kecil" name="qty_kecil[]"
                                    placeholder="Qty kecil" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Satuan Kecil <span class="text-danger">*</span></label>
                                <input type="text" class="form-control satuan-kecil" name="satuan_kecil[]"
                                    placeholder="Kg, Pcs, dll" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Harga Satuan</label>
                                <input type="number" class="form-control harga" name="harga_satuan[]" step="0.01"
                                    readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Total</label>
                                <input type="text" class="form-control total" readonly>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Hapus</label>
                                <button type="button" class="btn btn-danger btn-sm mt-1 hapus-produk" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="tambah-produk">
                        <i class="fas fa-plus me-1"></i>Tambah Produk
                    </button>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <!-- Keterangan tambahan -->
                        </div>
                        <div class="col-md-6 text-end">
                            <h4>Total PO: <span id="totalPO">0</span></h4>
                            <p class="text-muted" id="afterDiscount"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="buat_order">Buat Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Inisialisasi DataTables
        if ($('#tabelOrder').length && !$.fn.DataTable.isDataTable('#tabelOrder')) {
            $('#tabelOrder').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'desc']],
                language: {
                    search: "Cari Order : ",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    zeroRecords: "Tidak ada data yang cocok",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
                }
            });
        }

        // ensure existing rows have isi default
        $('.produk-item').each(function() {
            if ($(this).find('.isi').length === 0) {
                // jika baris awal HTML tidak punya hidden isi[], tambahkan
                $(this).append('<input type="hidden" name="isi[]" class="isi" value="1">');
            } else if ($(this).find('.isi').val() === '') {
                $(this).find('.isi').val('1');
            }
        });

        // initial total calc
        calculateTotal();
    });

    // utility: update qty-kecil for a single row (qty * isi)
    function updateQtyKecilForRow(row) {
        const isi = parseFloat(row.find('.isi').val()) || 1;
        const qty = parseFloat(row.find('.qty').val()) || 0;
        const qtyKecil = qty * isi;
        row.find('.qty-kecil').val(Number(qtyKecil.toFixed(3)));
    }

    // utility: update all rows qty-kecil
    function updateAllQtyKecil() {
        $('.produk-item').each(function() {
            updateQtyKecilForRow($(this));
        });
    }

    // Load produk berdasarkan supplier - DIPERBAIKI
    $('#selectSupplier').change(function () {
        const idSupplier = $(this).val();
        const supplierInfo = $('#supplierInfo');

        console.log('Supplier changed:', idSupplier);

        if (idSupplier) {
            supplierInfo.show();

            // Load produk dengan error handling
            $.ajax({
                url: 'pages/ajax/get_produk_by_supplier.php',
                type: 'GET',
                data: { id_supplier: idSupplier },
                dataType: 'html',
                success: function (response) {
                    console.log('AJAX Success - Response:', response);

                    // Update semua select produk yang ada
                    $('.select-produk').each(function () {
                        $(this).html(response);
                    });

                    // Reset nilai semua field
                    $('.satuan').val('');
                    $('.harga').val('');
                    $('.qty').val('1');
                    $('.qty-kecil').val('1');
                    $('.satuan-kecil').val('Pcs');
                    $('.total').val('');

                    // ensure isi hidden exists and default
                    $('.isi').each(function(){ if (!$(this).val()) $(this).val('1'); });

                    // recalc qty-kecil for all rows after supplier change
                    updateAllQtyKecil();
                    calculateTotal();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.log('Status:', status);

                    // Fallback options
                    const fallbackOptions = `
                            <option value="">Error loading products</option>
                            <option value="1" data-harga="10000" data-satuan="Pcs">Produk Contoh 1 - Rp 10.000/Pcs</option>
                            <option value="2" data-harga="20000" data-satuan="Kg">Produk Contoh 2 - Rp 20.000/Kg</option>
                        `;

                    $('.select-produk').html(fallbackOptions);
                }
            });

            // Load info supplier
            $.ajax({
                url: 'pages/ajax/get_supplier_info.php',
                type: 'GET',
                data: { id_supplier: idSupplier },
                dataType: 'json',
                success: function (data) {
                    console.log('Supplier info:', data);
                    try {
                        if (data.error) {
                            $('#infoAlamat').text('-');
                            $('#infoTelp').text('-');
                            $('#infoEmail').text('-');
                        } else {
                            $('#infoAlamat').text(data.alamat || '-');
                            $('#infoTelp').text(data.no_telp || '-');
                            $('#infoEmail').text(data.email || '-');
                        }
                    } catch (e) {
                        console.error('Error parsing supplier data:', e);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error loading supplier info:', error);
                }
            });
        } else {
            supplierInfo.hide();
            $('.select-produk').html('<option value="">Pilih Supplier terlebih dahulu</option>');

            // Reset semua field
            $('.satuan').val('');
            $('.harga').val('');
            $('.total').val('');
            calculateTotal();
        }
    });

    // Template untuk baris produk baru
    function getProdukRowTemplate() {
        const baseSelect = $('.select-produk:first').html() || '<option value="">Pilih Produk</option>';

        return `
        <div class="row produk-item mb-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Produk <span class="text-danger">*</span></label>
                <select class="form-select select-produk" name="produk[]" required>
                    ${baseSelect}
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Qty <span class="text-danger">*</span></label>
                <input type="number" class="form-control qty" name="jumlah[]" value="1" required min="1">
            </div>
            <div class="col-md-2">
                <label class="form-label">Satuan Besar</label>
                <input type="text" name="satuan[]" class="form-control satuan" readonly>
            </div>

            <!-- hidden isi (jumlah isi per satuan besar) -->
            <input type="hidden" name="isi[]" class="isi" value="1">

            <div class="col-md-1">
                <label class="form-label">Qty Kecil <span class="text-danger">*</span></label>
                <input type="number" class="form-control qty-kecil" name="qty_kecil[]" required>
            </div>
            <div class="col-md-1">
                <label class="form-label">Satuan Kecil <span class="text-danger">*</span></label>
                <input type="text" class="form-control satuan-kecil" name="satuan_kecil[]" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Harga Satuan</label>
                <input type="number" class="form-control harga" name="harga_satuan[]" step="0.01" readonly>
            </div>
            <div class="col-md-2">
                <label class="form-label">Total</label>
                <input type="text" class="form-control total" readonly>
            </div>
            <div class="col-md-1">
                <label class="form-label">Hapus</label>
                <button type="button" class="btn btn-danger btn-sm mt-1 hapus-produk" disabled>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;
    }


    // Tambah baris produk
    $('#tambah-produk').click(function () {
        const newRow = getProdukRowTemplate();
        $('#produk-container').append(newRow);
        updateHapusButtonState();
        console.log('Added new product row');

        // ensure the newly added row has isi default and qty-kecil computed
        const row = $('#produk-container .produk-item').last();
        if (row.find('.isi').length === 0) row.append('<input type="hidden" name="isi[]" class="isi" value="1">');
        row.find('.isi').val('1');
        updateQtyKecilForRow(row);
    });

    // Hapus baris produk
    $(document).on('click', '.hapus-produk', function () {
        if ($('.produk-item').length > 1) {
            $(this).closest('.produk-item').remove();
            calculateTotal();
            console.log('Removed product row');
        }
        updateHapusButtonState();
    });

    // Update harga, satuan, dan isi ketika produk dipilih
    $(document).on('change', '.select-produk', function () {
        const idProduk = $(this).val();
        const row = $(this).closest('.produk-item');

        console.log('Product selected:', idProduk);

        if (idProduk) {
            const selectedOption = $(this).find('option:selected');
            const harga = parseFloat(selectedOption.data('harga')) || 0;
            const satuan = selectedOption.data('satuan') || '';
            const isi = parseFloat(selectedOption.data('isi')) || 1;

            row.find('.satuan').val(satuan);
            row.find('.harga').val(harga);

            // set hidden isi[]
            if (row.find('.isi').length === 0) row.append('<input type="hidden" name="isi[]" class="isi" value="1">');
            row.find('.isi').val(isi);

            // recalc qty kecil from qty * isi
            updateQtyKecilForRow(row);

            // Auto-suggest satuan kecil
            const satuanKecilMap = {
                'ton': 'kg', 'dus': 'pcs', 'pack': 'pcs', 'kerat': 'botol',
                'kg': 'gram', 'liter': 'ml', 'pcs': 'pcs', 'unit': 'unit',
                'pack': 'pcs', 'kerat': 'botol', 'box': 'pcs', 'kwintal': 'kg'
            };
            const suggestedSatuanKecil = satuanKecilMap[satuan.toLowerCase()] || 'pcs';
            row.find('.satuan-kecil').val(suggestedSatuanKecil);

            // Trigger calculation of price total
            row.find('.qty').trigger('input');
        } else {
            row.find('.satuan').val('');
            row.find('.harga').val('');
            if (row.find('.isi').length === 0) row.append('<input type="hidden" name="isi[]" class="isi" value="1">');
            row.find('.isi').val('1');
            row.find('.qty-kecil').val('1');
            row.find('.total').val('');
        }

        calculateTotal();
    });

    // Calculate row total and update qty-kecil when qty changes
    $(document).on('input', '.qty', function () {
        const row = $(this).closest('.produk-item');

        // ambil isi (hidden)
        const isi = parseFloat(row.find('.isi').val()) || 1;
        const qty = parseFloat($(this).val()) || 0;
        const qtyKecil = qty * isi;

        // tulis ke field qty-kecil (3 desimal untuk aman)
        row.find('.qty-kecil').val(Number(qtyKecil.toFixed(3)));

        // calc price total
        calculateRowTotal(row);
        calculateTotal();
    });

    // Calculate total ketika qty atau harga berubah
    $(document).on('input', '.qty, .harga', function () {
        const row = $(this).closest('.produk-item');
        calculateRowTotal(row);
        calculateTotal();
    });

    // Hitung total per baris
    function calculateRowTotal(row) {
        const qty = parseFloat(row.find('.qty').val()) || 0;
        const harga = parseFloat(row.find('.harga').val()) || 0;
        const total = qty * harga;

        row.find('.total').val(total.toLocaleString('id-ID'));
        return total;
    }

    // Calculate total PO
    function calculateTotal() {
        let total = 0;

        $('.produk-item').each(function () {
            total += calculateRowTotal($(this));
        });

        const discount = parseFloat($('input[name="discount"]').val()) || 0;
        const totalAfterDiscount = total - (total * (discount / 100));

        $('#totalPO').text('Rp ' + totalAfterDiscount.toLocaleString('id-ID'));

        if (discount > 0) {
            $('#afterDiscount').html(`<small>Sebelum discount: Rp ${total.toLocaleString('id-ID')} (Discount: ${discount}%)</small>`);
        } else {
            $('#afterDiscount').html('');
        }

        console.log('Total calculated:', totalAfterDiscount);
    }

    // Update total ketika discount berubah
    $('input[name="discount"]').on('input', calculateTotal);

    // Update state tombol hapus
    function updateHapusButtonState() {
        const itemCount = $('.produk-item').length;
        $('.hapus-produk').prop('disabled', itemCount <= 1);
        console.log('Product items count:', itemCount);
    }

    // Validasi form sebelum submit
    $('#formOrder').on('submit', function (e) {
        console.log('Form submitted - validating...');

        // ensure qty-kecil up-to-date before validation
        updateAllQtyKecil();

        let valid = true;
        let errorMessage = "";

        // Validasi supplier
        if ($('#selectSupplier').val() === '') {
            valid = false;
            errorMessage += "• Pilih supplier\n";
        }

        // Validasi produk
        $('.select-produk').each(function (index) {
            if ($(this).val() === '') {
                valid = false;
                errorMessage += `• Pilih produk untuk baris ${index + 1}\n`;
            }
        });

        // Validasi qty kecil
        $('.qty-kecil').each(function (index) {
            const qtyKecil = parseFloat($(this).val()) || 0;
            if (qtyKecil <= 0) {
                valid = false;
                errorMessage += `• Qty kecil harus lebih dari 0 untuk baris ${index + 1}\n`;
            }
        });

        // Validasi satuan kecil
        $('.satuan-kecil').each(function (index) {
            if ($(this).val().trim() === '') {
                valid = false;
                errorMessage += `• Satuan kecil harus diisi untuk baris ${index + 1}\n`;
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Harap perbaiki kesalahan berikut:\n\n' + errorMessage);
            console.log('Form validation failed:', errorMessage);
            return false;
        }

        console.log('Form validation passed');
        return true;
    });

    // Inisialisasi
    updateHapusButtonState();
</script>
