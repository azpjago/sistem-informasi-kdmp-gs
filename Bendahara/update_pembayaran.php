<?php
// update_pembayaran.php (DENGAN LOG HISTORY)
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Include history log
require_once 'Bendahara/functions/history_log.php';

error_log('Received anggota_id: ' . $_POST['anggota_id']);
error_log('POST data: ' . print_r($_POST, true));

// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
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
    $tanggal_bayar = date('Y-m-d H:i:s');
    $metode = $_POST['metode'];
    $bank_tujuan = ($metode === 'transfer') ? ($_POST['bank_tujuan'] ?? '') : null;
    $bukti_path = null;

    // Validasi input
    if (empty($tanggal_bayar) || empty($metode)) {
        echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
        exit();
    }

    // Ambil data anggota
    $stmt_anggota = $conn->prepare("SELECT no_anggota, nama, simpanan_wajib, tanggal_jatuh_tempo FROM anggota WHERE id = ?");
    $stmt_anggota->bind_param("i", $anggota_id);
    $stmt_anggota->execute();
    $anggota_data = $stmt_anggota->get_result()->fetch_assoc();

    if (!$anggota_data) {
        echo json_encode(['status' => 'error', 'message' => 'Anggota tidak ditemukan.']);
        exit();
    }

    $no_anggota = $anggota_data['no_anggota'];
    $nama_anggota = $anggota_data['nama'];
    $jumlah = $anggota_data['simpanan_wajib'];
    $tanggal_jatuh_tempo = $anggota_data['tanggal_jatuh_tempo'];

    // Upload bukti pembayaran
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
        if ($_FILES['bukti']['size'] > 10485760) {
            echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 10MB.']);
            exit();
        }

        $upload_dir = 'bukti_bayar/';
        $extension = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
        $file_name = 'Wajib_' . $no_anggota . '_' . date('Y-m-d_His') . '.' . $extension;
        $target_file = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['bukti']['tmp_name'], $target_file)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal upload bukti.']);
            exit();
        }
        $bukti_path = $target_file;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Bukti pembayaran wajib diupload.']);
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

    // Generate bulan periode dari tanggal_bayar
    $tanggal = new DateTime($tanggal_jatuh_tempo);
    $bulan = (int) $tanggal->format('n');
    $tahun = $tanggal->format('Y');
    $bulan_periode = $tanggal_jatuh_tempo;

    // Status pembayaran
    $status = " Pembayaran Simpanan wajib " . $nama_bulan[$bulan] . " " . $tahun;

    // Get user info for logging
    $user_id = $_SESSION['id'] ?? 0;
    $user_role = $_SESSION['role'] ?? 'bendahara';

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // 1. Insert ke tabel pembayaran
        $stmt = $conn->prepare("INSERT INTO pembayaran (anggota_id, id_transaksi, jenis_simpanan, jenis_transaksi, jumlah, nama_anggota, tanggal_bayar, bulan_periode, metode, bank_tujuan, bukti, status) VALUES (?, ?, 'Simpanan Wajib', 'setor', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdsssssss", $anggota_id, $id_transaksi, $jumlah, $nama_anggota, $tanggal_bayar, $bulan_periode, $metode, $bank_tujuan, $bukti_path, $status);
        $stmt->execute();
        $pembayaran_id = $conn->insert_id;
        $stmt->close();

        // 2. Update tanggal jatuh tempo di tabel anggota (+1 bulan)
        $stmt_update = $conn->prepare("UPDATE anggota SET tanggal_jatuh_tempo = DATE_ADD(tanggal_jatuh_tempo, INTERVAL 1 MONTH) WHERE id = ?");
        $stmt_update->bind_param("i", $anggota_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 3. Tambahkan jumlah simpanan wajib ke saldo_total
        $stmt_saldo = $conn->prepare("UPDATE anggota SET saldo_total = saldo_total + ? WHERE id = ?");
        $stmt_saldo->bind_param("di", $jumlah, $anggota_id);
        $stmt_saldo->execute();
        $stmt_saldo->close();

        // LOG HISTORY: Pembayaran simpanan wajib
        log_pembayaran_activity(
            $pembayaran_id,
            'create',
            "Pembayaran simpanan wajib $nama_bulan[$bulan] $tahun sebesar Rp " . number_format($jumlah, 0, ',', '.') . " oleh $nama_anggota ($no_anggota)",
            $user_role
        );

        // LOG HISTORY: Update anggota
        log_anggota_activity(
            $anggota_id,
            'update',
            "Memperbarui tanggal jatuh tempo dan menambah saldo total sebesar Rp " . number_format($jumlah, 0, ',', '.'),
            $user_role
        );

        $conn->commit();

        // LOG HISTORY: Success final
        log_pembayaran_activity(
            $pembayaran_id,
            'complete',
            "Pembayaran simpanan wajib $nama_anggota ($no_anggota) berhasil diproses",
            $user_role
        );

        echo json_encode(['status' => 'success', 'message' => 'Pembayaran berhasil disimpan!']);

    } catch (Exception $e) {
        // LOG HISTORY: Error
        if (isset($pembayaran_id)) {
            log_pembayaran_activity(
                $pembayaran_id,
                'error',
                "Error pembayaran: " . $e->getMessage(),
                $user_role
            );
        }

        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }

    $conn->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
}
?>