<?php
session_start();
// Cek role usaha
if ($_SESSION['role'] !== 'usaha') {
    header('Location: ../index.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// PROSES TAMBAH PENGAJUAN PINJAMAN
if ($_POST['action'] ?? '' === 'ajukan_pinjaman') {
    $id_anggota = intval($_POST['id_anggota'] ?? 0);
    $jumlah_pinjaman = floatval($_POST['jumlah_pinjaman'] ?? 0);
    $tenor_bulan = intval($_POST['tenor_bulan'] ?? 0);
    $tujuan_pinjaman = $conn->real_escape_string($_POST['tujuan_pinjaman'] ?? '');
    $sumber_dana = $conn->real_escape_string($_POST['sumber_dana'] ?? '');

    // Validasi tenor
    $tenor_allowed = [3, 6, 10, 12, 24];
    if (!in_array($tenor_bulan, $tenor_allowed)) {
        $error = "Tenor tidak valid. Pilih: 3, 6, 10, 12, atau 24 bulan.";
    }

    // Hitung total simpanan anggota (Pokok + Wajib)
    $simpanan_result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total_simpanan 
        FROM pembayaran 
        WHERE anggota_id = $id_anggota 
        AND (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
        AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
    ");
    $simpanan_data = $simpanan_result->fetch_assoc();
    $total_simpanan = $simpanan_data['total_simpanan'];

    // Hitung max pinjaman (150% dari simpanan)
    $max_pinjaman = $total_simpanan * 1.5;

    // Validasi jumlah pinjaman vs max
    if ($jumlah_pinjaman > $max_pinjaman) {
        $error = "Jumlah pinjaman melebihi batas. Maksimal: Rp " . number_format($max_pinjaman, 0, ',', '.');
    }

    // Hitung cicilan (bunga flat 1.8% per bulan)
    $bunga_per_bulan = $jumlah_pinjaman * (1.8 / 100);
    $pokok_per_bulan = $jumlah_pinjaman / $tenor_bulan;
    $cicilan_per_bulan = $pokok_per_bulan + $bunga_per_bulan;
    $total_bunga = $bunga_per_bulan * $tenor_bulan;
    $total_pengembalian = $jumlah_pinjaman + $total_bunga;

    if (!isset($error)) {
        // Insert ke tabel pinjaman
        $stmt = $conn->prepare("
    INSERT INTO pinjaman (
        id_anggota, jumlah_pinjaman, bunga_bulan, tenor_bulan, 
        tujuan_pinjaman, sumber_dana, total_simpanan, max_pinjaman_dihitung,
        status, tanggal_pengajuan, cicilan_per_bulan, total_bunga, total_pengembalian
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?)
");
        $bunga_bulan = 1.8;
        $stmt->bind_param(
            "iddissddddd",  // Perhatikan: hapus 1 'i' untuk id_usaha
            $id_anggota,
            $jumlah_pinjaman,
            $bunga_bulan,
            $tenor_bulan,
            $tujuan_pinjaman,
            $sumber_dana,
            $total_simpanan,
            $max_pinjaman,
            $cicilan_per_bulan,
            $total_bunga,
            $total_pengembalian
        );

        if ($stmt->execute()) {
            $success = "Pengajuan pinjaman berhasil dikirim! Menunggu approval Ketua.";
        } else {
            $error = "Gagal mengajukan pinjaman: " . $stmt->error;
        }
    }
}

// AMBIL DATA ANGGOTA UNTUK DROPDOWN
$anggota_result = $conn->query("
    SELECT id, no_anggota, nama 
    FROM anggota 
    WHERE status_keanggotaan = 'Aktif'
    ORDER BY nama
");

// AMBIL DATA PENGAJUAN PINJAMAN USAHA INI
$pinjaman_result = $conn->query("
    SELECT 
        p.*,
        a.nama as nama_anggota,
        a.no_anggota
    FROM pinjaman p
    LEFT JOIN anggota a ON p.id_anggota = a.id
    ORDER BY p.tanggal_pengajuan DESC
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Pinjaman - KDMPGS</title>
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
        

        .calculation-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2>💸 Pengajuan Pinjaman Anggota</h2>
                <p class="text-muted">Ajukan pinjaman untuk anggota koperasi</p>
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
            <!-- Form Pengajuan -->
            <div class="col-lg-5 mb-4">
                <div class="card card-custom">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Form Pengajuan Pinjaman</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formPinjaman">
                            <input type="hidden" name="action" value="ajukan_pinjaman">
                            <input type="hidden" name="id_anggota" id="id_anggota">

                            <!-- Search Anggota -->
                            <div class="mb-3">
                                <label class="form-label">Cari Anggota</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchAnggota"
                                        placeholder="Ketik nama atau no. anggota...">
                                    <button type="button" class="btn btn-outline-secondary" id="btnSearchAnggota">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div id="searchResults" class="mt-2" style="display: none;">
                                    <!-- Results will appear here -->
                                </div>
                            </div>

                            <!-- Info Anggota Terpilih -->
                            <div class="alert alert-info" id="anggotaInfo" style="display: none;">
                                <div class="row">
                                    <div class="col-8">
                                        <strong id="anggotaNama">-</strong><br>
                                        <small>No. Anggota: <span id="anggotaNo">-</span></small><br>
                                        <small>Max Pinjaman: <span id="anggotaMax" class="fw-bold">-</span></small>
                                    </div>
                                    <div class="col-4 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            onclick="resetAnggota()">
                                            <i class="fas fa-times"></i> Ganti
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jumlah Pinjaman (Rp)</label>
                                <input type="number" class="form-control" name="jumlah_pinjaman" id="jumlah_pinjaman"
                                    min="100000" step="50000" placeholder="Contoh: 5000000" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenor Pinjaman</label>
                                <select class="form-select" name="tenor_bulan" id="tenor_bulan" required>
                                    <option value="">-- Pilih Tenor --</option>
                                    <option value="3">3 Bulan</option>
                                    <option value="6">6 Bulan</option>
                                    <option value="10">10 Bulan</option>
                                    <option value="12">12 Bulan</option>
                                    <option value="24">24 Bulan</option>
                                </select>
                            </div>

                            <!-- Sumber Dana dengan Saldo -->
                            <div class="mb-3">
                                <label class="form-label">Sumber Dana</label>
                                <div id="sumberDanaOptions">
                                    <!-- Options will be loaded via AJAX -->
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Loading saldo...
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tujuan Pinjaman</label>
                                <textarea class="form-control" name="tujuan_pinjaman" rows="3"
                                    placeholder="Jelaskan tujuan penggunaan pinjaman..." required></textarea>
                            </div>

                            <!-- Calculation Preview -->
                            <div class="calculation-box mb-3" id="calculationPreview" style="display: none;">
                                <h6 class="fw-bold mb-3">Preview Perhitungan:</h6>
                                <div class="row small">
                                    <div class="col-6">Jumlah Pinjaman:</div>
                                    <div class="col-6 text-end fw-bold" id="previewJumlah">-</div>

                                    <div class="col-6">Bunga (1.8%/bulan):</div>
                                    <div class="col-6 text-end fw-bold" id="previewBunga">-</div>

                                    <div class="col-6">Cicilan per Bulan:</div>
                                    <div class="col-6 text-end fw-bold" id="previewCicilan">-</div>

                                    <div class="col-6">Total Pengembalian:</div>
                                    <div class="col-6 text-end fw-bold" id="previewTotal">-</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>Ajukan Pinjaman
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Daftar Pengajuan -->
            <div class="col-lg-7">
                <div class="card card-custom">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Pengajuan Pinjaman</h5>
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
                                            <td class="fw-bold">Rp
                                                <?= number_format($pinjaman['jumlah_pinjaman'], 0, ',', '.') ?>
                                            </td>
                                            <td><?= $pinjaman['tenor_bulan'] ?> Bulan</td>
                                            <td>
                                                <span class="badge bg-<?= $status_color ?> status-badge">
                                                    <?= ucfirst($pinjaman['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-detail"
                                                    data-id="<?= $pinjaman['id_pinjaman'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>

                                    <?php if ($pinjaman_result->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                                <div>Belum ada pengajuan pinjaman</div>
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
    <script>
        // Pastikan semua function didefinisikan di global scope
        window.searchAnggota = function () {
            const search = document.getElementById('searchAnggota').value.trim();
            if (search.length < 2) {
                alert('Ketik minimal 2 karakter untuk pencarian');
                return;
            }

            fetch(`pages/ajax/search_anggota.php?search=${encodeURIComponent(search)}`)
                .then(response => response.json())
                .then(anggota => {
                    const results = document.getElementById('searchResults');
                    if (anggota.length === 0) {
                        results.innerHTML = '<div class="alert alert-warning py-2">Tidak ada anggota ditemukan</div>';
                        results.style.display = 'block';
                        return;
                    }

                    let html = '<div class="list-group">';
                    anggota.forEach(a => {
                        html += `
                    <button type="button" class="list-group-item list-group-item-action" 
                            onclick="selectAnggota(${a.id}, '${a.no_anggota}', '${a.nama.replace(/'/g, "\\'")}')">
                        <strong>${a.nama}</strong><br>
                        <small class="text-muted">${a.no_anggota}</small>
                    </button>
                `;
                    });
                    html += '</div>';
                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error searching anggota:', error);
                    document.getElementById('searchResults').innerHTML =
                        '<div class="alert alert-danger">Gagal mencari anggota</div>';
                    document.getElementById('searchResults').style.display = 'block';
                });
        }

        window.selectAnggota = function (id, noAnggota, nama) {
            document.getElementById('id_anggota').value = id;
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('searchAnggota').value = '';

            // Get max pinjaman for this anggota
            fetch(`pages/ajax/get_max_pinjaman.php?id_anggota=${id}`)
                .then(response => response.json())
                .then(data => {
                    const maxFormatted = 'Rp ' + data.max_pinjaman.toLocaleString('id-ID');
                    const simpananFormatted = 'Rp ' + data.total_simpanan.toLocaleString('id-ID');

                    document.getElementById('anggotaNama').textContent = nama;
                    document.getElementById('anggotaNo').textContent = noAnggota;
                    document.getElementById('anggotaMax').innerHTML = `${maxFormatted} <small class="text-muted">(Simpanan: ${simpananFormatted})</small>`;
                    document.getElementById('anggotaInfo').style.display = 'block';
                    document.getElementById('submitBtn').disabled = false;
                })
                .catch(error => {
                    console.error('Error getting max pinjaman:', error);
                    alert('Gagal memuat data maksimal pinjaman');
                });
        }

        window.resetAnggota = function () {
            document.getElementById('id_anggota').value = '';
            document.getElementById('anggotaInfo').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('calculationPreview').style.display = 'none';
            document.getElementById('jumlah_pinjaman').value = '';
            document.getElementById('tenor_bulan').value = '';
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function () {
            // Load saldo rekening
            loadSaldoRekening();

            // Event listener untuk tombol search
            document.getElementById('btnSearchAnggota').addEventListener('click', searchAnggota);

            // Event listener untuk input search (bisa enter juga)
            document.getElementById('searchAnggota').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchAnggota();
                }
            });
        });

        function loadSaldoRekening() {
            fetch('pages/ajax/get_saldo_rekening.php')
                .then(response => response.json())
                .then(saldo => {
                    let html = '';
                    for (const [sumber, jumlah] of Object.entries(saldo)) {
                        const saldoFormatted = 'Rp ' + jumlah.toLocaleString('id-ID');
                        html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="sumber_dana" 
                               value="${sumber}" id="sumber_${sumber.replace(/\s+/g, '')}" 
                               ${jumlah <= 0 ? 'disabled' : ''} required>
                        <label class="form-check-label w-100" for="sumber_${sumber.replace(/\s+/g, '')}">
                            <div class="d-flex justify-content-between">
                                <span>${sumber}</span>
                                <span class="badge ${jumlah <= 0 ? 'bg-danger' : 'bg-success'}">
                                    ${saldoFormatted}
                                </span>
                            </div>
                        </label>
                    </div>
                `;
                    }
                    document.getElementById('sumberDanaOptions').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading saldo:', error);
                    document.getElementById('sumberDanaOptions').innerHTML =
                        '<div class="alert alert-danger">Gagal memuat saldo rekening</div>';
                });
        }

        // Real-time calculation preview
        document.getElementById('jumlah_pinjaman').addEventListener('input', updateCalculation);
        document.getElementById('tenor_bulan').addEventListener('change', updateCalculation);

        function updateCalculation() {
            const idAnggota = document.getElementById('id_anggota').value;
            const jumlah = parseFloat(document.getElementById('jumlah_pinjaman').value) || 0;
            const tenor = parseInt(document.getElementById('tenor_bulan').value) || 0;

            if (idAnggota && jumlah > 0 && tenor > 0) {
                const bungaPerBulan = jumlah * 0.018;
                const pokokPerBulan = jumlah / tenor;
                const cicilanPerBulan = pokokPerBulan + bungaPerBulan;
                const totalBunga = bungaPerBulan * tenor;
                const totalPengembalian = jumlah + totalBunga;

                // Update preview
                document.getElementById('previewJumlah').textContent = 'Rp ' + jumlah.toLocaleString('id-ID');
                document.getElementById('previewBunga').textContent = 'Rp ' + totalBunga.toLocaleString('id-ID');
                document.getElementById('previewCicilan').textContent = 'Rp ' + cicilanPerBulan.toLocaleString('id-ID');
                document.getElementById('previewTotal').textContent = 'Rp ' + totalPengembalian.toLocaleString('id-ID');
                document.getElementById('calculationPreview').style.display = 'block';
            } else {
                document.getElementById('calculationPreview').style.display = 'none';
            }
        }

        // View detail modal
        document.querySelectorAll('.view-detail').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                fetch(`pages/ajax/get_detail_pinjaman.php?id=${id}`)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('detailContent').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('detailModal')).show();
                    })
                    .catch(error => {
                        console.error('Error loading detail:', error);
                        document.getElementById('detailContent').innerHTML =
                            '<div class="alert alert-danger">Gagal memuat detail pinjaman</div>';
                    });
            });
        });
    </script>