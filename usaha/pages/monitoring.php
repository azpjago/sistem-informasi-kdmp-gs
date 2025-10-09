<?php
// Ambil pengaturan jam kerja
$query_jam_kerja = "SELECT * FROM pengaturan_jam_kerja ORDER BY id DESC LIMIT 1";
$result_jam_kerja = mysqli_query($conn, $query_jam_kerja);
$jam_kerja = mysqli_fetch_assoc($result_jam_kerja);

if (!$jam_kerja) {
    // Default jam kerja jika tidak ada setting
    $jam_kerja = ['buka' => '07:00:00', 'tutup' => '23:00:00'];
}

// Cek status sistem (buka/tutup)
$waktu_sekarang = date('H:i:s');
$sistem_buka = ($waktu_sekarang >= $jam_kerja['buka'] && $waktu_sekarang <= $jam_kerja['tutup']);

// Filter yang tersedia
$filter_jadwal = $_GET['jadwal'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d'); // Default hari ini

// Query dasar dengan filter
$query = "
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.alamat,
        a.no_hp,
        TIMESTAMPDIFF(MINUTE, p.tanggal_pesan, NOW()) as menit_sejak_pesan
    FROM pemesanan p
    JOIN anggota a ON p.id_anggota = a.id
    WHERE DATE(p.tanggal_pesan) = '$filter_tanggal'
    AND p.status IN ('Menunggu', 'Disiapkan')
";

// Filter jadwal kirim
if ($filter_jadwal && $filter_jadwal != 'semua') {
    $query .= " AND p.jadwal_kirim = '$filter_jadwal'";
}

// Filter status
if ($filter_status && $filter_status != 'semua') {
    $query .= " AND p.status = '$filter_status'";
}

$query .= " ORDER BY p.tanggal_pesan DESC";

$pesanan = mysqli_query($conn, $query);
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-truck me-2"></i>Monitoring Pesanan - <?= date('d F Y', strtotime($filter_tanggal)) ?>
    </div>
    <div class="card-body">
        <!-- Filter Section -->
        <div class="row mb-3">
            <!-- Tampilkan Status Sistem -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert <?= $sistem_buka ? 'alert-success' : 'alert-danger' ?>">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Sistem Pemesanan: <?= $sistem_buka ? 'BUKA' : 'TUTUP' ?></strong>
                        <span class="ms-2">(Jam Operasional: <?= date('H:i', strtotime($jam_kerja['buka'])) ?> -
                            <?= date('H:i', strtotime($jam_kerja['tutup'])) ?>)</span>

                        <?php if (!$sistem_buka): ?>
                            <span class="ms-2">- Sistem akan buka kembali besok jam
                                <?= date('H:i', strtotime($jam_kerja['buka'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label>Filter Tanggal:</label>
                <input type="date" class="form-control" id="filterTanggal" value="<?= $filter_tanggal ?>"
                    onchange="applyFilter()">
            </div>
            <div class="col-md-3">
                <label>Filter Jadwal Kirim:</label>
                <select class="form-select" id="filterJadwal" onchange="applyFilter()">
                    <option value="semua" <?= $filter_jadwal == 'semua' ? 'selected' : '' ?>>Semua Jadwal</option>
                    <option value="09:00" <?= $filter_jadwal == '09:00' ? 'selected' : '' ?>>Pagi (09:00)</option>
                    <option value="12:00" <?= $filter_jadwal == '12:00' ? 'selected' : '' ?>>Siang (12:00)</option>
                    <option value="15:00" <?= $filter_jadwal == '15:00' ? 'selected' : '' ?>>Sore (15:00)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Filter Status:</label>
                <select class="form-select" id="filterStatus" onchange="applyFilter()">
                    <option value="semua" <?= $filter_status == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="Menunggu" <?= $filter_status == 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="Disiapkan" <?= $filter_status == 'Disiapkan' ? 'selected' : '' ?>>Disiapkan</option>
                    <option value="Dibatalkan" <?= $filter_status == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
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
        <!-- Di bagian atas card-body, setelah filter section -->
        <div class="row mb-3">
            <div class="col-12">
                <button class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#inputPesananModal">
                    <i class="fas fa-plus-circle me-1"></i>Input Pesanan Manual
                </button>
            </div>
        </div>
        <!-- Bulk Actions -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="btn-group">
                    <button class="btn btn-outline-primary" onclick="selectAll()">
                        <i class="fas fa-check-square me-1"></i>Pilih Semua
                    </button>
                    <button class="btn btn-success" onclick="updateStatus('Disiapkan')">
                        <i class="fas fa-box me-1"></i>Tandai Disiapkan
                    </button>
                    <button class="btn btn-danger" onclick="updateStatus('Dibatalkan')">
                        <i class="fas fa-times me-1"></i>Tandai Dibatalkan
                    </button>
                    <button class="btn btn-warning" onclick="updateStatus('Belum Dikirim')">
                        <i class="fas fa-truck me-1"></i>Tandai Pengiriman
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Pesanan -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-midle">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        </th>
                        <th width="20%">Nama Anggota</th>
                        <th width="15%">Tanggal Pesan</th>
                        <th width="10%">Jadwal Kirim</th>
                        <th width="8%">Metode</th>
                        <th width="12%">Total</th>
                        <th width="12%">Status</th>
                        <th width="11%">Waktu Tunggu</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($pesanan) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($pesanan)): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="pesanan-checkbox" value="<?= $row['id_pemesanan'] ?>"
                                        data-status="<?= $row['status'] ?>">
                                </td>
                                <td>
                                    <strong><?= $row['nama_anggota'] ?></strong>
                                    <br><small class="text-muted"><?= $row['no_hp'] ?></small>
                                    <br><small class="text-muted"><?= substr($row['alamat'], 0, 30) ?>...</small>
                                </td>
                                <td><?= date('d-m-Y H:i', strtotime($row['tanggal_pesan'])) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= $row['jadwal_kirim'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $row['metode'] === 'transfer' ? 'primary' : 'success' ?>">
                                        <?= $row['metode'] ?>
                                    </span>
                                    <?php if ($row['metode'] === 'transfer' && !empty($row['bank_tujuan'])): ?>
                                        <br><small class="text-muted"><?= $row['bank_tujuan'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $badge_color = [
                                        'Menunggu' => 'warning',
                                        'Disiapkan' => 'info',
                                        'Dikirim' => 'primary',
                                        'Selesai' => 'success',
                                        'Dibatalkan' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $badge_color[$row['status']] ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'Menunggu'): ?>
                                        <?php $waktu_tersisa = 30 - $row['menit_sejak_pesan']; ?>
                                        <?php if ($waktu_tersisa > 0): ?>
                                            <span class="badge bg-danger">
                                                Batal dalam <?= $waktu_tersisa ?> mnt
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                Dapat diproses
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm">
                                        <button class="btn btn-info" onclick="showDetail(<?= $row['id_pemesanan'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($row['status'] == 'Menunggu'): ?>
                                            <button class="btn btn-success"
                                                onclick="processOrder(<?= $row['id_pemesanan'] ?>, 'Disiapkan')">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">
                                Tidak ada pesanan untuk tanggal <?= date('d F Y', strtotime($filter_tanggal)) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Modal Detail Pesanan -->
        <div class="modal fade" id="detailModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Detail Pesanan #<span id="modalId"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Info Anggota</h6>
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
                            </div>
                        </div>

                        <hr>

                        <h6>Item Pesanan</h6>
                        <div class="table-responsive">
                            <table class="table table-sm" id="modalItems">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
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
                        <button type="button" class="btn btn-primary" onclick="printOrder()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Input Pesanan Manual -->
        <div class="modal fade" id="inputPesananModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle me-2"></i>Input Pesanan Manual
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tampilkan status sistem -->
                        <div class="alert <?= $sistem_buka ? 'alert-success' : 'alert-danger' ?> mb-3">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Sistem Pemesanan: <?= $sistem_buka ? 'BUKA' : 'TUTUP' ?></strong>
                            <span class="ms-2">(Jam Operasional: <?= date('H:i', strtotime($jam_kerja['buka'])) ?> -
                                <?= date('H:i', strtotime($jam_kerja['tutup'])) ?>)</span>
                        </div>

                        <?php if ($sistem_buka): ?>
                            <form id="formInputPesanan" action="pages/simpan_pesanan_manual.php" method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Cari Anggota</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="cariAnggota"
                                                placeholder="Masukkan No. Anggota atau Nama">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="cariDataAnggota()">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <div id="hasilPencarian" class="mt-2" style="display: none;">
                                            <!-- Hasil pencarian akan muncul di sini -->
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div id="infoAnggota" style="display: none;">
                                            <h6>Info Anggota</h6>
                                            <p><strong>Nama:</strong> <span id="selectedNama"></span></p>
                                            <p><strong>No. HP:</strong> <span id="selectedNoHp"></span></p>
                                            <p><strong>Alamat:</strong> <span id="selectedAlamat"></span></p>
                                            <input type="hidden" id="selectedIdAnggota" name="id_anggota">
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Jadwal Kirim</label>
                                        <select class="form-select" name="jadwal_kirim" required>
                                            <option value="09:00">Pagi (09:00)</option>
                                            <option value="12:00">Siang (12:00)</option>
                                            <option value="15:00">Sore (15:00)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tanggal Pesan</label>
                                        <input type="datetime-local" class="form-control" name="tanggal_pesan"
                                            value="<?= date('Y-m-d\TH:i') ?>" required>
                                    </div>
                                </div>
                                <h6>Data Pemesan</h6>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-weight: bold;">Nama Pemesan</label>
                                        <input type="text" class="form-control" name="nama_pemesan" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-weight: bold;">Alamat Pemesan</label>
                                        <textarea type="text" class="form-control" name="alamat_pemesan"
                                            required></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-weight: bold;">No. HP/WA</label>
                                        <input type="text" class="form-control" name="no_hp_pemesan" required>
                                    </div>
                                </div>
                                <h6>Metode Pembayaran</h6>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-weight: bold;">Metode Pembayaran</label>
                                        <select class="form-select" name="metode" id="metodePembayaran"
                                            onchange="toggleBankTujuan()" required>
                                            <option value="cash">Cash</option>
                                            <option value="transfer">Transfer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" style="font-weight: bold;">Bank Tujuan</label>
                                        <select class="form-select" name="bank_tujuan" id="bankTujuan"
                                            style="display: none;">
                                            <option value="">-- Pilih Bank --</option>
                                            <option value="Bank MANDIRI">Bank MANDIRI</option>
                                            <option value="Bank BRI">Bank BRI</option>
                                            <option value="Bank BNI">Bank BNI</option>
                                        </select>
                                        <div id="infoTunai" class="form-text">Pembayaran tunai akan masuk ke Kas Tunai</div>
                                    </div>
                                </div>
                                <h6>Daftar Produk</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm" id="tabelProduk">
                                        <thead>
                                            <tr>
                                                <th width="35%">Produk</th>
                                                <th width="15%">Satuan</th>
                                                <th width="15%">Harga</th>
                                                <th width="15%">Stok Tersedia</th>
                                                <th width="10%">Qty</th>
                                                <th width="5%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <select class="form-select form-select-sm" id="pilihProduk"
                                                        onchange="updateProdukInfo()">
                                                        <option value="">-- Pilih Produk --</option>
                                                        <?php
                                                        // PERBAIKAN: Query produk dengan informasi stok dari inventory_ready
                                                        $query_produk = "SELECT p.*, 
                                                            ir.nama_produk as nama_inventory,
                                                            ir.jumlah_tersedia as stok_tersedia,
                                                            ir.satuan_kecil as satuan_inventory
                                                     FROM produk p 
                                                     LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
                                                     WHERE p.status = 'aktif' 
                                                     ORDER BY p.nama_produk";
                                                        $produk = mysqli_query($conn, $query_produk);
                                                        while ($row_produk = mysqli_fetch_assoc($produk)) {
                                                            $stok_info = '';
                                                            $stok_class = '';

                                                            if ($row_produk['is_paket'] == 1) {
                                                                // Produk paket - stok berdasarkan komponen
                                                                $stok_info = 'Paket (Stok: Cek Komponen)';
                                                                $stok_class = 'text-warning';
                                                                $stok_produk = 999; // Nilai tinggi untuk paket
                                                            } else if ($row_produk['stok_tersedia'] !== null) {
                                                                // Produk eceran - stok langsung dari inventory
                                                                $stok_tersedia = floatval($row_produk['stok_tersedia']);
                                                                $konversi = floatval($row_produk['jumlah']);
                                                                $stok_produk = floor($stok_tersedia / $konversi);

                                                                if ($stok_produk > 10) {
                                                                    $stok_class = 'text-success';
                                                                    $stok_info = $stok_produk . ' ' . $row_produk['satuan'];
                                                                } else if ($stok_produk > 0) {
                                                                    $stok_class = 'text-warning';
                                                                    $stok_info = $stok_produk . ' ' . $row_produk['satuan'] . ' (Hampir Habis)';
                                                                } else {
                                                                    $stok_class = 'text-danger';
                                                                    $stok_info = 'Stok Habis';
                                                                }
                                                            } else {
                                                                $stok_class = 'text-danger';
                                                                $stok_info = 'Stok Tidak Tersedia';
                                                                $stok_produk = 0;
                                                            }

                                                            echo "<option value='{$row_produk['id_produk']}' 
                                                        data-harga='{$row_produk['harga']}'
                                                        data-satuan='{$row_produk['satuan']}'
                                                        data-stok='" . ($stok_produk ?? 0) . "'
                                                        data-is-paket='{$row_produk['is_paket']}'
                                                        data-stok-info='{$stok_info}'
                                                        data-stok-class='{$stok_class}'
                                                        data-konversi='{$row_produk['jumlah']}'
                                                        data-id-inventory='{$row_produk['id_inventory']}'>
                                                        {$row_produk['nama_produk']}
                                                </option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="satuanProduk" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="hargaProduk"
                                                        readonly>
                                                </td>
                                                <td>
                                                    <span id="stokInfo"
                                                        class="form-control-plaintext form-control-sm"></span>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm" id="qtyProduk"
                                                        min="1" value="1" onchange="validasiStok()"
                                                        onkeyup="validasiStok()">
                                                    <small id="pesanStok" class="text-danger"
                                                        style="display: none; font-size: 0.8rem;"></small>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        onclick="tambahProduk()" id="btnTambahProduk">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <h6>Produk Dipilih</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered" id="tabelProdukDipilih">
                                        <thead>
                                            <tr>
                                                <th>Produk</th>
                                                <th>Satuan</th>
                                                <th>Harga</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Produk yang dipilih akan muncul di sini -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Subtotal:</th>
                                                <th id="totalHarga">Rp 0</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <input type="hidden" name="total_harga" id="inputTotalHarga">
                                <input type="hidden" name="items" id="inputItems">
                            </form>
                        <?php else: ?>
                            <!-- Tampilan jika sistem tutup -->
                            <div class="text-center py-4">
                                <i class="fas fa-store-slash fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">Sistem Pemesanan Sedang Tutup</h4>
                                <p class="text-muted">Pemesanan manual hanya dapat dilakukan pada jam operasional:</p>
                                <p class="fw-bold"><?= date('H:i', strtotime($jam_kerja['buka'])) ?> -
                                    <?= date('H:i', strtotime($jam_kerja['tutup'])) ?></p>
                                <p class="text-muted">Silakan kembali pada jam operasional untuk input pesanan manual.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <?php if ($sistem_buka): ?>
                            <button type="button" class="btn btn-primary" onclick="simpanPesanan()">
                                <i class="fas fa-save me-1"></i>Simpan Pesanan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Filter function
            function applyFilter() {
                const tanggal = $('#filterTanggal').val();
                const jadwal = $('#filterJadwal').val();
                const status = $('#filterStatus').val();

                window.location.href = `?page=monitoring&tanggal=${tanggal}&jadwal=${jadwal}&status=${status}`;
            }

            // Reset filter
            function resetFilter() {
                window.location.href = '?page=monitoring';
            }

            // Bulk actions
            function selectAll() {
                $('.pesanan-checkbox').prop('checked', true);
            }

            function toggleSelectAll(checkbox) {
                $('.pesanan-checkbox').prop('checked', checkbox.checked);
            }

            function updateStatus(status) {
                const selected = [];
                const validSelected = [];

                $('.pesanan-checkbox:checked').each(function () {
                    const orderId = $(this).val();
                    const currentStatus = $(this).data('status');

                    selected.push(orderId);

                    // Validasi status transition
                    if (canUpdateStatus(currentStatus, status)) {
                        validSelected.push(orderId);
                    }
                });

                if (selected.length === 0) {
                    alert('Pilih minimal 1 pesanan!');
                    return;
                }

                if (validSelected.length === 0) {
                    alert('Tidak ada pesanan yang dapat diupdate ke status "' + status + '"');
                    return;
                }

                if (validSelected.length < selected.length) {
                    if (!confirm(`${selected.length - validSelected.length} pesanan tidak dapat diupdate ke status "${status}". Lanjutkan untuk ${validSelected.length} pesanan?`)) {
                        return;
                    }
                }

                if (confirm(`Update ${validSelected.length} pesanan ke status "${status}"?`)) {
                    // AJAX request untuk update status
                    $.post('pages/update_status.php', {
                        ids: validSelected,
                        status: status
                    }, function (response) {
                        try {
                            // PARSE RESPONSE JSON - INI YANG DIPERBAIKI
                            const result = typeof response === 'string' ? JSON.parse(response) : response;

                            if (result.success) {
                                alert(`Berhasil update ${result.updated} pesanan`);
                                location.reload();
                            } else {
                                alert('Error: ' + result.error);
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e, response);
                            alert('Terjadi kesalahan pada server. Silakan coba lagi.');
                        }
                    });
                }
            }

            // Validasi status transition
            function canUpdateStatus(currentStatus, newStatus) {
                const allowedTransitions = {
                    'Menunggu': ['Disiapkan', 'Dibatalkan'],
                    'Disiapkan': ['Belum Dikirim', 'Dibatalkan'],
                    'Belum Dikirim': [], // Tidak bisa diubah dari monitoring, lanjut ke pengiriman.php
                    'Dibatalkan': [] // Final state
                };

                return allowedTransitions[currentStatus].includes(newStatus);
            }

            // Single order processing
            // Single order processing
            function processOrder(id, status) {
                if (confirm(`Update pesanan #${id} ke status "${status}"?`)) {
                    $.post('pages/update_status.php', {
                        ids: [id],
                        status: status
                    }, function (response) {
                        try {
                            // PARSE RESPONSE JSON - INI YANG DIPERBAIKI
                            const result = typeof response === 'string' ? JSON.parse(response) : response;

                            if (result.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + result.error);
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e, response);
                            alert('Terjadi kesalahan pada server. Silakan coba lagi.');
                        }
                    });
                }
            }

            // Detail modal
            // Detail modal - PERBAIKAN
            function showDetail(id) {
                console.log('Loading detail for order ID:', id);

                $('#modalItems tbody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

                // Tampilkan modal segera (tanpa menunggu data)
                const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
                detailModal.show();

                $.ajax({
                    url: 'pages/get_order_detail.php',
                    type: 'GET',
                    data: { id: id },
                    dataType: 'json', // Explicitly expect JSON
                    timeout: 30000, // Timeout after 10 seconds
                    success: function (data) {
                        // data sudah di-parse otomatis karena dataType: 'json'
                        try {
                            $('#modalId').text(data.pemesanan.id_pemesanan || 'N/A');
                            $('#modalNama').text(data.pemesanan.nama_anggota || 'N/A');
                            $('#modalNoHp').text(data.pemesanan.no_hp || 'N/A');
                            $('#modalAlamat').text(data.pemesanan.alamat || 'N/A');
                            $('#modalTanggal').text(formatDateTime(data.pemesanan.tanggal_pesan));
                            $('#modalJadwal').text(data.pemesanan.jadwal_kirim || 'N/A');
                            $('#modalTotal').text(parseInt(data.pemesanan.total_harga || 0).toLocaleString('id-ID'));

                            const statusBadge = `<span class="badge bg-${getStatusColor(data.pemesanan.status)}">
                    ${data.pemesanan.status || 'N/A'}
                </span>`;
                            $('#modalStatus').html(statusBadge);

                            let itemsHtml = '';
                            if (data.items && data.items.length > 0) {
                                data.items.forEach(item => {
                                    itemsHtml += `
                            <tr>
                                <td>${item.nama_produk || 'N/A'}</td>
                                <td>${item.satuan}</td>
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

                        } catch (error) {
                            console.error('Error processing data:', error, data);
                            $('#modalItems tbody').html('<tr><td colspan="5" class="text-center text-danger">Error processing data</td></tr>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);

                        let errorMessage = 'Failed to load data';

                        if (status === 'timeout') {
                            errorMessage = 'Request timeout. Silakan coba lagi.';
                        } else if (xhr.responseText.includes('<b>') || xhr.responseText.includes('Warning') || xhr.responseText.includes('Error')) {
                            errorMessage = 'Server error occurred. Please check console.';
                        } else if (xhr.status === 404) {
                            errorMessage = 'Detail page not found.';
                        }

                        $('#modalItems tbody').html(`<tr><td colspan="5" class="text-center text-danger">${errorMessage}</td></tr>`);
                    }
                });
            }
            // Fungsi pembantu untuk format tanggal
            function formatDateTime(dateTimeStr) {
                const date = new Date(dateTimeStr);
                return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID');
            }

            function getStatusColor(status) {
                const colors = {
                    'Menunggu': 'warning',
                    'Disiapkan': 'info',
                    'Dibatalkan': 'danger'
                };
                return colors[status] || 'secondary';
            }

            // Auto refresh setiap 15 detik
            setInterval(function () {
                if (window.location.href.includes('page=monitoring') && !$('.modal').is(':visible')) {
                    location.reload();
                }
            }, 30000);

            // Terapkan filter on change
            $('#filterTanggal, #filterJadwal, #filterStatus').change(applyFilter);
            // Fungsi pencarian anggota
            function cariDataAnggota() {
                const keyword = $('#cariAnggota').val();
                if (keyword.length < 2) {
                    alert('Masukkan minimal 2 karakter untuk pencarian');
                    return;
                }

                // Tampilkan loading state
                $('#hasilPencarian').html('<div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i> Mencari...</div>').show();

                $.get('pages/cari_anggota.php', { keyword: keyword })
                    .done(function (response) {
                        try {
                            // Cek jika response sudah object (bukan string JSON)
                            let data;
                            if (typeof response === 'object') {
                                data = response; // Sudah object, tidak perlu parse
                            } else {
                                data = JSON.parse(response); // Parse jika string JSON
                            }

                            let html = '';

                            if (data.length > 0) {
                                data.forEach(anggota => {
                                    html += `
                            <div class="card mb-2 p-2 anggota-item" 
                                 data-id="${anggota.id}" 
                                 data-nama="${anggota.nama}"
                                 data-nohp="${anggota.no_hp}"
                                 data-alamat="${anggota.alamat}"
                                 onclick="pilihAnggota(this)">
                                <strong>${anggota.nama}</strong> - ${anggota.no_anggota}<br>
                                <small>${anggota.no_hp} - ${anggota.alamat.substring(0, 50)}...</small>
                            </div>
                        `;
                                });
                            } else {
                                html = '<div class="text-muted p-2">Tidak ada anggota ditemukan/Anggota sudah tidak Aktif</div>';
                            }

                            $('#hasilPencarian').html(html).show();
                        } catch (error) {
                            console.error('Error parsing response:', error, response);
                            $('#hasilPencarian').html('<div class="text-danger p-2">Error parsing data. Silakan coba lagi.</div>').show();
                        }
                    })
                    .fail(function (xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        $('#hasilPencarian').html('<div class="text-danger p-2">Terjadi kesalahan saat mencari data.</div>').show();
                    });
            }

            // Fungsi memilih anggota
            function pilihAnggota(element) {
                const id = $(element).data('id');
                const nama = $(element).data('nama');
                const nohp = $(element).data('nohp');
                const alamat = $(element).data('alamat');

                $('#selectedIdAnggota').val(id);
                $('#selectedNama').text(nama);
                $('#selectedNoHp').text(nohp);
                $('#selectedAlamat').text(alamat);
                $('#infoAnggota').show();
                $('#hasilPencarian').hide();
            }

            // Fungsi ketika produk dipilih
            $('#pilihProduk').change(function () {
                const selectedOption = $(this).find('option:selected');
                const harga = selectedOption.data('harga');
                const satuan = selectedOption.data('satuan');

                $('#hargaProduk').val('Rp ' + parseInt(harga).toLocaleString('id-ID'));
                if (satuan) {
                    $('#satuanProduk').val(satuan);

                    // Non-aktifkan dropdown jika produk sudah punya satuan tetap
                    $('#satuanProduk').prop('disabled', true);
                } else {
                    // Aktifkan dropdown jika produk tidak punya satuan tetap
                    $('#satuanProduk').prop('disabled', false);
                }
            });

            // Fungsi menambah produk ke daftar
            function tambahProduk() {
                const selectedOption = $('#pilihProduk option:selected');
                const idProduk = selectedOption.val();
                const namaProduk = selectedOption.text();
                const harga = selectedOption.data('harga');
                const satuanDefault = selectedOption.data('satuan');
                const qty = $('#qtyProduk').val();
                const satuan = $('#satuanProduk').val();

                if (!idProduk) {
                    alert('Pilih produk terlebih dahulu');
                    return;
                }

                if (qty < 1) {
                    alert('Quantity minimal 1');
                    return;
                }

                const subtotal = harga * qty;

                // Cek apakah produk sudah ada di daftar
                const existingRow = $(`#tabelProdukDipilih tr[data-produk="${idProduk}"]`);
                if (existingRow.length > 0 && existingRow.data('satuan') === satuan) {
                    // Update quantity jika sudah ada
                    const existingQty = parseFloat(existingRow.find('.qty-item').val());
                    const newQty = existingQty + parseFloat(qty);
                    existingRow.find('.qty-item').val(newQty);

                    const newSubtotal = harga * newQty;
                    existingRow.find('.subtotal-item').text('Rp ' + newSubtotal.toLocaleString('id-ID'));
                    existingRow.find('input[name="qty[]"]').val(newQty);
                } else {
                    // Tambah baru jika belum ada
                    const newRow = `
            <tr data-produk="${idProduk}" data-satuan="${satuan}">
                <td>${namaProduk}<input type="hidden" name="id_produk[]" value="${idProduk}"></td>
                <td>${satuan}<input type="hidden" name="satuan[]" value="${satuan}"></td>
                <td>Rp ${parseInt(harga).toLocaleString('id-ID')}<input type="hidden" name="harga[]" value="${harga}"></td>
                <td>
                    <input type="number" class="form-control form-control-sm qty-item" 
                           value="${qty}" min="1" onchange="updateSubtotal(this, ${harga})">
                    <input type="hidden" name="qty[]" value="${qty}">
                </td>
                <td class="subtotal-item">Rp ${subtotal.toLocaleString('id-ID')}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="hapusProduk(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>datt
            </tr>
        `;
                    $('#tabelProdukDipilih tbody').append(newRow);
                }

                // Update total
                updateTotalHarga();

                // Reset form
                $('#pilihProduk').val('');
                $('#hargaProduk').val('');
                $('#qtyProduk').val(1);
                $('#satuanProduk').val('');
            }

            // Fungsi update subtotal
            function updateSubtotal(input, harga) {
                const qty = $(input).val();
                const subtotal = harga * qty;
                $(input).closest('tr').find('.subtotal-item').text('Rp ' + subtotal.toLocaleString('id-ID'));
                $(input).next('input[name="qty[]"]').val(qty);
                updateTotalHarga();
            }

            // Fungsi hapus produk
            function hapusProduk(button) {
                $(button).closest('tr').remove();
                updateTotalHarga();
            }

            // Fungsi update total harga
            function updateTotalHarga() {
                let total = 0;
                $('.subtotal-item').each(function () {
                    const subtotalText = $(this).text().replace('Rp ', '').replace(/\./g, '');
                    total += parseInt(subtotalText);
                });

                $('#totalHarga').text('Rp ' + total.toLocaleString('id-ID'));
                $('#inputTotalHarga').val(total);
            }

            // Fungsi simpan pesanan
            function simpanPesanan() {
                if (!$('#selectedIdAnggota').val()) {
                    alert('Pilih anggota terlebih dahulu');
                    return;
                }

                if ($('#tabelProdukDipilih tbody tr').length === 0) {
                    alert('Tambahkan minimal satu produk');
                    return;
                }

                // Siapkan data items untuk dikirim
                const items = [];
                $('#tabelProdukDipilih tbody tr').each(function () {
                    const id_produk = $(this).find('input[name="id_produk[]"]').val();
                    const harga = $(this).find('input[name="harga[]"]').val();
                    const satuan = $(this).find('input[name="satuan[]"]').val();
                    const qty = $(this).find('input[name="qty[]"]').val();

                    items.push({
                        id_produk: id_produk,
                        harga: harga,
                        qty: qty
                    });
                });

                $('#inputItems').val(JSON.stringify(items));

                // Submit form
                $('#formInputPesanan').submit();
            }
            // Di bagian akhir script
            $('#formInputPesanan').on('submit', function (e) {
                e.preventDefault();

                // Tampilkan loading state
                const submitBtn = $(this).find('button[type="button"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...').prop('disabled', true);

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json', // Explicitly expect JSON
                    success: function (result) {
                        // dataType: 'json' akan otomatis parse JSON
                        if (result.success) {
                            alert('Pesanan berhasil disimpan dengan ID pemesanan: ' + result.id_pemesanan);
                            $('#inputPesananModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);

                        // Cek jika response mengandung error PHP
                        if (xhr.responseText.includes('<b>') || xhr.responseText.includes('<br />')) {
                            alert('Terjadi error pada server. Silakan cek console untuk detail.');
                        } else {
                            try {
                                // Coba parse response sebagai JSON
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    alert('Error: ' + response.message);
                                } else {
                                    alert('Terjadi kesalahan tidak diketahui. Silakan coba lagi.');
                                }
                            } catch (e) {
                                // Jika bukan JSON, tampilkan error umum
                                alert('Terjadi kesalahan pada server. Data mungkin sudah tersimpan tetapi ada masalah dengan response.');
                            }
                        }
                    },
                    complete: function () {
                        // Restore button state
                        submitBtn.html(originalText).prop('disabled', false);
                    }
                });
            });
            // Variabel global untuk menyimpan info produk yang dipilih
            let produkTerpilih = null;

            // Fungsi update info produk ketika dipilih
            function updateProdukInfo() {
                const selectedOption = $('#pilihProduk').find('option:selected');
                const harga = selectedOption.data('harga');
                const satuan = selectedOption.data('satuan');
                const stok = selectedOption.data('stok');
                const stokInfo = selectedOption.data('stok-info');
                const stokClass = selectedOption.data('stok-class');
                const isPaket = selectedOption.data('is-paket');

                produkTerpilih = {
                    id: selectedOption.val(),
                    nama: selectedOption.text(),
                    harga: harga,
                    satuan: satuan,
                    stok: stok,
                    isPaket: isPaket,
                    konversi: selectedOption.data('konversi'),
                    idInventory: selectedOption.data('id-inventory')
                };

                $('#hargaProduk').val('Rp ' + parseInt(harga).toLocaleString('id-ID'));
                $('#satuanProduk').val(satuan);

                // Tampilkan info stok
                $('#stokInfo').html(`<span class="${stokClass}">${stokInfo}</span>`);

                // Reset validasi
                $('#pesanStok').hide();
                $('#qtyProduk').removeClass('is-invalid');

                // Validasi stok awal
                validasiStok();
            }

            // Fungsi validasi stok real-time
            function validasiStok() {
                if (!produkTerpilih) return;

                const qty = parseInt($('#qtyProduk').val()) || 0;
                const btnTambah = $('#btnTambahProduk');
                const pesanStok = $('#pesanStok');

                // Reset state
                btnTambah.prop('disabled', false);
                pesanStok.hide();
                $('#qtyProduk').removeClass('is-invalid');

                if (qty < 1) {
                    pesanStok.text('Quantity minimal 1').show();
                    $('#qtyProduk').addClass('is-invalid');
                    btnTambah.prop('disabled', true);
                    return false;
                }

                // Validasi stok untuk produk eceran (bukan paket)
                if (produkTerpilih.isPaket == 0) {
                    if (produkTerpilih.stok === null || produkTerpilih.stok === undefined) {
                        pesanStok.text('Stok tidak tersedia').show();
                        $('#qtyProduk').addClass('is-invalid');
                        btnTambah.prop('disabled', true);
                        return false;
                    }

                    if (qty > produkTerpilih.stok) {
                        pesanStok.text(`Stok tidak mencukupi! Maksimal: ${produkTerpilih.stok}`).show();
                        $('#qtyProduk').addClass('is-invalid');
                        btnTambah.prop('disabled', true);
                        return false;
                    }
                }

                // Untuk produk paket, kita akan cek stok komponen saat simpan
                if (produkTerpilih.isPaket == 1) {
                    pesanStok.text('Produk paket - stok akan dicek saat simpan').addClass('text-warning').show();
                }

                return true;
            }

            // Fungsi menambah produk ke daftar dengan validasi stok
            function tambahProduk() {
                if (!produkTerpilih) {
                    alert('Pilih produk terlebih dahulu');
                    return;
                }

                if (!validasiStok()) {
                    return;
                }

                const qty = parseInt($('#qtyProduk').val());
                const subtotal = produkTerpilih.harga * qty;

                // Cek apakah produk sudah ada di daftar
                const existingRow = $(`#tabelProdukDipilih tr[data-produk="${produkTerpilih.id}"]`);
                if (existingRow.length > 0) {
                    // Update quantity jika sudah ada
                    const existingQty = parseInt(existingRow.find('.qty-item').val());
                    const newQty = existingQty + qty;

                    // Validasi stok lagi untuk penambahan
                    if (produkTerpilih.isPaket == 0 && newQty > produkTerpilih.stok) {
                        alert(`Stok tidak mencukupi untuk penambahan! Maksimal: ${produkTerpilih.stok}`);
                        return;
                    }

                    existingRow.find('.qty-item').val(newQty);
                    const newSubtotal = produkTerpilih.harga * newQty;
                    existingRow.find('.subtotal-item').text('Rp ' + newSubtotal.toLocaleString('id-ID'));
                    existingRow.find('input[name="qty[]"]').val(newQty);
                } else {
                    // Tambah baru jika belum ada
                    const newRow = `
        <tr data-produk="${produkTerpilih.id}" data-is-paket="${produkTerpilih.isPaket}" 
            data-id-inventory="${produkTerpilih.idInventory}" data-konversi="${produkTerpilih.konversi}">
            <td>${produkTerpilih.nama}<input type="hidden" name="id_produk[]" value="${produkTerpilih.id}"></td>
            <td>${produkTerpilih.satuan}<input type="hidden" name="satuan[]" value="${produkTerpilih.satuan}"></td>
            <td>Rp ${parseInt(produkTerpilih.harga).toLocaleString('id-ID')}<input type="hidden" name="harga[]" value="${produkTerpilih.harga}"></td>
            <td>
                <input type="number" class="form-control form-control-sm qty-item" 
                       value="${qty}" min="1" onchange="updateSubtotal(this, ${produkTerpilih.harga})">
                <input type="hidden" name="qty[]" value="${qty}">
            </td>
            <td class="subtotal-item">Rp ${subtotal.toLocaleString('id-ID')}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="hapusProduk(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
                    $('#tabelProdukDipilih tbody').append(newRow);
                }

                // Update total
                updateTotalHarga();

                // Reset form
                $('#pilihProduk').val('');
                $('#hargaProduk').val('');
                $('#qtyProduk').val(1);
                $('#satuanProduk').val('');
                $('#stokInfo').text('');
                produkTerpilih = null;
                $('#pesanStok').hide();
            }

            // Fungsi untuk cek stok real-time via AJAX (opsional, untuk stok yang sering berubah)
            function cekStokRealTime(idProduk, callback) {
                $.ajax({
                    url: 'pages/cek_stok.php',
                    type: 'GET',
                    data: { id_produk: idProduk },
                    dataType: 'json',
                    success: function (response) {
                        if (callback) callback(response);
                    },
                    error: function () {
                        console.error('Gagal memeriksa stok');
                    }
                });
            }

            // Update fungsi simpan pesanan untuk validasi stok final
            function simpanPesanan() {
                if (!$('#selectedIdAnggota').val()) {
                    alert('Pilih anggota terlebih dahulu');
                    return;
                }

                if ($('#tabelProdukDipilih tbody tr').length === 0) {
                    alert('Tambahkan minimal satu produk');
                    return;
                }
                // Validasi jika metode transfer dipilih tapi bank tujuan kosong
                const metode = $('#metodePembayaran').val();
                const bankTujuan = $('#bankTujuan').val();
                
                if (metode === 'transfer' && !bankTujuan) {
                    alert('Pilih bank tujuan untuk pembayaran transfer');
                    return;
                }

                // Validasi stok final sebelum simpan
                let stokCukup = true;
                let pesanError = '';

                $('#tabelProdukDipilih tbody tr').each(function () {
                    const isPaket = $(this).data('is-paket');
                    const qty = parseInt($(this).find('.qty-item').val());
                    const stok = $(this).data('stok');
                    const namaProduk = $(this).find('td:first').text().trim();

                    if (isPaket == 0 && qty > stok) {
                        stokCukup = false;
                        pesanError += `- ${namaProduk}: Stok tidak cukup (Butuh: ${qty}, Tersedia: ${stok})\n`;
                    }
                });

                if (!stokCukup) {
                    alert('Stok tidak mencukupi untuk produk berikut:\n\n' + pesanError);
                    return;
                }

                // Siapkan data items untuk dikirim
                const items = [];
                $('#tabelProdukDipilih tbody tr').each(function () {
                    const id_produk = $(this).find('input[name="id_produk[]"]').val();
                    const harga = $(this).find('input[name="harga[]"]').val();
                    const satuan = $(this).find('input[name="satuan[]"]').val();
                    const qty = $(this).find('input[name="qty[]"]').val();

                    items.push({
                        id_produk: id_produk,
                        harga: harga,
                        qty: qty,
                        satuan: satuan
                    });
                });

                $('#inputItems').val(JSON.stringify(items));

                // Submit form
                $('#formInputPesanan').submit();
            }
            // Fungsi untuk toggle bank tujuan berdasarkan metode pembayaran
            function toggleBankTujuan() {
                const metode = $('#metodePembayaran').val();
                const bankTujuan = $('#bankTujuan');
                const infoTunai = $('#infoTunai');
                
                if (metode === 'transfer') {
                    bankTujuan.show().prop('required', true);
                    infoTunai.hide();
                } else {
                    bankTujuan.hide().prop('required', false);
                    infoTunai.show();
                }
            }

            // Panggil saat modal dibuka
            $('#inputPesananModal').on('shown.bs.modal', function () {
                toggleBankTujuan();
            });
        </script>