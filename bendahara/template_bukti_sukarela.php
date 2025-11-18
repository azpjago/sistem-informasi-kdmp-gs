<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bukti Transaksi Simpanan Sukarela</title>
    <style>
        /* PERUBAHAN: Menambahkan @page untuk mengatur margin cetak */
        @page {
            margin: 1cm; /* Atur margin halaman menjadi 1 cm */
        }
        body { font-family: sans-serif; font-size: 10px; }
        .container { border: 1px solid #333; padding: 15px; width: 100%; /* Hapus posisi absolut */ }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 15px; }
        .header img { width: 60px; }
        .header h4, .header h5 { margin: 2px 0; }
        .details-table { width: 100%; margin-top: 10px; }
        .details-table td { padding: 2px 0; }
        .label { width: 40%; }
        .separator { width: 5%; }
        .footer { margin-top: 40px; text-align: right; font-size: 10px; }
        p { margin: 10px 0 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h4>BUKTI TRANSAKSI SIMPANAN SUKARELA</h4>
            <h5>KOPERASI DESA MERAH PUTIH GANJAR SABAR</h5>
        </div>

        <table class="details-table">
            <tr>
                <td class="label">Tanggal Cetak</td>
                <td class="separator">:</td>
                <td><?= date('d/m/Y, H:i:s') ?></td>
            </tr>
            <tr>
                <td class="label">ID Transaksi</td>
                <td class="separator">:</td>
                <td>TRK-SKR-<?= str_pad($transaksi_id, 5, '0', STR_PAD_LEFT) ?></td>
            </tr>
        </table>

        <p><strong><u>Detail Penerima</u></strong></p>
        <table class="details-table">
            <tr>
                <td class="label">No. Anggota</td>
                <td class="separator">:</td>
                <td><?= htmlspecialchars($no_anggota) ?></td>
            </tr>
            <tr>
                <td class="label">Nama Anggota</td>
                <td class="separator">:</td>
                <td><?= htmlspecialchars($nama_anggota) ?></td>
            </tr>
        </table>
        
        <p><strong><u>Detail Transaksi</u></strong></p>
        <table class="details-table">
            <tr>
                <td class="label">Tipe Transaksi</td>
                <td class="separator">:</td>
                <td><strong><?= strtoupper(htmlspecialchars($tipe_transaksi)) ?></strong></td>
            </tr>
            <tr>
                <td class="label">Tanggal Transaksi</td>
                <td class="separator">:</td>
                <td><?= date('d/m/Y', strtotime($tanggal_transaksi)) ?></td>
            </tr>
            <tr>
                <td class="label">Jumlah Transaksi</td>
                <td class="separator">:</td>
                <td><strong>Rp. <?= number_format($jumlah, 0, ',', '.') ?></strong></td>
            </tr>
            <tr>
                <td class="label">Keterangan</td>
                <td class="separator">:</td>
                <td><?= htmlspecialchars($keterangan ?: '-') ?></td>
            </tr>
        </table>

        <div class="footer">
            Bandung, <?= date('d F Y', strtotime($tanggal_transaksi)) ?><br><br><br><br>
            ( Bendahara )
        </div>
    </div>
</body>
</html>
