<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses ditolak');
}

require 'koneksi/koneksi.php';

if (isset($_GET['id_supplier'])) {
    $id_supplier = $conn->real_escape_string($_GET['id_supplier']);

    $query = "SELECT * FROM supplier_produk 
              WHERE id_supplier = '$id_supplier' AND status = 'active' 
              ORDER BY nama_produk";
    $result = $conn->query($query);

    $options = '<option value="">Pilih Produk</option>';

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // DEBUG: Log data untuk memastikan nilai isi terbaca
            error_log("Product: {$row['nama_produk']}, Isi: {$row['isi']}, Satuan: {$row['satuan_besar']}");

            // Pastikan isi memiliki nilai default jika NULL
            $isi = !empty($row['isi']) ? $row['isi'] : 1;
            $satuan_besar = !empty($row['satuan_besar']) ? $row['satuan_besar'] : 'Pcs';
            $harga_beli = !empty($row['harga_beli']) ? $row['harga_beli'] : 0;

            $options .= "<option value='{$row['id_supplier_produk']}' 
                           data-harga='{$harga_beli}' 
                           data-satuan='{$satuan_besar}'
                           data-isi='{$isi}'>
                           {$row['nama_produk']} - Rp " . number_format($harga_beli, 0, ',', '.') . " / {$satuan_besar}
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
