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
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela')
            +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'cash')
            +
            -- PENAMBAHAN: Cicilan yang sudah LUNAS via Kas Tunai
            (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM cicilan 
            WHERE status = 'lunas' AND metode = 'cash' AND jumlah_bayar > 0)
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'cash')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Kas Tunai
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
        ) as saldo
        ");
    } else {
        // Untuk bank
        $result = $conn->query("
            SELECT (
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$sumber_dana'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = '$sumber_dana'
                AND jenis_simpanan = 'Simpanan Sukarela')
                +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'transfer'
             AND p.bank_tujuan = '$sumber_dana')
            +
            (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM cicilan 
            WHERE status = 'lunas' AND metode = 'transfer' 
            AND bank_tujuan = '$sumber_dana' AND jumlah_bayar > 0)
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'transfer' AND bank_tujuan = '$sumber_dana')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$sumber_dana')
            -
            -- PENGURANGAN: Pengeluaran yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = '$sumber_dana')
            -
            -- PENGURANGAN: Pinjaman yang sudah APPROVED dari Bank tersebut
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status = 'approved' AND sumber_dana = '$sumber_dana')
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