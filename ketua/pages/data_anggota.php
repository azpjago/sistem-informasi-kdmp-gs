<?php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function untuk menghitung saldo kas tunai
function hitungSaldoKasTunai()
{
    global $conn;
    $result = $conn->query("
        SELECT (
            -- Simpanan Anggota (cash)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
             -
            -- Tarik Simpanan Anggota (cash)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            -- Simpanan Sukarela (cash) - setor
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'cash'
             AND jenis_simpanan = 'Simpanan Sukarela')
            -
            -- Simpanan Sukarela (cash) - tarik  
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash'
             AND jenis_simpanan = 'Simpanan Sukarela')
            +
            -- Penjualan (Tunai)
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'cash')
            +
            -- Hibah (cash)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'cash')
            +
            -- Cicilan (cash)
            (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM cicilan 
             WHERE status = 'lunas' AND metode = 'cash' AND jumlah_bayar > 0)
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
        ) as saldo_kas
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_kas'] ?? 0;
}

// Function untuk menghitung saldo bank
function hitungSaldoBank($nama_bank)
{
    global $conn;
    $result = $conn->query("
        SELECT (
            -- Simpanan Anggota (transfer ke bank tertentu) - setor
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            -
            -- Simpanan Anggota (transfer ke bank tertentu) - tarik
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            -- Simpanan Sukarela (transfer ke bank tertentu) - setor
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan = 'Simpanan Sukarela')
            -
            -- Simpanan Sukarela (transfer ke bank tertentu) - tarik
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan = 'Simpanan Sukarela')
            +
            -- Penjualan (Transfer ke bank tertentu)
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'transfer'
             AND p.bank_tujuan = '$nama_bank')
            +
            -- Hibah (transfer ke bank tertentu)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'transfer' AND bank_tujuan = '$nama_bank')
            +
            -- Cicilan (transfer ke bank tertentu)
            (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM cicilan 
             WHERE status = 'lunas' AND metode = 'transfer' AND bank_tujuan = '$nama_bank' AND jumlah_bayar > 0)
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
        ) as saldo_bank
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_bank'] ?? 0;
}

// Hitung saldo untuk setiap rekening
$saldo_kas_tunai = hitungSaldoKasTunai();
$saldo_bank_mandiri = hitungSaldoBank('Bank MANDIRI');
$saldo_bank_bri = hitungSaldoBank('Bank BRI');
$saldo_bank_bni = hitungSaldoBank('Bank BNI');

// Handle non-aktifkan anggota
if (isset($_POST['nonaktifkan_anggota'])) {
    $id_anggota = $_POST['id_anggota'];
    $metode_penarikan = $_POST['metode_penarikan'];
    $bank_tujuan = $_POST['bank_tujuan'] ?? null;

    // Validasi: jika metode transfer, bank_tujuan wajib diisi
    if ($metode_penarikan == 'transfer' && empty($bank_tujuan)) {
        $error_message = "Bank tujuan harus diisi untuk metode transfer";
    } else {
        // HITUNG TOTAL SIMPANAN DARI TABEL PEMBAYARAN
        $stmt_get_simpanan = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Pokok' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as total_pokok,
                COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Wajib' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as total_wajib,
                COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Sukarela' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Sukarela' AND jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as net_sukarela
            FROM pembayaran 
            WHERE anggota_id = ? 
            AND (status_bayar = 'Lunas' OR status = 'Lunas')
        ");
        $stmt_get_simpanan->bind_param("i", $id_anggota);
        $stmt_get_simpanan->execute();
        $result_simpanan = $stmt_get_simpanan->get_result();
        $simpanan_data = $result_simpanan->fetch_assoc();
        $stmt_get_simpanan->close();

        $total_pokok = $simpanan_data['total_pokok'] ?? 0;
        $total_wajib = $simpanan_data['total_wajib'] ?? 0;
        $net_sukarela = $simpanan_data['net_sukarela'] ?? 0;
        $total_penarikan = $total_pokok + $total_wajib + $net_sukarela;

        // CEK SALDO REKENING YANG DIPILIH
        $saldo_tersedia = 0;
        if ($metode_penarikan == 'cash') {
            $saldo_tersedia = hitungSaldoKasTunai();
        } else {
            $saldo_tersedia = hitungSaldoBank($bank_tujuan);
        }

        // VALIDASI: Cek apakah saldo mencukupi
        if ($saldo_tersedia < $total_penarikan) {
            $error_message = "Saldo " . ($metode_penarikan == 'cash' ? 'Kas Tunai' : $bank_tujuan) . " tidak mencukupi! " .
                "Saldo tersedia: Rp " . number_format($saldo_tersedia, 0, ',', '.') .
                ", Kebutuhan: Rp " . number_format($total_penarikan, 0, ',', '.');
        } else {
            // Mulai transaction untuk menjaga konsistensi data
            $conn->begin_transaction();

            try {
                // 1. Buat transaksi penarikan untuk Simpanan Pokok (jika ada)
                if ($total_pokok > 0) {
                    $stmt_tarik_pokok = $conn->prepare("INSERT INTO pembayaran 
                        (anggota_id, jenis_simpanan, jenis_transaksi, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_at) 
                        VALUES (?, 'Simpanan Pokok', 'tarik', ?, ?, ?, 'Lunas', 'Penarikan simpanan pokok - anggota dinonaktifkan', NOW())");
                    $stmt_tarik_pokok->bind_param("idss", $id_anggota, $total_pokok, $metode_penarikan, $bank_tujuan);
                    $stmt_tarik_pokok->execute();
                    $stmt_tarik_pokok->close();
                }

                // 2. Buat transaksi penarikan untuk Simpanan Wajib (jika ada)
                if ($total_wajib > 0) {
                    $stmt_tarik_wajib = $conn->prepare("INSERT INTO pembayaran 
                        (anggota_id, jenis_simpanan, jenis_transaksi, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_at) 
                        VALUES (?, 'Simpanan Wajib', 'tarik', ?, ?, ?, 'Lunas', 'Penarikan simpanan wajib - anggota dinonaktifkan', NOW())");
                    $stmt_tarik_wajib->bind_param("idss", $id_anggota, $total_wajib, $metode_penarikan, $bank_tujuan);
                    $stmt_tarik_wajib->execute();
                    $stmt_tarik_wajib->close();
                }

                // 3. Buat transaksi penarikan untuk Simpanan Sukarela (jika ada)
                if ($net_sukarela > 0) {
                    $stmt_tarik_sukarela = $conn->prepare("INSERT INTO pembayaran 
                        (anggota_id, jenis_simpanan, jenis_transaksi, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_at) 
                        VALUES (?, 'Simpanan Sukarela', 'tarik', ?, ?, ?, 'Lunas', 'Penarikan simpanan sukarela - anggota dinonaktifkan', NOW())");
                    $stmt_tarik_sukarela->bind_param("idss", $id_anggota, $net_sukarela, $metode_penarikan, $bank_tujuan);
                    $stmt_tarik_sukarela->execute();
                    $stmt_tarik_sukarela->close();
                }

                // 4. Update status anggota menjadi non-aktif dan reset saldo
                $stmt_update = $conn->prepare("UPDATE anggota SET 
                    status_keanggotaan = 'Non-Aktif',
                    simpanan_pokok = 0.00,
                    simpanan_wajib = 0.00, 
                    saldo_simpanan = 0.00,
                    saldo_sukarela = 0,
                    saldo_total = 0,
                    poin = 0,
                    status_anggota = 'non-aktif',
                    tanggal_nonaktif = NOW()
                    WHERE id = ?");
                $stmt_update->bind_param("i", $id_anggota);
                $stmt_update->execute();
                $stmt_update->close();

                // Commit transaction
                $conn->commit();

                // Log activity
                $log_description = "Non-aktifkan anggota ID: $id_anggota - Penarikan simpanan: Pokok: Rp " . number_format($total_pokok, 0, ',', '.') .
                    ", Wajib: Rp " . number_format($total_wajib, 0, ',', '.') .
                    ", Sukarela: Rp " . number_format($net_sukarela, 0, ',', '.') .
                    " - Total: Rp " . number_format($total_penarikan, 0, ',', '.') .
                    " - Metode: $metode_penarikan" .
                    ($bank_tujuan ? " - Bank: $bank_tujuan" : "");

                $conn->query("INSERT INTO history_activity (user_id, user_type, activity_type, description, table_affected, record_id) 
                             VALUES ({$_SESSION['user_id']}, '{$_SESSION['role']}', 'anggota_nonaktif', '$log_description', 'anggota', $id_anggota)");

                $success_message = "Anggota berhasil dinon-aktifkan. Total penarikan simpanan: Rp " . number_format($total_penarikan, 0, ',', '.') .
                    " via $metode_penarikan" . ($bank_tujuan ? " - $bank_tujuan" : "");

            } catch (Exception $e) {
                // Rollback transaction jika ada error
                $conn->rollback();
                $error_message = "Gagal menon-aktifkan anggota: " . $e->getMessage();
            }
        }
    }
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

// Function untuk menghitung simpanan dari pembayaran
function hitungSimpananDariPembayaran($conn, $id_anggota)
{
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Pokok' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as total_pokok,
            COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Wajib' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as total_wajib,
            COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Sukarela' AND jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor_sukarela,
            COALESCE(SUM(CASE WHEN jenis_simpanan = 'Simpanan Sukarela' AND jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik_sukarela
        FROM pembayaran 
        WHERE anggota_id = ? 
        AND (status_bayar = 'Lunas' OR status = 'Lunas')
    ");
    $stmt->bind_param("i", $id_anggota);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return [
        'pokok' => $data['total_pokok'] ?? 0,
        'wajib' => $data['total_wajib'] ?? 0,
        'sukarela' => ($data['setor_sukarela'] ?? 0) - ($data['tarik_sukarela'] ?? 0)
    ];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anggota - KDMPGS</title>
</head>

<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-4">Data Anggota 👨🏻‍🦱👩🏻‍🦱</h3>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="pages/export_anggota.php" class="btn btn-sm btn-success me-2">
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

            <!-- Kas Tunai -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card statistic-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Kas Tunai</h6>
                                <h4 class="fw-bold text-success mb-0">
                                    Rp <?php echo number_format($saldo_kas_tunai, 0, ',', '.'); ?>
                                </h4>
                                <small class="text-muted">Saldo tersedia</small>
                            </div>
                            <div class="statistic-icon bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-money-bill-wave fa-lg text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank MANDIRI -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card statistic-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Bank MANDIRI</h6>
                                <h4 class="fw-bold text-info mb-0">
                                    Rp <?php echo number_format($saldo_bank_mandiri, 0, ',', '.'); ?>
                                </h4>
                                <small class="text-muted">1234567890</small>
                            </div>
                            <div class="statistic-icon bg-info bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-university fa-lg text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Semua Rekening -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card statistic-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Total Rekening</h6>
                                <h4 class="fw-bold text-warning mb-0">
                                    Rp
                                    <?php echo number_format($saldo_kas_tunai + $saldo_bank_mandiri + $saldo_bank_bri + $saldo_bank_bni, 0, ',', '.'); ?>
                                </h4>
                                <small class="text-muted">Kas + Bank</small>
                            </div>
                            <div class="statistic-icon bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-wallet fa-lg text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#detailModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($row['status_keanggotaan'] == 'Aktif'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
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
                                                                <p><strong>No
                                                                        Anggota:</strong><?php echo $row['no_anggota']; ?>
                                                                </p>
                                                                <p><strong>Nama:</strong> <?php echo $row['nama']; ?></p>
                                                                <p><strong>NIK:</strong> <?php echo $row['nik']; ?></p>
                                                                <p><strong>TTL:</strong><?php echo $row['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($row['tanggal_lahir'])); ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Alamat:</strong> <?php echo $row['alamat']; ?>
                                                                </p>
                                                                <p><strong>RT/RW:</strong><?php echo $row['rt'] . '/' . $row['rw']; ?>
                                                                </p>
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
                                                                    <div class="fw-bold">
                                                                        Rp<?php echo number_format($row['simpanan_pokok'] ?? 10000, 0, ',', '.'); ?>
                                                                    </div>
                                                                    <small class="text-muted">Tidak dapat ditarik</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 text-center">
                                                                <div class="border rounded p-3">
                                                                    <div class="text-success fw-bold">Simpanan Wajib</div>
                                                                    <div>
                                                                        Rp<?php echo number_format($row['simpanan_wajib'], 0, ',', '.'); ?>
                                                                    </div>
                                                                    <small class="text-muted">Per bulan</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 text-center">
                                                                <div class="border rounded p-3">
                                                                    <div class="text-info fw-bold">Simpanan Sukarela</div>
                                                                    <div>
                                                                        Rp<?php echo number_format($row['saldo_sukarela'], 0, ',', '.'); ?>
                                                                    </div>
                                                                    <small class="text-muted">Flexible</small>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3 text-center">
                                                                <div class="border rounded p-3">
                                                                    <div class="text-warning fw-bold">Total Simpanan</div>
                                                                    <div>
                                                                        Rp<?php echo number_format($row['saldo_total'], 0, ',', '.'); ?>
                                                                    </div>
                                                                    <small class="text-muted">Current balance</small>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php if ($row['status_keanggotaan'] == 'Non-Aktif'): ?>
                                                            <div class="alert alert-warning mt-3">
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                <strong>Anggota Non-Aktif:</strong> Simpanan Pokok, Simpanan
                                                                Wajib dan Sukarela telah dikembalikan.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Non-Aktifkan -->
                                        <?php if ($row['status_keanggotaan'] == 'Aktif'): ?>
                                            <?php
                                            $simpanan_modal = hitungSimpananDariPembayaran($conn, $row['id']);
                                            $total_penarikan_modal = $simpanan_modal['pokok'] + $simpanan_modal['wajib'] + $simpanan_modal['sukarela'];
                                            ?>

                                            <div class="modal fade" id="nonaktifModal<?php echo $row['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title text-danger">
                                                                <i class="fas fa-exclamation-triangle me-2"></i>Non-Aktifkan
                                                                Anggota
                                                            </h5>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" id="formNonaktif<?php echo $row['id']; ?>">
                                                            <div class="modal-body">
                                                                <p>Apakah Anda yakin ingin menon-aktifkan anggota berikut?</p>
                                                                <div class="alert alert-warning">
                                                                    <strong><?php echo $row['nama']; ?></strong><br>
                                                                    <small><?php echo $row['no_anggota']; ?></small>
                                                                </div>

                                                                <div class="border rounded p-3 bg-light mb-3">
                                                                    <h6 class="text-danger">Dampak Non-Aktifasi:</h6>
                                                                    <ul class="small mb-0">
                                                                        <li>Status diubah menjadi <strong>Non-Aktif</strong>
                                                                        </li>
                                                                        <li><strong>Simpanan Pokok:
                                                                                Rp<?php echo number_format($simpanan_modal['pokok'], 0, ',', '.'); ?></strong>
                                                                        </li>
                                                                        <li><strong>Simpanan Wajib:
                                                                                Rp<?php echo number_format($simpanan_modal['wajib'], 0, ',', '.'); ?></strong>
                                                                        </li>
                                                                        <li><strong>Simpanan Sukarela:
                                                                                Rp<?php echo number_format($simpanan_modal['sukarela'], 0, ',', '.'); ?></strong>
                                                                        </li>
                                                                        <li>Total penarikan:
                                                                            <strong>Rp<?php echo number_format($total_penarikan_modal, 0, ',', '.'); ?></strong>
                                                                        </li>
                                                                    </ul>
                                                                </div>

                                                                <!-- Form Input Metode Penarikan -->
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Metode Penarikan *</label>
                                                                        <select class="form-select" name="metode_penarikan"
                                                                            id="metodePenarikan<?php echo $row['id']; ?>"
                                                                            onchange="toggleBankSection(<?php echo $row['id']; ?>); updateSaldoInfo(<?php echo $row['id']; ?>);"
                                                                            required>
                                                                            <option value="">Pilih Metode</option>
                                                                            <option value="cash">Cash (Tunai)</option>
                                                                            <option value="transfer">Transfer Bank</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div id="bankSection<?php echo $row['id']; ?>"
                                                                            style="display: none;">
                                                                            <label class="form-label">Bank Tujuan *</label>
                                                                            <select class="form-select" name="bank_tujuan"
                                                                                id="bankTujuan<?php echo $row['id']; ?>"
                                                                                onchange="updateSaldoInfo(<?php echo $row['id']; ?>)">
                                                                                <option value="">Pilih Bank</option>
                                                                                <option value="Bank MANDIRI">Bank MANDIRI
                                                                                </option>
                                                                                <option value="Bank BRI">Bank BRI</option>
                                                                                <option value="Bank BNI">Bank BNI</option>
                                                                            </select>
                                                                            <small class="text-muted">Wajib diisi untuk metode
                                                                                Transfer</small>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Info Saldo Real-time -->
                                                                <div id="saldoInfo<?php echo $row['id']; ?>" class="mt-3"
                                                                    style="display: none;">
                                                                    <div class="alert alert-info">
                                                                        <h6 class="mb-2"><i
                                                                                class="fas fa-wallet me-2"></i>Informasi Saldo
                                                                        </h6>
                                                                        <div class="row">
                                                                            <div class="col-6">
                                                                                <small>Saldo Tersedia:</small><br>
                                                                                <strong
                                                                                    id="saldoTersedia<?php echo $row['id']; ?>">-</strong>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <small>Total Penarikan:</small><br>
                                                                                <strong>Rp
                                                                                    <?php echo number_format($total_penarikan_modal, 0, ',', '.'); ?></strong>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mt-2"
                                                                            id="saldoStatus<?php echo $row['id']; ?>"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="hidden" name="id_anggota"
                                                                    value="<?php echo $row['id']; ?>">
                                                                <button type="submit" name="nonaktifkan_anggota"
                                                                    class="btn btn-danger"
                                                                    id="submitBtn<?php echo $row['id']; ?>">
                                                                    <i class="fas fa-user-times me-1"></i> Ya, Non-Aktifkan &
                                                                    Proses Penarikan
                                                                </button>
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Batal</button>
                                                            </div>
                                                        </form>
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
        // Fungsi global untuk toggle bank section
        function toggleBankSection(anggotaId) {
            const metodeSelect = document.getElementById('metodePenarikan' + anggotaId);
            const bankSection = document.getElementById('bankSection' + anggotaId);
            const bankSelect = document.getElementById('bankTujuan' + anggotaId);

            if (metodeSelect && bankSection && bankSelect) {
                if (metodeSelect.value === 'transfer') {
                    bankSection.style.display = 'block';
                    bankSelect.required = true;
                } else {
                    bankSection.style.display = 'none';
                    bankSelect.required = false;
                    bankSelect.value = '';
                }
            }
        }

        // Fungsi untuk update info saldo real-time
        function updateSaldoInfo(anggotaId) {
            const metodeSelect = document.getElementById('metodePenarikan' + anggotaId);
            const bankSelect = document.getElementById('bankTujuan' + anggotaId);
            const saldoInfo = document.getElementById('saldoInfo' + anggotaId);
            const saldoTersedia = document.getElementById('saldoTersedia' + anggotaId);
            const saldoStatus = document.getElementById('saldoStatus' + anggotaId);
            const submitBtn = document.getElementById('submitBtn' + anggotaId);

            const totalPenarikan = <?php echo isset($total_penarikan_modal) ? $total_penarikan_modal : 0; ?>;

            if (metodeSelect.value) {
                // Tampilkan info saldo
                saldoInfo.style.display = 'block';

                if (metodeSelect.value === 'cash') {
                    const saldoKas = <?php echo $saldo_kas_tunai; ?>;
                    saldoTersedia.textContent = 'Rp ' + saldoKas.toLocaleString('id-ID');

                    if (saldoKas >= totalPenarikan) {
                        saldoStatus.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Saldo mencukupi</span>';
                        submitBtn.disabled = false;
                    } else {
                        saldoStatus.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Saldo tidak mencukupi!</span>';
                        submitBtn.disabled = true;
                    }
                } else if (metodeSelect.value === 'transfer' && bankSelect.value) {
                    let saldoBank = 0;
                    switch (bankSelect.value) {
                        case 'Bank MANDIRI':
                            saldoBank = <?php echo $saldo_bank_mandiri; ?>;
                            break;
                        case 'Bank BRI':
                            saldoBank = <?php echo $saldo_bank_bri; ?>;
                            break;
                        case 'Bank BNI':
                            saldoBank = <?php echo $saldo_bank_bni; ?>;
                            break;
                        default:
                            saldoBank = 0;
                    }

                    saldoTersedia.textContent = 'Rp ' + saldoBank.toLocaleString('id-ID');

                    if (saldoBank >= totalPenarikan) {
                        saldoStatus.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Saldo mencukupi</span>';
                        submitBtn.disabled = false;
                    } else {
                        saldoStatus.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Saldo tidak mencukupi!</span>';
                        submitBtn.disabled = true;
                    }
                } else {
                    saldoTersedia.textContent = '-';
                    saldoStatus.innerHTML = '';
                    submitBtn.disabled = true;
                }
            } else {
                saldoInfo.style.display = 'none';
                submitBtn.disabled = false;
            }
        }

        // Event listener untuk semua modal saat dibuka
        document.addEventListener('DOMContentLoaded', function () {
            // Inisialisasi untuk modal yang sudah ada di DOM
            const metodeSelects = document.querySelectorAll('select[name="metode_penarikan"]');
            metodeSelects.forEach(select => {
                const anggotaId = select.id.replace('metodePenarikan', '');
                toggleBankSection(anggotaId);
            });

            // Event listener untuk modal Bootstrap saat ditampilkan
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', function () {
                    const metodeSelect = this.querySelector('select[name="metode_penarikan"]');
                    if (metodeSelect) {
                        const anggotaId = metodeSelect.id.replace('metodePenarikan', '');
                        toggleBankSection(anggotaId);
                        updateSaldoInfo(anggotaId);
                    }
                });
            });
        });

        // Validasi form sebelum submit
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function (e) {
                    const metodeSelect = this.querySelector('select[name="metode_penarikan"]');
                    const bankSelect = this.querySelector('select[name="bank_tujuan"]');

                    if (metodeSelect && metodeSelect.value === 'transfer' && bankSelect && !bankSelect.value) {
                        e.preventDefault();
                        alert('Bank tujuan harus dipilih untuk metode transfer');
                        bankSelect.focus();
                        return false;
                    }
                });
            });
        });

        // DataTables
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
</body>

</html>
