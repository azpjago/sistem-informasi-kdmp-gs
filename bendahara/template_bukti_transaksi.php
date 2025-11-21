<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bukti Transaksi</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            margin: 0;
            padding: 5px;
            color: #000;
            width: 80mm;
        }
        
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
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
        }
        
        .line {
            margin: 4px 0;
            white-space: nowrap;
        }
        
        .label {
            font-weight: bold;
        }
        
        .value {
            /* Values will be aligned naturally with monospace font */
        }
        
        .total-section {
            text-align: center;
            margin: 10px 0;
            padding: 8px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .total-amount {
            font-weight: bold;
            font-size: 14px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
        }
        
        .barcode-area {
            text-align: center;
            margin: 10px 0;
            font-family: 'Libre Barcode 39', monospace;
            font-size: 20px;
        }
        
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="title">KOPERASI SIMPAN PINJAM</div>
        <div class="subtitle">BUKTI TRANSAKSI</div>
    </div>
    
    <div class="separator"></div>
    
    <!-- Info Transaksi -->
    <div class="line"><span class="label">No. Transaksi:</span> <?= $transaksi['id_transaksi'] ?></div>
    <div class="line"><span class="label">Tanggal:</span> <?= $transaksi['tanggal_bayar_format'] ?></div>
    
    <div class="separator"></div>
    
    <!-- Info Anggota -->
    <div class="line"><span class="label">No. Anggota:</span> <?= $anggota['no_anggota'] ?></div>
    <div class="line"><span class="label">Nama:</span> <?= $anggota['nama'] ?></div>
    
    <div class="separator"></div>
    
    <!-- Detail Pembayaran -->
    <div class="line"><span class="label">Jenis Bayar:</span> <?= $transaksi['jenis_pembayaran'] ?></div>
    <div class="line"><span class="label">Keterangan:</span> <?= $transaksi['keterangan'] ?></div>
    
    <div class="separator"></div>
    
    <!-- Total Amount -->
    <div class="total-section">
        <div class="total-amount">Rp <?= number_format($transaksi['jumlah_bayar'], 0, ',', '.') ?></div>
        <div>TERBILANG: <?= terbilang($transaksi['jumlah_bayar']) ?> RUPIAH</div>
    </div>
    
    <div class="separator"></div>
    
    <!-- Barcode Area -->
    <div class="barcode-area">
        *<?= $transaksi['id_transaksi'] ?>*
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div>Terima kasih atas kepercayaan Anda</div>
        <div>---</div>
        <div>Dicetak: <?= $transaksi['waktu_cetak'] ?></div>
    </div>
</body>
</html>
