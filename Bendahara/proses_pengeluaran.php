<?php
// proses_pengeluaran.php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Function untuk handle upload file
function handleUpload($file, $folder, $prefix = '')
{
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File tidak valid. Code: ' . ($file['error'] ?? 'N/A')];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 5MB.'];
    }

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

    if ($action === 'ajukan_pengeluaran') {
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

        if (empty($tanggal) || $kategori_id <= 0 || empty($keterangan) || $jumlah <= 0 || empty($sumber_dana)) {
            throw new Exception("Semua field wajib diisi dengan benar.");
        }

        if (!isset($_FILES['bukti_file']) || $_FILES['bukti_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Bukti pengeluaran wajib diupload.");
        }

        // Cek saldo real-time berdasarkan sumber dana
        $saldo_real = getSaldoSumberDana($sumber_dana);
        if ($saldo_real < $jumlah) {
            throw new Exception("Saldo tidak mencukupi. Saldo $sumber_dana: Rp " . number_format($saldo_real, 0, ',', '.'));
        }

        // Upload bukti file
        $upload_result = handleUpload($_FILES['bukti_file'], 'bukti_pengeluaran', 'pengeluaran');
        if (isset($upload_result['error'])) {
            throw new Exception($upload_result['error']);
        }
        $bukti_path = $upload_result['path'];

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

            // CATATAN: Saldo TIDAK dikurangi di sini, karena masih menunggu approval
            // Saldo akan otomatis berkurang di perhitungan real-time setelah status 'approved'

            $conn->commit();

            echo json_encode([
                'status' => 'success',
                'message' => 'Pengajuan pengeluaran berhasil dikirim. Menunggu persetujuan Ketua.'
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } elseif ($action === 'update_status') {
        // Validasi role - hanya ketua yang bisa approve/reject
        if ($user_role !== 'ketua') {
            throw new Exception('Hanya ketua yang dapat menyetujui pengeluaran');
        }

        $pengeluaran_id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if ($pengeluaran_id <= 0 || !in_array($status, ['approved', 'rejected'])) {
            throw new Exception("Data tidak valid.");
        }

        // Ambil data pengeluaran yang akan di-update
        $result = $conn->query("
            SELECT jumlah, sumber_dana, status 
            FROM pengeluaran 
            WHERE id = $pengeluaran_id AND status = 'pending'
        ");

        if ($result->num_rows === 0) {
            throw new Exception('Pengajuan tidak ditemukan atau sudah diproses');
        }

        $pengeluaran = $result->fetch_assoc();

        // Validasi saldo jika status approved
        if ($status === 'approved') {
            $saldo_tersedia = getSaldoSumberDana($pengeluaran['sumber_dana']);

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

            // CATATAN: Tidak perlu update saldo manual
            // Saldo otomatis berkurang di perhitungan real-time karena status sudah 'approved'

            $conn->commit();

            $status_text = $status === 'approved' ? 'disetujui' : 'ditolak';
            echo json_encode([
                'status' => 'success',
                'message' => "Pengajuan pengeluaran berhasil $status_text"
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
        throw new Exception("Action tidak valid.");
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn))
        $conn->close();
}
?>