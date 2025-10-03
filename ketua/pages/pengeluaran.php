<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
error_log("SESSION DATA: " . print_r($_SESSION, true));
// FUNGSI HITUNG SALDO REAL-TIME
function hitungSaldoKasTunai() {
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
        ) as saldo_kas
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_kas'] ?? 0;
}

function hitungSaldoBank($nama_bank) {
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
        SELECT p.*, k.nama_kategori
        FROM pengeluaran p
        LEFT JOIN kategori_pengeluaran k ON p.kategori_id = k.id
        ORDER BY p.tanggal DESC, p.created_at DESC
    ");
} else {
    $pengeluaran_result = $conn->query("
        SELECT p.*, k.nama_kategori
        FROM pengeluaran p
        LEFT JOIN kategori_pengeluaran k ON p.kategori_id = k.id
        WHERE p.created_by = '" . ($_SESSION['id'] ?? 0) . "' OR p.status = 'approved'
        ORDER BY p.tanggal DESC, p.created_at DESC
    ");
}

// Hitung saldo real-time
$saldo_kas = hitungSaldoKasTunai();
$saldo_mandiri = hitungSaldoBank('Bank MANDIRI');
$saldo_bri = hitungSaldoBank('Bank BRI');
$saldo_bni = hitungSaldoBank('Bank BNI');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>💸 Manajemen Pengeluaran</h2>
        
        <!-- DEBUG: Tampilkan role untuk testing -->
        <!-- <div class="alert alert-warning py-1">Role: <?= $user_role ?></div> -->
        
        <?php if (in_array($user_role, ['bendahara', 'ketua', 'admin'])): ?>
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
                <table id="tabelPengeluaran" class="table table-sm table-bordered table-striped align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                            <th>Sumber Dana</th>
                            <th>Status</th>
                            <th>Diajukan Oleh</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
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
                                <td><?= htmlspecialchars($pengeluaran['keterangan']) ?></td>
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
                                        <a href="<?= $pengeluaran['bukti_file'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($can_edit): ?>
                                        <button class="btn btn-sm btn-warning edit-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger hapus-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php elseif ($can_approve): ?>
                                        <button class="btn btn-sm btn-success approve-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                            <i class="fas fa-check"></i> Setujui
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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
                                        <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
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
                                <input type="number" class="form-control" id="jumlah" name="jumlah" 
                                       min="1000" step="1000" placeholder="Contoh: 50000" required>
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
                        <input type="file" class="form-control" id="bukti_file" name="bukti_file" 
                               accept="image/*,.pdf" required>
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

<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    if ($('#tabelPengeluaran').length) {
        $('#tabelPengeluaran').DataTable({
            pageLength: 10,
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
            }
        });
    }

    // Update info saldo ketika pilih sumber dana
    $('#sumber_dana').on('change', function() {
        updateSaldoInfo();
    });

    // Validasi jumlah vs saldo
    $('#jumlah').on('input', function() {
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

    // Handle form submit
    $('#formPengeluaran').on('submit', function(e) {
        e.preventDefault();
        
        const selectedOption = $('#sumber_dana').find('option:selected');
        const saldo = selectedOption.data('saldo') || 0;
        const jumlah = $('#jumlah').val();
        
        if (parseFloat(jumlah) > saldo) {
            alert('Saldo tidak mencukupi! Silakan pilih sumber dana lain atau kurangi jumlah.');
            return false;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'ajukan_pengeluaran');

        $.ajax({
            url: 'proses_pengeluaran.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#modalPengeluaran').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan server. Silakan coba lagi.');
            }
        });
    });

    // Handle approval oleh ketua
    $('.approve-pengeluaran').on('click', function() {
        const id = $(this).data('id');
        if (confirm('Setujui pengajuan pengeluaran ini?')) {
            updateStatusPengeluaran(id, 'approved');
        }
    });

    $('.reject-pengeluaran').on('click', function() {
        const id = $(this).data('id');
        const reason = prompt('Alasan penolakan:');
        if (reason !== null) {
            updateStatusPengeluaran(id, 'rejected', reason);
        }
    });

    function updateStatusPengeluaran(id, status, reason = '') {
        $.ajax({
            url: 'proses_pengeluaran.php',
            type: 'POST',
            data: {
                action: 'update_status',
                id: id,
                status: status,
                reason: reason
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan server. Silakan coba lagi.');
            }
        });
    }
});
</script>