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
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .card-header {
      font-weight: bold;
      background: linear-gradient(135deg, #2c3e50, #4a6580);
    }

    th {
      width: 30%;
      background-color: #f1f8ff;
    }

    .card {
      border: none;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
    }

    .card-title {
      color: #2c3e50;
      font-weight: 600;
    }

    .profile-picture {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      border: 4px solid #fff;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .saldo-item {
      padding: 4px 0;
      border-bottom: 1px solid #eee;
    }

    .saldo-item:last-child {
      border-bottom: none;
    }

    .saldo-list {
      max-height: 200px;
      overflow-y: auto;
    }
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
            <tr>
              <th>Nomor Anggota</th>
              <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
            </tr>
            <tr>
              <th>NIK</th>
              <td><?= htmlspecialchars($anggota['nik']) ?></td>
            </tr>
            <tr>
              <th>Jenis Kelamin</th>
              <td><?= htmlspecialchars($anggota['jenis_kelamin']) ?></td>
            </tr>
            <tr>
              <th>Tempat, Tanggal Lahir</th>
              <td><?= $tempat_tanggal_lahir ?></td>
            </tr>
            <tr>
              <th>Alamat</th>
              <td><?= htmlspecialchars($anggota['alamat']) ?></td>
            </tr>
            <tr>
              <th>No. HP (WhatsApp)</th>
              <td><?= htmlspecialchars($anggota['no_hp']) ?></td>
            </tr>
            <tr>
              <th>Pekerjaan</th>
              <td><?= htmlspecialchars($anggota['pekerjaan']) ?></td>
            </tr>
            <tr>
              <th>Tanggal Bergabung</th>
              <td><?= date('d F Y', strtotime($anggota['tanggal_join'])) ?></td>
            </tr>
            <tr>
              <th>Lama Menjadi Anggota</th>
              <td><?= $durasi_str ?></td>
            </tr>
            <tr>
              <th>Simpanan Pokok</th>
              <td>Rp 10.000</td>
            </tr>
            <tr>
              <th>Simpanan Wajib</th>
              <td>Rp <?= number_format($anggota['simpanan_wajib'], 0, ',', '.') ?></td>
            </tr>
            <tr>
              <th>Simpanan Sukarela</th>
              <td>Rp <?= number_format($anggota['saldo_sukarela'], 0, ',', '.') ?></td>
            </tr>
            <tr>
              <th>Total Saldo Anggota</th>
              <td>Rp <?= number_format($anggota['saldo_total'], 0, ',', '.') ?></td>
            </tr>
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

  <!-- Modal Ubah Data -->
  <div class="modal fade" id="ubahDataModal" tabindex="-1" aria-labelledby="ubahDataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="ubahDataModalLabel">Ubah Data Anggota</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="proses_ubah.php" method="POST" id="formUbahData">
          <div class="modal-body">
            <input type="hidden" name="id" value="<?= $anggota['id'] ?>">
            <div class="mb-3">
              <label for="nama" class="form-label">Nama Lengkap</label>
              <input type="text" class="form-control" id="nama" name="nama"
                value="<?= htmlspecialchars($anggota['nama']) ?>" required>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="nik" class="form-label">NIK</label>
                <input type="text" class="form-control" id="nik" name="nik"
                  value="<?= htmlspecialchars($anggota['nik']) ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="npwp" class="form-label">NPWP (Opsional)</label>
                <input type="text" class="form-control" id="npwp" name="npwp"
                  value="<?= htmlspecialchars($anggota['npwp'] ?? '') ?>">
              </div>
            </div>
            <div class="mb-3">
              <label for="alamat" class="form-label">Alamat</label>
              <textarea class="form-control" id="alamat" name="alamat" rows="3"
                required><?= htmlspecialchars($anggota['alamat']) ?></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="rt" class="form-label">RT</label>
                <input type="text" class="form-control" id="rt" name="rt"
                  value="<?= htmlspecialchars($anggota['rt'] ?? '') ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label for="rw" class="form-label">RW</label>
                <input type="text" class="form-control" id="rw" name="rw"
                  value="<?= htmlspecialchars($anggota['rw'] ?? '') ?>">
              </div>
            </div>
            <div class="mb-3">
              <label for="pekerjaan" class="form-label">Pekerjaan</label>
              <input type="text" class="form-control" id="pekerjaan" name="pekerjaan"
                value="<?= htmlspecialchars($anggota['pekerjaan']) ?>" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Ubah Simpanan -->
  <input type="hidden" id="tanggal_join" value="<?= htmlspecialchars($anggota['tanggal_join']) ?>">
  <input type="hidden" id="simpanan_lama" value="<?= (int) $anggota['simpanan_wajib'] ?>">

  <div class="modal fade" id="ubahSimpananModal" tabindex="-1" aria-labelledby="ubahSimpananModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="/kdmpgs%20-%20v2/bendahara/proses_ubah_simpanan.php" method="POST" id="formUbahSimpanan"
          enctype="multipart/form-data">
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

            <!-- Sumber Dana -->
            <div class="mb-3">
              <label class="form-label">Sumber Dana *</label>
              <select class="form-select" name="metode" id="metodeInput" required>
                <option value="">Pilih Sumber Dana</option>
                <option value="cash">Kas Tunai</option>
                <option value="transfer">Rekening Bank</option>
              </select>
            </div>

            <!-- Informasi Saldo Rekening -->
            <div id="infoSaldoContainer" style="display: none;">
              <div class="alert alert-info py-2">
                <h6 class="alert-heading mb-2">Informasi Saldo Rekening</h6>
                <div id="infoSaldoContent">
                  <div class="text-center">
                    <div class="spinner-border spinner-border-sm" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                    Memuat data saldo...
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-3" id="bankTujuanContainer" style="display: none;">
              <label class="form-label">Rekening Bank Tujuan *</label>
              <select class="form-select" name="bank_tujuan" id="bankTujuanInput">
                <option value="">Pilih Bank</option>
                <option value="Bank MANDIRI">Bank MANDIRI</option>
                <option value="Bank BRI">Bank BRI</option>
                <option value="Bank BNI">Bank BNI</option>
              </select>
            </div>

            <!-- Upload bukti -->
            <div class="mt-3">
              <label class="form-label">Bukti Transaksi (gambar/PDF, maks 5MB)</label>
              <input type="file" class="form-control" name="bukti_file" accept=".jpg,.jpeg,.png,.webp" required>
            </div>

          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Proses Perubahan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // === FUNGSI UNTUK MENGAMBIL DATA SALDO DARI DATABASE ===
    function loadSaldoData(metode, bankTujuan = '') {
      const infoSaldoContent = document.getElementById('infoSaldoContent');

      // Tampilkan loading
      infoSaldoContent.innerHTML = `
        <div class="text-center">
          <div class="spinner-border spinner-border-sm" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          Memuat data saldo...
        </div>
      `;

      // AJAX request ke endpoint get_saldo_data.php
      fetch('/pages/ajax/get_saldo_data.php')
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            displaySaldoInfo(data.data, metode, bankTujuan);
          } else {
            infoSaldoContent.innerHTML = `<div class="text-danger">Error: ${data.message}</div>`;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          infoSaldoContent.innerHTML = `<div class="text-danger">Gagal memuat data saldo</div>`;
        });
    }

    // === FUNGSI UNTUK MENAMPILKAN INFORMASI SALDO ===
    function displaySaldoInfo(saldoData, metode, bankTujuan) {
      const infoSaldoContent = document.getElementById('infoSaldoContent');
      let html = '';

      if (metode === 'cash') {
        // Tampilkan saldo kas tunai
        const saldoKas = saldoData.saldo_kas || 0;
        html = `
          <div class="saldo-item">
            <strong>Kas Tunai:</strong> 
            <span class="float-end">Rp ${formatRupiah(saldoKas)}</span>
          </div>
        `;
      } else if (metode === 'transfer') {
        // Tampilkan semua saldo bank
        html = '<div class="saldo-list">';
        saldoData.saldo_bank.forEach(bank => {
          const isSelected = bank.nama_bank === bankTujuan;
          const highlight = isSelected ? 'text-primary fw-bold' : '';
          html += `
            <div class="saldo-item ${highlight}">
              <strong>${bank.nama_bank}:</strong> 
              <span class="float-end">Rp ${formatRupiah(bank.saldo)}</span>
              ${isSelected ? ' ← Dipilih' : ''}
            </div>
          `;
        });
        html += '</div>';
      }

      infoSaldoContent.innerHTML = html;

      // Validasi kecukupan saldo setelah data dimuat
      validasiKecukupanSaldo(saldoData);
    }

    // === FUNGSI VALIDASI KECUKUPAN SALDO ===
    function validasiKecukupanSaldo(saldoData) {
      const metode = document.getElementById('metodeInput').value;
      const bankTujuan = document.getElementById('bankTujuanInput').value;
      const infoPerubahan = document.getElementById('info-perubahan');

      // Cek apakah ada kekurangan yang harus dibayar
      const kekuranganText = infoPerubahan.textContent;
      let jumlahKekurangan = 0;

      if (kekuranganText.includes('kekurangan')) {
        const match = kekuranganText.match(/Rp\s*([\d.,]+)/);
        if (match) {
          jumlahKekurangan = parseInt(match[1].replace(/[.,]/g, ''));
        }
      }

      // Jika ada kekurangan, validasi saldo
      if (jumlahKekurangan > 0) {
        let saldoTersedia = 0;

        if (metode === 'cash') {
          saldoTersedia = saldoData.saldo_kas || 0;
        } else if (metode === 'transfer' && bankTujuan) {
          const bank = saldoData.saldo_bank.find(b => b.nama_bank === bankTujuan);
          saldoTersedia = bank ? bank.saldo : 0;
        }

        if (saldoTersedia < jumlahKekurangan) {
          // Tampilkan peringatan di UI
          const warningHtml = `
            <div class="alert alert-warning mt-2 py-2">
              <small>
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Saldo tidak mencukupi!</strong><br>
                Saldo tersedia: Rp ${formatRupiah(saldoTersedia)}<br>
                Kebutuhan: Rp ${formatRupiah(jumlahKekurangan)}
              </small>
            </div>
          `;

          // Tambahkan atau update warning
          let existingWarning = document.getElementById('saldoWarning');
          if (existingWarning) {
            existingWarning.innerHTML = warningHtml;
          } else {
            const warningDiv = document.createElement('div');
            warningDiv.id = 'saldoWarning';
            warningDiv.innerHTML = warningHtml;
            document.getElementById('infoSaldoContainer').appendChild(warningDiv);
          }
        } else {
          // Hapus warning jika ada
          const existingWarning = document.getElementById('saldoWarning');
          if (existingWarning) {
            existingWarning.remove();
          }
        }
      }
    }

    // === FUNGSI FORMAT RUPIAH ===
    function formatRupiah(angka) {
      return new Intl.NumberFormat('id-ID').format(angka);
    }

    // === FUNGSI UNTUK SUMBER DANA DAN INFO SALDO ===
    document.getElementById('metodeInput').addEventListener('change', function () {
      const metode = this.value;
      const bankContainer = document.getElementById('bankTujuanContainer');
      const infoSaldoContainer = document.getElementById('infoSaldoContainer');
      const bankInput = document.getElementById('bankTujuanInput');

      if (metode === 'transfer') {
        bankContainer.style.display = 'block';
        bankInput.required = true;
        infoSaldoContainer.style.display = 'block';
        loadSaldoData(metode);
      } else if (metode === 'cash') {
        bankContainer.style.display = 'none';
        bankInput.required = false;
        bankInput.value = '';
        infoSaldoContainer.style.display = 'block';
        loadSaldoData(metode);
      } else {
        bankContainer.style.display = 'none';
        infoSaldoContainer.style.display = 'none';
        bankInput.required = false;
        bankInput.value = '';
      }
    });

    document.getElementById('bankTujuanInput').addEventListener('change', function () {
      const metode = document.getElementById('metodeInput').value;
      loadSaldoData(metode, this.value);
    });

    // === FUNGSI UNTUK PERHITUNGAN PERIODE ===
    (function () {
      const lama = parseInt(document.getElementById('simpanan_lama').value, 10);
      const tJoinStr = document.getElementById('tanggal_join').value;
      const selectBaru = document.getElementById('simpanan_baru');
      const info = document.getElementById('info-perubahan');
      const infoPeriode = document.getElementById('info-periode');
      const opsiTurun = document.getElementById('opsi-turun');

      const indoBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      const rupiah = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
      const daysInMonth = (y, m) => new Date(y, m, 0).getDate();

      function hitungPeriodeJatuhTempo(joinStr, now = new Date()) {
        const [Yj, Mj, Dj] = joinStr.split('-').map(Number);
        const Ynow = now.getFullYear();
        const Mnow = now.getMonth() + 1;
        const Dnow = now.getDate();
        let diffBulan = (Ynow * 12 + Mnow) - (Yj * 12 + Mj);
        if (diffBulan < 0) diffBulan = 0;
        const barrier = Math.min(Dj, daysInMonth(Ynow, Mnow));
        const add = (Dnow >= barrier) ? 1 : 0;
        return Math.max(0, diffBulan + add);
      }

      function teksPeriode(joinStr, jml) {
        const [Yj, Mj] = joinStr.split('-').map(Number);
        const awal = `${indoBulan[Mj]} ${Yj}`;
        if (jml <= 0) return `${awal} — (belum jatuh tempo pertama)`;
        const total = (Yj * 12 + Mj) + (jml - 1);
        let Mend = total % 12;
        if (Mend === 0) Mend = 12;
        let Yend = Math.floor((total - 1) / 12);
        const akhir = `${indoBulan[Mend]} ${Yend}`;
        return `${awal} — ${akhir} (jumlah periode: ${jml})`;
      }

      const jmlPeriode = hitungPeriodeJatuhTempo(tJoinStr, new Date());

      function refresh() {
        const val = parseInt(selectBaru.value, 10);
        const diffPerBulan = val - lama;
        const total = Math.abs(diffPerBulan) * jmlPeriode;

        if (diffPerBulan < 0) {
          info.innerHTML = `Turun — kelebihan dana: <span class="text-warning fw-bold">${rupiah(total)}</span>`;
          opsiTurun.style.display = '';
        } else if (diffPerBulan > 0) {
          info.innerHTML = `Naik — kekurangan yang harus disetor: <span class="text-success fw-bold">${rupiah(total)}</span>`;
          opsiTurun.style.display = 'none';
        } else {
          info.innerHTML = `<span class="text-muted">Tidak ada perubahan nominal</span>`;
          opsiTurun.style.display = 'none';
        }

        infoPeriode.textContent = `Periode: ${teksPeriode(tJoinStr, jmlPeriode)}`;

        // Load ulang saldo jika metode sudah dipilih
        const metode = document.getElementById('metodeInput').value;
        const bankTujuan = document.getElementById('bankTujuanInput').value;
        if (metode) {
          loadSaldoData(metode, bankTujuan);
        }
      }

      // Event listeners
      document.getElementById('ubahSimpananModal').addEventListener('shown.bs.modal', refresh);
      selectBaru.addEventListener('change', refresh);

      // Inisialisasi pertama kali
      refresh();
    })();

    // Validasi form sebelum submit
    document.getElementById('formUbahSimpanan').addEventListener('submit', function (e) {
      const metode = document.getElementById('metodeInput').value;
      const bankTujuan = document.getElementById('bankTujuanInput').value;

      if (metode === 'transfer' && !bankTujuan) {
        e.preventDefault();
        alert('Bank tujuan wajib dipilih untuk metode transfer.');
        return false;
      }
    });

    // Reset form ketika modal ditutup
    document.getElementById('ubahSimpananModal').addEventListener('hidden.bs.modal', function () {
      document.getElementById('metodeInput').value = '';
      document.getElementById('bankTujuanContainer').style.display = 'none';
      document.getElementById('bankTujuanInput').value = '';
      document.getElementById('bankTujuanInput').required = false;
      document.getElementById('infoSaldoContainer').style.display = 'none';
    });
  </script>
</body>

</html>