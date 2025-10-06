<?php
// Include file history log
require_once 'functions/history_log.php';

error_log("SESSION DATA: " . print_r($_SESSION, true));

// Fungsi untuk log pengeluaran langsung dari sini
function logPengeluaran($action, $pengeluaran_id, $keterangan, $jumlah, $sumber_dana, $alasan = '')
{
    error_log("=== LOG PENGELUARAN START ===");
    error_log("Action: $action");
    error_log("Pengeluaran ID: $pengeluaran_id");
    error_log("Keterangan: $keterangan");
    error_log("Jumlah: $jumlah");
    error_log("Sumber Dana: $sumber_dana");
    error_log("Alasan: $alasan");

    // Debug session
    error_log("SESSION: " . print_r($_SESSION, true));

    $result = false;
    switch ($action) {
        case 'pengajuan':
            $result = log_pengajuan_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana);
            break;
        case 'approval':
            $result = log_approval_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana);
            break;
        case 'rejection':
            $result = log_rejection_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana, $alasan);
            break;
        case 'edit':
            $result = log_edit_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana);
            break;
        case 'hapus':
            $result = log_hapus_pengeluaran($pengeluaran_id, $keterangan, $jumlah, $sumber_dana);
            break;
    }

    error_log("Log Result: " . ($result ? "SUCCESS - ID: $result" : "FAILED"));
    error_log("=== LOG PENGELUARAN END ===");

    return $result;
}

// Handle log history requests
if (isset($_POST['log_action'])) {
    $action = $_POST['log_action'];
    $pengeluaran_id = $_POST['pengeluaran_id'] ?? 0;
    $keterangan = $_POST['keterangan'] ?? '';
    $jumlah = $_POST['jumlah'] ?? 0;
    $sumber_dana = $_POST['sumber_dana'] ?? '';
    $alasan = $_POST['alasan'] ?? '';

    logPengeluaran($action, $pengeluaran_id, $keterangan, $jumlah, $sumber_dana, $alasan);
    echo "Log berhasil";
    exit;
}

// FUNGSI HITUNG SALDO REAL-TIME - DIPERBAIKI: SUDAH KURANGI PENGELUARAN APPROVED
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
            -
            -- Tarik Sukarela (cash)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Kas Tunai - TAMBAHAN INI
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
        ) as saldo_kas
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_kas'] ?? 0;
}

function hitungSaldoBank($nama_bank)
{
    global $conn;
    $result = $conn->query("
        SELECT (
            -- Simpanan Anggota (transfer ke bank tertentu)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
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
            -
            -- Tarik Sukarela (transfer dari bank tertentu)
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Bank tersebut - TAMBAHAN INI
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
        ) as saldo_bank
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_bank'] ?? 0;
}

// Cek role user
$user_role = $_SESSION['role'] ?? '';
$is_ketua = ($user_role === 'ketua');
$is_bendahara = ($user_role === 'bendahara');

// Ambil data kategori untuk dropdown
$kategori_result = $conn->query("SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori");

// Query pengeluaran berdasarkan role
if ($is_ketua) {
    $pengeluaran_result = $conn->query("
        SELECT p.*, k.nama_kategori, pn.nama as created_by_name
        FROM pengeluaran p
        LEFT JOIN kategori_pengeluaran k ON p.kategori_id = k.id
        LEFT JOIN pengurus pn ON p.created_by = pn.id
        ORDER BY p.tanggal DESC, p.created_at DESC
    ");
} else {
    $pengeluaran_result = $conn->query("
        SELECT p.*, k.nama_kategori, pn.nama as created_by_name
        FROM pengeluaran p
        LEFT JOIN kategori_pengeluaran k ON p.kategori_id = k.id
        LEFT JOIN pengurus pn ON p.created_by = pn.id
        WHERE p.created_by = '" . ($_SESSION['id'] ?? 0) . "' OR p.status = 'approved'
        ORDER BY p.tanggal DESC, p.created_at DESC
    ");
}

// Hitung saldo real-time - SEKARANG SUDAH PERHITUNGKAN PENGELUARAN APPROVED
$saldo_kas = hitungSaldoKasTunai();
$saldo_mandiri = hitungSaldoBank('Bank MANDIRI');
$saldo_bri = hitungSaldoBank('Bank BRI');
$saldo_bni = hitungSaldoBank('Bank BNI');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>💸 Manajemen Pengeluaran</h2>

        <?php if (in_array($user_role, ['bendahara'])): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPengeluaran">
                <i class="fas fa-plus"></i> Ajukan Pengeluaran
            </button>
        <?php endif; ?>
    </div>

    <!-- Alert Info -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Info:</strong>
        <?php if ($is_ketua): ?>
            Anda sebagai <strong>Ketua</strong> dapat menyetujui atau menolak pengajuan pengeluaran.
        <?php elseif ($is_bendahara): ?>
            Anda sebagai <strong>Bendahara</strong> dapat mengajukan pengeluaran. Pengajuan membutuhkan persetujuan Ketua.
        <?php else: ?>
            Anda hanya dapat melihat pengeluaran yang sudah disetujui.
        <?php endif; ?>
        <strong>Simpanan Sukarela tidak dapat digunakan untuk pengeluaran.</strong>
    </div>

    <!-- Tabel Daftar Pengeluaran -->
    <div class="card">
        <div class="card-header">
            <strong>Daftar Pengeluaran</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelPengeluaran" class="table table-sm table-bordered table-striped align-middle"
                    style="width:100%">
                    <thead class="table-dark text-center">
                        <tr>
                            <th width="100">Tanggal</th>
                            <th width="120">Kategori</th>
                            <th>Keterangan</th>
                            <th width="120">Jumlah</th>
                            <th width="120">Sumber Dana</th>
                            <th width="100">Status</th>
                            <th width="120">Diajukan Oleh</th>
                            <th width="80">Bukti</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pengeluaran = $pengeluaran_result->fetch_assoc()):
                            $status_badge = [
                                'draft' => 'bg-warning',
                                'pending' => 'bg-info',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger'
                            ][$pengeluaran['status']] ?? 'bg-secondary';

                            $can_edit = ($pengeluaran['status'] == 'draft' && $pengeluaran['created_by'] == ($_SESSION['id'] ?? 0));
                            $can_approve = ($is_ketua && $pengeluaran['status'] == 'pending');
                            ?>
                            <tr>
                                <td class="text-center"><?= date('d/m/Y', strtotime($pengeluaran['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($pengeluaran['nama_kategori'] ?? '-') ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;"
                                        title="<?= htmlspecialchars($pengeluaran['keterangan']) ?>">
                                        <?= htmlspecialchars($pengeluaran['keterangan']) ?>
                                    </div>
                                </td>
                                <td class="text-end">Rp <?= number_format($pengeluaran['jumlah'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($pengeluaran['sumber_dana'] ?? '-') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $status_badge ?>">
                                        <?= ucfirst($pengeluaran['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($pengeluaran['created_by_name'] ?? 'System') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($pengeluaran['bukti_file']): ?>
                                        <a href="<?= $pengeluaran['bukti_file'] ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($can_edit): ?>
                                            <button class="btn btn-warning edit-pengeluaran" data-id="<?= $pengeluaran['id'] ?>"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger hapus-pengeluaran" data-id="<?= $pengeluaran['id'] ?>"
                                                title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php elseif ($can_approve): ?>
                                            <button type="button" class="btn btn-success approve-pengeluaran"
                                                data-id="<?= $pengeluaran['id'] ?>" title="Setujui">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger reject-pengeluaran"
                                                data-id="<?= $pengeluaran['id'] ?>" title="Tolak">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">Tidak ada aksi</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengeluaran -->
<?php if (in_array($user_role, ['bendahara', 'ketua', 'admin'])): ?>
    <div class="modal fade" id="modalPengeluaran" tabindex="-1" aria-labelledby="modalPengeluaranLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPengeluaranLabel">Form Pengajuan Pengeluaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formPengeluaran" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Perhatian:</strong> Pengajuan pengeluaran membutuhkan persetujuan Ketua.
                            Simpanan Sukarela <strong>tidak dapat</strong> digunakan untuk pengeluaran.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tanggal" class="form-label">Tanggal Pengeluaran</label>
                                    <input type="date" class="form-control" id="tanggal" name="tanggal"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kategori_id" class="form-label">Kategori Pengeluaran</label>
                                    <select class="form-select" id="kategori_id" name="kategori_id" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php
                                        mysqli_data_seek($kategori_result, 0);
                                        while ($kategori = $kategori_result->fetch_assoc()): ?>
                                            <option value="<?= $kategori['id'] ?>">
                                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"
                                placeholder="Deskripsi lengkap pengeluaran..." required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jumlah" class="form-label">Jumlah (Rp)</label>
                                    <input type="number" class="form-control" id="jumlah" name="jumlah" min="1000"
                                        step="1000" placeholder="Contoh: 50000" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sumber_dana" class="form-label">Sumber Dana</label>
                                    <select class="form-select" id="sumber_dana" name="sumber_dana" required>
                                        <option value="">-- Pilih Sumber Dana --</option>
                                        <option value="Kas Tunai" data-saldo="<?= $saldo_kas ?>">
                                            Kas Tunai (Saldo: Rp <?= number_format($saldo_kas, 0, ',', '.') ?>)
                                        </option>
                                        <option value="Bank MANDIRI" data-saldo="<?= $saldo_mandiri ?>">
                                            Bank MANDIRI (Saldo: Rp <?= number_format($saldo_mandiri, 0, ',', '.') ?>)
                                        </option>
                                        <option value="Bank BRI" data-saldo="<?= $saldo_bri ?>">
                                            Bank BRI (Saldo: Rp <?= number_format($saldo_bri, 0, ',', '.') ?>)
                                        </option>
                                        <option value="Bank BNI" data-saldo="<?= $saldo_bni ?>">
                                            Bank BNI (Saldo: Rp <?= number_format($saldo_bni, 0, ',', '.') ?>)
                                        </option>
                                    </select>
                                    <small class="text-muted" id="saldoInfo">Pilih sumber dana</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="bukti_file" class="form-label">Bukti Pengeluaran</label>
                            <input type="file" class="form-control" id="bukti_file" name="bukti_file" accept="image/*,.pdf"
                                required>
                            <small class="text-muted">Format: JPG, PNG, PDF (maks. 5MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Ajukan Pengeluaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Tambahan CSS untuk memperbaiki tampilan tabel */
    #tabelPengeluaran {
        font-size: 0.875rem;
    }

    #tabelPengeluaran th {
        white-space: nowrap;
    }

    #tabelPengeluaran td {
        vertical-align: middle;
    }

    .btn-group-sm>.btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Memastikan tabel responsive */
    .table-responsive {
        border-radius: 0.375rem;
    }

    /* Styling untuk status badge */
    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
</style>

<script>
    $(document).ready(function () {
        // Inisialisasi DataTable dengan konfigurasi yang lebih ketat
        if ($('#tabelPengeluaran').length) {
            $('#tabelPengeluaran').DataTable({
                pageLength: 10,
                responsive: true,
                autoWidth: false,
                language: {
                    search: "Cari: ",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    zeroRecords: "Tidak ada data yang cocok",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: "Berikutnya",
                        previous: "Sebelumnya"
                    }
                },
                columnDefs: [
                    { targets: [0, 5, 6, 7, 8], orderable: false },
                    { targets: [2], width: "200px" }
                ]
            });
        }

        // Update info saldo ketika pilih sumber dana
        $('#sumber_dana').on('change', function () {
            updateSaldoInfo();
        });

        // Validasi jumlah vs saldo
        $('#jumlah').on('input', function () {
            updateSaldoInfo();
        });

        function updateSaldoInfo() {
            const selectedOption = $('#sumber_dana').find('option:selected');
            const saldo = selectedOption.data('saldo') || 0;
            const jumlah = $('#jumlah').val() || 0;

            if (saldo > 0) {
                $('#saldoInfo').text(`Saldo tersedia: Rp ${saldo.toLocaleString('id-ID')}`);

                if (parseFloat(jumlah) > saldo) {
                    $('#saldoInfo').addClass('text-danger').removeClass('text-success')
                        .text(`Saldo tidak cukup! Kebutuhan: Rp ${parseFloat(jumlah).toLocaleString('id-ID')}, Tersedia: Rp ${saldo.toLocaleString('id-ID')}`);
                } else {
                    $('#saldoInfo').addClass('text-success').removeClass('text-danger')
                        .text(`Saldo mencukupi: Rp ${saldo.toLocaleString('id-ID')}`);
                }
            } else {
                $('#saldoInfo').text('Pilih sumber dana').removeClass('text-danger text-success');
            }
        }

        // Handle form submit pengajuan pengeluaran
        $('#formPengeluaran').on('submit', function (e) {
            e.preventDefault();

            const selectedOption = $('#sumber_dana').find('option:selected');
            const saldo = selectedOption.data('saldo') || 0;
            const jumlah = $('#jumlah').val();
            const keterangan = $('#keterangan').val();
            const sumber_dana = $('#sumber_dana').val();

            if (parseFloat(jumlah) > saldo) {
                alert('Saldo tidak mencukupi! Silakan pilih sumber dana lain atau kurangi jumlah.');
                return false;
            }

            const formData = new FormData(this);
            formData.append('action', 'ajukan_pengeluaran');

            $.ajax({
                url: 'pages/proses/proses_pengeluaran.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        $('#modalPengeluaran').modal('hide');

                        // Langsung panggil log history via AJAX
                        $.post('<?= $_SERVER['PHP_SELF'] ?>', {
                            log_action: 'pengajuan',
                            pengeluaran_id: response.pengeluaran_id || 0,
                            keterangan: keterangan,
                            jumlah: jumlah,
                            sumber_dana: sumber_dana
                        });

                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function () {
                    alert('Terjadi kesalahan server. Silakan coba lagi.');
                }
            });
        });

        // Handle approval oleh ketua
        $('.approve-pengeluaran').on('click', function () {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            const keterangan = row.find('td:nth-child(3)').text().trim();
            const jumlah = row.find('td:nth-child(4)').text().replace('Rp ', '').replace(/\./g, '');
            const sumber_dana = row.find('td:nth-child(5)').text().trim();

            if (confirm('Setujui pengajuan pengeluaran ini?')) {
                updateStatusPengeluaran(id, 'approved', '', keterangan, jumlah, sumber_dana);
            }
        });

        // Handle rejection oleh ketua
        $('.reject-pengeluaran').on('click', function () {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            const keterangan = row.find('td:nth-child(3)').text().trim();
            const jumlah = row.find('td:nth-child(4)').text().replace('Rp ', '').replace(/\./g, '');
            const sumber_dana = row.find('td:nth-child(5)').text().trim();

            const reason = prompt('Alasan penolakan:');
            if (reason !== null) {
                updateStatusPengeluaran(id, 'rejected', reason, keterangan, jumlah, sumber_dana);
            }
        });

        function updateStatusPengeluaran(id, status, reason = '', keterangan = '', jumlah = '', sumber_dana = '') {
            $.ajax({
                url: 'pages/proses/proses_pengeluaran.php',
                type: 'POST',
                data: {
                    action: 'update_status',
                    id: id,
                    status: status,
                    reason: reason
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        // ✅ LANGSUNG RELOAD - log sudah ditangani di proses_pengeluaran.php
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Terjadi kesalahan server. Silakan coba lagi.');
                }
            });
        }
        // Handle edit pengeluaran
        $('.edit-pengeluaran').on('click', function () {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            const keterangan = row.find('td:nth-child(3)').text().trim();
            const jumlah = row.find('td:nth-child(4)').text().replace('Rp ', '').replace(/\./g, '');
            const sumber_dana = row.find('td:nth-child(5)').text().trim();

            // Langsung panggil log history via AJAX
            $.post('<?= $_SERVER['PHP_SELF'] ?>', {
                log_action: 'edit',
                pengeluaran_id: id,
                keterangan: keterangan,
                jumlah: jumlah,
                sumber_dana: sumber_dana
            });

            alert('Fitur edit pengeluaran dengan ID: ' + id);
            // Implementasi edit sesuai kebutuhan
        });

        // Handle hapus pengeluaran
        $('.hapus-pengeluaran').on('click', function () {
            const id = $(this).data('id');
            const row = $(this).closest('tr');
            const keterangan = row.find('td:nth-child(3)').text().trim();
            const jumlah = row.find('td:nth-child(4)').text().replace('Rp ', '').replace(/\./g, '');
            const sumber_dana = row.find('td:nth-child(5)').text().trim();

            if (confirm('Hapus pengajuan pengeluaran ini?')) {
                // Langsung panggil log history via AJAX
                $.post('<?= $_SERVER['PHP_SELF'] ?>', {
                    log_action: 'hapus',
                    pengeluaran_id: id,
                    keterangan: keterangan,
                    jumlah: jumlah,
                    sumber_dana: sumber_dana
                });

                alert('Fitur hapus pengeluaran dengan ID: ' + id);
                // Implementasi hapus sesuai kebutuhan
            }
        });
    });
</script>