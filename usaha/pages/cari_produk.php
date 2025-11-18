<?php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$keyword = $_GET['keyword'] ?? '';

$query = "SELECT p.*, 
                 ir.nama_produk as nama_inventory,
                 ir.jumlah_tersedia as stok_tersedia,
                 ir.satuan_kecil as satuan_inventory
          FROM produk p 
          LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
          WHERE p.status = 'aktif'";

if (!empty($keyword)) {
    $query .= " AND p.nama_produk LIKE '%$keyword%'";
}

$query .= " ORDER BY p.nama_produk";

$result = mysqli_query($conn, $query);
$produk = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Hitung stok info seperti sebelumnya
    if ($row['is_paket'] == 1) {
        $stok_info = 'Paket';
    } else if ($row['stok_tersedia'] !== null) {
        $stok_tersedia = floatval($row['stok_tersedia']);
        $konversi = floatval($row['jumlah']);
        $stok_produk = floor($stok_tersedia / $konversi);
        $stok_info = $stok_produk . ' ' . $row['satuan'];
    } else {
        $stok_info = 'Stok Tidak Tersedia';
    }
    
    $row['stok_info'] = $stok_info;
    $produk[] = $row;
}

header('Content-Type: application/json');
echo json_encode($produk);
?>
