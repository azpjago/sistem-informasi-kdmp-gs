<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bukti Transaksi</title>
    <style>
        /* Ukuran A6: 105mm x 148mm */
        @page {
            size: 105mm 148mm;
            margin: 0;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 5mm;
            width: 95mm;
            height: 138mm;
            box-sizing: border-box;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: white;
        }
        .container {
            border: 1px solid #0066ffff;
            border-radius: 5px;
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            background-color: white;
        }
        .header {
            text-align: center;
            padding-bottom: 6px;
            margin-bottom: 8px;
            border-bottom: 1px solid #0066ffff;
        }
        .header h4 {
            margin: 4px 0;
            font-size: 12px;
            color: #2c5282;
            font-weight: bold;
        }
        .header h5 {
            margin: 3px 0;
            font-size: 9px;
            color: #4a5568;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        .details-table td {
            padding: 3px 0;
            vertical-align: top;
            line-height: 1.2;
        }
        .label {
            width: 35%;
            font-weight: bold;
            color: #4a5568;
        }
        .separator {
            width: 3%;
            padding: 0 2px;
            text-align: center;
        }
        .value {
            width: 62%;
        }
        .footer {
            text-align: right;
            font-size: 9px;
            color: #718096;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #ccc;
        }
        .section-title {
            font-weight: bold;
            margin: 8px 0 4px 0;
            color: #2c5282;
            padding-bottom: 2px;
            font-size: 10px;
        }
        .amount {
            font-weight: bold;
            color: #2d3748;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .bg-primary {
            background-color: #3b7ddd;
            color: white;
        }
        .bg-success {
            background-color: #38a169;
            color: white;
        }
        .bg-info {
            background-color: #4299e1;
            color: white;
        }
        .divider {
            border-top: 1px dashed #ccc;
            margin: 6px 0;
        }
        .print-info {
            font-size: 8px;
            color: #a0aec0;
            margin-bottom: 5px;
            text-align: center;
        }
        @media print {
            body {
                width: 105mm;
                height: 148mm;
                background-color: white;
                padding: 5mm;
            }
            .container {
                border: 1px solid #000;
            }
            .print-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <h4>BUKTI TRANSAKSI SIMPANAN</h4>
            <h5>KOPERASI DESA MERAH PUTIH GANJAR SABAR</h5>
        </div>

        <table class="details-table">
            <tr>
                <td class="label">Tanggal Cetak</td>
                <td class="separator">:</td>
                <td class="value"><?= date('d/m/Y, H:i:s') ?></td>
            </tr>
            <tr>
                <td class="label">ID Transaksi</td>
                <td class="separator">:</td>
                <td class="value"><?= htmlspecialchars($transaksi['id_transaksi']) ?></td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="section-title">Detail Anggota</div>
        <table class="details-table">
            <tr>
                <td class="label">No. Anggota</td>
                <td class="separator">:</td>
                <td class="value"><?= htmlspecialchars($anggota['no_anggota']) ?></td>
            </tr>
            <tr>
                <td class="label">Nama Anggota</td>
                <td class="separator">:</td>
                <td class="value"><?= htmlspecialchars($anggota['nama']) ?></td>
            </tr>
        </table>
        
        <div class="divider"></div>

        <div class="section-title">Detail Transaksi</div>
        <table class="details-table">
            <tr>
                <td class="label">Tanggal Transaksi</td>
                <td class="separator">:</td>
                <td class="value"><?= $transaksi['tanggal_bayar_format'] ?></td>
            </tr>
            <tr>
                <td class="label">Jenis Simpanan</td>
                <td class="separator">:</td>
                <td class="value">
                    <?php
                    if ($transaksi['jenis_simpanan'] == 'Simpanan Pokok' || $transaksi['jenis_simpanan'] == 'Pokik') {
                        echo '<span class="badge bg-success">Simpanan Pokok</span>';
                    } elseif ($transaksi['jenis_simpanan'] == 'Simpanan Wajib' || $transaksi['jenis_simpanan'] == 'Waja') {
                        echo '<span class="badge bg-primary">Simpanan Wajib</span>';
                    } elseif ($transaksi['jenis_simpanan'] == 'Simpanan Sukarela') {
                        echo '<span class="badge bg-info">Simpanan Sukarela</span>';
                    } else {
                        echo htmlspecialchars($transaksi['jenis_simpanan']);
                    }
                    ?>
                </td>
            </tr>
                        <tr>
                <td class="label">Jenis Transaksi</td>
                <td class="separator">:</td>
                <td class="value amount"><?= $transaksi['jenis_transaksi']?></td>
                        </tr>
            <tr>
                <td class="label">Jumlah</td>
                <td class="separator">:</td>
                <td class="value amount">Rp <?= number_format($transaksi['jumlah'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="label">Metode</td>
                <td class="separator">:</td>
                <td class="value"><?= ucfirst(htmlspecialchars($transaksi['metode'])) ?></td>
            </tr>
            <tr>
                <td class="label">Keterangan</td>
                <td class="separator">:</td>
                <td class="value">
                    <?php
                    // Membuat keterangan berdasarkan jenis simpanan
                    if ($transaksi['jenis_simpanan'] == 'Simpanan Pokok' || $transaksi['jenis_simpanan'] == 'Pokik') {
                        echo 'Simpanan Pokok ' . htmlspecialchars($anggota['nama']);
                    } elseif ($transaksi['jenis_simpanan'] == 'Simpanan Wajib' || $transaksi['jenis_simpanan'] == 'Waja') {
                        echo 'Pembayaran Simpanan Wajib ' . 
                             (isset($transaksi['bulan_periode']) ? htmlspecialchars($transaksi['bulan_periode']) : '');
                    } elseif ($transaksi['jenis_simpanan'] == 'Simpanan Sukarela') {
                        echo 'Simpanan Sukarela tanggal ' . date('d-m-Y', strtotime($transaksi['tanggal_bayar']));
                    } else {
                        echo htmlspecialchars($transaksi['status']);
                    }
                    ?>
                </td>
            </tr>
        </table>

        <div class="footer">
            <table class="details-table">
                <tr>
                    <td class="label">Dicetak pada</td>
                    <td class="separator">:</td>
                    <td class="value"><?= date('d/m/Y H:i:s') ?></td>
                </tr>
            </table>
            Bandung, <?= date('d F Y', strtotime($transaksi['tanggal_bayar'])) ?>
            <br>
            <br>
            <br>
            <br>
            ( Bendahara )
        </div>
    </div>

    <script>
        // Script untuk mengatur tinggi container secara dinamis
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.container');
            const body = document.querySelector('body');
            
            // Atur tinggi container agar sesuai dengan konten
            const containerHeight = container.scrollHeight;
            
            // Jika konten lebih pendek dari body, atur posisi vertikal
            if (containerHeight < body.clientHeight) {
                body.style.alignItems = 'center';
            } else {
                body.style.alignItems = 'flex-start';
            }
        });
    </script>
</body>
</html>