<?php
// pages/pengeluaran.php
require_once 'saldo_helper.php';

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ambil data kategori dan rekening untuk dropdown
$kategori_result = $conn->query("SELECT * FROM kategori_pengeluaran ORDER BY nama_kategori");
$rekening_result = $conn->query("SELECT * FROM rekening WHERE is_active = TRUE ORDER BY nama_rekening");

// Ambil data pengeluaran untuk tabel
$pengeluaran_result = $conn->query("
    SELECT p.*, k.nama_kategori, r.nama_rekening 
    FROM pengeluaran p
    LEFT JOIN kategori_pengeluaran k ON p.kategori_id = k.id
    LEFT JOIN rekening r ON p.rekening_id = r.id
    ORDER BY p.tanggal DESC, p.created_at DESC
");
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>💸 Manajemen Pengeluaran</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPengeluaran">
            <i class="fas fa-plus"></i> Tambah Pengeluaran
        </button>
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
                            <th>Rekening</th>
                            <th>Status</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pengeluaran = $pengeluaran_result->fetch_assoc()):
                            $status_badge = [
                                'draft' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger'
                            ][$pengeluaran['status']] ?? 'bg-secondary';
                            ?>
                                <tr>
                                    <td class="text-center"><?= date('d/m/Y', strtotime($pengeluaran['tanggal'])) ?></td>
                                    <td><?= htmlspecialchars($pengeluaran['nama_kategori'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($pengeluaran['keterangan']) ?></td>
                                    <td class="text-end">Rp <?= number_format($pengeluaran['jumlah'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($pengeluaran['nama_rekening'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $status_badge ?>">
                                            <?= ucfirst($pengeluaran['status']) ?>
                                        </span>
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
                                        <?php if ($pengeluaran['status'] == 'draft'): ?>
                                                <button class="btn btn-sm btn-warning edit-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger hapus-pengeluaran" data-id="<?= $pengeluaran['id'] ?>">
                                                    <i class="fas fa-trash"></i>
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
<div class="modal fade" id="modalPengeluaran" tabindex="-1" aria-labelledby="modalPengeluaranLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPengeluaranLabel">Form Pengeluaran Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formPengeluaran" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
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
                                  placeholder="Deskripsi pengeluaran..." required></textarea>
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
                                <label for="rekening_id" class="form-label">Sumber Dana</label>
                                <select class="form-select" id="rekening_id" name="rekening_id" required>
                                    <option value="">-- Pilih Rekening --</option>
                                    <?php
                                    mysqli_data_seek($rekening_result, 0);
                                    while ($rekening = $rekening_result->fetch_assoc()):
                                        $saldo_real = getSaldoRealTime($rekening['id']);
                                        ?>
                                            <option value="<?= $rekening['id'] ?>" data-saldo="<?= $saldo_real ?>">
                                                <?= htmlspecialchars($rekening['nama_rekening']) ?> 
                                                (Saldo: Rp <?= number_format($saldo_real, 0, ',', '.') ?>)
                                            </option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted" id="saldoInfo"></small>
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
                    <button type="submit" class="btn btn-primary">Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    // Update info saldo ketika pilih rekening
    $('#rekening_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const saldo = selectedOption.data('saldo') || 0;
        const jumlah = $('#jumlah').val() || 0;
        
        $('#saldoInfo').text(`Saldo tersedia: Rp ${saldo.toLocaleString('id-ID')}`);
        
        if (parseFloat(jumlah) > saldo) {
            $('#saldoInfo').addClass('text-danger').removeClass('text-success');
        } else {
            $('#saldoInfo').addClass('text-success').removeClass('text-danger');
        }
    });

    // Validasi jumlah vs saldo
    $('#jumlah').on('input', function() {
        const selectedOption = $('#rekening_id').find('option:selected');
        const saldo = selectedOption.data('saldo') || 0;
        const jumlah = $(this).val() || 0;
        
        if (parseFloat(jumlah) > saldo) {
            $('#saldoInfo').text(`Saldo tidak cukup! Kebutuhan: Rp ${jumlah.toLocaleString('id-ID')}, Tersedia: Rp ${saldo.toLocaleString('id-ID')}`)
                .addClass('text-danger').removeClass('text-success');
        } else {
            $('#saldoInfo').text(`Saldo mencukupi`).addClass('text-success').removeClass('text-danger');
        }
    });

    // Handle form submit
    $('#formPengeluaran').on('submit', function(e) {
        e.preventDefault();
        
        const selectedOption = $('#rekening_id').find('option:selected');
        const saldo = selectedOption.data('saldo') || 0;
        const jumlah = $('#jumlah').val();
        
        if (parseFloat(jumlah) > saldo) {
            alert('Saldo tidak mencukupi! Silakan pilih rekening lain atau kurangi jumlah.');
            return false;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'tambah_pengeluaran');

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
});
</script>