<?php
// get_saldo_data.php - WITH CASH/TRANSFER LOGIC + TARIK SUKARELA
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

try {
    // 1. HITUNG SALDO PER REKENING DARI TRANSAKSI

    // Daftar rekening yang ada
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // === SIMPANAN ANGGOTA ===
        // SETOR simpanan (menambah saldo)
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
            ");
        } else {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = '$nama_rekening'
            ");
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === TARIK SUKARELA === (mengurangi saldo)
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
            ");
        } else {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'transfer' 
                AND bank_tujuan = '$nama_rekening'
            ");
        }
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total']; // DIKURANGI karena tarik

        // === PENJUALAN SEMBAKO ===
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'Tunai'
            ");
        } else {
            $result = $conn->query("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'Transfer'
                AND p.bank_tujuan = '$nama_rekening'
            ");
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === HIBAH ===
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'cash'
            ");
        } else {
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'transfer' 
                AND bank_tujuan = '$nama_rekening'
            ");
        }
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Simpan data rekening
        $rekening[] = [
            'nama_rekening' => $nama_rekening,
            'saldo_sekarang' => $saldo_rekening,
            'nomor_rekening' => getNomorRekening($nama_rekening),
            'jenis' => ($nama_rekening == 'Kas Tunai') ? 'kas' : 'bank'
        ];

        $saldo_utama += $saldo_rekening;
    }

    // 2. BREAKDOWN PER SUMBER (total semua rekening)

    // Simpanan Anggota - SETOR saja (semua metode)
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
    ");
    $simpanan_data = $result->fetch_assoc();
    $saldo_simpanan = (float) $simpanan_data['total'];

    // TARIK SUKARELA - total penarikan (semua metode)
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'tarik'
    ");
    $tarik_data = $result->fetch_assoc();
    $saldo_tarik = (float) $tarik_data['total'];

    // Penjualan Sembako - semua metode
    $result = $conn->query("
        SELECT COALESCE(SUM(pd.subtotal), 0) as total 
        FROM pemesanan_detail pd 
        INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
        WHERE p.status = 'Terkirim'
    ");
    $penjualan_data = $result->fetch_assoc();
    $saldo_penjualan = (float) $penjualan_data['total'];

    // Hibah - semua metode
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
    ");
    $hibah_data = $result->fetch_assoc();
    $saldo_hibah = (float) $hibah_data['total'];

    // Hitung selisih
    $selisih = $saldo_utama - ($saldo_simpanan + $saldo_penjualan + $saldo_hibah - $saldo_tarik);

    echo json_encode([
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'simpanan_anggota' => $saldo_simpanan,
        'penjualan_sembako' => $saldo_penjualan,
        'hibah' => $saldo_hibah,
        'tarik_sukarela' => $saldo_tarik,
        'selisih' => $selisih,
        'rekening' => $rekening,
        'last_update' => date('d/m/Y H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// Helper function
function getNomorRekening($nama_rekening)
{
    $nomor_rekening = [
        'Kas Tunai' => '-',
        'Bank MANDIRI' => '1234567890',
        'Bank BRI' => '0987654321',
        'Bank BNI' => '55555555555'
    ];
    return $nomor_rekening[$nama_rekening] ?? '-';
}

$conn->close();
?>