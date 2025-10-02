<?php
// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Filter parameters
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
                   WHEN ha.user_type = 'pengurus' THEN p.username
                   WHEN ha.user_type = 'anggota' THEN a.nama 
                   ELSE 'System' 
                 END as user_name
          FROM history_activity ha 
          LEFT JOIN pengurus p ON ha.user_id = p.id AND ha.user_type = 'pengurus'
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

// Get all users for filter (pengurus with role 'usaha' and all anggota)
$users_query = "SELECT 'pengurus' as user_type, p.id, p.username
                FROM pengurus p 
                WHERE p.role = 'usaha'
                UNION 
                SELECT 'anggota' as user_type, a.id, a.nama 
                FROM anggota a
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

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 0.8rem 0;
            margin-bottom: 1.5rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 1.5rem;
        }

        .nav-menu a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.95rem;
        }

        .nav-menu a:hover {
            color: white;
        }

        .top-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: white;
            font-size: 0.9rem;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #048b26ff;
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

        .badge-login {
            background-color: #28a745;
        }

        .badge-logout {
            background-color: #dc3545;
        }

        .badge-product {
            background-color: #007bff;
        }

        .badge-order {
            background-color: #6f42c1;
        }

        .badge-delivery {
            background-color: #fd7e14;
        }

        .badge-status {
            background-color: #20c997;
        }

        .results-info {
            font-size: 0.85rem;
            color: #6c757d;
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

        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }

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

        .search-box {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
        }

        .search-box input {
            background: transparent;
            border: none;
            color: white;
            padding: 0.2rem 0.5rem;
            width: 180px;
        }

        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 992px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .nav-menu {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .top-info {
                justify-content: space-between;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-history me-2"></i>History & Activity Log
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="history">
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
                        <div class="col-md-3">
                            <label class="form-label">User:</label>
                            <select class="form-select" name="user">
                                <option value="">Semua User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
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
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Activity Table -->
            <div class="table-responsive">
                <table class="table table-striped" id="tabelHistory">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Jenis Aktivitas</th>
                            <th>Deskripsi</th>
                            <th>Tabel</th>
                            <th>ID Record</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?= date('d M Y H:i', strtotime($activity['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></td>
                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        if (strpos($activity['activity_type'], 'login') !== false)
                                            $badge_class = 'badge-login';
                                        elseif (strpos($activity['activity_type'], 'logout') !== false)
                                            $badge_class = 'badge-logout';
                                        elseif (strpos($activity['activity_type'], 'product') !== false)
                                            $badge_class = 'badge-product';
                                        elseif (strpos($activity['activity_type'], 'order') !== false)
                                            $badge_class = 'badge-order';
                                        elseif (strpos($activity['activity_type'], 'delivery') !== false)
                                            $badge_class = 'badge-delivery';
                                        elseif (strpos($activity['activity_type'], 'status') !== false)
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
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h5>Tidak ada activity log ditemukan</h5>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                $(document).ready(function () {
                    // ==========================================================
                    // == INISIALISASI DATATABLES                              ==
                    // ==========================================================
                    if ($('#tabelHistory').length && !$.fn.DataTable.isDataTable('#tabelHistory')) {
                        $('#tabelHistory').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50, 100],
                            language: {
                                search: "Cari Log : ",
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
            </script>
</body>

</html>