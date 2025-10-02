<?php
if (!isset($_SESSION['id'])) {
    header("Location: index.html");
    exit();
}
?>
<!-- faktur.html -->
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h4 class="text-center mb-3">Faktur Pemesanan</h4>
        <div class="border p-3 mb-3">
            <p><strong>Nama:</strong> Agus Santoso</p>
            <p><strong>Alamat:</strong> RT 05 / RW 03, Desa Maju</p>
            <p><strong>Tanggal Pesan:</strong> 07-09-2025</p>
            <p><strong>Jadwal Kirim:</strong> 09:00</p>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Beras 5kg</td>
                    <td>1</td>
                    <td>Rp 60.000</td>
                </tr>
                <tr>
                    <td>Minyak Goreng 2L</td>
                    <td>1</td>
                    <td>Rp 28.000</td>
                </tr>
                <tr>
                    <th colspan="2">Total</th>
                    <th>Rp 88.000</th>
                </tr>
            </tbody>
        </table>
        <button onclick="window.print()" class="btn btn-primary w-100">Cetak Faktur</button>
    </div>
</body>

</html>