<?php
// get_saldo_data.php - REAL-TIME VERSION
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

try {
    // 1. HITUNG SALDO PER REKENING DARI TRANSAKSI

    // Daftar rekening yang ada
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // Tambah dari SIMPANAN ANGGOTA
        $result = $conn->query("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE (status_bayar = 'Lunas' OR status = 'Lunas') 
            AND bank_tujuan = '$nama_rekening'
        ");
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Tambah dari PENJUALAN SEMBAKO
        $result = $conn->query("
            SELECT COALESCE(SUM(pd.subtotal), 0) as total 
            FROM pemesanan_detail pd 
            INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
            WHERE p.status = 'Terkirim' 
            AND p.bank_tujuan = '$nama_rekening'
        ");
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Tambah dari HIBAH (jika ada tabel hibah)
        $result = $conn->query("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
            AND bank_tujuan = '$nama_rekening'
        ");
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // Simpan data rekening
        $rekening[] = [
            'nama_rekening' => $nama_rekening,
            'saldo_sekarang' => $saldo_rekening,
            'nomor_rekening' => getNomorRekening($nama_rekening) // function helper
        ];

        $saldo_utama += $saldo_rekening;
    }

    // 2. BREAKDOWN PER SUMBER (total semua rekening)

    // Simpanan Anggota - semua rekening
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE status_bayar = 'Lunas' OR status = 'Lunas'
    ");
    $simpanan_data = $result->fetch_assoc();
    $saldo_simpanan = (float) $simpanan_data['total'];

    // Penjualan Sembako - semua rekening
    $result = $conn->query("
        SELECT COALESCE(SUM(pd.subtotal), 0) as total 
        FROM pemesanan_detail pd 
        INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
        WHERE p.status = 'Terkirim'
    ");
    $penjualan_data = $result->fetch_assoc();
    $saldo_penjualan = (float) $penjualan_data['total'];

    // Hibah - semua rekening
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%'
    ");
    $hibah_data = $result->fetch_assoc();
    $saldo_hibah = (float) $hibah_data['total'];

    echo json_encode([
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'simpanan_anggota' => $saldo_simpanan,
        'penjualan_sembako' => $saldo_penjualan,
        'hibah' => $saldo_hibah,
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