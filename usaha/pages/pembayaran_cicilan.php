<?php
// Include file history log
require_once 'functions/history_log.php';
// PROSES PEMBAYARAN CICILAN
if ($_POST['action'] ?? '' === 'bayar_cicilan') {
    $id_cicilan = intval($_POST['id_cicilan'] ?? 0);
    $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
    $metode = $conn->real_escape_string($_POST['metode'] ?? '');
    $bank_tujuan = $conn->real_escape_string($_POST['bank_tujuan'] ?? '');
    $tanggal_bayar = $conn->real_escape_string($_POST['tanggal_bayar'] ?? date('Y-m-d H:i:s'));
    $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

    // Ambil data cicilan
    $cicilan_result = $conn->query("
        SELECT c.*, p.id_pinjaman, p.id_anggota, p.jumlah_pinjaman,
               a.nama, a.no_anggota
        FROM cicilan c
        JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
        JOIN anggota a ON p.id_anggota = a.id
        WHERE c.id_cicilan = $id_cicilan
    ");

    if ($cicilan_result->num_rows === 0) {
        $error = "Data cicilan tidak ditemukan!";
    } else {
        $cicilan_data = $cicilan_result->fetch_assoc();

        // Validasi jumlah bayar
        $sisa_cicilan = $cicilan_data['total_cicilan'] - $cicilan_data['jumlah_bayar'];

        if ($jumlah_bayar <= 0) {
            $error = "Jumlah pembayaran harus lebih dari 0!";
        } elseif ($jumlah_bayar > $sisa_cicilan) {
            $error = "Jumlah pembayaran melebihi sisa cicilan! Sisa: Rp " . number_format($sisa_cicilan, 0, ',', '.');
        }

        if (!isset($error)) {
            // Hitung jumlah bayar baru dan sisa cicilan
            $jumlah_bayar_baru = $cicilan_data['jumlah_bayar'] + $jumlah_bayar;
            $sisa_cicilan_baru = $cicilan_data['total_cicilan'] - $jumlah_bayar_baru;

            // Tentukan status cicilan
            $old_status = $cicilan_data['status'];
            if ($jumlah_bayar_baru >= $cicilan_data['total_cicilan']) {
                $status = 'lunas';
            } else {
                // Cek jika telat bayar
                $today = new DateTime();
                $jatuh_tempo = new DateTime($cicilan_data['jatuh_tempo']);
                $status = ($today > $jatuh_tempo) ? 'telat' : 'pending';
            }

            // Update cicilan
            $stmt = $conn->prepare("
                UPDATE cicilan 
                SET jumlah_bayar = ?, 
                    tanggal_bayar = ?,
                    metode = ?,
                    bank_tujuan = ?,
                    status = ?,
                    keterangan = ?
                WHERE id_cicilan = ?
            ");

            $stmt->bind_param(
                "dsssssi",
                $jumlah_bayar_baru,
                $tanggal_bayar,
                $metode,
                $bank_tujuan,
                $status,
                $keterangan,
                $id_cicilan
            );

            if ($stmt->execute()) {
                // Cek jika semua cicilan sudah lunas, update status pinjaman
                $check_lunas = $conn->query("
                    SELECT COUNT(*) as sisa_cicilan 
                    FROM cicilan 
                    WHERE id_pinjaman = {$cicilan_data['id_pinjaman']} 
                    AND status != 'lunas'
                ");
                $sisa_data = $check_lunas->fetch_assoc();

                $is_pinjaman_lunas = false;
                if ($sisa_data['sisa_cicilan'] == 0) {
                    $conn->query("UPDATE pinjaman SET status = 'lunas' WHERE id_pinjaman = {$cicilan_data['id_pinjaman']}");
                    $is_pinjaman_lunas = true;

                    // LOG: Pelunasan Pinjaman
                    log_pelunasan_pinjaman(
                        $cicilan_data['id_pinjaman'],
                        $cicilan_data['no_anggota'],
                        $cicilan_data['nama'],
                        $cicilan_data['jumlah_pinjaman'],
                        'usaha'
                    );
                }

                // LOG: Pembayaran Cicilan dengan detail yang lebih baik
                if ($status === 'lunas') {
                    // Jika cicilan lunas
                    log_pelunasan_cicilan(
                        $id_cicilan,
                        $cicilan_data['id_anggota'],
                        $jumlah_bayar,
                        $metode,
                        'usaha'
                    );
                } else {
                    // Jika pembayaran sebagian
                    log_pembayaran_sebagian_cicilan(
                        $id_cicilan,
                        $cicilan_data['id_anggota'],
                        $jumlah_bayar,
                        $sisa_cicilan_baru,
                        $metode,
                        'usaha'
                    );
                }

                // LOG: Perubahan status cicilan jika berubah
                if ($old_status !== $status) {
                    log_status_cicilan_change($id_cicilan, $old_status, $status, 'usaha');
                }

                $success_message = "Pembayaran cicilan berhasil dicatat!";
                if ($status === 'lunas') {
                    $success_message .= " Cicilan telah dilunasi.";
                }
                if ($is_pinjaman_lunas) {
                    $success_message .= " Seluruh pinjaman telah dilunasi.";
                }

                $success = $success_message;
            } else {
                $error = "Gagal melakukan pembayaran: " . $stmt->error;
            }
        }
    }
}

// AMBIL DATA CICILAN YANG PERLU DIBAYAR
$cicilan_result = $conn->query("
    SELECT 
        c.*,
        p.id_pinjaman,
        p.jumlah_pinjaman,
        p.tanggal_pengajuan,
        p.tenor_bulan,
        a.nama,
        a.no_anggota,
        DATEDIFF(CURDATE(), c.jatuh_tempo) as hari_terlambat,
        (c.total_cicilan - c.jumlah_bayar) as sisa_cicilan
    FROM cicilan c
    JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE p.status = 'approved' 
    AND c.status != 'lunas'
    ORDER BY c.jatuh_tempo ASC, a.nama
");

// AMBIL RIWAYAT PEMBAYARAN TERBARU
$riwayat_result = $conn->query("
    SELECT 
        c.*,
        p.id_pinjaman,
        a.nama,
        a.no_anggota
    FROM cicilan c
    JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE c.jumlah_bayar > 0
    ORDER BY c.tanggal_bayar DESC
    LIMIT 50
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Cicilan - KDMPGS</title>
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

        .cicilan-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cicilan-item:hover {
            background-color: #f8f9fa;
            border-color: #0d6efd;
        }

        .cicilan-item.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
            box-shadow: 0 0 0 2px #0d6efd;
        }

        /* Kategori warna untuk cicilan */
        .cicilan-item.telat {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
        }

        .cicilan-item.due_soon {
            border-left: 4px solid #ffc107;
            background-color: #fff3cd;
        }

        .cicilan-item.pending {
            border-left: 4px solid #6c757d;
            background-color: #f8f9fa;
        }

        .payment-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #0d6efd;
        }

        .calculation-box {
            background: #e7f1ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .filter-section {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-section .form-select {
            width: auto !important;
        }

        #counterInfo .badge {
            margin-right: 5px;
            font-size: 0.7rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h3 class="mb-4">💰 Pembayaran Cicilan Pinjaman</h3>
                <p class="text-muted">Kelola pembayaran cicilan anggota koperasi</p>
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

        <div class="row">
            <!-- Daftar Cicilan yang Perlu Dibayar -->
            <div class="col-lg-6 mb-4">
                <div class="card card-custom">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Cicilan Perlu Dibayar</h5>
                            <div class="filter-section">
                                <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                                    <option value="all">Semua Status</option>
                                    <option value="telat">Terlambat</option>
                                    <option value="due_soon">Jatuh Tempo</option>
                                    <option value="pending">Menunggu</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filter Tambahan -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control form-control-sm" id="searchAnggota"
                                    placeholder="Cari nama anggota...">
                            </div>
                            <div class="col-md-6">
                                <select class="form-select form-select-sm" id="filterJatuhTempo">
                                    <option value="all">Semua Jatuh Tempo</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="week">Minggu Ini</option>
                                    <option value="month">Bulan Ini</option>
                                    <option value="overdue">Terlambat</option>
                                </select>
                            </div>
                        </div>

                        <!-- Counter Info -->
                        <div class="alert alert-light py-2 mb-3" id="counterInfo">
                            <small>
                                <span class="badge bg-danger" id="countTelat">0 Terlambat</span>
                                <span class="badge bg-warning" id="countDueSoon">0 Jatuh Tempo</span>
                                <span class="badge bg-info" id="countPending">0 Menunggu</span>
                                <span class="badge bg-success" id="totalCicilan">0 Total</span>
                            </small>
                        </div>

                        <?php if ($cicilan_result->num_rows > 0): ?>
                            <div id="listCicilan">
                                <?php while ($cicilan = $cicilan_result->fetch_assoc()):
                                    $status_class = $cicilan['status'];
                                    $is_telat = $cicilan['hari_terlambat'] > 0;
                                    $is_due_soon = $cicilan['hari_terlambat'] >= -7 && $cicilan['hari_terlambat'] <= 0;
                                    $sisa_cicilan = $cicilan['total_cicilan'] - $cicilan['jumlah_bayar'];

                                    // Tentukan kategori untuk filter
                                    $kategori = 'pending';
                                    if ($is_telat) {
                                        $kategori = 'telat';
                                    } elseif ($is_due_soon) {
                                        $kategori = 'due_soon';
                                    }
                                    ?>
                                    <div class="cicilan-item <?= $status_class ?> <?= $kategori ?>"
                                        onclick="selectCicilan(<?= $cicilan['id_cicilan'] ?>, this)"
                                        data-sisa="<?= $sisa_cicilan ?>" data-nama="<?= htmlspecialchars($cicilan['nama']) ?>"
                                        data-jatuh-tempo="<?= $cicilan['jatuh_tempo'] ?>" data-kategori="<?= $kategori ?>"
                                        data-status="<?= $cicilan['status'] ?>">
                                        <div class="row">
                                            <div class="col-8">
                                                <strong><?= $cicilan['nama'] ?></strong><br>
                                                <small class="text-muted">No. Anggota: <?= $cicilan['no_anggota'] ?></small><br>
                                                <small>Angsuran ke: <?= $cicilan['angsuran_ke'] ?></small>
                                            </div>
                                            <div class="col-4 text-end">
                                                <div class="fw-bold text-danger">Rp
                                                    <?= number_format($sisa_cicilan, 0, ',', '.') ?></div>
                                                <small class="text-muted">Jatuh
                                                    tempo:<br><?= date('d/m/Y', strtotime($cicilan['jatuh_tempo'])) ?></small>
                                                <?php if ($is_telat): ?>
                                                    <div class="badge bg-danger mt-1">Terlambat <?= $cicilan['hari_terlambat'] ?>
                                                        hari</div>
                                                <?php elseif ($is_due_soon): ?>
                                                    <div class="badge bg-warning mt-1">Segera</div>
                                                <?php elseif ($cicilan['status'] == 'pending'): ?>
                                                    <div class="badge bg-secondary mt-1">Menunggu</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($cicilan['jumlah_bayar'] > 0): ?>
                                            <div class="mt-2">
                                                <small class="text-success">
                                                    <i class="fas fa-check-circle"></i>
                                                    Sudah bayar: Rp <?= number_format($cicilan['jumlah_bayar'], 0, ',', '.') ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div>Tidak ada cicilan yang perlu dibayar</div>
                                <small>Semua cicilan sudah lunas</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Form Pembayaran -->
            <div class="col-lg-6">
                <div class="card card-custom">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Form Pembayaran Cicilan</h5>
                    </div>
                    <div class="card-body">
                        <div id="noSelection" class="text-center py-4 text-muted">
                            <i class="fas fa-hand-pointer fa-2x mb-2"></i>
                            <div>Pilih cicilan yang akan dibayar</div>
                            <small>Klik pada salah satu cicilan di sebelah kiri</small>
                        </div>

                        <form method="POST" id="formPembayaran" style="display: none;">
                            <input type="hidden" name="action" value="bayar_cicilan">
                            <input type="hidden" name="id_cicilan" id="id_cicilan">

                            <!-- Info Cicilan Terpilih -->
                            <div class="alert alert-info mb-3" id="cicilanInfo">
                                <div class="row">
                                    <div class="col-8">
                                        <strong id="infoNama">-</strong><br>
                                        <small>No. Anggota: <span id="infoNoAnggota">-</span></small><br>
                                        <small>Angsuran ke: <span id="infoAngsuranKe">-</span></small><br>
                                        <small>Jatuh tempo: <span id="infoJatuhTempo">-</span></small>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="fw-bold text-danger" id="infoSisaCicilan">-</div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2"
                                            onclick="resetSelection()">
                                            <i class="fas fa-times"></i> Ganti
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Detail Cicilan -->
                            <div class="calculation-box">
                                <h6 class="fw-bold mb-3">Detail Cicilan:</h6>
                                <div class="row small">
                                    <div class="col-6">Pokok:</div>
                                    <div class="col-6 text-end fw-bold" id="detailPokok">-</div>

                                    <div class="col-6">Bunga:</div>
                                    <div class="col-6 text-end fw-bold" id="detailBunga">-</div>

                                    <div class="col-6">Total Cicilan:</div>
                                    <div class="col-6 text-end fw-bold" id="detailTotal">-</div>

                                    <div class="col-6">Sudah Dibayar:</div>
                                    <div class="col-6 text-end fw-bold text-success" id="detailDibayar">-</div>

                                    <div class="col-6">Sisa Cicilan:</div>
                                    <div class="col-6 text-end fw-bold text-danger" id="detailSisa">-</div>
                                </div>
                            </div>

                            <div class="payment-form">
                                <div class="mb-3">
                                    <label class="form-label">Jumlah Bayar (Rp)</label>
                                    <input type="number" class="form-control" name="jumlah_bayar" id="jumlah_bayar"
                                        min="1000" step="1000" placeholder="Masukkan jumlah pembayaran" required readonly>
                                    <small class="text-muted" id="maxBayarInfo">Maksimal: -</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Metode Pembayaran</label>
                                    <select class="form-select" name="metode" id="metode" required>
                                        <option value="">-- Pilih Metode --</option>
                                        <option value="cash">Cash</option>
                                        <option value="transfer">Transfer</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="bankField" style="display: none;">
                                    <label class="form-label">Bank Tujuan</label>
                                    <select class="form-select" name="bank_tujuan" id="bank_tujuan">
                                        <option value="">-- Pilih Bank --</option>
                                        <option value="Bank BRI">Bank BRI</option>
                                        <option value="Bank MANDIRI">Bank MANDIRI</option>
                                        <option value="Bank BNI">Bank BNI</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Tanggal Bayar</label>
                                    <input type="datetime-local" class="form-control" name="tanggal_bayar"
                                        value="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Keterangan (Opsional)</label>
                                    <textarea class="form-control" name="keterangan" rows="2"
                                        placeholder="Contoh: Bayar cicilan via transfer..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-check-circle me-2"></i>Konfirmasi Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Riwayat Pembayaran Terbaru -->
                <div class="card card-custom mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Pembayaran Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Anggota</th>
                                        <th>Angsuran</th>
                                        <th>Jumlah</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($riwayat = $riwayat_result->fetch_assoc()):
                                        $status_color = [
                                            'lunas' => 'success',
                                            'pending' => 'warning',
                                            'telat' => 'danger'
                                        ][$riwayat['status']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($riwayat['tanggal_bayar'])) ?></td>
                                            <td>
                                                <div class="fw-medium"><?= $riwayat['nama'] ?></div>
                                                <small class="text-muted"><?= $riwayat['no_anggota'] ?></small>
                                            </td>
                                            <td>Ke-<?= $riwayat['angsuran_ke'] ?></td>
                                            <td class="fw-bold text-success">Rp
                                                <?= number_format($riwayat['jumlah_bayar'], 0, ',', '.') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= ucfirst($riwayat['metode']) ?></span>
                                                <?php if ($riwayat['metode'] === 'transfer' && $riwayat['bank_tujuan']): ?>
                                                    <small class="text-muted">(<?= $riwayat['bank_tujuan'] ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $status_color ?> status-badge">
                                                    <?= ucfirst($riwayat['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>

                                    <?php if ($riwayat_result->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                                <div>Belum ada riwayat pembayaran</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk update counter
        function updateCounter() {
            const allItems = document.querySelectorAll('.cicilan-item');
            const countTelat = document.querySelectorAll('.cicilan-item.telat').length;
            const countDueSoon = document.querySelectorAll('.cicilan-item.due_soon').length;
            const countPending = document.querySelectorAll('.cicilan-item.pending').length;

            document.getElementById('countTelat').textContent = countTelat + ' Terlambat';
            document.getElementById('countDueSoon').textContent = countDueSoon + ' Jatuh Tempo';
            document.getElementById('countPending').textContent = countPending + ' Menunggu';
            document.getElementById('totalCicilan').textContent = allItems.length + ' Total';
        }

        // Fungsi untuk filter cicilan
        function filterCicilan() {
            const filterStatus = document.getElementById('filterStatus').value;
            const filterJatuhTempo = document.getElementById('filterJatuhTempo').value;
            const searchQuery = document.getElementById('searchAnggota').value.toLowerCase();
            const today = new Date();

            document.querySelectorAll('.cicilan-item').forEach(item => {
                let show = true;
                const nama = item.getAttribute('data-nama').toLowerCase();
                const jatuhTempo = new Date(item.getAttribute('data-jatuh-tempo'));
                const kategori = item.getAttribute('data-kategori');

                // Filter by status
                if (filterStatus !== 'all' && filterStatus !== kategori) {
                    show = false;
                }

                // Filter by search
                if (searchQuery && !nama.includes(searchQuery)) {
                    show = false;
                }

                // Filter by jatuh tempo
                if (filterJatuhTempo !== 'all') {
                    const diffTime = jatuhTempo - today;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    switch (filterJatuhTempo) {
                        case 'today':
                            show = show && diffDays === 0;
                            break;
                        case 'week':
                            show = show && diffDays >= 0 && diffDays <= 7;
                            break;
                        case 'month':
                            show = show && diffDays >= 0 && diffDays <= 30;
                            break;
                        case 'overdue':
                            show = show && diffDays < 0;
                            break;
                    }
                }

                // Tampilkan/sembunyikan item
                item.style.display = show ? 'block' : 'none';
            });

            updateCounter();
        }

        // Fungsi untuk memilih cicilan
        function selectCicilan(idCicilan, element) {
            fetch(`pages/ajax/get_detail_cicilan.php?id=${idCicilan}`)
                .then(response => response.json())
                .then(cicilan => {
                    // Update info cicilan
                    document.getElementById('infoNama').textContent = cicilan.nama;
                    document.getElementById('infoNoAnggota').textContent = cicilan.no_anggota;
                    document.getElementById('infoAngsuranKe').textContent = cicilan.angsuran_ke;
                    document.getElementById('infoJatuhTempo').textContent = cicilan.jatuh_tempo_formatted;

                    const sisaCicilan = cicilan.total_cicilan - cicilan.jumlah_bayar;
                    document.getElementById('infoSisaCicilan').textContent = 'Rp ' + sisaCicilan.toLocaleString('id-ID');

                    // Update detail cicilan
                    document.getElementById('detailPokok').textContent = 'Rp ' + cicilan.jumlah_pokok.toLocaleString('id-ID');
                    document.getElementById('detailBunga').textContent = 'Rp ' + cicilan.jumlah_bunga.toLocaleString('id-ID');
                    document.getElementById('detailTotal').textContent = 'Rp ' + cicilan.total_cicilan.toLocaleString('id-ID');
                    document.getElementById('detailDibayar').textContent = 'Rp ' + cicilan.jumlah_bayar.toLocaleString('id-ID');
                    document.getElementById('detailSisa').textContent = 'Rp ' + sisaCicilan.toLocaleString('id-ID');

                    // Set hidden field
                    document.getElementById('id_cicilan').value = idCicilan;

                    // Update max bayar info
                    document.getElementById('maxBayarInfo').innerHTML =
                        `Maksimal: Rp ${sisaCicilan.toLocaleString('id-ID')}`;

                    // Set max value untuk input jumlah
                    document.getElementById('jumlah_bayar').max = sisaCicilan;
                    document.getElementById('jumlah_bayar').value = sisaCicilan;

                    // Tampilkan form, sembunyikan placeholder
                    document.getElementById('noSelection').style.display = 'none';
                    document.getElementById('formPembayaran').style.display = 'block';

                    // Highlight item yang dipilih
                    document.querySelectorAll('.cicilan-item').forEach(item => {
                        item.classList.remove('selected');
                    });

                    // Highlight element yang diklik
                    if (element) {
                        element.classList.add('selected');
                    }
                })
                .catch(error => {
                    console.error('Error loading cicilan detail:', error);
                    alert('Gagal memuat detail cicilan');
                });
        }

        // Fungsi untuk reset pilihan
        function resetSelection() {
            document.getElementById('noSelection').style.display = 'block';
            document.getElementById('formPembayaran').style.display = 'none';
            document.getElementById('id_cicilan').value = '';

            // Reset form
            document.getElementById('formPembayaran').reset();

            // Remove highlight
            document.querySelectorAll('.cicilan-item').forEach(item => {
                item.classList.remove('selected');
            });
        }

        // Initialize ketika DOM siap
        document.addEventListener('DOMContentLoaded', function () {
            // Update counter pertama kali
            updateCounter();

            // Event listeners untuk filter
            document.getElementById('filterStatus').addEventListener('change', filterCicilan);
            document.getElementById('filterJatuhTempo').addEventListener('change', filterCicilan);
            document.getElementById('searchAnggota').addEventListener('input', filterCicilan);

            // Event listener untuk metode bayar
            const metodeSelect = document.getElementById('metode');
            if (metodeSelect) {
                metodeSelect.addEventListener('change', function () {
                    const bankField = document.getElementById('bankField');
                    if (this.value === 'transfer') {
                        bankField.style.display = 'block';
                        document.getElementById('bank_tujuan').required = true;
                    } else {
                        bankField.style.display = 'none';
                        document.getElementById('bank_tujuan').required = false;
                    }
                });
            }

            // Event listener untuk jumlah bayar
            const jumlahBayarInput = document.getElementById('jumlah_bayar');
            if (jumlahBayarInput) {
                jumlahBayarInput.addEventListener('input', function () {
                    const maxBayar = parseFloat(this.max) || 0;
                    const jumlahBayar = parseFloat(this.value) || 0;

                    if (jumlahBayar > maxBayar) {
                        this.classList.add('is-invalid');
                        const maxBayarInfo = document.getElementById('maxBayarInfo');
                        if (maxBayarInfo) {
                            maxBayarInfo.innerHTML =
                                `<span class="text-danger">Jumlah melebihi sisa cicilan! Maksimal: Rp ${maxBayar.toLocaleString('id-ID')}</span>`;
                        }
                    } else {
                        this.classList.remove('is-invalid');
                        const maxBayarInfo = document.getElementById('maxBayarInfo');
                        if (maxBayarInfo) {
                            maxBayarInfo.innerHTML =
                                `Maksimal: Rp ${maxBayar.toLocaleString('id-ID')}`;
                        }
                    }
                });
            }
        });

        // Form submission validation
        document.getElementById('formPembayaran').addEventListener('submit', function (e) {
            const jumlahBayar = parseFloat(document.getElementById('jumlah_bayar').value) || 0;
            const maxBayar = parseFloat(document.getElementById('jumlah_bayar').max) || 0;

            if (jumlahBayar > maxBayar) {
                e.preventDefault();
                alert('Jumlah pembayaran melebihi sisa cicilan! Silakan periksa kembali.');
                return;
            }

            if (jumlahBayar <= 0) {
                e.preventDefault();
                alert('Jumlah pembayaran harus lebih dari 0!');
                return;
            }

            if (!confirm('Konfirmasi pembayaran cicilan? Tindakan ini tidak dapat dibatalkan.')) {
                e.preventDefault();
            }
        });
    </script>
</body>

</html>
