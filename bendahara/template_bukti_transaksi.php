<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bukti Transaksi</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            margin: 0;
            padding: 10px 15px;
            color: #000;
            width: 80mm;
            min-height: 0;
        }
        
        .container {
            width: 100%;
            max-width: 80mm;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #000;
        }
        
        .title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .subtitle {
            font-size: 10px;
            margin-bottom: 5px;
        }
        
        .separator {
            border-top: 1px dashed #000;
            margin: 8px 0;
            padding: 0;
        }
        
        .line {
            margin: 3px 0;
            padding: 1px 0;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 35%;
        }
        
        .value {
            display: inline-block;
            width: 63%;
            vertical-align: top;
        }
        
        .total-section {
            text-align: center;
            margin: 12px 0;
            padding: 8px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .total-amount {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .terbilang {
            font-size: 10px;
            font-style: italic;
        }
        
        .footer {
            text-align: center;
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px dashed #000;
            font-size: 9px;
        }
        
        .barcode-area {
            text-align: center;
            margin: 10px 0;
            padding: 5px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
        }
        
        .center {
            text-align: center;
        }
        
        .spacer {
            height: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="title">KDMP Ganjar Sabar</div>
            <div class="subtitle">BUKTI TRANSAKSI</div>
        </div>
        
        <div class="separator"></div>
        
        <!-- Info Transaksi -->
        <div class="line">
            <span class="label">No. Transaksi:</span>
            <span class="value"><?= $transaksi['id_transaksi'] ?></span>
        </div>
        <div class="line">
            <span class="label">Tanggal:</span>
            <span class="value"><?= $transaksi['tanggal_bayar_format'] ?></span>
        </div>
        
        <div class="separator"></div>
        
        <!-- Info Anggota -->
        <div class="line">
            <span class="label">No. Anggota:</span>
            <span class="value"><?= $anggota['no_anggota'] ?></span>
        </div>
        <div class="line">
            <span class="label">Nama:</span>
            <span class="value"><?= $anggota['nama'] ?></span>
        </div>
        
        <div class="separator"></div>
        
        <!-- Detail Pembayaran -->
        <div class="line">
            <span class="label">Jenis Bayar:</span>
            <span class="value"><?= $transaksi['jenis_transaksi'] ?></span>
        </div>
        <div class="line">
            <span class="label">Keterangan:</span>
            <span class="value"><?= !empty($transaksi['keterangan']) ? $transaksi['keterangan'] : '-' ?></span>
        </div>
        
        <div class="separator"></div>
        
        <!-- Total Amount -->
        <div class="total-section">
            <div class="total-amount"><?= $transaksi['jumlah_bayar_format'] ?></div>
            <div class="terbilang">TERBILANG: <?= $transaksi['terbilang'] ?></div>
        </div>
        
        <div class="separator"></div>
        
        <!-- Barcode Area -->
        <div class="barcode-area">
            *<?= $transaksi['id_transaksi'] ?>*
        </div>
        
        <div class="spacer"></div>
        
        <!-- Footer -->
        <div class="footer">
            <div>Terima kasih atas kepercayaan Anda</div>
            <div class="spacer"></div>
            <div>Dicetak: <?= $transaksi['waktu_cetak'] ?></div>
        </div>
    </div>
</body>
</html>
