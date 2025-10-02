<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

// Check connection
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Handle non-aktifkan anggota
if (isset($_POST['nonaktifkan_anggota'])) {
    $id_anggota = $_POST['id_anggota'];

    // Update status keanggotaan menjadi non-aktif dan reset simpanan
    // HANYA simpanan_pokok yang tetap, lainnya direset ke 0
    $stmt = $conn->prepare("UPDATE anggota SET 
        status_keanggotaan = 'Non-Aktif',
        simpanan_pokok = 0.00,
        simpanan_wajib = 0.00, 
        saldo_simpanan = 0.00,
        saldo_sukarela = 0,
        saldo_total = 0,
        poin = 0,
        status_anggota = 'non-aktif'
        WHERE id = ?");
    $stmt->bind_param("i", $id_anggota);

    if ($stmt->execute()) {
        // Log activity
        $log_description = "Non-aktifkan anggota ID: $id_anggota - Reset semua simpanan";
        $conn->query("INSERT INTO history_activity (user_id, user_type, activity_type, description, table_affected, record_id) 
                     VALUES ({$_SESSION['user_id']}, 'admin', 'anggota_nonaktif', '$log_description', 'anggota', $id_anggota)");

        $success_message = "Anggota berhasil dinon-aktifkan.";
    } else {
        $error_message = "Gagal menon-aktifkan anggota: " . $conn->error;
    }
    $stmt->close();
}

// Handle reset simpanan (opsional manual trigger)
if (isset($_POST['reset_simpanan'])) {
    $id_anggota = $_POST['id_anggota'];

    // Reset semua simpanan ke 0
    $stmt = $conn->prepare("UPDATE anggota SET 
        simpanan_wajib = 0.00, 
        saldo_simpanan = 0.00,
        saldo_sukarela = 0,
        saldo_total = 0,
        poin = 0 
        WHERE id = ?");
    $stmt->bind_param("i", $id_anggota);

    if ($stmt->execute()) {
        $success_message = "Simpanan anggota berhasil direset";
    } else {
        $error_message = "Gagal reset simpanan: " . $conn->error;
    }
    $stmt->close();
}

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(nama LIKE ? OR no_anggota LIKE ? OR nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status_keanggotaan = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Query data anggota
$query = "SELECT * FROM anggota $where_sql ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <!-- Header -->
    <div
        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3 fw-bold">Data Anggota</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="?page=data_anggota&export=excel" class="btn btn-sm btn-success me-2">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="">
                <input type="hidden" name="page" value="data_anggota">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Pencarian</label>
                        <input type="text" class="form-control" name="search"
                            placeholder="Cari nama, no anggota, atau NIK..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status Keanggotaan</label>
                        <select class="form-select" name="status">
                            <option value="">Semua Status</option>
                            <option value="Aktif" <?php echo $status_filter == 'Aktif' ? 'selected' : ''; ?>>Aktif
                            </option>
                            <option value="Non-Aktif" <?php echo $status_filter == 'Non-Aktif' ? 'selected' : ''; ?>>
                                Non-Aktif</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Anggota</h6>
                            <h4 class="fw-bold text-primary mb-0">
                                <?php
                                $total_result = $conn->query("SELECT COUNT(*) as total FROM anggota");
                                echo $total_result->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <div class="statistic-icon bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-users fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Anggota Aktif</h6>
                            <h4 class="fw-bold text-success mb-0">
                                <?php
                                $active_result = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status_keanggotaan = 'Aktif'");
                                echo $active_result->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <div class="statistic-icon bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-user-check fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Anggota Non-Aktif</h6>
                            <h4 class="fw-bold text-warning mb-0">
                                <?php
                                $inactive_result = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE status_keanggotaan = 'Non-Aktif'");
                                echo $inactive_result->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <div class="statistic-icon bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-user-times fa-lg text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card statistic-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted mb-2">Total Simpanan</h6>
                            <h4 class="fw-bold text-info mb-0">
                                Rp <?php
                                $simpanan_result = $conn->query("SELECT SUM(saldo_total) as total FROM anggota WHERE status_keanggotaan = 'Aktif'");
                                echo number_format($simpanan_result->fetch_assoc()['total'] ?? 0, 0, ',', '.');
                                ?>
                            </h4>
                        </div>
                        <div class="statistic-icon bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-piggy-bank fa-lg text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>Daftar Anggota</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="anggotaTable">
                    <thead>
                        <tr>
                            <th>No Anggota</th>
                            <th>Nama</th>
                            <th>Jenis Kelamin</th>
                            <th>No HP</th>
                            <th>Simpanan Wajib</th>
                            <th>Saldo Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?php echo $row['no_anggota']; ?></td>
                                <td>
                                    <div class="fw-medium"><?php echo $row['nama']; ?></div>
                                    <small class="text-muted"><?php echo $row['email']; ?></small>
                                </td>
                                <td><?php echo $row['jenis_kelamin']; ?></td>
                                <td><?php echo $row['no_hp']; ?></td>
                                <td class="text-success fw-bold">
                                    Rp <?php echo number_format($row['simpanan_wajib'], 0, ',', '.'); ?>
                                </td>
                                <td class="text-primary fw-bold">
                                    Rp <?php echo number_format($row['saldo_total'], 0, ',', '.'); ?>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo $row['status_keanggotaan'] == 'Aktif' ? 'success' : 'warning'; ?>">
                                        <?php echo $row['status_keanggotaan']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#detailModal<?php echo $row['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($row['status_keanggotaan'] == 'Aktif'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                                                data-bs-target="#nonaktifModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Modal Detail -->
                                    <div class="modal fade" id="detailModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Detail Anggota</h5>
                                                    <button type="button" class="btn-close"
                                                        data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>No Anggota:</strong>
                                                                <?php echo $row['no_anggota']; ?></p>
                                                            <p><strong>Nama:</strong> <?php echo $row['nama']; ?></p>
                                                            <p><strong>NIK:</strong> <?php echo $row['nik']; ?></p>
                                                            <p><strong>TTL:</strong>
                                                                <?php echo $row['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($row['tanggal_lahir'])); ?>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Alamat:</strong> <?php echo $row['alamat']; ?></p>
                                                            <p><strong>RT/RW:</strong>
                                                                <?php echo $row['rt'] . '/' . $row['rw']; ?></p>
                                                            <p><strong>No HP:</strong> <?php echo $row['no_hp']; ?></p>
                                                            <p><strong>Email:</strong> <?php echo $row['email']; ?></p>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <h6>Informasi Simpanan</h6>
                                                    <div class="row">
                                                        <div class="col-md-3 text-center">
                                                            <div class="border rounded p-3">
                                                                <div class="text-primary fw-bold">Simpanan Pokok</div>
                                                                <div class="fw-bold">Rp
                                                                    <?php echo number_format($row['simpanan_pokok'] ?? 10000, 0, ',', '.'); ?>
                                                                </div>
                                                                <small class="text-muted">Tidak dapat ditarik</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 text-center">
                                                            <div class="border rounded p-3">
                                                                <div class="text-success fw-bold">Simpanan Wajib</div>
                                                                <div>Rp
                                                                    <?php echo number_format($row['simpanan_wajib'], 0, ',', '.'); ?>
                                                                </div>
                                                                <small class="text-muted">Per bulan</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 text-center">
                                                            <div class="border rounded p-3">
                                                                <div class="text-info fw-bold">Simpanan Sukarela</div>
                                                                <div>Rp
                                                                    <?php echo number_format($row['saldo_sukarela'], 0, ',', '.'); ?>
                                                                </div>
                                                                <small class="text-muted">Flexible</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3 text-center">
                                                            <div class="border rounded p-3">
                                                                <div class="text-warning fw-bold">Total Simpanan</div>
                                                                <div>Rp
                                                                    <?php echo number_format($row['saldo_total'], 0, ',', '.'); ?>
                                                                </div>
                                                                <small class="text-muted">Current balance</small>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Info jika anggota non-aktif -->
                                                    <?php if ($row['status_keanggotaan'] == 'Non-Aktif'): ?>
                                                        <div class="alert alert-warning mt-3">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            <strong>Anggota Non-Aktif:</strong> Simpanan Pokok, Simpanan Wajib
                                                            dan Sukarela telah dikembalikan.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Modal Non-Aktifkan -->
                                    <!-- Modal Non-Aktifkan -->
<?php if ($row['status_keanggotaan'] == 'Aktif'): ?>
                                        <div class="modal fade" id="nonaktifModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Non-Aktifkan
                                                            Anggota</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin menon-aktifkan anggota berikut?</p>
                                                        <div class="alert alert-warning">
                                                            <strong><?php echo $row['nama']; ?></strong><br>
                                                            <small><?php echo $row['no_anggota']; ?></small>
                                                        </div>
                                    
                                                        <div class="border rounded p-3 bg-light">
                                                            <h6 class="text-danger">Dampak Non-Aktifasi:</h6>
                                                            <ul class="small mb-0">
                                                                <li>Status diubah menjadi <strong>Non-Aktif</strong></li>
                                                                <li><strong>Simpanan Pokok: Rp
                                                                        <?php echo number_format($row['simpanan_pokok'] ?? 10000, 0, ',', '.'); ?></strong> -
                                                                    Tetap disimpan</li>
                                                                <li><strong>Simpanan Wajib: Rp
                                                                        <?php echo number_format($row['simpanan_wajib'], 0, ',', '.'); ?></strong> - Direset ke
                                                                    0</li>
                                                                <li><strong>Simpanan Sukarela: Rp
                                                                        <?php echo number_format($row['saldo_sukarela'], 0, ',', '.'); ?></strong> - Direset ke
                                                                    0</li>
                                                                <li>Total simpanan akan menjadi <strong>Rp. 0 ,-</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="id_anggota" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" name="nonaktifkan_anggota" class="btn btn-warning">
                                                                <i class="fas fa-user-times me-1"></i> Ya, Non-Aktifkan
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#anggotaTable').DataTable({
            "pageLength": 25,
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ data per halaman",
                "zeroRecords": "Tidak ada data yang ditemukan",
                "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                "infoEmpty": "Tidak ada data tersedia",
                "infoFiltered": "(disaring dari _MAX_ total data)"
            }
        });
    });
</script>

<?php
$stmt->close();
$conn->close();
?>