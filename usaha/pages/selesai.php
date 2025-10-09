<?php
// Filter yang tersedia
$filter_status = $_GET['status'] ?? 'all';
$filter_tanggal = $_GET['tanggal'] ?? '';
$filter_search = $_GET['cari'] ?? '';

// Query dasar dengan filter
$query = "
    SELECT 
        p.*,
        k.nama as nama_kurir
    FROM pemesanan p
    LEFT JOIN kurir k ON p.id_kurir = k.id
    WHERE p.status IN ('Dibatalkan', 'Terkirim', 'Gagal')
";

// Filter status
if ($filter_status != 'all') {
    $query .= " AND p.status = '$filter_status'";
}

// Filter tanggal
if (!empty($filter_tanggal)) {
    $query .= " AND DATE(p.tanggal_pesan) = '$filter_tanggal'";
}

// Filter pencarian
if (!empty($filter_search)) {
    $query .= " AND (p.id_pemesanan LIKE '%$filter_search%' OR p.nama_pemesan LIKE '%$filter_search%')";
}

$query .= " ORDER BY p.tanggal_pesan DESC";

$result = mysqli_query($conn, $query);
$pesanan = [];
if ($result) {
    $pesanan = $result;
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-history me-2"></i>History Pesanan Selesai / Gagal / Dibatalkan
    </div>
    <div class="card-body">
        <!-- Filter Section -->
        <div class="row mb-3">
            <div class="col-md-3">
                <label>Filter Status:</label>
                <select class="form-select" id="filterStatus" onchange="applyFilter()">
                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Dibatalkan" <?= $filter_status == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    <option value="Terkirim" <?= $filter_status == 'Terkirim' ? 'selected' : '' ?>>Terkirim</option>
                    <option value="Gagal" <?= $filter_status == 'Gagal' ? 'selected' : '' ?>>Gagal</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Filter Tanggal:</label>
                <input type="date" class="form-control" id="filterTanggal" value="<?= $filter_tanggal ?>"
                    onchange="applyFilter()">
            </div>
            <div class="col-md-4">
                <label>Cari Pesanan:</label>
                <input type="text" class="form-control" id="filterSearch" value="<?= $filter_search ?>"
                    placeholder="Masukkan ID pesanan atau nama pemesan" onchange="applyFilter()">
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label>
                <div>
                    <button class="btn btn-secondary w-100" onclick="resetFilter()">
                        <i class="fas fa-sync me-1"></i>Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Pesanan -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th width="8%">ID Pesanan</th>
                        <th width="18%">Nama Pemesan</th>
                        <th width="12%">Tanggal Pesan</th>
                        <th width="13%">Waktu Dikirim</th>
                        <th width="13%">Waktu Selesai</th>
                        <th width="10%">Total</th>
                        <th width="10%">Kurir</th>
                        <th width="10%">Status</th>
                        <th width="10%">Keterangan</th>
                        <th width="5%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($pesanan) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($pesanan)): ?>
                            <tr>
                                <td>
                                    <strong>#ORD-<?= $row['id_pemesanan'] ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_pemesan']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($row['no_hp_pemesan']) ?></small>
                                    <br><small class="text-muted"><?= substr($row['alamat_pemesan'], 0, 30) ?>...</small>
                                </td>
                                <td><?= date('d-m-Y H:i', strtotime($row['tanggal_pesan'])) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= $row['waktu_dikirim'] ?? '-' ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $row['waktu_selesai'] ?? '-' ?></span>
                                </td>
                                <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($row['nama_kurir'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $badge_color = [
                                        'Terkirim' => 'success',
                                        'Dibatalkan' => 'danger',
                                        'Gagal' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $badge_color[$row['status']] ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['keterangan_gagal'])): ?>
                                        <span class="text-muted"><?= htmlspecialchars($row['keterangan_gagal']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm">
                                        <button class="btn btn-info" onclick="showDetail(<?= $row['id_pemesanan'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">
                                Tidak ada pesanan yang sesuai dengan filter
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail Pesanan -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Detail Pesanan #ORD-<span id="modalId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Info Pemesan</h6>
                        <p><strong>ID Pesanan:</strong> #ORD-<span id="modalIdDisplay"></span></p>
                        <p><strong>Nama:</strong> <span id="modalNama"></span></p>
                        <p><strong>No HP:</strong> <span id="modalNoHp"></span></p>
                        <p><strong>Alamat:</strong> <span id="modalAlamat"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Info Pesanan</h6>
                        <p><strong>Tanggal Pesan:</strong> <span id="modalTanggal"></span></p>
                        <p><strong>Jadwal Kirim:</strong> <span id="modalJadwal"></span></p>
                        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                        <p><strong>Total:</strong> Rp <span id="modalTotal"></span></p>
                        <p><strong>Kurir:</strong> <span id="modalKurir"></span></p>
                        <p><strong>Keterangan:</strong> <span id="modalKeterangan"></span></p>
                    </div>
                </div>

                <hr>

                <h6>Item Pesanan</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="modalItems">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Satuan</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items akan diisi oleh JavaScript -->
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
    // Filter function
    function applyFilter() {
        const status = $('#filterStatus').val();
        const tanggal = $('#filterTanggal').val();
        const search = $('#filterSearch').val();

        let url = '?page=selesai';
        if (status !== 'all') url += '&status=' + status;
        if (tanggal) url += '&tanggal=' + tanggal;
        if (search) url += '&cari=' + encodeURIComponent(search);

        window.location.href = url;
    }

    // Reset filter
    function resetFilter() {
        window.location.href = '?page=selesai';
    }

    // Detail modal
    function showDetail(id) {
        $('#modalItems tbody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

        // Tampilkan modal segera
        const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
        detailModal.show();

        $.ajax({
            url: 'pages/get_order_detail.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                $('#modalId').text(data.pemesanan.id_pemesanan || 'N/A');
                $('#modalIdDisplay').text(data.pemesanan.id_pemesanan || 'N/A');
                $('#modalNama').text(data.pemesanan.nama_pemesan || 'N/A');
                $('#modalNoHp').text(data.pemesanan.no_hp_pemesan || 'N/A');
                $('#modalAlamat').text(data.pemesanan.alamat_pemesan || 'N/A');
                $('#modalTanggal').text(formatDateTime(data.pemesanan.tanggal_pesan));
                $('#modalJadwal').text(data.pemesanan.jadwal_kirim || 'N/A');
                $('#modalTotal').text(parseInt(data.pemesanan.total_harga || 0).toLocaleString('id-ID'));
                $('#modalKurir').text(data.pemesanan.nama_kurir || '-');
                $('#modalKeterangan').text(data.pemesanan.keterangan_gagal || '-');

                const statusBadge = `<span class="badge bg-${getStatusColor(data.pemesanan.status)}">${data.pemesanan.status || 'N/A'}</span>`;
                $('#modalStatus').html(statusBadge);

                let itemsHtml = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        itemsHtml += `
                        <tr>
                            <td>${item.nama_produk || 'N/A'}</td>
                            <td>${item.satuan || 'N/A'}</td>
                            <td>${item.jumlah || 0}</td>
                            <td>Rp ${parseInt(item.harga_satuan || 0).toLocaleString('id-ID')}</td>
                            <td>Rp ${parseInt(item.subtotal || 0).toLocaleString('id-ID')}</td>
                        </tr>
                    `;
                    });
                } else {
                    itemsHtml = '<tr><td colspan="5" class="text-center text-muted">Tidak ada item</td></tr>';
                }
                $('#modalItems tbody').html(itemsHtml);
            },
            error: function() {
                $('#modalItems tbody').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data</td></tr>');
            }
        });
    }

    // Fungsi pembantu untuk format tanggal
    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return 'N/A';
        const date = new Date(dateTimeStr);
        return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID');
    }

    function getStatusColor(status) {
        const colors = {
            'Terkirim': 'success',
            'Dibatalkan': 'danger',
            'Gagal': 'warning'
        };
        return colors[status] || 'secondary';
    }
</script>