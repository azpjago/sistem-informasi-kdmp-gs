<?php
// TAMBAHKAN INI DI PALING ATAS FILE
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Filter
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$filter_kurir = $_GET['kurir'] ?? '';
$filter_status = $_GET['status'] ?? 'semua';

// DEBUG: Tampilkan parameter filter
error_log("=== FILTER PENGIRIMAN ===");
error_log("Tanggal: $filter_tanggal");
error_log("Kurir: $filter_kurir");
error_log("Status: $filter_status");

// Query yang lebih komprehensif untuk menangani semua kemungkinan
$query = "
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.alamat,
        a.no_hp,
        k.nama as nama_kurir,
        k.id as id_kurir,
        SUM(dp.jumlah) as total_item,
        SUM(dp.subtotal) as total_pembayaran,
        p.tanggal_pengiriman as tgl_pengiriman_actual,
        p.tanggal_pesan as tgl_pesan_actual
    FROM pemesanan p
    JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN kurir k ON p.id_kurir = k.id
    JOIN pemesanan_detail dp ON p.id_pemesanan = dp.id_pemesanan
    WHERE p.status IN ('Belum Dikirim', 'Assign Kurir', 'Dalam Perjalanan') 
    AND (
        -- Kondisi 1: Sesuai tanggal pengiriman yang sudah di-set
        p.tanggal_pengiriman = '$filter_tanggal' 
        -- Kondisi 2: Atau tanggal pengiriman NULL (belum diassign)
        OR p.tanggal_pengiriman IS NULL
        -- Kondisi 3: Atau sesuai tanggal pesan (fallback untuk pesanan baru)
        OR DATE(p.tanggal_pesan) = '$filter_tanggal'
    )
";

// Filter kurir
if ($filter_kurir && $filter_kurir != '') {
    $query .= " AND p.id_kurir = '$filter_kurir'";
}

// Filter status
if ($filter_status && $filter_status != 'semua') {
    $query .= " AND p.status = '$filter_status'";
}

$query .= " GROUP BY p.id_pemesanan ORDER BY p.tanggal_pesan DESC";

// DEBUG: Tampilkan query
error_log("QUERY: " . $query);

$pesanan = mysqli_query($conn, $query);
if (!$pesanan) {
    error_log("Query Error: " . mysqli_error($conn));
}

// Hitung summary dengan kondisi yang sama
$total_siap_dikirim = 0;
$total_dalam_pengiriman = 0;
$total_assign_kurir = 0;
$total_cod = 0;

$summary_query = "
    SELECT 
        status,
        COUNT(*) as jumlah,
        SUM(total_harga) as total
    FROM pemesanan 
    WHERE status IN ('Belum Dikirim', 'Assign Kurir', 'Dalam Perjalanan') 
    AND (
        tanggal_pengiriman = '$filter_tanggal' 
        OR tanggal_pengiriman IS NULL 
        OR DATE(tanggal_pesan) = '$filter_tanggal'
    )
";

if ($filter_kurir && $filter_kurir != '') {
    $summary_query .= " AND id_kurir = '$filter_kurir'";
}

if ($filter_status && $filter_status != 'semua') {
    $summary_query .= " AND status = '$filter_status'";
}

$summary_query .= " GROUP BY status";

$summary_result = mysqli_query($conn, $summary_query);
while ($summary = mysqli_fetch_assoc($summary_result)) {
    switch ($summary['status']) {
        case 'Belum Dikirim':
            $total_siap_dikirim = $summary['jumlah'];
            break;
        case 'Assign Kurir':
            $total_assign_kurir = $summary['jumlah'];
            break;
        case 'Dalam Perjalanan':
            $total_dalam_pengiriman = $summary['jumlah'];
            break;
    }
    $total_cod += $summary['total'];
}

// Hitung total pesanan
$total_pesanan = mysqli_num_rows($pesanan);
error_log("Total pesanan ditemukan: $total_pesanan");

// Fungsi helper untuk mendapatkan nama kurir
function getNamaKurir($conn, $id_kurir)
{
    if (!$id_kurir)
        return '';

    $query = "SELECT nama FROM kurir WHERE id = '$id_kurir'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $kurir = mysqli_fetch_assoc($result);
        return $kurir['nama'];
    }
    return 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengiriman</title>
    <style>
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
        }

        .table th {
            background-color: #f8f9fa;
        }

        .badge {
            font-size: 0.75em;
        }

        .summary-card {
            transition: transform 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
        }

        /* Style untuk struk print */
        @media print {
            body * {
                visibility: hidden;
            }

            .struk-print,
            .struk-print * {
                visibility: visible;
            }

            .struk-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                font-size: 12px;
            }

            .no-print {
                display: none !important;
            }

            .struk-table {
                width: 100%;
                border-collapse: collapse;
            }

            .struk-table th,
            .struk-table td {
                border: 1px solid #000;
                padding: 4px;
                font-size: 11px;
            }
        }

        .struk-preview {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border: 2px solid #000;
            z-index: 1000;
            max-width: 80mm;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-truck me-2"></i>Manajemen Pengiriman -
                <?= date('d F Y', strtotime($filter_tanggal)) ?>
            </div>
            <div class="badge bg-light text-dark">
                <i class="fas fa-box me-1"></i><?= $total_pesanan ?> Pesanan
            </div>
        </div>
        <div class="card-body">

            <!-- Summary Section -->
            <div class="row mb-4 no-print">
                <div class="col-md-3">
                    <div class="card summary-card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-box me-2"></i>Total Pesanan</h5>
                            <h2><?= $total_pesanan ?></h2>
                            <small>Semua Status</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card bg-success text-white">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-check-circle me-2"></i>Siap Dikirim</h5>
                            <h2><?= $total_siap_dikirim ?></h2>
                            <small>Status: Belum Dikirim</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card bg-warning text-white">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-user me-2"></i>Assign Kurir</h5>
                            <h2><?= $total_assign_kurir ?></h2>
                            <small>Status: Assign Kurir</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card bg-info text-white">
                        <div class="card-body text-center">
                            <h5><i class="fas fa-truck me-2"></i>Dalam Pengiriman</h5>
                            <h2><?= $total_dalam_pengiriman ?></h2>
                            <small>Status: Dalam Perjalanan</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="row mb-4 no-print">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Pengiriman:</label>
                    <input type="date" class="form-control" id="filterTanggal" value="<?= $filter_tanggal ?>"
                        onchange="applyFilter()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter Kurir:</label>
                    <select class="form-select" id="filterKurir" onchange="applyFilter()">
                        <option value="">Semua Kurir</option>
                        <?php
                        $kurir_query = "SELECT * FROM kurir WHERE status = 'Aktif'";
                        $kurir_result = mysqli_query($conn, $kurir_query);
                        while ($kurir = mysqli_fetch_assoc($kurir_result)) {
                            $selected = $filter_kurir == $kurir['id'] ? 'selected' : '';
                            echo "<option value='{$kurir['id']}' $selected>{$kurir['nama']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status Pengiriman:</label>
                    <select class="form-select" id="filterStatus" onchange="applyFilter()">
                        <option value="semua" <?= $filter_status == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="Belum Dikirim" <?= $filter_status == 'Belum Dikirim' ? 'selected' : '' ?>>Belum
                            Dikirim</option>
                        <option value="Assign Kurir" <?= $filter_status == 'Assign Kurir' ? 'selected' : '' ?>>Assign
                            Kurir</option>
                        <option value="Dalam Perjalanan" <?= $filter_status == 'Dalam Perjalanan' ? 'selected' : '' ?>>
                            Dalam Perjalanan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button class="btn btn-secondary w-100" onclick="resetFilter()">
                            <i class="fas fa-sync me-1"></i>Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Informasi Filter Aktif -->
            <div class="alert alert-info mb-4 no-print">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-filter me-2"></i>
                        <strong>Filter Aktif:</strong>
                        Tanggal <strong><?= date('d/m/Y', strtotime($filter_tanggal)) ?></strong> |
                        Status: <strong><?= $filter_status == 'semua' ? 'Semua' : $filter_status ?></strong>
                        <?php if ($filter_kurir): ?>
                            | Kurir: <strong><?= getNamaKurir($conn, $filter_kurir) ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">
                            Ditemukan <strong><?= $total_pesanan ?></strong> pesanan
                        </small>
                    </div>
                </div>
            </div>
            <!-- Tombol Print Struk -->
            <div class="row mb-4 no-print">
                <div class="col-12">
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="selectAll()">
                            <i class="fas fa-check-square me-1"></i>Pilih Semua
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#assignKurirModal">
                            <i class="fas fa-user me-1"></i>Assign Kurir
                        </button>
                        <!-- TOMBOL PRINT STRUK BARU -->
                        <button class="btn btn-info" onclick="printStruk()">
                            <i class="fas fa-print me-1"></i>Print Struk
                        </button>
                        <button class="btn btn-warning" onclick="startDelivery()">
                            <i class="fas fa-play me-1"></i>Start Pengiriman
                        </button>
                        <button class="btn btn-success" onclick="completeDelivery()">
                            <i class="fas fa-check-circle me-1"></i>Selesai Pengiriman
                        </button>
                        <button class="btn btn-danger" onclick="failDelivery()">
                            <i class="fas fa-times-circle me-1"></i>Gagal Pengiriman
                        </button>
                    </div>
                </div>
            </div>
            <!-- Tabel Pengiriman -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50" class="no-print">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>No. Pesanan</th>
                            <th>Nama Anggota</th>
                            <th>Alamat</th>
                            <th>Jadwal & Tanggal</th>
                            <th>Total COD</th>
                            <th>Kurir</th>
                            <th>Status</th>
                            <th width="100" class="no-print">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_pesanan > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($pesanan)): ?>
                                <tr>
                                    <td class="no-print">
                                        <input type="checkbox" class="delivery-checkbox" value="<?= $row['id_pemesanan'] ?>">
                                    </td>
                                    <td>
                                        <strong>#<?= $row['id_pemesanan'] ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= $row['nama_anggota'] ?></strong>
                                        <br><small class="text-muted"><?= $row['no_hp'] ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;"
                                            title="<?= htmlspecialchars($row['alamat']) ?>">
                                            <?= $row['alamat'] ?>
                                        </div>
                                        <small class="text-muted">
                                            Tgl Kirim:
                                            <?= $row['tgl_pengiriman_actual'] ? date('d/m/Y', strtotime($row['tgl_pengiriman_actual'])) : '<span class="text-warning">Belum di-set</span>' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $row['jadwal_kirim'] ?></span>
                                        <br>
                                        <small class="text-muted">
                                            Pesan: <?= date('d/m/Y', strtotime($row['tgl_pesan_actual'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong>Rp <?= number_format($row['total_pembayaran'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($row['nama_kurir']): ?>
                                            <span class="badge bg-info"><?= $row['nama_kurir'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum diassign</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badge = [
                                            'Belum Dikirim' => 'warning',
                                            'Assign Kurir' => 'primary',
                                            'Dalam Perjalanan' => 'info',
                                            'Terkirim' => 'success',
                                            'Gagal' => 'danger'
                                        ];
                                        $badge_color = $status_badge[$row['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge_color ?>">
                                            <?= $row['status'] ?>
                                        </span>
                                        <?php if ($row['status'] == 'Dalam Perjalanan'): ?>
                                            <br><small class="text-success">✅ Bisa diselesaikan</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <?php if ($row['status'] == 'Assign Kurir'): ?>
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="printSingleStruk(<?= $row['id_pemesanan'] ?>)">
                                                <i class="fas fa-print"></i> Print
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fas fa-box-open fa-4x mb-3" style="opacity: 0.5;"></i>
                                    <h4>Tidak ada pesanan yang ditemukan</h4>
                                    <p class="mb-3">Untuk filter yang dipilih:</p>
                                    <div class="row justify-content-center">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <p class="mb-1"><strong>Tanggal:</strong>
                                                        <?= date('d F Y', strtotime($filter_tanggal)) ?></p>
                                                    <p class="mb-1"><strong>Status:</strong>
                                                        <?= $filter_status == 'semua' ? 'Semua Status' : $filter_status ?>
                                                    </p>
                                                    <?php if ($filter_kurir): ?>
                                                        <p class="mb-1"><strong>Kurir:</strong>
                                                            <?= getNamaKurir($conn, $filter_kurir) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary mt-3" onclick="resetFilter()">
                                        <i class="fas fa-sync me-1"></i>Reset Filter
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Assign Kurir -->
    <div class="modal fade" id="assignKurirModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Assign Kurir ke Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pilih Kurir:</label>
                        <select class="form-select" id="selectedKurir" required>
                            <option value="">-- Pilih Kurir --</option>
                            <?php
                            $kurir_result = mysqli_query($conn, "SELECT * FROM kurir WHERE status = 'Aktif'");
                            while ($kurir = mysqli_fetch_assoc($kurir_result)) {
                                echo "<option value='{$kurir['id']}'>{$kurir['nama']} - {$kurir['kendaraan']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pengiriman:</label>
                        <input type="date" class="form-control" id="assignTanggalPengiriman"
                            value="<?= $filter_tanggal ?>" required>
                        <small class="text-muted">Tanggal ketika kurir akan mengirim pesanan</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="selectedOrdersCount">0 pesanan</span> akan diassign ke kurir terpilih
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="assignKurir()">
                        <i class="fas fa-save me-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Area untuk print struk (hidden) -->
    <div id="strukPrintArea" style="display: none;"></div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk halaman pengiriman
        function applyFilter() {
            const tanggal = $('#filterTanggal').val();
            const kurir = $('#filterKurir').val();
            const status = $('#filterStatus').val();

            window.location.href = `?page=pengiriman&tanggal=${tanggal}&kurir=${kurir}&status=${status}`;
        }

        function resetFilter() {
            window.location.href = '?page=pengiriman';
        }

        function selectAll() {
            $('.delivery-checkbox').prop('checked', true);
            updateSelectedCount();
        }

        function toggleSelectAll(checkbox) {
            $('.delivery-checkbox').prop('checked', checkbox.checked);
            updateSelectedCount();
        }

        function getSelectedOrders() {
            const selected = [];
            $('.delivery-checkbox:checked').each(function () {
                selected.push($(this).val());
            });
            return selected;
        }

        function updateSelectedCount() {
            const selectedCount = getSelectedOrders().length;
            $('#selectedOrdersCount').text(selectedCount + ' pesanan');
        }

        // Update count ketika checkbox diubah
        $(document).on('change', '.delivery-checkbox', function () {
            updateSelectedCount();
        });

        // Update count di modal assign kurir
        $('#assignKurirModal').on('show.bs.modal', function () {
            updateSelectedCount();
            $('#assignTanggalPengiriman').val($('#filterTanggal').val());
        });

        // FUNGSI PRINT STRUK BARU
        function printStruk() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Pilih minimal 1 pesanan untuk print struk!');
                return;
            }

            // Validasi: hanya pesanan dengan status "Assign Kurir" yang bisa di-print
            let semuaAssignKurir = true;
            $('.delivery-checkbox:checked').each(function () {
                const statusBadge = $(this).closest('tr').find('td:nth-child(8) .badge').text().trim();
                if (statusBadge !== 'Assign Kurir') {
                    semuaAssignKurir = false;
                    return false;
                }
            });

            if (!semuaAssignKurir) {
                alert('Hanya pesanan dengan status "Assign Kurir" yang bisa di-print struk!');
                return;
            }

            // Tampilkan loading
            const printBtn = $('button:contains("Print Struk")');
            printBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Loading...');

            // Ambil data pesanan yang dipilih
            $.post('pages/get_struk_data.php', {
                ids: selected
            }, function (result) {
                printBtn.prop('disabled', false).html('<i class="fas fa-print me-1"></i> Print Struk');

                if (result.success) {
                    generateStrukPrint(result.data);
                } else {
                    alert('Error: ' + result.error);
                }
            }, 'json').fail(function (xhr, status, error) {
                printBtn.prop('disabled', false).html('<i class="fas fa-print me-1"></i> Print Struk');
                console.error('AJAX Error:', status, error);
                alert('Terjadi kesalahan saat mengambil data struk. Silakan coba lagi.');
            });
        }

        // Fungsi print struk single
        function printSingleStruk(idPemesanan) {
            $.post('pages/get_struk_data.php', {
                ids: [idPemesanan]
            }, function (result) {
                if (result.success) {
                    generateStrukPrint(result.data);
                } else {
                    alert('Error: ' + result.error);
                }
            }, 'json').fail(function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('Terjadi kesalahan saat mengambil data struk. Silakan coba lagi.');
            });
        }

        // Fungsi generate struk untuk print
        function generateStrukPrint(data) {
            let strukHTML = '';

            data.forEach((pesanan, index) => {
                strukHTML += `
                    <div class="struk-print" style="page-break-after: ${index < data.length - 1 ? 'always' : 'auto'};">
                        <div style="text-align: center; margin-bottom: 10px;">
                            <h3 style="margin: 0; font-size: 16px;">STRUK PENGIRIMAN</h3>
                            <p style="margin: 0; font-size: 11px;">${pesanan.toko_nama || 'TOKO KOPERASI'}</p>
                            <p style="margin: 0; font-size: 10px;">${pesanan.toko_alamat || ''}</p>
                        </div>
                        
                        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                        
                        <table style="width: 100%; font-size: 11px; margin-bottom: 10px;">
                            <tr>
                                <td><strong>No. Pesanan</strong></td>
                                <td>:</td>
                                <td>#${pesanan.id_pemesanan}</td>
                            </tr>
                            <tr>
                                <td><strong>Tanggal</strong></td>
                                <td>:</td>
                                <td>${pesanan.tanggal_pengiriman}</td>
                            </tr>
                            <tr>
                                <td><strong>Kurir</strong></td>
                                <td>:</td>
                                <td>${pesanan.nama_kurir}</td>
                            </tr>
                        </table>
                        
                        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                        
                        <table style="width: 100%; font-size: 11px; margin-bottom: 10px;">
                            <tr>
                                <td><strong>Penerima</strong></td>
                                <td>:</td>
                                <td>${pesanan.nama_anggota}</td>
                            </tr>
                            <tr>
                                <td><strong>Telepon</strong></td>
                                <td>:</td>
                                <td>${pesanan.no_hp}</td>
                            </tr>
                            <tr>
                                <td style="vertical-align: top;"><strong>Alamat</strong></td>
                                <td style="vertical-align: top;">:</td>
                                <td>${pesanan.alamat}</td>
                            </tr>
                        </table>
                        
                        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                        
                        <table class="struk-table" style="margin-bottom: 10px;">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th width="60">Qty</th>
                                    <th width="80">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${pesanan.items.map(item => `
                                    <tr>
                                        <td>${item.nama_produk}</td>
                                        <td style="text-align: center;">${item.jumlah}</td>
                                        <td style="text-align: right;">Rp ${formatNumber(item.subtotal)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        
                        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                        
                        <table style="width: 100%; font-size: 12px; font-weight: bold;">
                            <tr>
                                <td>TOTAL COD</td>
                                <td style="text-align: right;">Rp ${formatNumber(pesanan.total_pembayaran)}</td>
                            </tr>
                        </table>
                        
                        <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                        
                        <div style="text-align: center; margin-top: 20px; font-size: 10px;">
                            <p style="margin: 5px 0;">** TERIMA KASIH **</p>
                            <p style="margin: 5px 0;">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
                        </div>
                        
                        <div style="margin-top: 30px; border-top: 1px dashed #000; padding-top: 10px;">
                            <table style="width: 100%; font-size: 10px;">
                                <tr>
                                    <td style="text-align: center; width: 50%;">
                                        <strong>Tanda Terima</strong><br>
                                        (Penerima)
                                    </td>
                                    <td style="text-align: center; width: 50%;">
                                        <strong>Hormat Kami</strong><br>
                                        (Kurir)
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height: 60px; vertical-align: bottom; text-align: center;">
                                        _________________________
                                    </td>
                                    <td style="height: 60px; vertical-align: bottom; text-align: center;">
                                        _________________________
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                `;
            });

            // Tampilkan di area print
            $('#strukPrintArea').html(strukHTML).show();

            // Print
            window.print();

            // Sembunyikan kembali setelah print
            setTimeout(() => {
                $('#strukPrintArea').hide().empty();
            }, 500);
        }

        // Fungsi format number
        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }

        // Fungsi assign kurir - DIPERBAIKI dengan update tanggal_pengiriman
        function assignKurir() {
            const selected = getSelectedOrders();
            const kurirId = $('#selectedKurir').val();
            const tanggalPengiriman = $('#assignTanggalPengiriman').val();

            if (selected.length === 0) {
                alert('Pilih minimal 1 pesanan!');
                return;
            }

            if (!kurirId) {
                alert('Pilih kurir terlebih dahulu!');
                return;
            }

            if (!tanggalPengiriman) {
                alert('Pilih tanggal pengiriman!');
                return;
            }

            $('#assignKurirModal .btn-primary').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Memproses...');

            // PERBAIKAN: Kirim juga tanggal_pengiriman
            $.post('pages/update_status.php', {
                ids: selected,
                status: 'Assign Kurir',
                kurir_id: kurirId,
                tanggal_pengiriman: tanggalPengiriman
            }, function (result) {
                $('#assignKurirModal .btn-primary').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Simpan');

                if (result.success) {
                    alert('Berhasil assign kurir ke ' + result.updated + ' pesanan');
                    $('#assignKurirModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            }, 'json').fail(function (xhr, status, error) {
                $('#assignKurirModal .btn-primary').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Simpan');
                console.error('AJAX Error:', status, error);
                alert('Terjadi kesalahan saat mengassign kurir. Silakan coba lagi.');
            });
        }

        // Fungsi start pengiriman
        function startDelivery() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Pilih minimal 1 pesanan!');
                return;
            }

            // Validasi: pastikan semua pesanan terpilih sudah ada kurirnya
            let semuaSudahKurir = true;
            $('.delivery-checkbox:checked').each(function () {
                const hasKurir = $(this).closest('tr').find('.badge.bg-info').length > 0;
                if (!hasKurir) {
                    semuaSudahKurir = false;
                    return false;
                }
            });

            if (!semuaSudahKurir) {
                alert('Beberapa pesanan belum diassign kurir. Silakan assign kurir terlebih dahulu.');
                return;
            }

            if (confirm(`Mulai pengiriman untuk ${selected.length} pesanan?`)) {
                $.post('pages/update_status.php', {
                    ids: selected,
                    status: 'Dalam Perjalanan'
                }, function (result) {
                    if (result.success) {
                        alert('Pengiriman dimulai untuk ' + result.updated + ' pesanan');
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                }, 'json').fail(function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Terjadi kesalahan saat memulai pengiriman. Silakan coba lagi.');
                });
            }
        }

        // Fungsi complete delivery
        function completeDelivery() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Pilih minimal 1 pesanan!');
                return;
            }

            // Validasi: hanya pesanan dengan status "Dalam Perjalanan" yang bisa diselesaikan
            let semuaDalamPerjalanan = true;
            $('.delivery-checkbox:checked').each(function () {
                const statusBadge = $(this).closest('tr').find('td:nth-child(8) .badge').text().trim();
                if (statusBadge !== 'Dalam Perjalanan') {
                    semuaDalamPerjalanan = false;
                    return false;
                }
            });

            if (!semuaDalamPerjalanan) {
                alert('Hanya pesanan dengan status "Dalam Perjalanan" yang bisa diselesaikan!');
                return;
            }

            if (confirm(`Konfirmasi pengiriman selesai untuk ${selected.length} pesanan?`)) {
                $.post('pages/update_status.php', {
                    ids: selected,
                    status: 'Terkirim'
                }, function (result) {
                    if (result.success) {
                        alert('✅ ' + result.updated + ' pesanan berhasil dikonfirmasi terkirim!');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + result.error);
                    }
                }, 'json').fail(function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('❌ Terjadi kesalahan server. Silakan coba lagi.');
                });
            }
        }

        // Fungsi fail delivery
        function failDelivery() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Pilih minimal 1 pesanan!');
                return;
            }

            const alasan = prompt('Alasan kegagalan pengiriman:');
            if (alasan === null) return;

            if (!alasan.trim()) {
                alert('Harap berikan alasan kegagalan!');
                return;
            }

            if (confirm(`Tandai ${selected.length} pesanan sebagai Gagal?`)) {
                $.ajax({
                    url: 'pages/update_status.php',
                    type: 'POST',
                    data: {
                        ids: selected,
                        status: 'Gagal',
                        alasan_gagal: alasan.trim()
                    },
                    dataType: 'json',
                    success: function (result) {
                        if (result.success) {
                            alert('❌ ' + result.updated + ' pesanan ditandai sebagai Gagal!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('Terjadi kesalahan server. Silakan coba lagi.');
                    }
                });
            }
        }
    </script>
</body>

</html>

<?php
// Tutup koneksi database
mysqli_close($conn);
?>
