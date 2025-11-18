<?php
// pages/cek_stok.php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');

header('Content-Type: application/json');

if (isset($_GET['id_produk'])) {
    $id_produk = intval($_GET['id_produk']);
    
    $query = "SELECT p.*, ir.jumlah_tersedia as stok_tersedia
              FROM produk p 
              LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
              WHERE p.id_produk = $id_produk";
    
    $result = $conn->query($query);
    $produk = $result->fetch_assoc();
    
    if ($produk) {
        if ($produk['is_paket'] == 1) {
            echo json_encode(['stok' => 999, 'type' => 'paket']); // Stok tinggi untuk paket
        } else {
            $stok_tersedia = floatval($produk['stok_tersedia']);
            $konversi = floatval($produk['jumlah']);
            $stok_produk = floor($stok_tersedia / $konversi);
            
            echo json_encode(['stok' => $stok_produk, 'type' => 'eceran']);
        }
    } else {
        echo json_encode(['error' => 'Produk tidak ditemukan']);
    }
} else {
    echo json_encode(['error' => 'ID produk tidak diberikan']);
}
?>
