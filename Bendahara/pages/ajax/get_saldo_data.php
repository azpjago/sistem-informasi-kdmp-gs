<?php
// get_saldo_data.php - VERSION DENGAN PERHITUNGAN CICILAN YANG DETAIL
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

        // === 2. SIMPANAN SUKARELA (SETOR) ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
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
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['total'];

        // === 3. TARIK SUKARELA === (mengurangi saldo)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
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
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening -= (float) $data['total'];

        // === 4. PENJUALAN SEMBAKO ===
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

        // === 5. HIBAH ===
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

        // === 6. PENGELUARAN APPROVED === (mengurangi saldo)
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

        // === 7. PINJAMAN APPROVED === (mengurangi saldo)
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

        // === 8. CICILAN PINJAMAN === (menambah saldo) - PERBAIKAN DETAIL
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                FROM cicilan 
                WHERE status = 'lunas'
                AND metode = 'cash'
                AND jumlah_bayar > 0
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                FROM cicilan 
                WHERE status = 'lunas'
                AND metode = 'transfer'
                AND bank_tujuan = ?
                AND jumlah_bayar > 0
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
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

    // Hitung breakdown untuk informasi dengan detail per rekening
    $breakdown = hitungBreakdownDetail($conn, $rekening_list);

    echo json_encode([
        'status' => 'success',
        'saldo_utama' => $saldo_utama,
        'simpanan_pokok_wajib' => $breakdown['simpanan_pokok_wajib'],
        'simpanan_sukarela' => $breakdown['simpanan_sukarela'],
        'penjualan_sembako' => $breakdown['penjualan_sembako'],
        'hibah' => $breakdown['hibah'],
        'tarik_sukarela' => $breakdown['tarik_sukarela'],
        'total_pengeluaran' => $breakdown['total_pengeluaran'],
        'total_pinjaman_approved' => $breakdown['total_pinjaman_approved'],
        'total_angsuran' => $breakdown['total_angsuran'],
        'selisih' => $breakdown['selisih'],
        'dana_operasional' => $breakdown['dana_operasional'],
        'rekening' => $rekening,
        'breakdown_detail' => $breakdown['per_rekening'],
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

// Fungsi untuk menghitung breakdown detail per rekening
function hitungBreakdownDetail($conn, $rekening_list)
{
    $breakdown = [
        'simpanan_pokok_wajib' => 0,
        'simpanan_sukarela' => 0,
        'penjualan_sembako' => 0,
        'hibah' => 0,
        'tarik_sukarela' => 0,
        'total_pengeluaran' => 0,
        'total_pinjaman_approved' => 0,
        'total_angsuran' => 0,
        'per_rekening' => []
    ];

    foreach ($rekening_list as $nama_rekening) {
        $per_rekening = [
            'nama_rekening' => $nama_rekening,
            'simpanan_pokok_wajib' => 0,
            'simpanan_sukarela_setor' => 0,
            'simpanan_sukarela_tarik' => 0,
            'penjualan_sembako' => 0,
            'hibah' => 0,
            'pengeluaran' => 0,
            'pinjaman' => 0,
            'angsuran' => 0
        ];

        // Simpanan Pokok & Wajib
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
        $per_rekening['simpanan_pokok_wajib'] = (float) $data['total'];
        $breakdown['simpanan_pokok_wajib'] += $per_rekening['simpanan_pokok_wajib'];

        // Simpanan Sukarela (Setor)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
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
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['simpanan_sukarela_setor'] = (float) $data['total'];

        // Simpanan Sukarela (Tarik)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
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
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['simpanan_sukarela_tarik'] = (float) $data['total'];

        // Net Simpanan Sukarela
        $net_sukarela = $per_rekening['simpanan_sukarela_setor'] - $per_rekening['simpanan_sukarela_tarik'];
        $breakdown['simpanan_sukarela'] += $net_sukarela;
        $breakdown['tarik_sukarela'] += $per_rekening['simpanan_sukarela_tarik'];

        // Penjualan Sembako
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
        $per_rekening['penjualan_sembako'] = (float) $data['total'];
        $breakdown['penjualan_sembako'] += $per_rekening['penjualan_sembako'];

        // Hibah
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
        $per_rekening['hibah'] = (float) $data['total'];
        $breakdown['hibah'] += $per_rekening['hibah'];

        // Pengeluaran
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = ?
        ");
        $stmt->bind_param("s", $nama_rekening);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['pengeluaran'] = (float) $data['total'];
        $breakdown['total_pengeluaran'] += $per_rekening['pengeluaran'];

        // Pinjaman
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
        $per_rekening['pinjaman'] = (float) $data['total'];
        $breakdown['total_pinjaman_approved'] += $per_rekening['pinjaman'];

        // Angsuran (Cicilan)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                FROM cicilan 
                WHERE status = 'lunas'
                AND metode = 'cash'
                AND jumlah_bayar > 0
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah_bayar), 0) as total 
                FROM cicilan 
                WHERE status = 'lunas'
                AND metode = 'transfer'
                AND bank_tujuan = ?
                AND jumlah_bayar > 0
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['angsuran'] = (float) $data['total'];
        $breakdown['total_angsuran'] += $per_rekening['angsuran'];

        $breakdown['per_rekening'][] = $per_rekening;
    }

    // Hitung dana operasional dan selisih
    $breakdown['dana_operasional'] = $breakdown['simpanan_pokok_wajib'] +
        $breakdown['simpanan_sukarela'] +
        $breakdown['penjualan_sembako'] +
        $breakdown['hibah'] +
        $breakdown['total_angsuran'] -
        $breakdown['total_pengeluaran'] -
        $breakdown['total_pinjaman_approved'];

    $breakdown['selisih'] = $breakdown['dana_operasional'] -
        ($breakdown['simpanan_pokok_wajib'] +
            $breakdown['simpanan_sukarela'] +
            $breakdown['penjualan_sembako'] +
            $breakdown['hibah'] +
            $breakdown['total_angsuran'] -
            $breakdown['total_pengeluaran'] -
            $breakdown['total_pinjaman_approved']);

    return $breakdown;
}

$conn->close();
?>