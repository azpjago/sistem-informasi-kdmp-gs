<?php
session_start();
if ($_SESSION['role'] !== 'ketua') exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
$id = intval($_GET['id']);

$pemesanan = $conn->query("
    SELECT p.*, a.nama as nama_anggota, a.no_hp, a.alamat, a.no_anggota
    FROM pemesanan p 
    LEFT JOIN anggota a ON p.id_anggota = a.id 
    WHERE p.id_pemesanan = $id
")->fetch_assoc();

$detail = $conn->query("
    SELECT pd.*, pr.nama_produk, pr.harga
    FROM pemesanan_detail pd 
    LEFT JOIN produk pr ON pd.id_produk = pr.id_produk 
    WHERE pd.id_pemesanan = $id
");
?>

<div class="row">
    <div class="col-md-6">
        <h6>Info Pemesan</h6>
        <table class="table table-sm">
            <tr><td><strong>Nama</strong></td><td><?= $pemesanan['nama_anggota'] ?></td></tr>
            <tr><td><strong>No. Anggota</strong></td><td><?= $pemesanan['no_anggota'] ?></td></tr>
            <tr><td><strong>No. HP</strong></td><td><?= $pemesanan['no_hp'] ?></td></tr>
            <tr><td><strong>Alamat</strong></td><td><?= $pemesanan['alamat'] ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Info Pemesanan</h6>
        <table class="table table-sm">
            <tr><td><strong>No. Order</strong></td><td>#<?= $pemesanan['id_pemesanan'] ?></td></tr>
            <tr><td><strong>Tanggal</strong></td><td><?= date('d/m/Y H:i', strtotime($pemesanan['tanggal_pesan'])) ?></td></tr>
            <tr><td><strong>Metode</strong></td><td><?= $pemesanan['metode'] ?></td></tr>
            <tr><td><strong>Status</strong></td><td><?= $pemesanan['status'] ?></td></tr>
            <tr><td><strong>Total</strong></td><td>Rp <?= number_format($pemesanan['total_harga'], 0, ',', '.') ?></td></tr>
        </table>
    </div>
</div>

<h6 class="mt-3">Detail Produk</h6>
<table class="table table-sm table-bordered">
    <thead class="table-light">
        <tr>
            <th>Produk</th>
            <th>Harga</th>
            <th>Qty</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($item = $detail->fetch_assoc()): ?>
        <tr>
            <td><?= $item['nama_produk'] ?></td>
            <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
            <td><?= $item['jumlah'] ?></td>
            <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>