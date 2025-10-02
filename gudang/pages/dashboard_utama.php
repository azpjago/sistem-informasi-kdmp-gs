<div class="container-fluid">
    <h2 class="mb-4">Dashboard Gudang</h2>
    
    <!-- Alert Notification -->
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Total Produk -->
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Produk</h5>
                            <h3>
                                <?php
                                $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
                                if ($conn->connect_error)
                                    die("Connection failed: " . $conn->connect_error);
                                $result = $conn->query("SELECT COUNT(*) as total FROM produk");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </h3>
                        </div>
                        <i class="fas fa-boxes fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stok Ready untuk Ditambahkan -->
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Stok Ready</h5>
                            <h3>
                                <?php
                                $result = $conn->query("SELECT SUM(jumlah_tersedia) as total FROM inventory_ready WHERE status = 'tersedia'");
                                echo $result->fetch_assoc()['total'] ?: 0;
                                ?>
                            </h3>
                        </div>
                        <i class="fas fa-arrow-up fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending QC -->
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">PO Bulan Ini</h5>
                            <h3>
                                <?php
                                $current_month = date('Y-m');
                                $result = $conn->query("SELECT COUNT(*) as total 
                           FROM purchase_order 
                           WHERE DATE_FORMAT(tanggal_order, '%Y-%m') = '$current_month' 
                           AND status = 'disetujui'");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </h3>
                        </div>
                        <i class="fas fa-clipboard-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Supplier -->
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title">Total Supplier</h5>
                            <h3>
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM supplier");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </h3>
                        </div>
                        <i class="fas fa-truck fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Inventory Ready untuk Ditambahkan -->
        <div class="col-md-6">
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Ready Tersedia</h5>
            <a href="?page=inventory_ready" class="btn btn-sm btn-light">Lihat Semua</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Jumlah Tersedia</th>
                            <th>Satuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                                $query = "SELECT ir.id_inventory, ir.nama_produk, ir.jumlah_tersedia, ir.satuan_kecil 
                                  FROM inventory_ready ir 
                                  WHERE ir.status = 'available' AND ir.jumlah_tersedia > 0
                                  ORDER BY ir.created_at DESC 
                                  LIMIT 5";
                                $result = $conn->query($query);

                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>
                                        <td>{$row['nama_produk']}</td>
                                        <td>{$row['jumlah_tersedia']}</td>
                                        <td>{$row['satuan_kecil']}</td>
                                        <td>
                                            <a href='?page=gudang&edit={$row['id_inventory']}' 
                                               class='btn btn-sm btn-success'>
                                                <i class='fas fa-edit'></i> Kelola
                                            </a>
                                        </td>
                                      </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center'>Tidak ada inventory tersedia</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stok Produk Menipis -->
        <div class="col-md-6">
    <div class="card">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Stok Produk Menipis</h5>
            <a href="?page=produk" class="btn btn-sm btn-light">Lihat Semua</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Stok Tersedia</th>
                            <th>Satuan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                                // QUERY YANG BENAR - sesuaikan dengan struktur tabel produk
                                $query = "SELECT 
                                    ir.nama_produk, 
                                    ir.jumlah_tersedia, 
                                    ir.satuan_kecil
                                FROM inventory_ready ir
                                WHERE ir.jumlah_tersedia <= 5  -- asumsi ada kolom jumlah_total
                                AND ir.status = 'available'
                                ORDER BY ir.jumlah_tersedia ASC
                                LIMIT 5;
                                ";
                                $result = $conn->query($query);

                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $status = $row['jumlah'] == 0 ? 'Habis' : 'Menipis';
                                        $class = $row['jumlah'] == 0 ? 'danger' : 'warning';

                                        echo "<tr>
                                        <td>{$row['nama_produk']}</td>
                                        <td>{$row['jumlah']}</td>
                                        <td>{$row['satuan']}</td>
                                        <td><span class='badge bg-{$class}'>{$status}</span></td>
                                      </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center'>Tidak ada stok menipis</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
</div>

<!-- Modal Tambah Stok ke Produk -->
<div class="modal fade" id="tambahStokModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Stok ke Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_tambah_stok.php" method="POST">
                <input type="hidden" name="id_inventory" id="tambah_id_inventory">
                <input type="hidden" name="id_produk" id="tambah_id_produk">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Produk</label>
                        <input type="text" class="form-control" id="tambah_nama_produk" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah yang akan ditambahkan</label>
                        <input type="number" class="form-control" id="tambah_jumlah" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stok Sekarang</label>
                        <input type="number" class="form-control" id="tambah_stok_sekarang" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stok setelah ditambahkan</label>
                        <input type="number" class="form-control" id="tambah_stok_akhir" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Konfirmasi Tambah Stok</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle tombol tambah stok
    $('.tambah-stok').click(function() {
        const id = $(this).data('id');
        const produk = $(this).data('produk');
        const jumlah = $(this).data('jumlah');
        
        // Isi data ke modal
        $('#tambah_id_inventory').val(id);
        $('#tambah_nama_produk').val(produk);
        $('#tambah_jumlah').val(jumlah);
        
        // Ambil stok sekarang dari server
        $.get('get_stok_produk.php?produk=' + encodeURIComponent(produk), function(data) {
            if (!data.error) {
                $('#tambah_stok_sekarang').val(data.stok);
                $('#tambah_stok_akhir').val(parseInt(data.stok) + parseInt(jumlah));
                $('#tambah_id_produk').val(data.id_produk);
                $('#tambahStokModal').modal('show');
            } else {
                alert('Error: ' + data.error);
            }
        }).fail(function() {
            alert('Error mengambil data stok');
        });
    });
});
</script>