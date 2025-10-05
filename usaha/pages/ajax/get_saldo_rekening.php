<?php
session_start();
require_once 'functions/history_log.php';

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

function hitungSaldoSumberDana($sumber_dana, $conn)
{
    if ($sumber_dana === 'Kas Tunai') {
        $result = $conn->query("
            SELECT (
                -- Simpanan Anggota (cash)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                 AND jenis_transaksi = 'setor' AND metode = 'cash'
                 AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
                +
                -- Penjualan (Tunai)
                (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
                 INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                 WHERE p.status = 'Terkirim' AND p.metode = 'cash')
                +
                -- Hibah (cash)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                 AND metode = 'cash')
                -
                -- Tarik Sukarela (cash)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                 AND jenis_transaksi = 'tarik' AND metode = 'cash')
                -
                -- Pengeluaran Approved
                (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
                 WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
                -
                -- Pinjaman Approved
                (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
                 WHERE status IN ('approved', 'active') AND sumber_dana = 'Kas Tunai')
            ) as saldo
        ");
    } else {
        // Untuk bank
        $result = $conn->query("
            SELECT (
                -- Simpanan Anggota (transfer)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                 AND jenis_transaksi = 'setor' AND metode = 'transfer' 
                 AND bank_tujuan = '$sumber_dana'
                 AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
                +
                -- Penjualan (Transfer)
                (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
                 INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                 WHERE p.status = 'Terkirim' AND p.metode = 'transfer'
                 AND p.bank_tujuan = '$sumber_dana')
                +
                -- Hibah (transfer)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                 AND metode = 'transfer' AND bank_tujuan = '$sumber_dana')
                -
                -- Tarik Sukarela (transfer)
                (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
                 WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                 AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
                 AND bank_tujuan = '$sumber_dana')
                -
                -- Pengeluaran Approved
                (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
                 WHERE status = 'approved' AND sumber_dana = '$sumber_dana')
                -
                -- Pinjaman Approved
                (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
                 WHERE status IN ('approved', 'active') AND sumber_dana = '$sumber_dana')
            ) as saldo
        ");
    }

    $data = $result->fetch_assoc();
    return $data['saldo'] ?? 0;
}

$sumber_dana_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
$saldo_data = [];

foreach ($sumber_dana_list as $sumber) {
    $saldo_data[$sumber] = hitungSaldoSumberDana($sumber, $conn);
}

header('Content-Type: application/json');
echo json_encode($saldo_data);
?>