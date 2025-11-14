<?php
session_start();

// Pastikan hanya metode POST yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses ditolak.");
}

// Include file history log
require_once 'functions/history_log.php';

// Ambil semua data dari form
$id = $_POST['id'];
$nama = $_POST['nama'];
$nik = $_POST['nik'];
$npwp = $_POST['npwp'];
$alamat = $_POST['alamat'];
$rt = $_POST['rt'];
$rw = $_POST['rw'];
$pekerjaan = $_POST['pekerjaan'];

// Validasi sederhana (pastikan ID tidak kosong)
if (empty($id)) {
    die("Error: ID Anggota tidak valid.");
}

// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data lama untuk log
$old_data_query = $conn->prepare("SELECT nama, nik, npwp, alamat, rt, rw, pekerjaan FROM anggota WHERE id = ?");
$old_data_query->bind_param('i', $id);
$old_data_query->execute();
$old_data_result = $old_data_query->get_result();
$old_data = $old_data_result->fetch_assoc();
$old_data_query->close();

// Gunakan PREPARED STATEMENT untuk keamanan dari SQL Injection
$stmt = $conn->prepare(
    "UPDATE anggota SET 
        nama = ?, 
        nik = ?, 
        npwp = ?, 
        alamat = ?, 
        rt = ?, 
        rw = ?, 
        pekerjaan = ? 
    WHERE id = ?"
);

// Binding parameter ke query
// 'sssssssi' -> s = string, i = integer
$stmt->bind_param('sssssssi', $nama, $nik, $npwp, $alamat, $rt, $rw, $pekerjaan, $id);

// Eksekusi query
if ($stmt->execute()) {
    // LOG ACTIVITY: Update data anggota
    $description = "Mengupdate data anggota: $nama (ID: $id)";

    // Tambahkan detail perubahan jika ada data lama
    if ($old_data) {
        $changes = [];
        if ($old_data['nama'] != $nama)
            $changes[] = "nama";
        if ($old_data['nik'] != $nik)
            $changes[] = "NIK";
        if ($old_data['npwp'] != $npwp)
            $changes[] = "NPWP";
        if ($old_data['alamat'] != $alamat)
            $changes[] = "alamat";
        if ($old_data['rt'] != $rt)
            $changes[] = "RT";
        if ($old_data['rw'] != $rw)
            $changes[] = "RW";
        if ($old_data['pekerjaan'] != $pekerjaan)
            $changes[] = "pekerjaan";

        if (!empty($changes)) {
            $description .= " - Field yang diubah: " . implode(', ', $changes);
        }
    }

    log_activity(
        $_SESSION['user_id'] ?? 0,
        $_SESSION['role'] ?? 'system',
        'update_anggota',
        $description,
        'anggota',
        $id
    );

    // Jika berhasil, kembalikan pengguna ke halaman detail
    header("Location: detail_anggota.php?id=" . $id . "&status=sukses");
} else {
    // Jika gagal, tampilkan pesan error
    echo "Error: Gagal memperbarui data. " . $stmt->error;
}

// Tutup statement dan koneksi
$stmt->close();
$conn->close();
?>
