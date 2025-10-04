<?php
// proses_pendaftaran.php (VERSI PERBAIKAN - DENGAN LOG HISTORY)
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Include history log
require_once 'Bendahra/functions/history_log.php';

function handleUpload($file, $folder, $prefix = '')
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK)
        return ['error' => 'File tidak valid. Code: ' . ($file['error'] ?? 'N/A')];
    if ($file['size'] > 5 * 1024 * 1024)
        return ['error' => 'Ukuran file maks 5MB.'];
    if (!is_dir($folder) && !mkdir($folder, 0777, true))
        return ['error' => 'Gagal membuat direktori.'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = ($prefix ? $prefix . '_' : '') . uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $folder . '/' . $fileName;
    if (move_uploaded_file($file['tmp_name'], $targetPath))
        return ['success' => true, 'path' => $targetPath];
    return ['error' => 'Gagal memindahkan file. Periksa izin folder.'];
}

function getTransactionPrefix($jenis_simpanan)
{
    switch ($jenis_simpanan) {
        case 'Simpanan Pokok':
            return 'TRX-PKK';
        case 'Simpanan Wajib':
            return 'TRX-WJB';
        case 'Simpanan Sukarela':
            return 'TRX-SKR';
        default:
            return 'TRX-OTH';
    }
}

try {
    // Ambil semua data dari POST
    $nama = trim($_POST['nama'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $npwp = trim($_POST['npwp'] ?? '');
    $agama = trim($_POST['agama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $rw = trim($_POST['rw'] ?? '');
    $rt = trim($_POST['rt'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $simpanan_wajib_option = trim($_POST['simpanan_wajib'] ?? '');
    $tanggal_join_str = trim($_POST['tanggal_join'] ?? '');
    $jenis_transaksi = trim($_POST['jenis_transaksi'] ?? '');

    // Validasi data
    if (empty($nama) || empty($pekerjaan) || empty($nik) || empty($simpanan_wajib_option) || empty($tanggal_join_str) || empty($jenis_transaksi)) {
        throw new Exception("Semua field yang wajib diisi harus lengkap.");
    }
    if (!isset($_FILES['foto_diri']) || !isset($_FILES['foto_ktp'])) {
        throw new Exception("File foto diri dan KTP wajib diunggah.");
    }

    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error)
        throw new Exception('Koneksi database gagal: ' . $conn->connect_error);
    $conn->begin_transaction();

    // Generate No Anggota
    $result = $conn->query("SELECT no_anggota FROM anggota ORDER BY id DESC LIMIT 1");
    $lastNum = 0;
    if ($result && $result->num_rows > 0) {
        $lastNo = $result->fetch_assoc()['no_anggota'];
        if (preg_match('/(\d{4})$/', $lastNo, $matches))
            $lastNum = (int) $matches[1];
    }
    $no_anggota = '32041011' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);

    // Upload Dokumen
    $foto_diri_path = handleUpload($_FILES['foto_diri'], 'foto_diri', 'diri_' . $no_anggota)['path'] ?? null;
    $foto_ktp_path = handleUpload($_FILES['foto_ktp'], 'foto_ktp', 'ktp_' . $no_anggota)['path'] ?? null;
    $foto_kk_path = (isset($_FILES['foto_kk']) && $_FILES['foto_kk']['error'] == UPLOAD_ERR_OK) ? (handleUpload($_FILES['foto_kk'], 'foto_kk', 'kk_' . $no_anggota)['path'] ?? null) : null;

    // Logika Tanggal
    $tanggal_join = new DateTime($tanggal_join_str);
    $tanggal_jatuh_tempo = (clone $tanggal_join)->modify('+1 month');
    $bulan_periode_tanggal = $tanggal_join->format('Y-m-d');
    $nama_bulan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $bulan_daftar_txt_info = $nama_bulan_map[(int) $tanggal_join->format('n')] . ' ' . $tanggal_join->format('Y');
    $status_anggota = "Aktif (" . $bulan_daftar_txt_info . ")";

    // Hitung saldo
    $simpanan_pokok = isset($_POST['check-pokok']) ? (float) ($_POST['amount_pokok'] ?? 10000) : 0;
    $simpanan_wajib_amount = isset($_POST['check-wajib']) ? (float) ($_POST['amount_wajib'] ?? 0) : 0;
    $saldo_sukarela = isset($_POST['check-sukarela']) ? (float) ($_POST['amount_sukarela'] ?? 0) : 0;
    $saldo_total = $simpanan_pokok + $simpanan_wajib_amount + $saldo_sukarela;
    $tanggal_jatuh_tempo_str = $tanggal_jatuh_tempo->format('Y-m-d');

    // Insert data anggota
    $stmt_anggota = $conn->prepare("INSERT INTO anggota (no_anggota, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, nik, npwp, agama, alamat, rw, rt, no_hp, pekerjaan, simpanan_wajib, foto_diri, foto_ktp, foto_kk, tanggal_join, tanggal_jatuh_tempo, status, saldo_sukarela, saldo_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_anggota->bind_param('sssssssssssssdssssssdd', $no_anggota, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $nik, $npwp, $agama, $alamat, $rw, $rt, $no_hp, $pekerjaan, $simpanan_wajib_option, $foto_diri_path, $foto_ktp_path, $foto_kk_path, $tanggal_join_str, $tanggal_jatuh_tempo_str, $status_anggota, $saldo_sukarela, $saldo_total);
    if (!$stmt_anggota->execute())
        throw new Exception("Gagal menyimpan data anggota: " . $stmt_anggota->error);
    $anggota_id = $conn->insert_id;

    // LOG HISTORY: Pendaftaran anggota baru
    $user_id = $_SESSION['id'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'bendahara';
    log_anggota_activity(
        $anggota_id,
        'create',
        "Mendaftarkan anggota baru: $nama ($no_anggota) dengan simpanan pokok Rp " . number_format($simpanan_pokok, 0, ',', '.') . ", wajib Rp " . number_format($simpanan_wajib_amount, 0, ',', '.') . ", sukarela Rp " . number_format($saldo_sukarela, 0, ',', '.'),
        $user_role
    );

    // Upload bukti pembayaran
    $bukti_tunggal_path = (isset($_FILES['bukti_tunggal']) && $_FILES['bukti_tunggal']['error'] == 0) ? (handleUpload($_FILES['bukti_tunggal'], 'bukti_bayar', 'bukti_tunggal_' . $no_anggota)['path'] ?? null) : null;

    // Menyiapkan data pembayaran dalam array
    $pembayaran_data = [];
    if ($simpanan_pokok > 0)
        $pembayaran_data[] = ['jenis_simpanan' => 'Simpanan Pokok', 'jumlah' => $simpanan_pokok, 'status' => 'Simpanan Pokok ' . $nama . ' - ' . $bulan_daftar_txt_info];
    if ($simpanan_wajib_amount > 0)
        $pembayaran_data[] = ['jenis_simpanan' => 'Simpanan Wajib', 'jumlah' => $simpanan_wajib_amount, 'status' => 'Pembayaran Simpanan Wajib ' . $bulan_daftar_txt_info];
    if ($saldo_sukarela > 0)
        $pembayaran_data[] = ['jenis_simpanan' => 'Simpanan Sukarela', 'jumlah' => $saldo_sukarela, 'status' => 'Setor Simpanan Sukarela Sebesar ' . number_format($saldo_sukarela) . ' - ' . $bulan_daftar_txt_info];

    // LOGIKA ID TRANSAKSI GLOBAL
    $result = $conn->query("SELECT MAX(id) as max_id FROM pembayaran");
    $last_payment_id = ($result->fetch_assoc()['max_id'] ?? 0);

    // Metode pembayaran dan bank tujuan
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
    $bank_tujuan = ($metode_pembayaran === 'transfer') ? ($_POST['bank_tujuan'] ?? '') : null;

    // Siapkan statement untuk pembayaran
    $stmt_pembayaran = $conn->prepare("INSERT INTO pembayaran (anggota_id, id_transaksi, jenis_simpanan, jenis_transaksi, jumlah, nama_anggota, bulan_periode, metode, bank_tujuan, bukti, status, tanggal_bayar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    // PERBAIKAN: HANYA SATU LOOP - tidak ada duplicate
    foreach ($pembayaran_data as $data) {
        $last_payment_id++;
        $prefix = getTransactionPrefix($data['jenis_simpanan']);
        $id_transaksi = $prefix . '-' . str_pad($last_payment_id, 7, '0', STR_PAD_LEFT);

        // Bind parameter dengan benar - 11 parameter
        $stmt_pembayaran->bind_param(
            'isssdssssss',
            $anggota_id,                // i
            $id_transaksi,              // s
            $data['jenis_simpanan'],    // s
            $jenis_transaksi,           // s
            $data['jumlah'],            // d
            $nama,                      // s
            $bulan_periode_tanggal,     // s
            $metode_pembayaran,         // s
            $bank_tujuan,               // s
            $bukti_tunggal_path,        // s
            $data['status']             // s
        );

        if (!$stmt_pembayaran->execute()) {
            throw new Exception("Gagal menyimpan " . $data['jenis_simpanan'] . ": " . $stmt_pembayaran->error);
        }

        $pembayaran_id = $conn->insert_id;

        // LOG HISTORY: Pembayaran simpanan
        log_pembayaran_activity(
            $pembayaran_id,
            'create',
            "Mencatat pembayaran $data[jenis_simpanan] sebesar Rp " . number_format($data['jumlah'], 0, ',', '.') . " untuk anggota $nama ($no_anggota)",
            $user_role
        );
    }

    $conn->commit();

    // LOG HISTORY: Success final
    log_anggota_activity(
        $anggota_id,
        'complete',
        "Pendaftaran anggota $nama ($no_anggota) berhasil diselesaikan dengan total simpanan Rp " . number_format($saldo_total, 0, ',', '.'),
        $user_role
    );

    echo json_encode(['status' => 'success', 'message' => 'Pendaftaran anggota ' . $no_anggota . ' berhasil!']);

} catch (Exception $e) {
    // LOG HISTORY: Error
    if (isset($anggota_id)) {
        log_anggota_activity(
            $anggota_id,
            'error',
            "Error pendaftaran: " . $e->getMessage(),
            $user_role ?? 'system'
        );
    }

    if (isset($conn))
        $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt_anggota))
        $stmt_anggota->close();
    if (isset($stmt_pembayaran))
        $stmt_pembayaran->close();
    if (isset($conn))
        $conn->close();
}
?>