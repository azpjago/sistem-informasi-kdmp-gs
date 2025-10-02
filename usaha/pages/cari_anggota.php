<?php
// pages/cari_anggota.php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Tambahkan header JSON
header('Content-Type: application/json');

$keyword = $_GET['keyword'] ?? '';

// Validasi input
if (empty($keyword) || strlen($keyword) < 2) {
    echo json_encode([]);
    exit;
}

// Bersihkan keyword
$keyword = mysqli_real_escape_string($conn, $keyword);

$query = "SELECT id, no_anggota, nama, no_hp, alamat 
          FROM anggota 
          WHERE no_anggota LIKE '%$keyword%' 
             OR nama LIKE '%$keyword%' 
             OR no_hp LIKE '%$keyword%'
          ORDER BY nama ASC
          LIMIT 10";

$result = mysqli_query($conn, $query);
$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

echo json_encode($data);

// Pastikan tidak ada output setelah ini
exit;
?>