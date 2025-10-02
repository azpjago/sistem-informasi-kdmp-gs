<?php
session_start();
include 'db.php';

$id_anggota = $_SESSION['id'];
$produk = $_POST['produk']; // Array produk [{id_produk, jumlah, subtotal}]
$total_harga = $_POST['total'];

$tanggal_pesan = date("Y-m-d H:i:s");
$batas_batal = date("Y-m-d H:i:s", strtotime("+30 minutes"));
$jadwal_kirim = $_POST['jadwal']; // 09:00 / 12:00 / 14:00

mysqli_query($conn, "INSERT INTO pemesanan(id_anggota, tanggal_pesan, total_harga, status, batas_batal, jadwal_kirim)
VALUES ('$id_anggota', '$tanggal_pesan', '$total_harga', 'Menunggu', '$batas_batal', '$jadwal_kirim')");

$id_pemesanan = mysqli_insert_id($conn);

foreach ($produk as $item) {
    $id_produk = $item['id_produk'];
    $jumlah = $item['jumlah'];
    $subtotal = $item['subtotal'];
    mysqli_query($conn, "INSERT INTO pemesanan_detail(id_pemesanan, id_produk, jumlah, subtotal)
    VALUES ('$id_pemesanan', '$id_produk', '$jumlah', '$subtotal')");
}

echo json_encode(['status' => 'sukses']);
?>