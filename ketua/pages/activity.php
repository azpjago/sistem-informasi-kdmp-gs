<?php
// Filter parameters
date_default_timezone_set("Asia/Jakarta");
$filter_type = $_GET['type'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query dengan kondisi dinamis
$query = "SELECT ha.*, 
       CASE 
         WHEN ha.user_type IN ('pengurus', 'ketua', 'bendahara', 'usaha', 'gudang') AND p.role IS NOT NULL THEN p.role
         WHEN ha.user_type = 'anggota' AND a.nama IS NOT NULL THEN a.nama
         ELSE 'Pengurus'
       END as user_name
FROM history_activity ha 
LEFT JOIN pengurus p ON ha.user_id = p.id AND ha.user_type IN ('pengurus', 'ketua', 'bendahara', 'usaha', 'gudang')
LEFT JOIN anggota a ON ha.user_id = a.id AND ha.user_type = 'anggota'
WHERE 1=1";

$count_query = "SELECT COUNT(*) as total 
                FROM history_activity ha 
                WHERE 1=1";

$params = [];
$params_count = [];
$types = '';
$types_count = '';

if (!empty($filter_type)) {
    $query .= " AND ha.activity_type = ?";
    $count_query .= " AND ha.activity_type = ?";
    $params[] = $filter_type;
    $params_count[] = $filter_type;
    $types .= 's';
    $types_count .= 's';
}

if (!empty($filter_user)) {
    $query .= " AND ha.user_id = ?";
    $count_query .= " AND ha.user_id = ?";
    $params[] = $filter_user;
    $params_count[] = $filter_user;
    $types .= 's';
    $types_count .= 's';
}

if (!empty($filter_date)) {
    $query .= " AND DATE(ha.created_at) = ?";
    $count_query .= " AND DATE(ha.created_at) = ?";
    $params[] = $filter_date;
    $params_count[] = $filter_date;
    $types .= 's';
    $types_count .= 's';
}

if (!empty($filter_search)) {
    $query .= " AND ha.description LIKE ?";
    $count_query .= " AND ha.description LIKE ?";
    $params[] = '%' . $filter_search . '%';
    $params_count[] = '%' . $filter_search . '%';
    $types .= 's';
    $types_count .= 's';
}

// Query utama
$query .= " ORDER BY ha.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

// Execute main query
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $activities = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $activities = [];
    echo "Error dalam query: " . mysqli_error($conn);
}

// Get total count
$stmt_count = mysqli_prepare($conn, $count_query);
if ($stmt_count) {
    if (!empty($types_count)) {
        mysqli_stmt_bind_param($stmt_count, $types_count, ...$params_count);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $total_count = mysqli_fetch_assoc($result_count)['total'];
    mysqli_stmt_close($stmt_count);
} else {
    $total_count = 0;
}

$total_pages = ceil($total_count / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// Get distinct activity types for filter
$types_query = "SELECT DISTINCT activity_type FROM history_activity ORDER BY activity_type";
$types_result = mysqli_query($conn, $types_query);
$activity_types = $types_result ? mysqli_fetch_all($types_result, MYSQLI_ASSOC) : [];

// PERBAIKAN QUERY USER: Gunakan UNION ALL dan ambil semua pengurus
$users_query = "SELECT 'pengurus' as user_type, p.id, p.username
                FROM pengurus p 
                WHERE p.status = 'active' OR p.status IS NULL
                UNION ALL
                SELECT 'anggota' as user_type, a.id, a.nama as username 
                FROM anggota a
                WHERE a.status_keanggotaan = 'Aktif' OR a.status_keanggotaan IS NULL
                ORDER BY username";
$users_result = mysqli_query($conn, $users_query);
$users = $users_result ? mysqli_fetch_all($users_result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDMPGS - History & Activity Log</title>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
        }

        .container {
            max-width: 1800px;
            padding: 0 15px;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #458fdaff;
            color: white;
            border-radius: 8px 8px 0 0 !important;
            font-weight: 600;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
        }

        .filter-section {
            background-color: #f8f9fa;
            padding: 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            border: 1px solid #dee2e6;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.4rem;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .table th {
            background-color: #e9ecef;
            font-weight: 600;
            color: #2c3e50;
            padding: 0.75rem;
            border-top: 1px solid #dee2e6;
        }

        .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }

        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.5em;
        }

        .badge-login { background-color: #28a745; }
        .badge-logout { background-color: #dc3545; }
        .badge-product { background-color: #007bff; }
        .badge-order { background-color: #6f42c1; }
        .badge-delivery { background-color: #fd7e14; }
        .badge-status { background-color: #20c997; }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .pagination {
            margin: 1rem 0;
        }

        .page-item.active .page-link {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }

        .page-link {
            color: #2c3e50;
            font-size: 0.9rem;
            padding: 0.4rem 0.75rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>History & Activity Log
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="filter-section">
                    <!-- PERBAIKAN: Tentukan action form dan method GET -->
                    <form method="GET" action="">
                        <!-- PERBAIKAN: Simpan semua parameter di hidden fields -->
                        <input type="hidden" name="page" value="activity">
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Jenis Aktivitas:</label>
                                <select class="form-select" name="type">
                                    <option value="">Semua Jenis</option>
                                    <?php foreach ($activity_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type['activity_type']) ?>"
                                                <?= $filter_type == $type['activity_type'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['activity_type']) ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">User:</label>
                                <select class="form-select" name="user">
                                    <option value="">Semua User</option>
                                    <?php foreach ($users as $user): ?>
                                            <option value="<?= htmlspecialchars($user['id']) ?>" 
                                                <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['username']) ?>
                                                (<?= htmlspecialchars($user['user_type']) ?>)
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal:</label>
                                <input type="date" class="form-control" name="date"
                                    value="<?= htmlspecialchars($filter_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pencarian:</label>
                                <input type="text" class="form-control" name="search"
                                    value="<?= htmlspecialchars($filter_search) ?>" placeholder="Cari deskripsi...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <!-- PERBAIKAN: Tambahkan tombol reset -->
                                <a href="?page=activity" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-refresh"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Info Results -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted">
                        Menampilkan <?= count($activities) ?> dari <?= $total_count ?> aktivitas
                    </div>
                    <?php if (!empty($filter_type) || !empty($filter_user) || !empty($filter_date) || !empty($filter_search)): ?>
                            <div class="text-info">
                                <i class="fas fa-filter me-1"></i>Filter aktif
                            </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Table -->
                <!-- PERBAIKAN: Hapus DataTables, gunakan table biasa -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>User</th>
                                <th>Jenis Aktivitas</th>
                                <th>Deskripsi</th>
                                <th>Tabel</th>
                                <th>#ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($activities) > 0): ?>
                                    <?php foreach ($activities as $activity): ?>
                                            <tr>
                                                <td><?= date('d M Y H:i', strtotime($activity['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($activity['user_name'] ?? 'Pengurus') ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = 'bg-secondary';
                                                    $activity_type = strtolower($activity['activity_type']);
                                                    if (strpos($activity_type, 'login') !== false)
                                                        $badge_class = 'badge-login';
                                                    elseif (strpos($activity_type, 'logout') !== false)
                                                        $badge_class = 'badge-logout';
                                                    elseif (strpos($activity_type, 'product') !== false)
                                                        $badge_class = 'badge-product';
                                                    elseif (strpos($activity_type, 'order') !== false)
                                                        $badge_class = 'badge-order';
                                                    elseif (strpos($activity_type, 'delivery') !== false)
                                                        $badge_class = 'badge-delivery';
                                                    elseif (strpos($activity_type, 'status') !== false)
                                                        $badge_class = 'badge-status';
                                                    ?>
                                                    <span class="badge <?= $badge_class ?>">
                                                        <?= htmlspecialchars($activity['activity_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($activity['description']) ?></td>
                                                <td><?= htmlspecialchars($activity['table_affected'] ?? '-') ?></td>
                                                <td>
                                                    <?php if ($activity['record_id']): ?>
                                                            <span class="badge bg-info">#<?= htmlspecialchars($activity['record_id']) ?></span>
                                                    <?php else: ?>
                                                            -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <h5>Tidak ada activity log ditemukan</h5>
                                            <p class="text-muted">
                                                <?php if (!empty($filter_type) || !empty($filter_user) || !empty($filter_date) || !empty($filter_search)): ?>
                                                        Coba ubah kriteria filter Anda
                                                <?php else: ?>
                                                        Belum ada aktivitas yang tercatat
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Halaman <?= $page ?> dari <?= $total_pages ?>
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= $filter_type ?>&user=<?= $filter_user ?>&date=<?= $filter_date ?>&search=<?= $filter_search ?>">
                                                Sebelumnya
                                            </a>
                                        </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&type=<?= $filter_type ?>&user=<?= $filter_user ?>&date=<?= $filter_date ?>&search=<?= $filter_search ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= $filter_type ?>&user=<?= $filter_user ?>&date=<?= $filter_date ?>&search=<?= $filter_search ?>">
                                                Berikutnya
                                            </a>
                                        </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>