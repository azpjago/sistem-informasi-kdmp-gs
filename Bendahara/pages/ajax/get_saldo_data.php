<?php
// get_saldo_data.php - VERSION SIMPLE DENGAN PINJAMAN
session_start();
if (!isset($_SESSION['role'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

try {
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // === 1. SIMPANAN ANGGOTA (SETOR) - POKOK & WAJIB ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === 2. TARIK SUKARELA === (mengurangi saldo)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // === 3. PENJUALAN SEMBAKO ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'cash'
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'transfer'
                AND p.bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === 4. HIBAH ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'cash'
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === 5. PENGELUARAN APPROVED === (mengurangi saldo)
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = ?
        ");
        $stmt->bind_param("s", $nama_rekening);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // === 6. PINJAMAN APPROVED === (mengurangi saldo) - YANG INI SAJA!
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah_pinjaman), 0) as total 
            FROM pinjaman 
            WHERE status = 'approved' 
            AND sumber_dana = ?
        ");
        $stmt->bind_param("s", $nama_rekening);
        $stmt->execute();
        $result = $stmt->get_result();
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

    // Hitung breakdown untuk informasi
    $saldo_simpanan = hitungTotalSimpanan($conn);
    $saldo_sukarela = hitungSimpananSukarela($conn);
    $saldo_tarik = hitungTarikSukarela($conn);
    $saldo_penjualan = hitungPenjualanSembako($conn);
    $saldo_hibah = hitungHibah($conn);

    // Hitung total pinjaman approved (untuk informasi)
    $total_pinjaman = hitungTotalPinjamanApproved($conn);

    $dana_operasional = $saldo_simpanan + $saldo_penjualan + $saldo_hibah - $saldo_tarik - $total_pinjaman;
    $selisih = $saldo_utama - $dana_operasional;

    echo json_encode([
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'simpanan_anggota' => $saldo_simpanan,
        'simpanan_sukarela' => $saldo_sukarela,
        'penjualan_sembako' => $saldo_penjualan,
        'hibah' => $saldo_hibah,
        'tarik_sukarela' => $saldo_tarik,
        'total_pinjaman_approved' => $total_pinjaman, // BARU - untuk informasi
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

// Fungsi helper untuk nomor rekening
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

// Fungsi-fungsi tambahan untuk breakdown
function hitungTotalSimpanan($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
        AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

function hitungSimpananSukarela($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'setor'
        AND jenis_simpanan = 'Simpanan Sukarela'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

function hitungTarikSukarela($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
        AND jenis_transaksi = 'tarik'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

function hitungPenjualanSembako($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(pd.subtotal), 0) as total 
        FROM pemesanan_detail pd 
        INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
        WHERE p.status = 'Terkirim'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

function hitungHibah($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pembayaran 
        WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

// FUNGSI BARU: Hitung total pinjaman yang approved
function hitungTotalPinjamanApproved($conn)
{
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(jumlah_pinjaman), 0) as total 
        FROM pinjaman 
        WHERE status = 'approved'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return (float) $data['total'];
}

$conn->close();
?>