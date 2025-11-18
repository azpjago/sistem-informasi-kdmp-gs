<?php
// get_saldo_data.php - VERSION DENGAN PERHITUNGAN YANG AKURAT (SETOR - TARIK)
session_start();
if (!isset($_SESSION['role'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

require 'koneksi/koneksi.php';

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

try {
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening = [];
    $saldo_utama = 0;

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // === 1. SIMPANAN POKOK & WAJIB (SETOR - TARIK) ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['setor'] - (float) $data['tarik'];

        // === 2. SIMPANAN SUKARELA (SETOR - TARIK) ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $saldo_rekening += (float) $data['setor'] - (float) $data['tarik'];

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

        // === 5. CICILAN PINJAMAN ===
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
        'tarik_simpanan' => $breakdown['tarik_simpanan'],
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
        'tarik_simpanan' => 0, // Total semua penarikan
        'total_pengeluaran' => 0,
        'total_pinjaman_approved' => 0,
        'total_angsuran' => 0,
        'per_rekening' => []
    ];

    foreach ($rekening_list as $nama_rekening) {
        $per_rekening = [
            'nama_rekening' => $nama_rekening,
            'simpanan_pokok_wajib_setor' => 0,
            'simpanan_pokok_wajib_tarik' => 0,
            'simpanan_pokok_wajib_net' => 0,
            'simpanan_sukarela_setor' => 0,
            'simpanan_sukarela_tarik' => 0,
            'simpanan_sukarela_net' => 0,
            'penjualan_sembako' => 0,
            'hibah' => 0,
            'pengeluaran' => 0,
            'pinjaman' => 0,
            'angsuran' => 0
        ];

        // === SIMPANAN POKOK & WAJIB (SETOR - TARIK) ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['simpanan_pokok_wajib_setor'] = (float) $data['setor'];
        $per_rekening['simpanan_pokok_wajib_tarik'] = (float) $data['tarik'];
        $per_rekening['simpanan_pokok_wajib_net'] = $per_rekening['simpanan_pokok_wajib_setor'] - $per_rekening['simpanan_pokok_wajib_tarik'];
        $breakdown['simpanan_pokok_wajib'] += $per_rekening['simpanan_pokok_wajib_net'];

        // === SIMPANAN SUKARELA (SETOR - TARIK) ===
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran 
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) as setor,
                    COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) as tarik
                FROM pembayaran
                WHERE (status_bayar = 'Lunas' OR status = 'Lunas')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan = 'Simpanan Sukarela'
            ");
            $stmt->bind_param("s", $nama_rekening);
            $stmt->execute();
        }
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $per_rekening['simpanan_sukarela_setor'] = (float) $data['setor'];
        $per_rekening['simpanan_sukarela_tarik'] = (float) $data['tarik'];
        $per_rekening['simpanan_sukarela_net'] = $per_rekening['simpanan_sukarela_setor'] - $per_rekening['simpanan_sukarela_tarik'];
        $breakdown['simpanan_sukarela'] += $per_rekening['simpanan_sukarela_net'];

        // Total penarikan semua jenis simpanan
        $breakdown['tarik_simpanan'] += $per_rekening['simpanan_pokok_wajib_tarik'] + $per_rekening['simpanan_sukarela_tarik'];

        // === PENJUALAN SEMBAKO ===
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

        // === HIBAH ===
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

        // === PENGELUARAN ===
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

        // === PINJAMAN ===
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

        // === ANGSURAN (CICILAN) ===
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
        //$breakdown['simpanan_sukarela'] +
        $breakdown['penjualan_sembako'] +
        $breakdown['hibah'] +
        $breakdown['total_angsuran'] -
        $breakdown['total_pengeluaran'] -
        $breakdown['total_pinjaman_approved'];

    // Selisih seharusnya 0 jika perhitungan benar
    $breakdown['selisih'] = $breakdown['dana_operasional'] - 
        ($breakdown['simpanan_pokok_wajib'] +
         //$breakdown['simpanan_sukarela'] +
         $breakdown['penjualan_sembako'] +
         $breakdown['hibah'] +
         $breakdown['total_angsuran'] -
         $breakdown['total_pengeluaran'] -
         $breakdown['total_pinjaman_approved']);

    return $breakdown;
}

$conn->close();
?>
