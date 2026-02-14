<?php
// update_pembayaran.php (DENGAN DEBUGGING LENGKAP)
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Include history log
require_once 'functions/history_log.php';

// DEBUGGING: Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DEBUGGING: Log semua input
error_log('========== DEBUG START ==========');
error_log('REQUEST METHOD: ' . $_SERVER["REQUEST_METHOD"]);
error_log('POST DATA: ' . print_r($_POST, true));
error_log('FILES DATA: ' . print_r($_FILES, true));
error_log('SESSION: ' . print_r($_SESSION, true));

// Koneksi database
require 'koneksi/koneksi.php';

// DEBUGGING: Cek koneksi database
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit();
}

// Helper untuk nama bulan
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['anggota_id'])) {

    $anggota_id = intval($_POST['anggota_id']);
    $tanggal_bayar = $_POST['tanggal_bayar'] ?? date('Y-m-d H:i:s');
    $metode = $_POST['metode'] ?? '';
    $bank_tujuan = ($metode === 'transfer') ? ($_POST['bank_tujuan'] ?? '') : null;
    $bukti_path = null;

    // DEBUGGING: Log data yang diproses
    error_log('anggota_id: ' . $anggota_id);
    error_log('metode: ' . $metode);
    error_log('bank_tujuan: ' . ($bank_tujuan ?? 'null'));

    // Validasi input
    if (empty($metode)) {
        echo json_encode(['status' => 'error', 'message' => 'Metode pembayaran wajib diisi.']);
        exit();
    }

    // Ambil data anggota
    $stmt_anggota = $conn->prepare("SELECT no_anggota, nama, simpanan_wajib, tanggal_jatuh_tempo FROM anggota WHERE id = ?");
    if (!$stmt_anggota) {
        error_log('Prepare statement error: ' . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Error database: ' . $conn->error]);
        exit();
    }
    
    $stmt_anggota->bind_param("i", $anggota_id);
    if (!$stmt_anggota->execute()) {
        error_log('Execute error: ' . $stmt_anggota->error);
        echo json_encode(['status' => 'error', 'message' => 'Error eksekusi query']);
        exit();
    }
    
    $anggota_data = $stmt_anggota->get_result()->fetch_assoc();
    $stmt_anggota->close();

    if (!$anggota_data) {
        error_log('Anggota tidak ditemukan untuk ID: ' . $anggota_id);
        echo json_encode(['status' => 'error', 'message' => 'Anggota tidak ditemukan.']);
        exit();
    }

    $no_anggota = $anggota_data['no_anggota'];
    $nama_anggota = $anggota_data['nama'];
    $jumlah = $anggota_data['simpanan_wajib'];
    $tanggal_jatuh_tempo = $anggota_data['tanggal_jatuh_tempo'];

    error_log('Data anggota ditemukan: ' . print_r($anggota_data, true));

    // Upload bukti pembayaran
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == UPLOAD_ERR_OK) {
        error_log('File upload detected: ' . $_FILES['bukti']['name']);
        
        if ($_FILES['bukti']['size'] > 10 * 1024 * 1024) { // 10MB
            echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 10MB.']);
            exit();
        }

        $upload_dir = 'bukti_bayar/';
        
        // Buat folder jika belum ada
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log('Gagal membuat folder: ' . $upload_dir);
                echo json_encode(['status' => 'error', 'message' => 'Gagal membuat folder upload.']);
                exit();
            }
            error_log('Folder created: ' . $upload_dir);
        }

        // Cek permission folder
        if (!is_writable($upload_dir)) {
            error_log('Folder tidak writable: ' . $upload_dir);
            echo json_encode(['status' => 'error', 'message' => 'Folder upload tidak dapat ditulis.']);
            exit();
        }

        // Validasi ekstensi
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $extension = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed)) {
            echo json_encode(['status' => 'error', 'message' => 'Tipe file harus JPG, PNG, GIF, atau PDF.']);
            exit();
        }

        // Generate nama file
        $file_name = 'Wajib_' . $no_anggota . '_' . date('Y-m-d_His') . '.' . $extension;
        $target_file = $upload_dir . $file_name;
        
        error_log('Target file: ' . $target_file);

        // Upload file
        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $target_file)) {
            $bukti_path = $target_file;
            error_log('File uploaded successfully: ' . $target_file);
        } else {
            $phpFileUploadErrors = [
                0 => 'Tidak ada error',
                1 => 'Ukuran file melebihi upload_max_filesize',
                2 => 'Ukuran file melebihi MAX_FILE_SIZE',
                3 => 'File hanya terupload sebagian',
                4 => 'Tidak ada file yang diupload',
                6 => 'Tidak ada temporary folder',
                7 => 'Gagal menulis file ke disk',
                8 => 'Ekstensi PHP menghentikan upload'
            ];
            
            $error_msg = $phpFileUploadErrors[$_FILES['bukti']['error']] ?? 'Unknown error';
            error_log('Move uploaded file failed: ' . $error_msg);
            echo json_encode(['status' => 'error', 'message' => 'Gagal upload: ' . $error_msg]);
            exit();
        }
    } else {
        $error_code = $_FILES['bukti']['error'] ?? UPLOAD_ERR_NO_FILE;
        error_log('File upload error: ' . $error_code);
        echo json_encode(['status' => 'error', 'message' => 'Bukti pembayaran wajib diupload. Error code: ' . $error_code]);
        exit();
    }

    // Generate ID Transaksi
    $result = $conn->query("SELECT MAX(id) as max_id FROM pembayaran");
    $next_id = 1;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $next_id = $row['max_id'] + 1;
    }
    $id_transaksi = 'TRX-WJB-' . str_pad($next_id, 7, '0', STR_PAD_LEFT);
    error_log('ID Transaksi: ' . $id_transaksi);

    // Generate bulan periode dari tanggal_jatuh_tempo
    try {
        $tanggal = new DateTime($tanggal_jatuh_tempo);
        $bulan = (int) $tanggal->format('n');
        $tahun = $tanggal->format('Y');
        $bulan_periode = $tanggal_jatuh_tempo;
        
        error_log('Bulan: ' . $bulan . ', Tahun: ' . $tahun);
    } catch (Exception $e) {
        error_log('Error parsing date: ' . $e->getMessage());
        $bulan = date('n');
        $tahun = date('Y');
        $bulan_periode = date('Y-m-d');
    }

    // Status pembayaran
    $status = "Pembayaran Simpanan wajib " . ($nama_bulan[$bulan] ?? 'Unknown') . " " . $tahun;
    error_log('Status: ' . $status);

    // Get user info for logging
    $user_id = $_SESSION['id'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'bendahara';

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // 1. Insert ke tabel pembayaran
        $stmt = $conn->prepare("INSERT INTO pembayaran (anggota_id, id_transaksi, jenis_simpanan, jenis_transaksi, jumlah, nama_anggota, tanggal_bayar, bulan_periode, metode, bank_tujuan, bukti, status) VALUES (?, ?, 'Simpanan Wajib', 'setor', ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception('Prepare statement error: ' . $conn->error);
        }
        
        $stmt->bind_param("isdsssssss", $anggota_id, $id_transaksi, $jumlah, $nama_anggota, $tanggal_bayar, $bulan_periode, $metode, $bank_tujuan, $bukti_path, $status);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute error: ' . $stmt->error);
        }
        
        $pembayaran_id = $conn->insert_id;
        $stmt->close();
        error_log('Pembayaran inserted with ID: ' . $pembayaran_id);

        // 2. Update tanggal jatuh tempo di tabel anggota (+1 bulan)
        $stmt_update = $conn->prepare("UPDATE anggota SET tanggal_jatuh_tempo = DATE_ADD(tanggal_jatuh_tempo, INTERVAL 1 MONTH) WHERE id = ?");
        if (!$stmt_update) {
            throw new Exception('Prepare update error: ' . $conn->error);
        }
        
        $stmt_update->bind_param("i", $anggota_id);
        if (!$stmt_update->execute()) {
            throw new Exception('Execute update error: ' . $stmt_update->error);
        }
        $stmt_update->close();
        error_log('Tanggal jatuh tempo updated');

        // 3. Tambahkan jumlah simpanan wajib ke saldo_total
        $stmt_saldo = $conn->prepare("UPDATE anggota SET saldo_total = saldo_total + ? WHERE id = ?");
        if (!$stmt_saldo) {
            throw new Exception('Prepare saldo error: ' . $conn->error);
        }
        
        $stmt_saldo->bind_param("di", $jumlah, $anggota_id);
        if (!$stmt_saldo->execute()) {
            throw new Exception('Execute saldo error: ' . $stmt_saldo->error);
        }
        $stmt_saldo->close();
        error_log('Saldo total updated');

        // LOG HISTORY
        if (function_exists('log_pembayaran_activity')) {
            log_pembayaran_activity(
                $pembayaran_id,
                'create',
                "Pembayaran simpanan wajib {$nama_bulan[$bulan]} $tahun sebesar Rp " . number_format($jumlah, 0, ',', '.') . " oleh $nama_anggota ($no_anggota)",
                $user_role
            );
            
            log_anggota_activity(
                $anggota_id,
                'update',
                "Memperbarui tanggal jatuh tempo dan menambah saldo total sebesar Rp " . number_format($jumlah, 0, ',', '.'),
                $user_role
            );
        } else {
            error_log('Warning: log_pembayaran_activity function not found');
        }

        $conn->commit();
        error_log('Transaction committed successfully');

        echo json_encode(['status' => 'success', 'message' => 'Pembayaran berhasil disimpan!']);

    } catch (Exception $e) {
        error_log('ERROR in transaction: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        $conn->rollback();
        error_log('Transaction rolled back');
        
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }

    $conn->close();
    error_log('========== DEBUG END ==========');

} else {
    error_log('Invalid request: ' . $_SERVER["REQUEST_METHOD"]);
    echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
}
?>
