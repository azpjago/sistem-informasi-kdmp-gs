<?php
session_start();

// Cek role ketua
if ($_SESSION['role'] !== 'ketua') {
    header('Location: ../index.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// PROSES APPROVAL/REJECT
if ($_POST['action'] ?? '' === 'update_status') {
    $id_pinjaman = intval($_POST['id_pinjaman'] ?? 0);
    $status = $_POST['status'] ?? '';
    $catatan = $conn->real_escape_string($_POST['catatan'] ?? '');

    if ($id_pinjaman > 0 && in_array($status, ['approved', 'rejected'])) {
        $conn->begin_transaction();

        try {
            // Update status pinjaman
            $stmt = $conn->prepare("
                UPDATE pinjaman 
                SET status = ?, approved_by = ?, tanggal_approve = NOW(), catatan_approval = ?
                WHERE id_pinjaman = ? AND status = 'pending'
            ");
            $ketua = 1;
            $stmt->bind_param("sisi", $status, $ketua, $catatan, $id_pinjaman);

            if ($stmt->execute() && $stmt->affected_rows > 0) {

                // Jika APPROVED, generate jadwal cicilan
                if ($status === 'approved') {
                    generateJadwalCicilan($conn, $id_pinjaman);
                }

                $conn->commit();
                $success = "Pinjaman berhasil " . ($status === 'approved' ? 'disetujui' : 'ditolak');
            } else {
                throw new Exception("Gagal update status. Mungkin sudah diproses sebelumnya.");
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// FUNGSI GENERATE JADWAL CICILAN
function generateJadwalCicilan($conn, $id_pinjaman)
{
    // Ambil data pinjaman
    $pinjaman = $conn->query("
        SELECT jumlah_pinjaman, tenor_bulan, cicilan_per_bulan, bunga_bulan 
        FROM pinjaman WHERE id_pinjaman = $id_pinjaman
    ")->fetch_assoc();

    $jumlah_pinjaman = $pinjaman['jumlah_pinjaman'];
    $tenor = $pinjaman['tenor_bulan'];
    $cicilan_per_bulan = $pinjaman['cicilan_per_bulan'];
    $bunga_per_bulan = $jumlah_pinjaman * ($pinjaman['bunga_bulan'] / 100);
    $pokok_per_bulan = $cicilan_per_bulan - $bunga_per_bulan;

    // Hapus cicilan lama jika ada
    $conn->query("DELETE FROM cicilan WHERE id_pinjaman = $id_pinjaman");

    // Generate cicilan baru
    $today = date('Y-m-d');
    for ($i = 1; $i <= $tenor; $i++) {
        $jatuh_tempo = date('Y-m-d', strtotime("+$i months", strtotime($today)));

        $stmt = $conn->prepare("
            INSERT INTO cicilan (
                id_pinjaman, angsuran_ke, jatuh_tempo, 
                jumlah_pokok, jumlah_bunga, total_cicilan, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->bind_param(
            "iisddd",
            $id_pinjaman,
            $i,
            $jatuh_tempo,
            $pokok_per_bulan,
            $bunga_per_bulan,
            $cicilan_per_bulan
        );

        $stmt->execute();
    }
}

// AMBIL DATA PENGAJUAN PINJAMAN DENGAN FILTER YANG AMAN
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Validasi status filter untuk mencegah SQL injection
$allowed_statuses = ['pending', 'approved', 'rejected', 'active', 'lunas'];
if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'pending'; // Default value jika status tidak valid
}

// Membangun query dengan prepared statement
$where_conditions = ["p.status = ?"];
$params = [$status_filter];
$types = "s";

if (!empty($search)) {
    $where_conditions[] = "(a.nama LIKE ? OR a.no_anggota LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Prepare statement untuk query utama
$stmt = $conn->prepare("
    SELECT
        p.*,
        a.nama as nama_anggota,
        a.no_anggota,
        a.no_hp,
        u.nama as diajukan_oleh
    FROM pinjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    LEFT JOIN pengurus u ON p.approved_by = u.id
    $where_sql
    ORDER BY p.tanggal_pengajuan DESC
");

// Bind parameters jika ada
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$pinjaman_result = $stmt->get_result();

// HITUNG STATISTIK
$stats = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM pinjaman 
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$stat_counts = [];
foreach ($stats as $stat) {
    $stat_counts[$stat['status']] = $stat['count'];
}
?>

<!-- HTML CODE TETAP SAMA -->
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Pinjaman - KDMPGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .stat-card {
            border-left: 4px solid #0d6efd;
        }

        .stat-pending {
            border-left-color: #ffc107;
        }

        .stat-approved {
            border-left-color: #198754;
        }

        .stat-rejected {
            border-left-color: #dc3545;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h3>✅ Approval Pinjaman Anggota</h3>
                <p class="text-muted">Tinjau dan setujui pengajuan pinjaman dari anggota</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center p-3">
                        <h4 class="fw-bold text-primary mb-1"><?= $stat_counts['pending'] ?? 0 ?></h4>
                        <small class="text-muted">Menunggu Approval</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center p-3">
                        <h4 class="fw-bold text-success mb-1"><?= $stat_counts['approved'] ?? 0 ?></h4>
                        <small class="text-muted">Disetujui</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center p-3">
                        <h4 class="fw-bold text-danger mb-1"><?= $stat_counts['rejected'] ?? 0 ?></h4>
                        <small class="text-muted">Ditolak</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center p-3">
                        <h4 class="fw-bold text-info mb-1"><?= $stat_counts['active'] ?? 0 ?></h4>
                        <small class="text-muted">Aktif</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6 mb-3">
                <div class="card stat-card bg-light">
                    <div class="card-body text-center p-3">
                        <h4 class="fw-bold text-dark mb-1"><?= $stat_counts['lunas'] ?? 0 ?></h4>
                        <small class="text-muted">Lunas</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card card-custom mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <!-- TAMBAHKAN INPUT HIDDEN UNTUK PAGE -->
                    <input type="hidden" name="page" value="approval_pinjaman">

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Menunggu Approval
                            </option>
                            <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Disetujui
                            </option>
                            <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="lunas" <?= $status_filter == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pencarian</label>
                        <input type="text" class="form-control" name="search" placeholder="Nama atau No. Anggota..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="dashboard.php?page=approval_pinjaman" class="btn btn-outline-secondary">
                            <i class="fas fa-refresh me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar Pinjaman -->
        <div class="card card-custom">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Pengajuan Pinjaman
                    <span class="badge bg-primary ms-2"><?= $pinjaman_result->num_rows ?> data</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Anggota</th>
                                <th>Jumlah</th>
                                <th>Tenor</th>
                                <th>Sumber Dana</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pinjaman = $pinjaman_result->fetch_assoc()):
                                $status_color = [
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'active' => 'info',
                                    'lunas' => 'dark'
                                ][$pinjaman['status']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($pinjaman['tanggal_pengajuan'])) ?></td>
                                    <td>
                                        <div class="fw-medium"><?= $pinjaman['nama_anggota'] ?></div>
                                        <small class="text-muted"><?= $pinjaman['no_anggota'] ?></small>
                                    </td>
                                    <td class="fw-bold">Rp <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?>
                                    </td>
                                    <td><?= $pinjaman['tenor_bulan'] ?> Bulan</td>
                                    <td><?= $pinjaman['sumber_dana'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $status_color ?> status-badge">
                                            <?= ucfirst($pinjaman['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-detail"
                                            data-id="<?= $pinjaman['id_pinjaman'] ?>">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                        <?php if ($pinjaman['status'] == 'pending'): ?>
                                            <button class="btn btn-sm btn-success approve-pinjaman"
                                                data-id="<?= $pinjaman['id_pinjaman'] ?>">
                                                <i class="fas fa-check"></i> Setujui
                                            </button>
                                            <button class="btn btn-sm btn-danger reject-pinjaman"
                                                data-id="<?= $pinjaman['id_pinjaman'] ?>">
                                                <i class="fas fa-times"></i> Tolak
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                            <?php if ($pinjaman_result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <div>Tidak ada data pinjaman</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pinjaman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Approval -->
    <div class="modal fade" id="approvalModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalTitle">Approval Pinjaman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="approvalForm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id_pinjaman" id="approvalIdPinjaman">
                    <input type="hidden" name="status" id="approvalStatus">

                    <div class="modal-body">
                        <div id="approvalMessage"></div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" name="catatan" rows="3"
                                placeholder="Berikan catatan untuk pemohon..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // View detail modal
        document.querySelectorAll('.view-detail').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const detailContent = document.getElementById('detailContent');

                // Show loading
                detailContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat detail pinjaman...</p>
            </div>
        `;

                // Show modal first
                const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                modal.show();

                // Fetch data
                fetch(`pages/ajax/get_detail_pinjaman.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(html => {
                        detailContent.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error loading detail:', error);
                        detailContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Gagal memuat detail pinjaman. Error: ${error.message}
                    </div>
                `;
                    });
            });
        });

        // Approval process (tetap sama)
        document.querySelectorAll('.approve-pinjaman').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                document.getElementById('approvalIdPinjaman').value = id;
                document.getElementById('approvalStatus').value = 'approved';
                document.getElementById('approvalModalTitle').textContent = 'Setujui Pinjaman';
                document.getElementById('approvalMessage').innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Anda akan menyetujui pengajuan pinjaman ini. 
                Sistem akan secara otomatis membuat jadwal cicilan.
            </div>
        `;
                new bootstrap.Modal(document.getElementById('approvalModal')).show();
            });
        });

        document.querySelectorAll('.reject-pinjaman').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                document.getElementById('approvalIdPinjaman').value = id;
                document.getElementById('approvalStatus').value = 'rejected';
                document.getElementById('approvalModalTitle').textContent = 'Tolak Pinjaman';
                document.getElementById('approvalMessage').innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Anda akan menolak pengajuan pinjaman ini.
            </div>
        `;
                new bootstrap.Modal(document.getElementById('approvalModal')).show();
            });
        });
    </script>
</body>

</html>

<?php $conn->close(); ?>