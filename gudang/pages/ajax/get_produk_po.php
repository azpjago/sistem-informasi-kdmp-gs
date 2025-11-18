<?php
require 'koneksi/koneksi.php';

$id_po = intval($_GET['id_po']);

$query = "SELECT i.id_item, sp.nama_produk, i.qty, i.satuan 
          FROM purchase_order_item i
          JOIN supplier_produk sp ON i.id_supplier_produk = sp.id_supplier_produk
          WHERE i.id_po = '$id_po'";
$result = $conn->query($query);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
