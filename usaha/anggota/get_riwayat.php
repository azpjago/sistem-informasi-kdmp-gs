<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$id_anggota = $_SESSION['id'];
$query = mysqli_query($conn, "SELECT * FROM pemesanan WHERE id_anggota='$id_anggota' ORDER BY tanggal_pesan DESC");

$riwayat = [];
while ($row = mysqli_fetch_assoc($query)) {
    $id_p = $row['id_pemesanan'];
    $detail = mysqli_query($conn, "SELECT pd.*, pr.nama_produk 
                                   FROM pemesanan_detail pd 
                                   JOIN produk pr ON pd.id_produk = pr.id_produk
                                   WHERE id_pemesanan='$id_p'");
    $items = [];
    while ($d = mysqli_fetch_assoc($detail)) {
        $items[] = $d;
    }

    $row['items'] = $items;
    $riwayat[] = $row;
}

echo json_encode($riwayat);
?>