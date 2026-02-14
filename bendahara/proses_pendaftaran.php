<?php
// proses_pendaftaran.php (VERSI PERBAIKAN - TANGGAL_BAYAR MENGGUNAKAN TANGGAL_JOIN)
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Include history log
require_once 'functions/history_log.php';

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
    error_log("Data POST: " . print_r($_POST, true));
    error_log("Data FILES: " . print_r($_FILES, true));
    error_log("Session data: " . print_r($_SESSION, true));

    // Ambil data dari form
    $nama = trim($_POST['nama'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $nik = trim($_POST['nik'] ?? ''); // NIK akan digunakan sebagai no_anggota
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
    
    // Validasi NIK (minimal 16 digit untuk KTP)
    if (strlen($nik) < 16) {
        throw new Exception("NIK harus minimal 16 digit.");
    }
    
    if (!isset($_FILES['foto_diri']) || !isset($_FILES['foto_ktp'])) {
        throw new Exception("File foto diri dan KTP wajib diunggah.");
    }
    
    require 'koneksi/koneksi.php';
    
    // CEK DUPLIKASI NIK - Pastikan NIK belum terdaftar
    $cek_nik = $conn->prepare("SELECT id FROM anggota WHERE nik = ?");
    $cek_nik->bind_param("s", $nik);
    $cek_nik->execute();
    $result_nik = $cek_nik->get_result();
    if ($result_nik->num_rows > 0) {
        throw new Exception("NIK sudah terdaftar sebagai anggota.");
    }
    $cek_nik->close();
    
    // CEK DUPLIKASI NO ANGGOTA (NIK) - Pastikan no_anggota belum terdaftar
    $cek_no_anggota = $conn->prepare("SELECT id FROM anggota WHERE no_anggota = ?");
    $cek_no_anggota->bind_param("s", $nik);
    $cek_no_anggota->execute();
    $result_no_anggota = $cek_no_anggota->get_result();
    if ($result_no_anggota->num_rows > 0) {
        throw new Exception("Nomor anggota (NIK) sudah terdaftar.");
    }
    $cek_no_anggota->close();
    
    $conn->begin_transaction();

    // Gunakan NIK sebagai No Anggota
    $no_anggota = $nik;

    // Upload Dokumen
    $upload_foto_diri = handleUpload($_FILES['foto_diri'], 'foto_diri', 'diri_' . $no_anggota);
    if (isset($upload_foto_diri['error'])) {
        throw new Exception("Gagal upload foto diri: " . $upload_foto_diri['error']);
    }
    $foto_diri_path = $upload_foto_diri['path'] ?? null;
    
    $upload_foto_ktp = handleUpload($_FILES['foto_ktp'], 'foto_ktp', 'ktp_' . $no_anggota);
    if (isset($upload_foto_ktp['error'])) {
        throw new Exception("Gagal upload foto KTP: " . $upload_foto_ktp['error']);
    }
    $foto_ktp_path = $upload_foto_ktp['path'] ?? null;
    
    $foto_kk_path = null;
    if (isset($_FILES['foto_kk']) && $_FILES['foto_kk']['error'] == UPLOAD_ERR_OK) {
        $upload_foto_kk = handleUpload($_FILES['foto_kk'], 'foto_kk', 'kk_' . $no_anggota);
        if (isset($upload_foto_kk['error'])) {
            throw new Exception("Gagal upload foto KK: " . $upload_foto_kk['error']);
        }
        $foto_kk_path = $upload_foto_kk['path'] ?? null;
    }

    // PERBAIKAN: Konversi objek DateTime ke string dengan format()
    $tanggal_join = new DateTime($tanggal_join_str);
    $tanggal_jatuh_tempo = (clone $tanggal_join)->modify('+1 month');
    
    // PASTIKAN SEMUA TANGGAL DIKONVERSI KE STRING DENGAN format()
    $tanggal_join_str_formatted = $tanggal_join->format('Y-m-d'); // untuk field tanggal_join (DATE)
    $tanggal_jatuh_tempo_str = $tanggal_jatuh_tempo->format('Y-m-d'); // untuk field tanggal_jatuh_tempo (DATE)
    $bulan_periode_tanggal = $tanggal_join->format('Y-m-d'); // untuk field bulan_periode (DATE)
    
    // PERBAIKAN: Format tanggal_bayar untuk pembayaran (bisa DATETIME lengkap)
    $tanggal_bayar = $tanggal_join->format('Y-m-d H:i:s'); // Format lengkap untuk DATETIME
    
    // Untuk teks informasi
    $nama_bulan_map = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $bulan_daftar_txt_info = $nama_bulan_map[(int) $tanggal_join->format('n')] . ' ' . $tanggal_join->format('Y');
    $status_anggota = "Aktif (" . $bulan_daftar_txt_info . ")";

    // Hitung saldo
    $simpanan_pokok = isset($_POST['check-pokok']) ? (float) ($_POST['amount_pokok'] ?? 10000) : 0;
    $simpanan_wajib_amount = isset($_POST['check-wajib']) ? (float) ($_POST['amount_wajib'] ?? 0) : 0;
    $saldo_sukarela = isset($_POST['check-sukarela']) ? (float) ($_POST['amount_sukarela'] ?? 0) : 0;
    $saldo_total = $simpanan_pokok + $simpanan_wajib_amount + $saldo_sukarela;

    // Insert data anggota
    $stmt_anggota = $conn->prepare("INSERT INTO anggota (no_anggota, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, nik, npwp, agama, alamat, rw, rt, no_hp, pekerjaan, simpanan_wajib, foto_diri, foto_ktp, foto_kk, tanggal_join, tanggal_jatuh_tempo, status, saldo_sukarela, saldo_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // PERBAIKAN: Gunakan string yang sudah diformat, BUKAN objek DateTime
    $stmt_anggota->bind_param('sssssssssssssdssssssdd', 
        $no_anggota, 
        $nama, 
        $jenis_kelamin, 
        $tempat_lahir, 
        $tanggal_lahir, 
        $nik, 
        $npwp, 
        $agama, 
        $alamat, 
        $rw, 
        $rt, 
        $no_hp, 
        $pekerjaan, 
        $simpanan_wajib_option, 
        $foto_diri_path, 
        $foto_ktp_path, 
        $foto_kk_path, 
        $tanggal_join_str_formatted, // string Y-m-d
        $tanggal_jatuh_tempo_str,    // string Y-m-d
        $status_anggota, 
        $saldo_sukarela, 
        $saldo_total
    );
    
    if (!$stmt_anggota->execute()) {
        throw new Exception("Gagal menyimpan data anggota: " . $stmt_anggota->error);
    }
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
    $bukti_tunggal_path = null;
    if (isset($_FILES['bukti_tunggal']) && $_FILES['bukti_tunggal']['error'] == UPLOAD_ERR_OK) {
        $upload_bukti = handleUpload($_FILES['bukti_tunggal'], 'bukti_bayar', 'bukti_' . $no_anggota);
        if (isset($upload_bukti['error'])) {
            throw new Exception("Gagal upload bukti pembayaran: " . $upload_bukti['error']);
        }
        $bukti_tunggal_path = $upload_bukti['path'] ?? null;
    }

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
    $row = $result->fetch_assoc();
    $last_payment_id = $row['max_id'] ?? 0;

    // Metode pembayaran dan bank tujuan
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'cash';
    $bank_tujuan = ($metode_pembayaran === 'transfer') ? ($_POST['bank_tujuan'] ?? '') : null;

    // PERBAIKAN: Siapkan statement untuk pembayaran dengan parameter yang benar
    $stmt_pembayaran = $conn->prepare("INSERT INTO pembayaran (anggota_id, id_transaksi, jenis_simpanan, jenis_transaksi, jumlah, nama_anggota, bulan_periode, metode, bank_tujuan, bukti, status, tanggal_bayar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Loop untuk setiap jenis pembayaran
    foreach ($pembayaran_data as $data) {
        $last_payment_id++;
        $prefix = getTransactionPrefix($data['jenis_simpanan']);
        $id_transaksi = $prefix . '-' . str_pad($last_payment_id, 7, '0', STR_PAD_LEFT);

        // PERBAIKAN: Bind parameter dengan urutan yang benar - 12 parameter
        $stmt_pembayaran->bind_param(
            'isssdsssssss',  // i=integer, s=string, d=double
            $anggota_id,                // i (integer)
            $id_transaksi,              // s (string)
            $data['jenis_simpanan'],    // s (string)
            $jenis_transaksi,           // s (string)
            $data['jumlah'],            // d (double)
            $nama,                      // s (string)
            $bulan_periode_tanggal,     // s (string - Y-m-d)
            $metode_pembayaran,         // s (string)
            $bank_tujuan,               // s (string)
            $bukti_tunggal_path,        // s (string)
            $data['status'],            // s (string)
            $tanggal_bayar              // s (string - Y-m-d H:i:s dari tanggal_join)
        );

        if (!$stmt_pembayaran->execute()) {
            throw new Exception("Gagal menyimpan " . $data['jenis_simpanan'] . ": " . $stmt_pembayaran->error);
        }

        $pembayaran_id = $conn->insert_id;

        // LOG HISTORY: Pembayaran simpanan
        log_pembayaran_activity(
            $pembayaran_id,
            'create',
            "Mencatat pembayaran {$data['jenis_simpanan']} sebesar Rp " . number_format($data['jumlah'], 0, ',', '.') . " untuk anggota $nama ($no_anggota)",
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

    echo json_encode([
        'status' => 'success', 
        'message' => 'Pendaftaran anggota ' . $no_anggota . ' berhasil!',
        'no_anggota' => $no_anggota
    ]);

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

    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log("Error dalam pendaftaran: " . $e->getMessage());
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
