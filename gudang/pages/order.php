<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header("Location: ../dashboard.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// PROSES ORDER
if (isset($_POST['buat_order'])) {
    error_log("POST data: " . print_r($_POST, true));
    $id_supplier = $conn->real_escape_string($_POST['id_supplier']);
    $tanggal_pengiriman = $conn->real_escape_string($_POST['tanggal_pengiriman']);
    $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

    // Generate nomor order
    $tahun = date('Y');
    $bulan = date('m');
    $query_counter = "SELECT COUNT(*) as total FROM orders WHERE YEAR(tanggal_order) = '$tahun' AND MONTH(tanggal_order) = '$bulan'";
    $result_counter = $conn->query($query_counter);
    $counter_data = $result_counter->fetch_assoc();
    $counter = $counter_data['total'] + 1;
    $no_order = "ORD-" . date('Ym') . "-" . str_pad($counter, 4, '0', STR_PAD_LEFT);

    // Insert order
    $query_order = "INSERT INTO orders (no_order, id_supplier, tanggal_order, tanggal_pengiriman, keterangan, status) 
                    VALUES ('$no_order', '$id_supplier', NOW(), '$tanggal_pengiriman', '$keterangan', 'pending')";

    if ($conn->query($query_order)) {
        $id_order = $conn->insert_id;

        // Insert detail order - PERBAIKAN DI SINI
        if (isset($_POST['produk']) && is_array($_POST['produk'])) {
            $produk = $_POST['produk'];
            $jumlah = $_POST['jumlah'];
            $satuan = $_POST['satuan'];

            for ($i = 0; $i < count($produk); $i++) {
                if (!empty($produk[$i]) && !empty($jumlah[$i])) {
                    $id_produk = $conn->real_escape_string($produk[$i]);
                    $qty = $conn->real_escape_string($jumlah[$i]);
                    $unit = $conn->real_escape_string($satuan[$i]);

                    $query_detail = "INSERT INTO order_detail (id_order, id_produk, jumlah, satuan) 
                                     VALUES ('$id_order', '$id_produk', '$qty', '$unit')";
                    if (!$conn->query($query_detail)) {
                        $_SESSION['error'] = "Error detail: " . $conn->error;
                    }
                }
            }
        }

        $_SESSION['success'] = "Order berhasil dibuat dengan nomor: $no_order";
    } else {
        $_SESSION['error'] = "Error order: " . $conn->error;
    }

    header("Location: dashboard.php?page=order");
    exit;
}

// HAPUS ORDER
if (isset($_GET['hapus_order'])) {
    $id_order = $conn->real_escape_string($_GET['hapus_order']);

    // Hapus detail order terlebih dahulu
    $query_delete_detail = "DELETE FROM order_detail WHERE id_order = '$id_order'";
    $conn->query($query_delete_detail);

    // Hapus order
    $query_delete_order = "DELETE FROM orders WHERE id_order = '$id_order'";

    if ($conn->query($query_delete_order)) {
        $_SESSION['success'] = "Order berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=order");
    exit;
}

// UBAH STATUS ORDER - PERBAIKAN DI SINI
if (isset($_GET['ubah_status'])) {
    $id_order = $conn->real_escape_string($_GET['ubah_status']);
    $status = $conn->real_escape_string($_GET['status']);

    $query = "UPDATE orders SET status = '$status' WHERE id_order = '$id_order'";

    if ($conn->query($query)) {
        $_SESSION['success'] = "Status order berhasil diubah!";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=order");
    exit;
}
?>

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
                            <th width="10%">No. Order</th>
                            <th width="15%">Supplier</th>
                            <th width="10%">Tanggal Order</th>
                            <th width="10%">Pengiriman</th>
                            <th width="10%">Jumlah Item</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT 
                                    o.id_order, o.no_order, o.id_supplier, o.tanggal_order, o.tanggal_pengiriman, o.keterangan, o.status,
                                    s.nama_supplier, 
                                    COUNT(od.id_order_detail) as jumlah_item 
                                FROM orders o 
                                LEFT JOIN supplier s ON o.id_supplier = s.id_supplier 
                                LEFT JOIN order_detail od ON o.id_order = od.id_order 
                                GROUP BY o.id_order, o.no_order, o.id_supplier, o.tanggal_order, o.tanggal_pengiriman, o.keterangan, o.status, s.nama_supplier
                                ORDER BY o.tanggal_order DESC
                                ";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $badge_color = '';
                                switch ($row['status']) {
                                    case 'pending':
                                        $badge_color = 'bg-warning';
                                        break;
                                    case 'diproses':
                                        $badge_color = 'bg-info';
                                        break;
                                    case 'dikirim':
                                        $badge_color = 'bg-primary';
                                        break;
                                    case 'selesai':
                                        $badge_color = 'bg-success';
                                        break;
                                    case 'dibatalkan':
                                        $badge_color = 'bg-danger';
                                        break;
                                    default:
                                        $badge_color = 'bg-secondary';
                                }

                                echo "
                                <tr>
                                    <td>{$row['no_order']}</td>
                                    <td>{$row['nama_supplier']}</td>
                                    <td>" . date('d/m/Y', strtotime($row['tanggal_order'])) . "</td>
                                    <td>" . date('d/m/Y', strtotime($row['tanggal_pengiriman'])) . "</td>
                                    <td>{$row['jumlah_item']}</td>
                                    <td><span class='badge $badge_color'>{$row['status']}</span></td>
                                    <td>
                                        <button class='btn btn-sm btn-info btn-action view-order' 
                                                data-id='{$row['id_order']}'
                                                data-no='{$row['no_order']}'
                                                data-supplier='{$row['nama_supplier']}'
                                                data-tanggal='" . date('d/m/Y', strtotime($row['tanggal_order'])) . "'
                                                data-pengiriman='" . date('d/m/Y', strtotime($row['tanggal_pengiriman'])) . "'
                                                data-status='{$row['status']}'
                                                data-keterangan='" . ($row['keterangan'] ?? '') . "'>
                                            <i class='fas fa-eye'></i>
                                        </button>
                                        <div class='btn-group'>
                                            <button type='button' class='btn btn-sm btn-secondary dropdown-toggle' data-bs-toggle='dropdown'>
                                                Status
                                            </button>
                                            <ul class='dropdown-menu'>
                                                <li><a class='dropdown-item' href='dashboard.php?page=order&ubah_status={$row['id_order']}&status=pending'>Pending</a></li>
                                                <li><a class='dropdown-item' href='dashboard.php?page=order&ubah_status={$row['id_order']}&status=diproses'>Diproses</a></li>
                                                <li><a class='dropdown-item' href='dashboard.php?page=order&ubah_status={$row['id_order']}&status=dikirim'>Dikirim</a></li>
                                                <li><a class='dropdown-item' href='dashboard.php?page=order&ubah_status={$row['id_order']}&status=selesai'>Selesai</a></li>
                                                <li><a class='dropdown-item' href='dashboard.php?page=order&ubah_status={$row['id_order']}&status=dibatalkan'>Dibatalkan</a></li>
                                            </ul>
                                        </div>
                                        <button class='btn btn-sm btn-danger btn-action delete-order' 
                                                data-id='{$row['id_order']}'
                                                data-no='{$row['no_order']}'>
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center py-4'>Tidak ada data order</td></tr>";
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Order Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formOrder">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_supplier" required>
                                <option value="">Pilih Supplier</option>
                                <?php
                                $query_supplier = "SELECT * FROM supplier ORDER BY nama_supplier";
                                $result_supplier = $conn->query($query_supplier);
                                if ($result_supplier && $result_supplier->num_rows > 0) {
                                    while ($supplier = $result_supplier->fetch_assoc()) {
                                        echo "<option value='{$supplier['id_supplier']}'>{$supplier['nama_supplier']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Pengiriman <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_pengiriman" required
                                min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>

                    <h6 class="mt-4 mb-3">Daftar Produk</h6>

                    <div id="produk-container">
                        <div class="row produk-item mb-3">
                            <div class="col-md-5">
                                <select class="form-select" name="produk[]" required>
                                    <option value="">Pilih Produk</option>
                                    <?php
                                    $query_produk = "SELECT * FROM produk ORDER BY nama_produk";
                                    $result_produk = $conn->query($query_produk);
                                    if ($result_produk && $result_produk->num_rows > 0) {
                                        while ($produk = $result_produk->fetch_assoc()) {
                                            echo "<option value='{$produk['id_produk']}'>{$produk['nama_produk']}</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="jumlah[]" placeholder="Jumlah" required
                                    min="1">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="satuan[]" required>
                                    <option value="pcs">Pcs</option>
                                    <option value="unit">Unit</option>
                                    <option value="kg">Kg</option>
                                    <option value="gram">Gram</option>
                                    <option value="liter">Liter</option>
                                    <option value="pack">Pack</option>
                                    <option value="dus">Dus</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm hapus-produk" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary" id="tambah-produk">
                        <i class="fas fa-plus me-1"></i>Tambah Produk
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="buat_order">Buat Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Order -->
<div class="modal fade" id="detailOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>No. Order:</strong> <span id="detail_no"></span></p>
                        <p><strong>Supplier:</strong> <span id="detail_supplier"></span></p>
                        <p><strong>Tanggal Order:</strong> <span id="detail_tanggal"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tanggal Pengiriman:</strong> <span id="detail_pengiriman"></span></p>
                        <p><strong>Status:</strong> <span id="detail_status"></span></p>
                        <p><strong>Keterangan:</strong> <span id="detail_keterangan"></span></p>
                    </div>
                </div>

                <h6>Daftar Produk</h6>
                <div class="table-responsive">
                    <table class="table table-bordered" id="tabelDetailOrder">
                        <thead>
                            <tr>
                                <th>Nama Produk</th>
                                <th>Jumlah</th>
                                <th>Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data akan diisi oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Inisialisasi DataTables
    // ==========================================================
    // == INISIALISASI DATATABLES                              ==
    // ==========================================================
        if ($('#tabelProduk').length && !$.fn.DataTable.isDataTable('#tabelProduk')) {
        $('#tabelProduk').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: "Cari Produk : ",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                zeroRecords: "Tidak ada data yang cocok",
                infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
            }
        });
    }
    });

        // Tambah baris produk
        $('#tambah-produk').on('click', function() {
        console.log("Tombol tambah produk diklik");
        
        const newRow = `
        <div class="row produk-item mb-3">
            <div class="col-md-5">
                <select class="form-select" name="produk[]" required>
                    <option value="">Pilih Produk</option>
                    <?php
                    $query_produk = "SELECT * FROM produk ORDER BY nama_produk";
                    $result_produk = $conn->query($query_produk);
                    if ($result_produk && $result_produk->num_rows > 0) {
                        while ($produk = $result_produk->fetch_assoc()) {
                            echo "<option value='{$produk['id_produk']}'>{$produk['nama_produk']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" name="jumlah[]" placeholder="Jumlah" required min="1">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="satuan[]" required>
                    <option value="pcs">Pcs</option>
                    <option value="unit">Unit</option>
                    <option value="kg">Kg</option>
                    <option value="gram">Gram</option>
                    <option value="liter">Liter</option>
                    <option value="pack">Pack</option>
                    <option value="dus">Dus</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm hapus-produk">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;

                $('#produk-container').append(newRow);
                updateHapusButtonState();
            });

            // Hapus baris produk
            $(document).on('click', '.hapus-produk', function () {
                console.log("Tombol hapus produk diklik");
                if ($('.produk-item').length > 1) {
                    $(this).closest('.produk-item').remove();
                }
                updateHapusButtonState();
            });

            // Update state tombol hapus
            function updateHapusButtonState() {
                $('.hapus-produk').prop('disabled', $('.produk-item').length <= 1);
            }

            // Handle view button click
            $(document).on('click', '.view-order', function () {
                const id = $(this).data('id');

                // Isi data utama
                $('#detail_no').text($(this).data('no'));
                $('#detail_supplier').text($(this).data('supplier'));
                $('#detail_tanggal').text($(this).data('tanggal'));
                $('#detail_pengiriman').text($(this).data('pengiriman'));
                $('#detail_keterangan').text($(this).data('keterangan') || '-');

                // Status dengan badge
                const status = $(this).data('status');
                let badgeColor = 'bg-secondary';
                switch (status) {
                    case 'pending': badgeColor = 'bg-warning'; break;
                    case 'diproses': badgeColor = 'bg-info'; break;
                    case 'dikirim': badgeColor = 'bg-primary'; break;
                    case 'selesai': badgeColor = 'bg-success'; break;
                    case 'dibatalkan': badgeColor = 'bg-danger'; break;
                }
                $('#detail_status').html(`<span class="badge ${badgeColor}">${status}</span>`);

                // Ambil data detail order
                $.ajax({
                    url: 'ajax/get_order_detail.php',
                    type: 'GET',
                    data: { id_order: id },
                    success: function (response) {
                        $('#tabelDetailOrder tbody').html(response);
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        $('#tabelDetailOrder tbody').html('<tr><td colspan="3" class="text-center">Gagal memuat data</td></tr>');
                    }
                });

                $('#detailOrderModal').modal('show');
            });

            // Handle delete button click
            $(document).on('click', '.delete-order', function () {
                const id = $(this).data('id');
                const no = $(this).data('no');

                if (confirm(`Apakah Anda yakin ingin menghapus order: ${no}?`)) {
                    window.location.href = 'dashboard.php?page=order&hapus_order=' + id;
                }
            });

            // Validasi form sebelum submit
            $('#formOrder').on('submit', function (e) {
                let valid = true;
                let errorMessage = "";

                // Validasi supplier
                const supplier = $('select[name="id_supplier"]');
                if (supplier.val() === '') {
                    valid = false;
                    supplier.addClass('is-invalid');
                    errorMessage += "- Pilih supplier\n";
                }

                // Validasi tanggal pengiriman
                const tanggal = $('input[name="tanggal_pengiriman"]');
                if (tanggal.val() === '') {
                    valid = false;
                    tanggal.addClass('is-invalid');
                    errorMessage += "- Isi tanggal pengiriman\n";
                }

                // Validasi setiap produk
                $('.produk-item').each(function (index) {
                    const produk = $(this).find('select[name="produk[]"]');
                    const jumlah = $(this).find('input[name="jumlah[]"]');

                    if (produk.val() === '' || jumlah.val() === '' || parseInt(jumlah.val()) < 1) {
                        valid = false;
                        if (produk.val() === '') {
                            produk.addClass('is-invalid');
                            errorMessage += "- Pilih produk untuk baris " + (index + 1) + "\n";
                        }
                        if (jumlah.val() === '' || parseInt(jumlah.val()) < 1) {
                            jumlah.addClass('is-invalid');
                            errorMessage += "- Isi jumlah yang valid untuk baris " + (index + 1) + "\n";
                        }
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    alert('Harap perbaiki kesalahan berikut:\n' + errorMessage);
                    return false;
                }

                return true;
            });

            // Hapus kelas invalid saat input diubah
            $(document).on('change', 'select[name="id_supplier"], input[name="tanggal_pengiriman"], select[name="produk[]"], input[name="jumlah[]"]', function () {
                $(this).removeClass('is-invalid');
            });
            $(document).on('change', 'select[name="id_supplier"]', function() {
                const id_supplier = $(this).val();
                
                if (id_supplier) {
                    // Ambil produk berdasarkan supplier yang dipilih
                    $.ajax({
                        url: 'pages/ajax/get_produk_by_supplier.php',
                        type: 'GET',
                        data: { id_supplier: id_supplier },
                        success: function(response) {
                            $('select[name="produk[]"]').html(response);
                        }
                    });
                } else {
                    $('select[name="produk[]"]').html('<option value="">Pilih Produk</option>');
                }
            });
            // Inisialisasi state tombol hapus
            updateHapusButtonState();

            // Debug: Pastikan tombol dapat ditemukan
            console.log("Tombol tambah produk ditemukan:", $('#tambah-produk').length > 0);
;
        </script>