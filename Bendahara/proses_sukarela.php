<?php
// SET header ke JSON. Ini wajib.
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');
require_once 'saldo_helper.php';
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['anggota_id'])) {

    // Ambil dan bersihkan data dari form
    $anggota_id = intval($_POST['anggota_id']);
    $no_anggota = trim($_POST['no_anggota'] ?? '');
    $jenis_simpanan = trim($_POST['jenis_simpanan'] ?? '');
    $jenis_transaksi = trim($_POST['jenis_transaksi'] ?? '');
    $jumlah = (float) ($_POST['jumlah'] ?? 0);
    $metode = trim($_POST['metode'] ?? '');
    $bank_tujuan = ($metode === 'transfer') ? ($_POST['bank_tujuan'] ?? '') : null;
    $keterangan = trim($_POST['keterangan'] ?? null);
    $tanggal_transaksi = $_POST['tanggal_transaksi'] . ' ' . date('H:i:s');

    if (empty($jenis_transaksi) || empty($jenis_simpanan) || $jumlah <= 0) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi dan jumlah harus positif.']);
        exit();
    }

    // Validasi untuk tarik
    if ($jenis_transaksi === 'tarik') {
        $stmt_cek_saldo = $conn->prepare("SELECT saldo_sukarela FROM anggota WHERE id = ?");
        $stmt_cek_saldo->bind_param("i", $anggota_id);
        $stmt_cek_saldo->execute();
        $result_saldo = $stmt_cek_saldo->get_result();
        $current_saldo = $result_saldo->num_rows > 0 ? (float) $result_saldo->fetch_assoc()['saldo_sukarela'] : 0;
        $stmt_cek_saldo->close();

        if ($current_saldo < $jumlah) {
            $pesan_error = "Maaf, saldo tidak mencukupi. Saldo tersedia: Rp " . number_format($current_saldo, 0, ',', '.');
            echo json_encode(['success' => false, 'message' => $pesan_error]);
            exit();
        }
    }

    // Handle file upload
    $bukti_path = null;
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        $file_name = 'Bukti bayar Sukarela_' . $jenis_transaksi . preg_replace('/[^A-Za-z0-9\-]/', '_', $no_anggota) . '_' . date('Ymd_His') . '.' . $file_ext;
        $upload_dir = 'bukti_bayar/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $target_file)) {
            $bukti_path = $target_file;
        }
    }

    $conn->begin_transaction();
    try {
        // ================== PERBAIKAN LOGIKA ID TRANSAKSI (GLOBAL) ==================
        $stmt_id = $conn->prepare("SELECT id_transaksi FROM pembayaran WHERE id_transaksi LIKE 'TRX-%' ORDER BY id DESC LIMIT 1");
        $stmt_id->execute();
        $result_id = $stmt_id->get_result();
        $max_id_row = $result_id->fetch_assoc();
        $next_id = 1;
        if ($max_id_row && preg_match('/(\d+)$/', $max_id_row['id_transaksi'], $matches)) {
            $next_id = (int) $matches[1] + 1;
        }
        // Prefix tetap SKR karena ini adalah proses sukarela, tapi nomornya melanjutkan dari yang terakhir
        $id_transaksi = 'TRX-SKR-' . str_pad($next_id, 7, '0', STR_PAD_LEFT);
        // ================== AKHIR PERBAIKAN ==================

        if ($jenis_simpanan === 'Simpanan Sukarela') {
            // Update saldo_sukarela
            $saldo_sql = ($jenis_transaksi === 'setor')
                ? "UPDATE anggota SET saldo_sukarela = saldo_sukarela + ? WHERE id = ?"
                : "UPDATE anggota SET saldo_sukarela = saldo_sukarela - ? WHERE id = ?";
            $stmt_update = $conn->prepare($saldo_sql);
            $stmt_update->bind_param("di", $jumlah, $anggota_id);
            $stmt_update->execute();
            $stmt_update->close();

            // BARU: Tambahkan logic untuk mengupdate saldo_total
            $saldo_total_sql = ($jenis_transaksi === 'setor')
                ? "UPDATE anggota SET saldo_total = saldo_total + ? WHERE id = ?"
                : "UPDATE anggota SET saldo_total = saldo_total - ? WHERE id = ?";
            $stmt_update_total = $conn->prepare($saldo_total_sql);
            $stmt_update_total->bind_param("di", $jumlah, $anggota_id);
            $stmt_update_total->execute();
            $stmt_update_total->close();
        }

        $stmt_nama = $conn->prepare("SELECT nama FROM anggota WHERE id = ?");
        $stmt_nama->bind_param("i", $anggota_id);
        $stmt_nama->execute();
        $nama_anggota = $stmt_nama->get_result()->fetch_assoc()['nama'] ?? 'N/A';
        $stmt_nama->close();

        $bulan_periode = null;
        $status = ucfirst($jenis_transaksi) . " " . $jenis_simpanan . " oleh " . $nama_anggota . " tanggal " . date('d-m-Y', strtotime($tanggal_transaksi));

        $stmt_insert = $conn->prepare("INSERT INTO pembayaran 
    (id_transaksi, anggota_id, nama_anggota, jenis_simpanan, jenis_transaksi, jumlah, bulan_periode, 
     tanggal_bayar, metode, bank_tujuan, bukti, status, keterangan) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt_insert->bind_param(
            "sisssdsssssss",
            $id_transaksi,
            $anggota_id,
            $nama_anggota,
            $jenis_simpanan,
            $jenis_transaksi,
            $jumlah,
            $bulan_periode,
            $tanggal_transaksi,
            $metode,
            $bank_tujuan,
            $bukti_path,
            $status,
            $keterangan
        );
        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal mencatat transaksi: " . $stmt_insert->error);
        }
        $stmt_insert->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Transaksi berhasil diproses!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Request tidak valid.']);
}
exit();
?>