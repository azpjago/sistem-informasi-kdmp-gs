<?php
// proses_pengeluaran.php - VERSION WITH DEBUGGING
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log semua data yang diterima
error_log("=== PROSES PENGELUARAN DIMULAI ===");
error_log("POST DATA: " . print_r($_POST, true));
error_log("FILES DATA: " . print_r($_FILES, true));
error_log("SESSION DATA: " . print_r($_SESSION, true));

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Include history log
require_once 'functions/history_log.php';

// Function untuk handle upload file
function handleUpload($file, $folder, $prefix = '')
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File tidak valid. Code: ' . ($file['error'] ?? 'N/A')];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 5MB.'];
    }

    // Buat folder jika belum ada
    if (!is_dir($folder) && !mkdir($folder, 0777, true)) {
        return ['error' => 'Gagal membuat direktori.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extension, $allowed_extensions)) {
        return ['error' => 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.'];
    }

    $fileName = ($prefix ? $prefix . '_' : '') . uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $folder . '/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath];
    }

    return ['error' => 'Gagal memindahkan file. Periksa izin folder.'];
}

// FUNGSI HITUNG SALDO REAL-TIME (tanpa tabel rekening)
function hitungSaldoKasTunai()
{
    global $conn;
    $result = $conn->query("
        SELECT (
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela')
            +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'cash')
            +
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'cash')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
        ) as saldo_kas
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_kas'] ?? 0;
}

function hitungSaldoBank($nama_bank)
{
    global $conn;
    $result = $conn->query("
        SELECT (
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = '$nama_bank'
                AND jenis_simpanan = 'Simpanan Sukarela')
                +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'transfer'
             AND p.bank_tujuan = '$nama_bank')
            +
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'transfer' AND bank_tujuan = '$nama_bank')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
        ) as saldo_bank
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_bank'] ?? 0;
}

function getSaldoSumberDana($sumber_dana)
{
    if ($sumber_dana === 'Kas Tunai') {
        return hitungSaldoKasTunai();
    } else {
        return hitungSaldoBank($sumber_dana);
    }
}

try {
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error) {
        throw new Exception('Koneksi database gagal: ' . $conn->connect_error);
    }

    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['id'] ?? 0;
    $user_role = $_SESSION['role'] ?? '';

    error_log("ACTION: $action, USER ROLE: $user_role, USER ID: $user_id");

    if ($action === 'ajukan_pengeluaran') {
        error_log("PROSES: Mengajukan pengeluaran");

        // Validasi role - hanya bendahara yang bisa ajukan
        if ($user_role !== 'bendahara') {
            throw new Exception('Hanya bendahara yang dapat mengajukan pengeluaran');
        }

        // Validasi input
        $tanggal = $_POST['tanggal'] ?? '';
        $kategori_id = intval($_POST['kategori_id'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');
        $jumlah = floatval($_POST['jumlah'] ?? 0);
        $sumber_dana = trim($_POST['sumber_dana'] ?? '');

        error_log("DATA: Tanggal=$tanggal, Kategori=$kategori_id, Keterangan=$keterangan, Jumlah=$jumlah, Sumber=$sumber_dana");

        if (empty($tanggal) || $kategori_id <= 0 || empty($keterangan) || $jumlah <= 0 || empty($sumber_dana)) {
            throw new Exception("Semua field wajib diisi dengan benar.");
        }

        if (!isset($_FILES['bukti_file']) || $_FILES['bukti_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Bukti pengeluaran wajib diupload.");
        }

        // Cek saldo real-time berdasarkan sumber dana
        $saldo_real = getSaldoSumberDana($sumber_dana);
        error_log("SALDO REAL: $sumber_dana = Rp " . number_format($saldo_real, 0, ',', '.'));

        if ($saldo_real < $jumlah) {
            throw new Exception("Saldo tidak mencukupi. Saldo $sumber_dana: Rp " . number_format($saldo_real, 0, ',', '.'));
        }

        // Upload bukti file
        $upload_result = handleUpload($_FILES['bukti_file'], '../uploads/pengeluaran', 'pengeluaran');
        if (isset($upload_result['error'])) {
            throw new Exception($upload_result['error']);
        }
        $bukti_path = $upload_result['path'];
        error_log("BUKTI UPLOADED: $bukti_path");

        $conn->begin_transaction();

        try {
            // Insert pengeluaran dengan status 'pending' (menunggu approval)
            $stmt = $conn->prepare("INSERT INTO pengeluaran 
                (tanggal, kategori_id, keterangan, jumlah, sumber_dana, bukti_file, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");

            $stmt->bind_param("sisdssi", $tanggal, $kategori_id, $keterangan, $jumlah, $sumber_dana, $bukti_path, $user_id);

            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan pengajuan pengeluaran: " . $stmt->error);
            }

            $pengeluaran_id = $conn->insert_id;
            error_log("PENGELUARAN ID: $pengeluaran_id");

            // LOG HISTORY: Pengajuan pengeluaran baru
            error_log("=== MEMULAI LOG HISTORY PENGELUARAN ===");
            $log_result = log_pengeluaran_activity(
                $pengeluaran_id,
                'create',
                "Mengajukan pengeluaran baru: $keterangan sebesar Rp " . number_format($jumlah, 0, ',', '.') . " dari $sumber_dana",
                $user_role
            );

            if ($log_result) {
                error_log("SUKSES: Log history pengeluaran berhasil dengan ID: $log_result");
            } else {
                error_log("GAGAL: Log history pengeluaran gagal");
            }

            $conn->commit();
            error_log("=== TRANSAKSI COMMIT BERHASIL ===");

            echo json_encode([
                'status' => 'success',
                'message' => 'Pengajuan pengeluaran berhasil dikirim. Menunggu persetujuan Ketua.'
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } elseif ($action === 'update_status') {
        error_log("PROSES: Update status pengeluaran");

        // Validasi role - hanya ketua yang bisa approve/reject
        if ($user_role !== 'ketua') {
            throw new Exception('Hanya ketua yang dapat menyetujui pengeluaran');
        }

        $pengeluaran_id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        error_log("UPDATE STATUS: ID=$pengeluaran_id, Status=$status, Reason=$reason");

        if ($pengeluaran_id <= 0 || !in_array($status, ['approved', 'rejected'])) {
            throw new Exception("Data tidak valid.");
        }

        // Ambil data pengeluaran yang akan di-update
        $result = $conn->query("
            SELECT jumlah, sumber_dana, status, keterangan 
            FROM pengeluaran 
            WHERE id = $pengeluaran_id AND status = 'pending'
        ");

        if ($result->num_rows === 0) {
            throw new Exception('Pengajuan tidak ditemukan atau sudah diproses');
        }

        $pengeluaran = $result->fetch_assoc();
        $old_status = $pengeluaran['status'];
        error_log("DATA PENGELUARAN: Jumlah=" . $pengeluaran['jumlah'] . ", Sumber=" . $pengeluaran['sumber_dana'] . ", Old Status=$old_status");

        // Validasi saldo jika status approved
        if ($status === 'approved') {
            $saldo_tersedia = getSaldoSumberDana($pengeluaran['sumber_dana']);
            error_log("SALDO TERSEDIA: " . $pengeluaran['sumber_dana'] . " = Rp " . number_format($saldo_tersedia, 0, ',', '.'));

            if ($pengeluaran['jumlah'] > $saldo_tersedia) {
                throw new Exception("Saldo tidak mencukupi. Saldo {$pengeluaran['sumber_dana']}: Rp " . number_format($saldo_tersedia, 0, ',', '.'));
            }
        }

        $conn->begin_transaction();

        try {
            // Update status pengeluaran
            $stmt = $conn->prepare("
                UPDATE pengeluaran 
                SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sisi", $status, $user_id, $reason, $pengeluaran_id);

            if (!$stmt->execute()) {
                throw new Exception('Gagal update status: ' . $stmt->error);
            }

            // LOG HISTORY: Perubahan status pengeluaran
            error_log("=== MEMULAI LOG STATUS CHANGE ===");
            $log_status_result = log_status_change(
                'pengeluaran',
                $pengeluaran_id,
                $old_status,
                $status,
                $user_role
            );

            if ($log_status_result) {
                error_log("SUKSES: Log status change berhasil dengan ID: $log_status_result");
            } else {
                error_log("GAGAL: Log status change gagal");
            }

            // LOG HISTORY: Detail approval/rejection
            $action_text = $status === 'approved' ? 'approved' : 'rejected';
            $reason_text = $reason ? " dengan alasan: $reason" : "";
            error_log("=== MEMULAI LOG PENGELUARAN ACTIVITY ===");
            $log_activity_result = log_pengeluaran_activity(
                $pengeluaran_id,
                'status_change',
                "$action_text pengeluaran: {$pengeluaran['keterangan']} sebesar Rp " . number_format($pengeluaran['jumlah'], 0, ',', '.') . $reason_text,
                $user_role
            );

            if ($log_activity_result) {
                error_log("SUKSES: Log pengeluaran activity berhasil dengan ID: $log_activity_result");
            } else {
                error_log("GAGAL: Log pengeluaran activity gagal");
            }

            $conn->commit();
            error_log("=== UPDATE STATUS COMMIT BERHASIL ===");

            $status_text = $status === 'approved' ? 'approved' : 'rejected';
            echo json_encode([
                'status' => 'success',
                'message' => "Pengajuan pengeluaran berhasil $status_text"
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
        error_log("ERROR: Action tidak valid - '$action'");
        throw new Exception("Action tidak valid: '$action'");
    }

} catch (Exception $e) {
    error_log("=== ERROR TERJADI: " . $e->getMessage() . " ===");

    // LOG HISTORY: Error
    if (isset($pengeluaran_id)) {
        log_pengeluaran_activity(
            $pengeluaran_id,
            'error',
            "Error: " . $e->getMessage(),
            $user_role ?? 'system'
        );
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn))
        $conn->close();
}

error_log("=== PROSES PENGELUARAN SELESAI ===");
?>