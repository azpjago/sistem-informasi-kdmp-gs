<?php
// Pastikan hanya metode POST yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses ditolak.");
}

// Ambil semua data dari form
$id         = $_POST['id'];
$nama       = $_POST['nama'];
$nik        = $_POST['nik'];
$npwp       = $_POST['npwp'];
$alamat     = $_POST['alamat'];
$rt         = $_POST['rt'];
$rw         = $_POST['rw'];
$pekerjaan  = $_POST['pekerjaan'];

// Validasi sederhana (pastikan ID tidak kosong)
if (empty($id)) {
    die("Error: ID Anggota tidak valid.");
}

// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

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