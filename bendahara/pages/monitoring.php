<?php
// Mengambil data unik untuk dropdown filter
$rws = $conn->query("SELECT DISTINCT rw FROM anggota WHERE rw IS NOT NULL AND rw != '' ORDER BY rw ASC");
$rts = $conn->query("SELECT DISTINCT rt FROM anggota WHERE rt IS NOT NULL AND rt != '' ORDER BY rt ASC");

// Mengambil nilai filter yang dipilih dari URL
$selected_rw = $_GET['rw'] ?? '';
$selected_rt = $_GET['rt'] ?? '';
$selected_status = $_GET['status'] ?? ''; // Mengambil nilai filter status

// Membangun query dasar dengan filter RW dan RT
$sql = "SELECT * FROM anggota WHERE 1=1";
$params = [];
$types = "";

if (!empty($selected_rw)) {
    $sql .= " AND rw = ?";
    $params[] = $selected_rw;
    $types .= "s";
}
if (!empty($selected_rt)) {
    $sql .= " AND rt = ?";
    $params[] = $selected_rt;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$anggota = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-4">📊 Monitoring Simpanan Anggota</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPendaftaran">
        <i class="fa fa-plus"></i> 🧒 Pendaftaran Anggota Baru
    </button>
</div>


<div class="card mb-4">
    <div class="card-header">
        <strong>Filter Data Anggota</strong>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="monitoring">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="rw" class="form-label">Filter Berdasarkan RW</label>
                    <select name="rw" id="rw" class="form-select">
                        <option value="">-- Semua RW --</option>
                        <?php mysqli_data_seek($rws, 0);
                        while ($row = $rws->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['rw']) ?>" <?= $selected_rw == $row['rw'] ? 'selected' : '' ?>>
                                RW <?= htmlspecialchars($row['rw']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="rt" class="form-label">Filter Berdasarkan RT</label>
                    <select name="rt" id="rt" class="form-select">
                        <option value="">-- Semua RT --</option>
                        <?php mysqli_data_seek($rts, 0);
                        while ($row = $rts->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['rt']) ?>" <?= $selected_rt == $row['rt'] ? 'selected' : '' ?>>
                                RT <?= htmlspecialchars($row['rt']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Filter Berdasarkan Status</label>
                    <select name="status" id="status" class="form-select bg-light">
                        <option value="">-- Semua Status --</option>
                        <option value="jatuh_tempo" <?= $selected_status == 'jatuh_tempo' ? 'selected' : '' ?>>Telah Jatuh
                            Tempo</option>
                        <option value="aktif" <?= $selected_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <a href="?page=monitoring" class="btn btn-secondary">Reset Filter</a>
                </div>
            </div>
        </form>
    </div>
</div>


<div class="table-responsive">
    <table id="tabelAnggota" class="table table-sm table-bordered table-striped align-middle">
        <thead class="table-dark text-center">
            <tr>
                <th class="text-center">No Anggota</th>
                <th class="d-none">No Anggota (Search)</th>
                <th class="text-center">Nama</th>
                <th class="text-center">Simpanan Wajib</th>
                <th class="text-center">Tgl Jatuh Tempo</th>
                <th class="text-center">Status</th>
                <th style="width: 20%;" class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $nama_bulan = [
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember'
            ];
            $tanggal_sekarang = new DateTime();

            while ($row = $anggota->fetch_assoc()):
                $jatuh_tempo = new DateTime($row['tanggal_jatuh_tempo']);
                $is_jatuh_tempo = ($jatuh_tempo < $tanggal_sekarang);

                // Logika baru untuk memfilter baris berdasarkan status
                $show_row = false;
                if (empty($selected_status)) {
                    $show_row = true;
                } elseif ($selected_status == 'jatuh_tempo' && $is_jatuh_tempo) {
                    $show_row = true;
                } elseif ($selected_status == 'aktif' && !$is_jatuh_tempo) {
                    $show_row = true;
                }

                if ($show_row):
                    // Mengambil nama bulan dari array helper
                    $bulan_jatuh_tempo_nama = $nama_bulan[(int) $jatuh_tempo->format('n')];
                    // Mengambil tahun dari tanggal jatuh tempo
                    $tahun_jatuh_tempo = $jatuh_tempo->format('Y');
                    // PERUBAHAN: Menggabungkan bulan dan tahun menjadi satu variabel
                    $periode_jatuh_tempo = $bulan_jatuh_tempo_nama . ' ' . $tahun_jatuh_tempo;

                    if ($is_jatuh_tempo) {
                        // Menggunakan variabel periode yang baru
                        $status_text = "Telah jatuh tempo, belum bayar simpanan wajib bulan $periode_jatuh_tempo";
                        $status_class = 'badge bg-danger';
                    } else {
                        // Menggunakan variabel periode yang baru
                        $status_text = "Aktif, Menunggu Jatuh Tempo Berikutnya (bulan $periode_jatuh_tempo)";
                        $status_class = 'badge bg-success';
                    }

                    $simpanan_wajib_formatted = "Rp. " . number_format($row['simpanan_wajib'], 0, ',', '.');

                    $pesan_text = "Halo Bapak/Ibu {$row['nama']},\n\n" .
                        "Kami dari *Koperasi Desa Merah Putih Ganjar Sabar* mengingatkan bahwa simpanan wajib Anda sebesar {$simpanan_wajib_formatted} telah jatuh tempo bulan {$periode_jatuh_tempo}.\n\n" . // Menggunakan variabel periode yang baru
                        "Mohon segera melakukan pembayaran melalui salah satu metode berikut:\n" .
                        "1. Bayar langsung ke kantor\n" .
                        "2. Dikolektifkan melalui koordinator wilayah masing-masing\n" .
                        "3. Transfer via rekening:\n" .
                        "   • BNI : 2228883302 atas nama *KOPPERASI DESA MERAH PUTIH GANJAR SABAR*\n" .
                        "   • BRI : 346801041356536 atas nama *KOPPERASI DESA MERAH PUTIH GANJAR SABAR*\n\n" .
                        "🙏 Jika melalui transfer, harap kirimkan bukti pembayaran.\n\n" .
                        "Terima kasih 🙏";

                    $pesan = urlencode($pesan_text);
                    $wa_link = "https://wa.me/{$row['no_hp']}?text=$pesan";
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['no_anggota']) ?></td>
                        <td class="d-none"><?= htmlspecialchars(str_replace('.', '', $row['no_anggota'])) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td>Rp. <?= number_format($row['simpanan_wajib'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= $jatuh_tempo->format('d/m/Y') ?></td>
                        <td class="text-center"><span class="p-2 <?= $status_class ?>"><?= $status_text ?></span></td>
                        <!-- monitoring.php - bagian tombol di tabel -->
                        <td class="text-center">
                            <a href="pages/detail_anggota.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-info btn-sm"
                                title="Lihat Detail">Detail</a>
                            <?php if ($is_jatuh_tempo): ?>
                                <a href="<?= $wa_link ?>" target="whatsapp_tab" class="btn btn-success btn-sm"
                                    title="Kirim WA">WA</a>
                                <button type="button" class="btn btn-primary btn-sm update-bayar-btn" data-bs-toggle="modal"
                                    data-bs-target="#updateBayarModal" data-id="<?= $row['id'] ?>"
                                    data-no-anggota="<?= $row['no_anggota'] ?>" data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                    data-jumlah="<?= $row['simpanan_wajib'] ?>" title="Update Pembayaran">
                                    Update Bayar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                endif; // Akhir dari if ($show_row)
            endwhile;
            ?>
        </tbody>
    </table>
</div>
<div class="modal fade" id="modalPendaftaran" tabindex="-1" role="dialog" aria-labelledby="modalPendaftaranLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="../modalPendaftaranLabel">Form Pendaftaran Anggota Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formPendaftaran" action="proses_pendaftaran.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- ================== FASE 1 ================== -->
                    <div id="fase-1">
                        <h4>Langkah 1: Data Diri Anggota</h4>
                        <hr>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="jenis_transaksi" class="form-label fw-bold">Jenis Transaksi</label>
                                    <select class="form-control" id="jenis_transaksi" name="jenis_transaksi" required readonly>
                                        <option value="setor" selected>Setor</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="nama" class="form-label">Nama
                                        Lengkap</label><input type="text" class="form-control" id="nama" name="nama"
                                        required></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="jenis_kelamin" class="form-label">Jenis
                                        Kelamin</label><select class="form-control" id="jenis_kelamin"
                                        name="jenis_kelamin" required>
                                        <option value="">-- Pilih Jenis Kelamin --</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="tempat_lahir" class="form-label">Tempat
                                        Lahir</label><input type="text" class="form-control" id="tempat_lahir"
                                        name="tempat_lahir" required></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="tanggal_lahir" class="form-label">Tanggal
                                        Lahir</label><input type="date" class="form-control" id="tanggal_lahir"
                                        name="tanggal_lahir" required></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="nik" class="form-label">NIK</label><input
                                        type="number" class="form-control" id="nik" name="nik" required></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="npwp" class="form-label">NPWP (Jika
                                        ada)</label><input type="text" class="form-control" id="npwp" name="npwp"></div>
                            </div>
                        </div>
                        <div class="form-group mb-3"><label for="alamat" class="form-label">Alamat
                                Lengkap</label><textarea class="form-control" id="alamat" name="alamat" rows="2"
                                required></textarea></div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="agama" class="form-label">Agama</label><select
                                        class="form-control" id="agama" name="agama" required>
                                        <option value="">-- Pilih Agama --</option>
                                        <option value="Islam">Islam</option>
                                        <option value="Kristen Protestan">Kristen Protestan</option>
                                        <option value="Kristen Katolik">Kristen Katolik</option>
                                        <option value="Hindu">Hindu</option>
                                        <option value="Buddha">Buddha</option>
                                        <option value="Khonghucu">Khonghucu</option>
                                    </select></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="rw" class="form-label">RW</label><input
                                        type="number" class="form-control" id="rw" name="rw" required></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="rt" class="form-label">RT</label><input
                                        type="number" class="form-control" id="rt" name="rt" required></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="no_hp" class="form-label">No. Handphone
                                        (Format: 628...)</label><input type="text" class="form-control" id="no_hp"
                                        name="no_hp" required></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="pekerjaan"
                                        class="form-label">Pekerjaan</label><input type="text" class="form-control"
                                        id="pekerjaan" name="pekerjaan" required></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="simpanan_wajib" class="form-label">Pilihan
                                        Simpanan Wajib</label><select class="form-control" id="simpanan_wajib"
                                        name="simpanan_wajib" required>
                                        <option value="">-- Pilih Nominal --</option>
                                        <option value="25000">Rp. 25.000</option>
                                        <option value="50000">Rp. 50.000</option>
                                        <option value="75000">Rp. 75.000</option>
                                        <option value="100000">Rp. 100.000</option>
                                    </select></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3"><label for="tanggal_join" class="form-label">Tanggal
                                        Bergabung</label><input type="date" class="form-control" id="tanggal_join"
                                        name="tanggal_join" value="<?= date('Y-m-d') ?>" required></div>
                            </div>
                        </div>
                        <hr>
                        <p class="fw-bold">Upload Dokumen:</p>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="foto_diri" class="form-label">Foto Diri
                                        (Wajib)</label><input type="file" class="form-control" id="foto_diri"
                                        name="foto_diri" accept="image/*" required></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="foto_ktp" class="form-label">Foto KTP
                                        (Wajib)</label><input type="file" class="form-control" id="foto_ktp"
                                        name="foto_ktp" accept="image/*" required></div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3"><label for="foto_kk" class="form-label">Foto KK
                                        (Opsional)</label><input type="file" class="form-control" id="foto_kk"
                                        name="foto_kk" accept="image/*"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ================== FASE 2 ================== -->
                    <div id="fase-2" style="display:none;">
                        <h4>Langkah 2: Rincian Simpanan & Pembayaran</h4>
                        <hr>
                        <div class="row">
                            <div class="col-md-12"><label class="form-label fw-bold">Pilih Jenis Simpanan yang Akan
                                    Dibayar:</label>
                                <div class="form-check"><input class="form-check-input simpanan-checkbox"
                                        type="checkbox" value="pokok" id="check-pokok" checked><label
                                        class="form-check-label" for="check-pokok">Simpanan Pokok</label></div>
                                <div class="form-check"><input class="form-check-input simpanan-checkbox"
                                        type="checkbox" value="wajib" id="check-wajib" checked><label
                                        class="form-check-label" for="check-wajib">Simpanan Wajib</label></div>
                                <div class="form-check"><input class="form-check-input simpanan-checkbox"
                                        type="checkbox" value="sukarela" id="check-sukarela"><label
                                        class="form-check-label" for="check-sukarela">Simpanan Sukarela</label></div>
                            </div>
                        </div>
                        <hr>

                        <div id="payment-details-container">
                            <div class="mb-3" id="field-pokok"><label for="amount_pokok" class="form-label">Jumlah
                                    Simpanan Pokok</label><input type="number" class="form-control" id="amount_pokok"
                                    name="amount_pokok" value="10000" readonly></div>
                            <div class="mb-3" id="field-wajib"><label for="amount_wajib" class="form-label">Jumlah
                                    Simpanan Wajib</label><input type="number" class="form-control" id="amount_wajib"
                                    name="amount_wajib" readonly></div>
                            <div class="mb-3" id="field-sukarela" style="display:none;"><label for="amount_sukarela"
                                    class="form-label">Jumlah Simpanan Sukarela (Minimal Rp 5.000)</label><input
                                    type="number" class="form-control" id="amount_sukarela" name="amount_sukarela"
                                    min="5000" placeholder="Masukkan jumlah">
                                </div>
                        </div>

                        <div class="mb-3"><label for="metode_pembayaran" class="form-label fw-bold">Metode
                                Pembayaran</label><select class="form-select" id="metode_pembayaran"
                                name="metode_pembayaran">
                                <option value="cash">Cash</option>
                                <option value="transfer">Transfer</option>
                            </select></div>
                        <div class="mb-3" id="bank_tujuan_container" style="display:none;">
                            <label for="bank_tujuan" class="form-label fw-bold">Bank Tujuan</label>
                            <select class="form-select" id="bank_tujuan" name="bank_tujuan">
                                <option value="">-- Pilih Bank --</option>
                                <option value="Bank MANDIRI">Bank MANDIRI</option>
                                <option value="Bank BRI">Bank BRI</option>
                                <option value="Bank BNI">Bank BNI</option>
                            </select>
                        </div>
                        <div id="bukti-pembayaran-section">
                            <label class="form-label fw-bold">Bukti Pembayaran</label>
                            <div class="alert alert-info py-2">
                                <div class="form-check"><input class="form-check-input" type="checkbox"
                                        id="bukti_sama"><label class="form-check-label" for="bukti_sama">Gunakan satu
                                        bukti untuk semua?</label></div>
                            </div>
                            <div class="mb-3" id="bukti-tunggal-container" style="display:none;"><label
                                    for="bukti_tunggal" class="form-label">Upload Bukti Pembayaran</label><input
                                    type="file" class="form-control" id="bukti_tunggal" name="bukti_tunggal"
                                    accept="image/*"></div>
                            <div id="bukti-terpisah-container">
                                <div class="mb-3" id="field-bukti-pokok" style="display:none;"><label for="bukti_pokok"
                                        class="form-label">Bukti Simpanan Pokok</label><input type="file"
                                        class="form-control" id="bukti_pokok" name="bukti_pokok" accept="image/*"></div>
                                <div class="mb-3" id="field-bukti-wajib" style="display:none;"><label for="bukti_wajib"
                                        class="form-label">Bukti Simpanan Wajib</label><input type="file"
                                        class="form-control" id="bukti_wajib" name="bukti_wajib" accept="image/*"></div>
                                <div class="mb-3" id="field-bukti-sukarela" style="display:none;"><label
                                        for="bukti_sukarela" class="form-label">Bukti Simpanan Sukarela</label><input
                                        type="file" class="form-control" id="bukti_sukarela" name="bukti_sukarela"
                                        accept="image/*"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ================== FASE 3 ================== -->
                    <div id="fase-3" style="display:none;">
                        <h4>Langkah 3: Konfirmasi Data</h4>
                        <hr>
                        <div id="summary-container">
                            <h5>Data Diri Anggota</h5>
                            <dl class="row">
                                <dt class="col-sm-4">Nama Lengkap</dt>
                                <dd class="col-sm-8"><span id="summary_nama"></span></dd>
                                <dt class="col-sm-4">Jenis Kelamin</dt>
                                <dd class="col-sm-8"><span id="summary_jenis_kelamin"></span></dd>
                                <dt class="col-sm-4">Tempat, Tanggal Lahir</dt>
                                <dd class="col-sm-8"><span id="summary_ttl"></span></dd>
                                <dt class="col-sm-4">NIK</dt>
                                <dd class="col-sm-8"><span id="summary_nik"></span></dd>
                                <dt class="col-sm-4">Alamat</dt>
                                <dd class="col-sm-8"><span id="summary_alamat"></span></dd>
                                <dt class="col-sm-4">No. Handphone</dt>
                                <dd class="col-sm-8"><span id="summary_no_hp"></span></dd>
                                <dt class="col-sm-4">Tanggal Bergabung</dt>
                                <dd class="col-sm-8"><span id="summary_tanggal_join"></span></dd>
                            </dl>
                            <hr>
                            <h5>Rincian Pembayaran</h5>
                            <dl class="row">
                                <div id="summary_pokok_section" class="contents">
                                    <dt class="col-sm-4 bg-light p-2">Simpanan Pokok</dt>
                                    <dd class="col-sm-8 bg-light p-2"><span id="summary_amount_pokok"></span></dd>
                                </div>
                                <div id="summary_wajib_section" class="contents">
                                    <dt class="col-sm-4 p-2">Simpanan Wajib</dt>
                                    <dd class="col-sm-8 p-2"><span id="summary_amount_wajib"></span></dd>
                                </div>
                                <div id="summary_sukarela_section" class="contents">
                                    <dt class="col-sm-4 bg-light p-2">Simpanan Sukarela</dt>
                                    <dd class="col-sm-8 bg-light p-2"><span id="summary_amount_sukarela"></span></dd>
                                </div>
                                <hr class="my-2">
                                <dt class="col-sm-4">Jenis Transaksi</dt>
                                <dd class="col-sm-8"><span id="summary_jenis_transaksi"></span></dd>
                                <dt class="col-sm-4">Metode Pembayaran</dt>
                                <dd class="col-sm-8"><span id="summary_metode"></span></dd>
                                <dt class="col-sm-4">Bukti Pembayaran</dt>
                                <dd class="col-sm-8"><span id="summary_bukti"></span></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-default" id="tombolKembali"
                        style="display:none;">Kembali</button>
                    <button type="button" class="btn btn-primary" id="tombolLanjut">Lanjutkan</button>
                    <button type="submit" class="btn btn-success" id="tombolSimpan" style="display:none;">Simpan
                        Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Update Pembayaran -->
<!-- monitoring.php -->
<!-- Tambahkan modal update bayar -->
<div class="modal fade" id="updateBayarModal" tabindex="-1" aria-labelledby="updateBayarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateBayarModalLabel">Update Pembayaran Simpanan Wajib</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formUpdateBayar" method="POST" action="update_pembayaran.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="anggotaIdModal" name="anggota_id">
                    <div class="mb-3">
                        <label class="form-label">No. Anggota:</label>
                        <p class="fw-bold" id="noAnggotaModal"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Anggota:</label>
                        <p class="fw-bold" id="namaAnggotaModal"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah Simpanan Wajib:</label>
                        <p class="fw-bold" id="jumlahWajibModal"></p>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_bayar" class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="metode" class="form-label">Metode Pembayaran</label>
                        <select class="form-select" id="metode" name="metode" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="cash" selected>Cash</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3" id="bank_tujuan_update_container" style="display:none;">
                        <label for="bank_tujuan_update" class="form-label">Bank Tujuan</label>
                        <select class="form-select" id="bank_tujuan_update" name="bank_tujuan">
                            <option value="">-- Pilih Bank --</option>
                            <option value="Bank MANDIRI">Bank MANDIRI</option>
                            <option value="Bank BRI">Bank BRI</option>
                            <option value="Bank BNI">Bank BNI</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="bukti" class="form-label">Bukti Pembayaran</label>
                        <input type="file" class="form-control" id="bukti" name="bukti" accept="image/*" required>
                        <small class="text-muted">Upload bukti pembayaran (maks. 10MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Konfirmasi Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
