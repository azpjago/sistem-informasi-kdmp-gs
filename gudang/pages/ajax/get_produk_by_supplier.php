<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses ditolak');
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

if (isset($_GET['id_supplier'])) {
    $id_supplier = $conn->real_escape_string($_GET['id_supplier']);

    $query = "SELECT * FROM supplier_produk 
              WHERE id_supplier = '$id_supplier' AND status = 'active' 
              ORDER BY nama_produk";
    $result = $conn->query($query);

    $options = '';

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $options .= "<option value='{$row['id_supplier_produk']}' 
                           data-harga='{$row['harga_beli']}' 
                           data-satuan='{$row['satuan_besar']}'>
                           {$row['nama_produk']} - Rp " . number_format($row['harga_beli'], 0, ',', '.') . "
                        </option>";
        }
    } else {
        $options .= '<option value="" disabled>Tidak ada produk tersedia</option>';
    }

    echo $options;
} else {
    echo '<option value="">Pilih Supplier terlebih dahulu</option>';
}

$conn->close();
?>