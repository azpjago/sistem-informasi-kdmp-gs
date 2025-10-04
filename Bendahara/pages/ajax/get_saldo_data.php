<?php
// get_saldo_data.php - PASTIKAN SUDAH KURANGI PENGELUARAN APPROVED
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

try {
    // 1. HITUNG SALDO PER REKENING DARI TRANSAKSI (SUDAH KURANGI PENGELUARAN APPROVED)

    // Daftar rekening yang ada
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // === SIMPANAN ANGGOTA (SETOR) - HANYA POKOK & WAJIB ===
        if ($nama_rekening == 'Kas Tunai') {
            // KAS TUNAI: ambil dari pembayaran dengan metode cash (HANYA POKOK & WAJIB)
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
        } else {
            // BANK: ambil dari pembayaran dengan metode transfer ke bank tertentu (HANYA POKOK & WAJIB)
            $result = $conn->query("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = '$nama_rekening'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
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
        $saldo_rekening -= (float) $data['total'];

        // === PENJUALAN SEMBAKO ===
        if ($nama_rekening == 'Kas Tunai') {
            $result = $conn->query("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'cash'
            ");
        } else {
            $result = $conn->query("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'transfer'
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

        // === PENGELUARAN APPROVED === (mengurangi saldo) - TAMBAHAN INI
        $result = $conn->query("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = '$nama_rekening'
        ");
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // Simpan data rekening
        $rekening[] = [
            'nama_rekening' => $nama_rekening,
            'saldo_sekarang' => $saldo_rekening,
            'nomor_rekening' => getNomorRekening($nama_rekening),
            'jenis' => ($nama_rekening == 'Kas Tunai') ? 'kas' : 'bank'
        ];

        $saldo_utama += $saldo_rekening;
    }


    // 2. BREAKDOWN PER SUMBER (untuk informasi)

    // Simpanan Anggota - SETOR saja (semua metode) - HANYA POKOK & WAJIB
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
        AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')  -- HANYA POKOK & WAJIB
    ");
    $simpanan_data = $result->fetch_assoc();
    $saldo_simpanan = (float) $simpanan_data['total'];

    // SIMPANAN SUKARELA (TERPISAH) - untuk informasi saja, tidak untuk operasional
    $result = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
        AND jenis_simpanan = 'Simpanan Sukarela'
    ");
    $sukarela_data = $result->fetch_assoc();
    $saldo_sukarela = (float) $sukarela_data['total'];

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

    // Hitung selisih (DANA OPERASIONAL = Simpanan Pokok/Wajib + Penjualan + Hibah - Tarik)
    $dana_operasional = $saldo_simpanan + $saldo_penjualan + $saldo_hibah - $saldo_tarik;
    $selisih = $saldo_utama - $dana_operasional;

    echo json_encode([
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'simpanan_anggota' => $saldo_simpanan, // Hanya pokok & wajib
        'simpanan_sukarela' => $saldo_sukarela, // Sukarela terpisah (hanya info)
        'penjualan_sembako' => $saldo_penjualan,
        'hibah' => $saldo_hibah,
        'tarik_sukarela' => $saldo_tarik,
        'selisih' => $selisih,
        'dana_operasional' => $dana_operasional,
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