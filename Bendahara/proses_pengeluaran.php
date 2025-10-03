<?php
// proses_pengeluaran.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Function untuk handle upload file
function handleUpload($file, $folder, $prefix = '') {
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

try {
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
    if ($conn->connect_error) {
        throw new Exception('Koneksi database gagal: ' . $conn->connect_error);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_pengeluaran') {
        // Validasi input
        $tanggal = $_POST['tanggal'] ?? '';
        $kategori_id = intval($_POST['kategori_id'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');
        $jumlah = floatval($_POST['jumlah'] ?? 0);
        $rekening_id = intval($_POST['rekening_id'] ?? 0);

        if (empty($tanggal) || $kategori_id <= 0 || empty($keterangan) || $jumlah <= 0 || $rekening_id <= 0) {
            throw new Exception("Semua field wajib diisi dengan benar.");
        }

        if (!isset($_FILES['bukti_file']) || $_FILES['bukti_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Bukti pengeluaran wajib diupload.");
        }

        // Upload bukti file
        $upload_result = handleUpload($_FILES['bukti_file'], 'bukti_pengeluaran', 'pengeluaran');
        if (isset($upload_result['error'])) {
            throw new Exception($upload_result['error']);
        }
        $bukti_path = $upload_result['path'];

        // Cek saldo rekening
        $stmt_saldo = $conn->prepare("SELECT saldo_sekarang FROM rekening WHERE id = ?");
        $stmt_saldo->bind_param("i", $rekening_id);
        $stmt_saldo->execute();
        $saldo_result = $stmt_saldo->get_result();
        
        if ($saldo_result->num_rows === 0) {
            throw new Exception("Rekening tidak ditemukan.");
        }
        
        $saldo = $saldo_result->fetch_assoc()['saldo_sekarang'];
        if ($saldo < $jumlah) {
            throw new Exception("Saldo tidak mencukupi. Saldo tersedia: Rp " . number_format($saldo, 0, ',', '.'));
        }

        // Insert pengeluaran
        $stmt = $conn->prepare("INSERT INTO pengeluaran 
            (tanggal, kategori_id, keterangan, jumlah, rekening_id, bukti_file, created_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')");
        
        $user_id = 1; // Ganti dengan ID user yang login
        $stmt->bind_param("sisdisi", $tanggal, $kategori_id, $keterangan, $jumlah, $rekening_id, $bukti_path, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan pengeluaran: " . $stmt->error);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Pengeluaran berhasil disimpan dan menunggu approval!'
        ]);

    } else {
        throw new Exception("Action tidak valid.");
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) $conn->close();
}
?>