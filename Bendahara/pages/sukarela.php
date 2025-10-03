<?php
// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$anggota_info = null;
$search_term = '';

// Fungsi untuk format Rupiah
function format_rupiah_sukarela($angka)
{
    return "Rp. " . number_format($angka, 0, ',', '.');
}

// Cek apakah form pencarian sudah disubmit
if (isset($_GET['no_anggota']) && !empty($_GET['no_anggota'])) {
    $search_term = trim($_GET['no_anggota']);

    // 1. Cari anggota di tabel 'anggota'
    $stmt_anggota = $conn->prepare("SELECT id, no_anggota, nama, alamat, saldo_sukarela FROM anggota WHERE REPLACE(no_anggota, '.', '') = ?");

    $stmt_anggota->bind_param("s", $search_term);
    $stmt_anggota->execute();
    $result_anggota = $stmt_anggota->get_result();

    if ($result_anggota->num_rows > 0) {
        $anggota_info = $result_anggota->fetch_assoc();
        $anggota_id = $anggota_info['id'];

        // Hitung total sukarela dari tabel pembayaran
        $stmt_sukarela = $conn->prepare("
            SELECT SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE -jumlah END) as total 
            FROM pembayaran WHERE anggota_id = ? AND jenis_simpanan = 'sukarela'
        ");
        $stmt_sukarela->bind_param("i", $anggota_id);
        $stmt_sukarela->execute();
        $total_sukarela = (float) $stmt_sukarela->get_result()->fetch_assoc()['total'];
        $stmt_sukarela->close();

        $anggota_info['total_simpanan_sukarela'] = $total_sukarela;
    }
    $stmt_anggota->close();
}
?>

<h3 class="mb-4">💸 Transaksi Simpanan Sukarela</h3>

<div class="card mb-4">
    <div class="card-header">
        <strong>Cari Anggota</strong>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sukarela">
            <div class="row">
                <div class="col-md-8">
                    <label for="no_anggota" class="form-label">Masukkan Nomor Anggota</label>
                    <input type="text" class="form-control" name="no_anggota" id="no_anggota"
                        value="<?= htmlspecialchars($search_term) ?>" placeholder="Cari berdasarkan nomor anggota"
                        required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Cari</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['no_anggota'])): ?>
    <hr>
    <?php if ($anggota_info): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Informasi Saldo Anggota</strong>
                <div>
                    <button class="btn btn-success btn-sm btn-transaksi" data-bs-toggle="modal" data-bs-target="#modalSukarela"
                        data-anggota-id="<?= $anggota_info['id'] ?>" data-jenis="setor">Setor Sukarela</button>
                    <button class="btn btn-warning btn-sm btn-transaksi" data-bs-toggle="modal" data-bs-target="#modalSukarela"
                        data-anggota-id="<?= $anggota_info['id'] ?>" data-jenis="tarik">Tarik Sukarela</button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>No Anggota:</strong><br><?= htmlspecialchars($anggota_info['no_anggota']) ?></p>
                        <p><strong>Nama:</strong><br><?= htmlspecialchars($anggota_info['nama']) ?></p>
                        <p><strong>Alamat:</strong><br><?= htmlspecialchars($anggota_info['alamat']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p>
                            <span
                                id="saldoSukarelaDisplay"><?= format_rupiah_sukarela($anggota_info['saldo_sukarela']) ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4">
            Anggota dengan nomor <strong><?= htmlspecialchars($search_term) ?></strong> tidak ditemukan.
        </div>
    <?php endif; ?>
<?php endif; ?>
<div class="modal fade" id="modalSukarela" tabindex="-1" aria-labelledby="modalSukarelaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSukarelaLabel">Form Transaksi Sukarela</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSukarela" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="anggota_id" id="anggotaIdSukarela">
                    <input type="hidden" name="nama" id="namaSukarela">
                    <input type="hidden" name="no_anggota" id="noAnggotaSukarela">

                    <div class="mb-3">
                        <label for="jenis_simpanan" class="form-label">Jenis Simpanan</label>
                        <select class="form-select bg-light" id="jenis_simpanan" name="jenis_simpanan" required>
                            <option value="Simpanan Sukarela" selected>Simpanan Sukarela</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="jenis_transaksi" class="form-label">Jenis Transaksi</label>
                        <select class="form-select" id="jenis_transaksi" name="jenis_transaksi" required>
                            <option value="">-- Pilih Jenis Transaksi --</option>
                            <option value="setor">Setor</option>
                            <option value="tarik">Tarik</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="jumlah" class="form-label">Jumlah (Rp)</label>
                        <input type="number" class="form-control" id="jumlah" name="jumlah" placeholder="Contoh: 50000"
                            required min="1000">
                    </div>

                    <div class="mb-3">
                        <label for="metode" class="form-label">Metode</label>
                        <select class="form-select" id="metode" name="metode" required>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3" id="bank_tujuan_sukarela_container" style="display:none;">
                        <label for="bank_tujuan_sukarela" class="form-label">Bank Tujuan</label>
                        <select class="form-select" id="bank_tujuan_sukarela" name="bank_tujuan">
                            <option value="">-- Pilih Bank --</option>
                            <option value="Bank MANDIRI">Bank MANDIRI</option>
                            <option value="Bank BRI">Bank BRI</option>
                            <option value="Bank BNI">Bank BNI</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="bukti" class="form-label">Bukti Transaksi (Struk/Foto)</label>
                        <input class="form-control" type="file" id="bukti" name="bukti" accept="image/*,.pdf" required>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_transaksi" class="form-label">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="tanggal_transaksi" name="tanggal_transaksi" required
                            value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitSukarela">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnTransaksi = document.querySelectorAll('.btn-transaksi');

        btnTransaksi.forEach(btn => {
            btn.addEventListener('click', function () {
                const anggotaId = this.getAttribute('data-anggota-id');
                const noAnggota = '<?= $anggota_info["no_anggota"] ?? "" ?>';
                const namaAnggota = '<?= $anggota_info["nama"] ?? "" ?>';
                const jenis = this.getAttribute('data-jenis'); // 'setor' atau 'tarik'

                // Mengisi input hidden
                document.getElementById('anggotaIdSukarela').value = anggotaId;
                document.getElementById('namaSukarela').value = namaAnggota;
                document.getElementById('noAnggotaSukarela').value = noAnggota;

                // Mengatur nilai default untuk dropdown jenis_transaksi
                document.getElementById('jenis_transaksi').value = jenis;

                // Mengatur judul modal (opsional, tapi bagus untuk UX)
                const modalTitle = document.getElementById('modalSukarelaLabel');
                modalTitle.textContent = jenis === 'setor' ? 'Form Setor Sukarela' : 'Form Tarik Sukarela';
            });
        });
    });

    // Di sukarela.php - tambah script ini
    document.getElementById('metode').addEventListener('change', function() {
        const bankContainer = document.getElementById('bank_tujuan_sukarela_container');
        const bankSelect = document.getElementById('bank_tujuan_sukarela');
        
        if (this.value === 'transfer') {
            bankContainer.style.display = 'block';
            bankSelect.required = true;
        } else {
            bankContainer.style.display = 'none';
            bankSelect.required = false;
        }
    });
</script>