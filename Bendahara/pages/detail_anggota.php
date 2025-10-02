<?php
// ... (bagian PHP atas tetap persis seperti yang kamu kirim)
if (!isset($_GET['id']) || empty($_GET['id'])) {
  die("Error: ID Anggota tidak valid atau tidak ditemukan.");
}
$anggota_id = intval($_GET['id']);
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}
$stmt = $conn->prepare("SELECT * FROM anggota WHERE id = ?");
$stmt->bind_param("i", $anggota_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
  $anggota = $result->fetch_assoc();
} else {
  die("Data anggota dengan ID " . $anggota_id . " tidak ditemukan.");
}
$stmt->close();
$conn->close();

$tanggal_bergabung = new DateTime($anggota['tanggal_join']);
$tanggal_sekarang = new DateTime('now');
$durasi = $tanggal_sekarang->diff($tanggal_bergabung);

$tanggal_lahir_formatted = date('d F Y', strtotime($anggota['tanggal_lahir']));
$tempat_tanggal_lahir = htmlspecialchars($anggota['tempat_lahir']) . ', ' . $tanggal_lahir_formatted;

$parts = [];
if ($durasi->y > 0)
  $parts[] = $durasi->y . ' Tahun';
if ($durasi->m > 0)
  $parts[] = $durasi->m . ' Bulan';
if ($durasi->d > 0)
  $parts[] = $durasi->d . ' Hari';
$durasi_str = empty($parts) ? 'Kurang dari sehari' : implode(', ', $parts);

$folder_foto = '../';
$foto_path = $folder_foto . $anggota['foto_diri'];
$foto_default = '../uploads/foto_anggota/default-avatar.png';
$gambar_profil = (!empty($anggota['foto_diri']) && file_exists($foto_path)) ? $foto_path : $foto_default;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Anggota - <?= htmlspecialchars($anggota['nama']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background-color:#f8f9fa; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .card-header { font-weight:bold; background:linear-gradient(135deg,#2c3e50,#4a6580); }
    th { width:30%; background-color:#f1f8ff; }
    .card { border:none; border-radius:12px; overflow:hidden; box-shadow:0 6px 15px rgba(0,0,0,0.1); }
    .card-title { color:#2c3e50; font-weight:600; }
    .profile-picture{ width:150px; height:150px; object-fit:cover; border-radius:50%; border:4px solid #fff; box-shadow:0 4px 10px rgba(0,0,0,0.15); }
  </style>
</head>
<body>

<div class="container my-5">
  <div class="card shadow-sm">
    <div class="card-header text-white">
      <h3 class="mb-0">Detail Lengkap Anggota</h3>
    </div>
    <div class="card-body">

      <div class="text-center mb-4">
        <img src="<?= $gambar_profil ?>" alt="Foto Diri" class="profile-picture">
        <h4 class="card-title mt-3 mb-1"><?= htmlspecialchars($anggota['nama']) ?></h4>
        <hr>
      </div>

      <table class="table table-bordered table-striped">
        <tbody>
          <tr><th>Nomor Anggota</th><td><?= htmlspecialchars($anggota['no_anggota']) ?></td></tr>
          <tr><th>NIK</th><td><?= htmlspecialchars($anggota['nik']) ?></td></tr>
          <tr><th>Jenis Kelamin</th><td><?= htmlspecialchars($anggota['jenis_kelamin']) ?></td></tr>
          <tr><th>Tempat, Tanggal Lahir</th><td><?= $tempat_tanggal_lahir ?></td></tr>
          <tr><th>Alamat</th><td><?= htmlspecialchars($anggota['alamat']) ?></td></tr>
          <tr><th>No. HP (WhatsApp)</th><td><?= htmlspecialchars($anggota['no_hp']) ?></td></tr>
          <tr><th>Pekerjaan</th><td><?= htmlspecialchars($anggota['pekerjaan']) ?></td></tr>
          <tr><th>Tanggal Bergabung</th><td><?= date('d F Y', strtotime($anggota['tanggal_join'])) ?></td></tr>
          <tr><th>Lama Menjadi Anggota</th><td><?= $durasi_str ?></td></tr>
          <tr><th>Simpanan Pokok</th><td>Rp 10.000</td></tr>
          <tr><th>Simpanan Wajib</th><td>Rp <?= number_format($anggota['simpanan_wajib'], 0, ',', '.') ?></td></tr>
          <tr><th>Simpanan Sukarela</th><td>Rp <?= number_format($anggota['saldo_sukarela'], 0, ',', '.') ?></td></tr>
          <tr><th>Total Saldo Anggota</th><td>Rp <?= number_format($anggota['saldo_total'], 0, ',', '.') ?></td></tr>
        </tbody>
      </table>

      <div class="mt-4 text-end">
        <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#ubahDataModal">
          <i class="bi bi-pencil-square"></i> Ubah Data
        </button>
        <button onclick="window.print()" class="btn btn-primary me-2">
          <i class="bi bi-printer"></i> Cetak
        </button>
        <a href="javascript:window.close();" class="btn btn-secondary">
          <i class="bi bi-x-circle"></i> Tutup Halaman
        </a>
        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#ubahSimpananModal">
          <i class="bi bi-cash-stack"></i> Ubah Simpanan Wajib
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Modal Ubah Data (unchanged) -->
<div class="modal fade" id="ubahDataModal" tabindex="-1" aria-labelledby="ubahDataModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
      <h1 class="modal-title fs-5" id="ubahDataModalLabel">Ubah Data Anggota</h1>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <form action="proses_ubah.php" method="POST" id="formUbahData"> <div class="modal-body"> <input type="hidden" name="id" value="<?= $anggota['id'] ?>">
        <div class="mb-3"> <label for="nama" class="form-label">Nama Lengkap</label> <input type="text" class="form-control"
            id="nama" name="nama" value="<?= htmlspecialchars($anggota['nama']) ?>" required> </div>
        <div class="row">
          <div class="col-md-6 mb-3"> <label for="nik" class="form-label">NIK</label> <input type="text"
              class="form-control" id="nik" name="nik" value="<?= htmlspecialchars($anggota['nik']) ?>" required> </div>
          <div class="col-md-6 mb-3"> <label for="npwp" class="form-label">NPWP (Opsional)</label> <input type="text"
              class="form-control" id="npwp" name="npwp" value="<?= htmlspecialchars($anggota['npwp'] ?? '') ?>"> </div>
        </div>
        <div class="mb-3"> <label for="alamat" class="form-label">Alamat</label> <textarea class="form-control" id="alamat"
            name="alamat" rows="3" required><?= htmlspecialchars($anggota['alamat']) ?></textarea> </div>
        <div class="row">
          <div class="col-md-6 mb-3"> <label for="rt" class="form-label">RT</label> <input type="text" class="form-control"
              id="rt" name="rt" value="<?= htmlspecialchars($anggota['rt'] ?? '') ?>"> </div>
          <div class="col-md-6 mb-3"> <label for="rw" class="form-label">RW</label> <input type="text" class="form-control"
              id="rw" name="rw" value="<?= htmlspecialchars($anggota['rw'] ?? '') ?>"> </div>
        </div>
        <div class="mb-3"> <label for="pekerjaan" class="form-label">Pekerjaan</label> <input type="text"
            class="form-control" id="pekerjaan" name="pekerjaan" value="<?= htmlspecialchars($anggota['pekerjaan']) ?>"
            required> </div>
      </div>
      <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button> </div>
    </form>
  </div></div>
</div>

<!-- ====== Modal Ubah Simpanan (dengan upload bukti) ====== -->
<input type="hidden" id="tanggal_join" value="<?= htmlspecialchars($anggota['tanggal_join']) ?>">
<input type="hidden" id="simpanan_lama" value="<?= (int) $anggota['simpanan_wajib'] ?>">

<div class="modal fade" id="ubahSimpananModal" tabindex="-1" aria-labelledby="ubahSimpananModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <!-- ABSOLUTE path + enctype multipart -->
    <form action="/kdmpgs%20-%20v2/bendahara/proses_ubah_simpanan.php"
          method="POST" id="formUbahSimpanan" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Ubah Simpanan Wajib</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" value="<?= $anggota['id'] ?>">

        <div class="mb-3">
          <label class="form-label">Nominal Simpanan Wajib Baru</label>
          <select class="form-select" name="simpanan_baru" id="simpanan_baru" required>
            <?php
            $opsi = [25000, 50000, 75000, 100000];
            foreach ($opsi as $nilai) {
              $disabled = ((int) $nilai === (int) $anggota['simpanan_wajib']) ? 'disabled' : '';
              echo "<option value=\"$nilai\" $disabled>Rp " . number_format($nilai, 0, ',', '.') . "</option>";
            }
            ?>
          </select>
          <div class="form-text">Opsi yang sama dengan simpanan saat ini dinonaktifkan.</div>
        </div>

        <div id="info-perubahan" class="mt-2 fw-semibold"></div>
        <div id="info-periode" class="small text-muted"></div>

        <div id="opsi-turun" class="mt-3" style="display:none;">
          <label class="form-label">Kelebihan dana (pilih tindakan)</label>
          <select class="form-select" name="opsi">
            <option value="tarik" selected>Tarik Tunai (mengurangi saldo_total)</option>
          </select>
        </div>

        <!-- NEW: upload bukti -->
        <div class="mt-3">
          <label class="form-label">Bukti Transaksi (gambar/PDF, maks 5MB)</label>
          <input type="file" class="form-control" name="bukti_file"
                 accept=".jpg,.jpeg,.png,.webp" required>
                 </div>

        <div class="mt-3">
          <label class="form-label">Metode</label>
          <select class="form-select" name="metode" required>
            <option value="cash">Cash</option>
            <option value="transfer">Transfer</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Proses Perubahan</button>
      </div>
    </form>
  </div></div>
</div>

<script>
// === Perhitungan periode berbasis jatuh tempo (seperti versi sebelumnya) ===
(function(){
  const lama            = parseInt(document.getElementById('simpanan_lama').value, 10);
  const tJoinStr        = document.getElementById('tanggal_join').value; // 'YYYY-MM-DD'
  const selectBaru      = document.getElementById('simpanan_baru');
  const info            = document.getElementById('info-perubahan');
  const infoPeriode     = document.getElementById('info-periode');
  const opsiTurun       = document.getElementById('opsi-turun');

  const indoBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const rupiah = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
  const daysInMonth = (y,m) => new Date(y, m, 0).getDate(); // m: 1..12

  function hitungPeriodeJatuhTempo(joinStr, now = new Date()){
    const [Yj, Mj, Dj] = joinStr.split('-').map(Number);
    const Ynow = now.getFullYear();
    const Mnow = now.getMonth()+1;
    const Dnow = now.getDate();
    let diffBulan = (Ynow*12 + Mnow) - (Yj*12 + Mj);
    if (diffBulan < 0) diffBulan = 0;
    const barrier = Math.min(Dj, daysInMonth(Ynow, Mnow));
    const add     = (Dnow >= barrier) ? 1 : 0;
    return Math.max(0, diffBulan + add);
  }
  function teksPeriode(joinStr, jml){
    const [Yj, Mj] = joinStr.split('-').map(Number);
    const awal = `${indoBulan[Mj]} ${Yj}`;
    if (jml <= 0) return `${awal} — (belum jatuh tempo pertama)`;
    const total = (Yj*12 + Mj) + (jml - 1);
    let Mend = total % 12; if (Mend === 0) Mend = 12;
    let Yend = Math.floor((total - 1)/12);
    const akhir = `${indoBulan[Mend]} ${Yend}`;
    return `${awal} — ${akhir} (jumlah periode: ${jml})`;
  }

  const jmlPeriode = hitungPeriodeJatuhTempo(tJoinStr, new Date());

  function refresh(){
    const val = parseInt(selectBaru.value, 10);
    const diffPerBulan = val - lama;
    const total = Math.abs(diffPerBulan) * jmlPeriode;

    if (diffPerBulan < 0){ info.innerHTML = `Turun — kelebihan dana: <span class="text-warning fw-bold">${rupiah(total)}</span>`; opsiTurun.style.display=''; }
    else if (diffPerBulan > 0){ info.innerHTML = `Naik — kekurangan yang harus disetor: <span class="text-success fw-bold">${rupiah(total)}</span>`; opsiTurun.style.display='none'; }
    else { info.innerHTML = `<span class="text-muted">Tidak ada perubahan nominal</span>`; opsiTurun.style.display='none'; }

    infoPeriode.textContent = `Periode: ${teksPeriode(tJoinStr, jmlPeriode)}`;
  }

  document.getElementById('ubahSimpananModal').addEventListener('shown.bs.modal', refresh);
  selectBaru.addEventListener('change', refresh);
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
