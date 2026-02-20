<?php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$keyword = $_GET['keyword'] ?? '';

// Query dengan perhitungan stok real-time
$query = "SELECT 
            p.*, 
            ir.nama_produk as nama_inventory,
            ir.jumlah_tersedia as stok_tersedia,
            ir.satuan_kecil as satuan_inventory,
            -- Hitung stok produk berdasarkan konversi
            CASE 
                WHEN p.is_paket = 1 THEN 999
                WHEN ir.jumlah_tersedia IS NULL THEN 0
                ELSE FLOOR(ir.jumlah_tersedia / p.jumlah)
            END as stok_produk
          FROM produk p 
          LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
          WHERE p.status = 'aktif'";

// Filter produk dengan stok > 0 (kecuali paket)
$query .= " AND (p.is_paket = 1 OR (p.is_paket = 0 AND ir.jumlah_tersedia IS NOT NULL AND FLOOR(ir.jumlah_tersedia / p.jumlah) > 0))";

if (!empty($keyword)) {
    $keyword = mysqli_real_escape_string($conn, $keyword);
    $query .= " AND p.nama_produk LIKE '%$keyword%'";
}

$query .= " ORDER BY p.nama_produk";

$result = mysqli_query($conn, $query);
$produk = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Hitung stok berdasarkan konversi
    if ($row['is_paket'] == 1) {
        $stok_tersedia = 999;
        $stok_info = 'Paket (Cek komponen)';
        $stok_class = 'text-info';
    } else {
        // Hitung stok produk = stok_inventory / konversi
        $stok_inventory = floatval($row['stok_tersedia'] ?? 0);
        $konversi = floatval($row['jumlah'] ?? 1);
        $stok_tersedia = floor($stok_inventory / $konversi);
        
        // Tentukan info stok berdasarkan hasil perhitungan
        if ($stok_tersedia <= 0) {
            $stok_info = 'Stok Habis';
            $stok_class = 'text-danger';
        } else if ($stok_tersedia <= 5) {
            $stok_info = "Stok: $stok_tersedia " . $row['satuan'] . " (Menipis)";
            $stok_class = 'text-warning';
        } else {
            $stok_info = "Stok: $stok_tersedia " . $row['satuan'];
            $stok_class = 'text-success';
        }
    }
    
    // Format data untuk dikirim ke client
    $produk[] = [
        'id_produk' => $row['id_produk'],
        'nama_produk' => $row['nama_produk'],
        'harga' => $row['harga'],
        'satuan' => $row['satuan'] ?? $row['satuan_inventory'] ?? '-',
        'is_paket' => $row['is_paket'],
        'jumlah' => $stok_tersedia, // Stok yang sudah dihitung
        'stok_info' => $stok_info,
        'stok_class' => $stok_class,
        'id_inventory' => $row['id_inventory'],
        'konversi' => $row['jumlah'] ?? 1
    ];
}

header('Content-Type: application/json');
echo json_encode($produk);
?>
