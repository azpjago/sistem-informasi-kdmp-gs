<?php
session_start();
if ($_SESSION['role'] !== 'usaha')
    exit;

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
header('Content-Type: application/json');

// Fungsi hitung saldo (sama seperti di pengeluaran)
function hitungSaldoKasTunai()
{
    global $conn;
    $result = $conn->query("
        SELECT (
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'cash'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'cash')
            +
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'cash')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'cash')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = 'Kas Tunai')
            -
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status IN ('approved', 'active') AND sumber_dana = 'Kas Tunai')
        ) as saldo_kas
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_kas'] ?? 0;
}

function hitungSaldoBank($nama_bank)
{
    global $conn;
    $result = $conn->query("
        SELECT (
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'setor' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank'
             AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib'))
            +
            (SELECT COALESCE(SUM(pd.subtotal), 0) FROM pemesanan_detail pd 
             INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
             WHERE p.status = 'Terkirim' AND p.metode = 'transfer'
             AND p.bank_tujuan = '$nama_bank')
            +
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
             AND metode = 'transfer' AND bank_tujuan = '$nama_bank')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran 
             WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
             AND jenis_transaksi = 'tarik' AND metode = 'transfer' 
             AND bank_tujuan = '$nama_bank')
            -
            (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran 
             WHERE status = 'approved' AND sumber_dana = '$nama_bank')
            -
            (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman 
             WHERE status IN ('approved', 'active') AND sumber_dana = '$nama_bank')
        ) as saldo_bank
    ");
    $data = $result->fetch_assoc();
    return $data['saldo_bank'] ?? 0;
}

$saldo_kas = hitungSaldoKasTunai();
$saldo_mandiri = hitungSaldoBank('Bank MANDIRI');
$saldo_bri = hitungSaldoBank('Bank BRI');
$saldo_bni = hitungSaldoBank('Bank BNI');

echo json_encode([
    'Kas Tunai' => $saldo_kas,
    'Bank MANDIRI' => $saldo_mandiri,
    'Bank BRI' => $saldo_bri,
    'Bank BNI' => $saldo_bni
]);

$conn->close();
?>