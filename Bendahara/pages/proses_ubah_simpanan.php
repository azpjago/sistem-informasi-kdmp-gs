<?php
/**
 * proses_ubah_simpanan.php (VERSI DENGAN LOG HISTORY)
 */
session_start();
date_default_timezone_set('Asia/Jakarta');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Include history log
require_once 'functions/history_log.php';

$DETAIL_URL = 'detail_anggota.php';

function alert_redirect(string $toUrl, string $message): void
{
  echo "<!doctype html><html><head><meta charset='utf-8'></head><body>
<script>
  alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
  window.location.href = " . json_encode($toUrl, JSON_UNESCAPED_UNICODE) . ";
</script>
</body></html>";
  exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

$id_anggota = (int) ($_POST['id'] ?? 0);
$wajib_baru = (int) ($_POST['simpanan_baru'] ?? 0);
$metode = trim($_POST['metode'] ?? '');
$bank_tujuan = trim($_POST['bank_tujuan'] ?? '');
$opsi = $_POST['opsi'] ?? null;

if ($id_anggota <= 0 || $wajib_baru <= 0 || !in_array($metode, ['cash', 'transfer'], true)) {
  alert_redirect("{$DETAIL_URL}?id={$id_anggota}&msg=invalid", 'Input tidak valid.');
}

function indo_bulan(int $n): string
{
  static $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  return $b[$n] ?? (string) $n;
}

function hitung_periode_jatuh_tempo(DateTime $join, DateTime $now): int
{
  $diffBulan = max(
    0,
    ((int) $now->format('Y') * 12 + (int) $now->format('n'))
    - ((int) $join->format('Y') * 12 + (int) $join->format('n'))
  );
  $hari_join = (int) $join->format('j');
  $hari_now = (int) $now->format('j');
  $hari_akhir = (int) $now->format('t');
  $barrier_day = min($hari_join, $hari_akhir);
  $plus = ($hari_now >= $barrier_day) ? 1 : 0;
  return max(0, $diffBulan + $plus);
}

function bulan_terakhir_due(DateTime $join, int $jml_periode): ?DateTime
{
  if ($jml_periode <= 0)
    return null;
  $d = (clone $join)->modify('first day of this month');
  $d->modify('+' . ($jml_periode - 1) . ' months');
  return $d;
}

function generate_id_transaksi_manual(mysqli $conn, string $jenis_simpanan): string
{
  $prefix = match (strtolower($jenis_simpanan)) {
    'simpanan wajib' => 'TRX-WJB',
    'simpanan sukarela' => 'TRX-SKR',
    'simpanan pokok' => 'TRX-PKK',
    default => 'TRX-UDT',
  };

  $q = $conn->query("SELECT MAX(id) AS maxid FROM pembayaran");
  $row = $q->fetch_assoc();
  $next_id = (int) ($row['maxid'] ?? 0) + 1;
  $nomor = str_pad((string) $next_id, 7, '0', STR_PAD_LEFT);
  return "{$prefix}-{$nomor}";
}

function save_bukti(string $fieldName, string $idTransaksi, string $jenisTransaksi): ?string
{
  if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE)
    return null;
  $f = $_FILES[$fieldName];
  if ($f['error'] !== UPLOAD_ERR_OK)
    throw new RuntimeException('Upload bukti gagal (kode ' . $f['error'] . ')');
  if ($f['size'] > 5 * 1024 * 1024)
    throw new RuntimeException('Ukuran bukti maksimal 5MB.');

  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
  if (!in_array($ext, $allowed, true))
    throw new RuntimeException('Format bukti harus jpg/jpeg/png/webp/pdf.');

  $destDir = __DIR__ . DIRECTORY_SEPARATOR . 'bukti_bayar';
  if (!is_dir($destDir) && !mkdir($destDir, 0775, true))
    throw new RuntimeException('Gagal membuat folder bukti_bayar.');

  $base = strtolower($jenisTransaksi) . '-' . $idTransaksi . '.' . $ext;
  $destAbs = $destDir . DIRECTORY_SEPARATOR . $base;
  if (!move_uploaded_file($f['tmp_name'], $destAbs))
    throw new RuntimeException('Gagal menyimpan bukti ke server.');

  return 'bukti_bayar/' . $base;
}

$conn->begin_transaction();
try {
  $stmt = $conn->prepare("SELECT id, nama, no_anggota, simpanan_wajib, saldo_sukarela, saldo_total, tanggal_join FROM anggota WHERE id=? FOR UPDATE");
  $stmt->bind_param('i', $id_anggota);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res->num_rows === 0)
    throw new Exception('Anggota tidak ditemukan.');
  $agt = $res->fetch_assoc();
  $stmt->close();

  $wajib_lama = (int) $agt['simpanan_wajib'];
  $nama_anggota = $agt['nama'];
  $no_anggota = $agt['no_anggota'];
  $tanggal_join = new DateTime($agt['tanggal_join']);
  $now = new DateTime();

  $jml_periode = hitung_periode_jatuh_tempo($tanggal_join, $now);
  $periode_awal = indo_bulan((int) $tanggal_join->format('n')) . ' ' . $tanggal_join->format('Y');
  $akhir_due_dt = bulan_terakhir_due($tanggal_join, $jml_periode);
  $periode_akhir = $akhir_due_dt ? indo_bulan((int) $akhir_due_dt->format('n')) . ' ' . $akhir_due_dt->format('Y') : '';
  $periode_teks = $periode_akhir ? "$periode_awal — $periode_akhir (jumlah periode: $jml_periode)" : "$periode_awal — (belum jatuh tempo pertama)";

  if ($wajib_baru === $wajib_lama) {
    $conn->rollback();
    alert_redirect("{$DETAIL_URL}?id={$id_anggota}&msg=tidak-ada-perubahan", 'Tidak ada perubahan nominal.');
  }

  $tanggal_bayar = $now->format('Y-m-d H:i:s');
  $bulan_periode = $now->format('Y-m');

  // Get user info for logging
  $user_id = $_SESSION['id'] ?? 0;
  $user_role = $_SESSION['role'] ?? 'bendahara';

  if ($wajib_baru > $wajib_lama) {
    $jenis_simpanan = "Simpanan Wajib";
    $jenis_transaksi = "setor";
    $jumlah = ($wajib_baru - $wajib_lama) * $jml_periode;
    $status = "Membayar kekurangan simpanan wajib. Periode: {$periode_teks}";

    $id_transaksi = generate_id_transaksi_manual($conn, $jenis_simpanan);
    $bukti = save_bukti('bukti_file', $id_transaksi, $jenis_transaksi);

    $stmt = $conn->prepare("INSERT INTO pembayaran (id_transaksi, anggota_id, jenis_simpanan, jenis_transaksi, jumlah, tanggal_bayar, bulan_periode, metode, bank_tujuan, status, bukti) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sissdssssss', $id_transaksi, $id_anggota, $jenis_simpanan, $jenis_transaksi, $jumlah, $tanggal_bayar, $bulan_periode, $metode, $bank_tujuan, $status, $bukti);
    $stmt->execute();
    $pembayaran_id = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE anggota SET simpanan_wajib=?, saldo_total=saldo_total+? WHERE id=?");
    $stmt->bind_param('idi', $wajib_baru, $jumlah, $id_anggota);
    $stmt->execute();
    $stmt->close();

    // LOG HISTORY: Pembayaran kekurangan simpanan wajib
    log_pembayaran_activity(
      $pembayaran_id,
      'create',
      "Membayar kekurangan simpanan wajib sebesar Rp " . number_format($jumlah, 0, ',', '.') . " untuk $nama_anggota ($no_anggota). Periode: $periode_teks",
      $user_role
    );

    log_anggota_activity(
      $id_anggota,
      'update',
      "Mengubah simpanan wajib dari Rp " . number_format($wajib_lama, 0, ',', '.') . " menjadi Rp " . number_format($wajib_baru, 0, ',', '.') . " dan menambah saldo total",
      $user_role
    );

  } else {
    $jenis_simpanan = "Simpanan Wajib";
    $jumlah = ($wajib_lama - $wajib_baru) * $jml_periode;
    $jenis_transaksi = 'tarik';
    $status = "Menarik kelebihan simpanan wajib. Periode: {$periode_teks}";

    $id_transaksi = generate_id_transaksi_manual($conn, $jenis_simpanan);
    $bukti = save_bukti('bukti_file', $id_transaksi, $jenis_transaksi);

    $stmt = $conn->prepare("INSERT INTO pembayaran (id_transaksi, anggota_id, jenis_simpanan, jenis_transaksi, jumlah, tanggal_bayar, bulan_periode, metode, bank_tujuan, status, bukti) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('sissdssssss', $id_transaksi, $id_anggota, $jenis_simpanan, $jenis_transaksi, $jumlah, $tanggal_bayar, $bulan_periode, $bank_tujuan, $metode, $status, $bukti);
    $stmt->execute();
    $pembayaran_id = $conn->insert_id;
    $stmt->close();

    // Update anggota (saldo_total dikurangi)
    $stmt = $conn->prepare("UPDATE anggota SET simpanan_wajib=?, saldo_total=saldo_total-? WHERE id=?");
    $stmt->bind_param('idi', $wajib_baru, $jumlah, $id_anggota);
    $stmt->execute();
    $stmt->close();

    // LOG HISTORY: Penarikan kelebihan simpanan wajib
    log_pembayaran_activity(
      $pembayaran_id,
      'create',
      "Menarik kelebihan simpanan wajib sebesar Rp " . number_format($jumlah, 0, ',', '.') . " dari $nama_anggota ($no_anggota). Periode: $periode_teks",
      $user_role
    );

    log_anggota_activity(
      $id_anggota,
      'update',
      "Mengubah simpanan wajib dari Rp " . number_format($wajib_lama, 0, ',', '.') . " menjadi Rp " . number_format($wajib_baru, 0, ',', '.') . " dan mengurangi saldo total",
      $user_role
    );
  }

  $conn->commit();

  // LOG HISTORY: Success final
  log_anggota_activity(
    $id_anggota,
    'complete',
    "Perubahan simpanan wajib $nama_anggota ($no_anggota) berhasil. Lama: Rp " . number_format($wajib_lama, 0, ',', '.') . ", Baru: Rp " . number_format($wajib_baru, 0, ',', '.'),
    $user_role
  );

  alert_redirect("{$DETAIL_URL}?id={$id_anggota}&msg=berhasil", 'Perubahan simpanan berhasil dicatat.');

} catch (Throwable $e) {
  // LOG HISTORY: Error
  if (isset($id_anggota)) {
    log_anggota_activity(
      $id_anggota,
      'error',
      "Error perubahan simpanan: " . $e->getMessage(),
      $user_role ?? 'system'
    );
  }

  $conn->rollback();
  alert_redirect("{$DETAIL_URL}?id={$id_anggota}&msg=gagal", 'Error: ' . $e->getMessage());
}