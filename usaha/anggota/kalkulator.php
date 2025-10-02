<?php
if (!isset($_SESSION['id'])) {
    header("Location: index.html");
    exit();
}
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);
?>
<!-- kalkulator.html -->
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulator Pinjaman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h4 class="mb-3">Kalkulator Pinjaman</h4>
        <form id="calc-form">
            <div class="mb-3">
                <label for="simpanan" class="form-label">Total Simpanan Wajib (Rp)</label>
                <input type="number" class="form-control" id="simpanan" required>
            </div>
            <div class="mb-3">
                <label for="tenor" class="form-label">Tenor (bulan)</label>
                <input type="number" class="form-control" id="tenor" max="10" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Hitung</button>
        </form>


        <div id="hasil" class="mt-4 d-none">
            <h5>Hasil:</h5>
            <p><strong>Pinjaman Maks:</strong> <span id="pinjaman"></span></p>
            <p><strong>Angsuran per Bulan:</strong> <span id="angsuran"></span></p>
            <p><strong>Total Bayar/Bulan (dengan bunga 18%):</strong> <span id="total_bayar"></span></p>
        </div>
    </div>


    <script>
        document.getElementById('calc-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const simpanan = parseFloat(document.getElementById('simpanan').value);
            const tenor = parseInt(document.getElementById('tenor').value);
            const pinjaman = simpanan * 1.5;
            const angsuran = pinjaman / tenor;
            const bunga = angsuran * 0.18;
            const total = angsuran + bunga;


            document.getElementById('pinjaman').textContent = 'Rp ' + pinjaman.toLocaleString();
            document.getElementById('angsuran').textContent = 'Rp ' + angsuran.toLocaleString();
            document.getElementById('total_bayar').textContent = 'Rp ' + total.toLocaleString();
            document.getElementById('hasil').classList.remove('d-none');
        });
    </script>
</body>

</html>