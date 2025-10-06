<?php
// get_laporan_data.php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Cek authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ketua') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';

// Validasi tanggal
if (!validateDate($start_date) || !validateDate($end_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Format tanggal tidak valid']);
    exit;
}

try {
    $response = [];

    switch ($report_type) {
        case 'overview':
            $response = getOverviewData($conn, $start_date, $end_date);
            break;
        case 'simpanan':
            $response = getSimpananData($conn, $start_date, $end_date);
            break;
        case 'kasbank':
            $response = getKasBankData($conn, $start_date, $end_date);
            break;
        case 'pinjaman':
            $response = getPinjamanData($conn, $start_date, $end_date);
            break;
        default:
            $response = getOverviewData($conn, $start_date, $end_date);
    }

    $response['status'] = 'success';
    $response['period'] = [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'period_text' => date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date))
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();

// ==================== FUNCTIONS ====================

function validateDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function getOverviewData($conn, $start_date, $end_date)
{
    $data = [];

    // 1. DATA UNTUK TREND CHART (6 bulan terakhir dari end_date)
    $bulan_labels = [];
    $data_simpanan = [];
    $data_pendapatan = [];
    $data_pengeluaran = [];

    $end = new DateTime($end_date);
    for ($i = 5; $i >= 0; $i--) {
        $current = clone $end;
        $current->modify("-$i months");
        $bulan = $current->format('Y-m');
        $bulan_label = $current->format('M Y');

        $bulan_labels[] = $bulan_label;

        // Simpanan bulan tersebut
        $result_simpanan = $conn->query("
            SELECT COALESCE(SUM(jumlah), 0) as simpanan_bulan
            FROM pembayaran 
            WHERE status_bayar = 'Lunas' 
            AND DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan'
            AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
        ");
        $data_simpanan[] = (float) $result_simpanan->fetch_assoc()['simpanan_bulan'];

        // Pendapatan bulan tersebut
        $result_pendapatan = $conn->query("
            SELECT COALESCE(SUM(pd.subtotal), 0) as pendapatan_bulan
            FROM pemesanan_detail pd 
            INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
            WHERE p.status = 'Terkirim' AND DATE_FORMAT(p.tanggal_pesan, '%Y-%m') = '$bulan'
        ");
        $data_pendapatan[] = (float) $result_pendapatan->fetch_assoc()['pendapatan_bulan'];

        // Pengeluaran bulan tersebut
        $result_pengeluaran = $conn->query("
            SELECT COALESCE(SUM(jumlah), 0) as pengeluaran_bulan
            FROM pengeluaran 
            WHERE status = 'approved' AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan'
        ");
        $data_pengeluaran[] = (float) $result_pengeluaran->fetch_assoc()['pengeluaran_bulan'];
    }

    $data['chart_data'] = [
        'bulan_labels' => $bulan_labels,
        'data_simpanan' => $data_simpanan,
        'data_pendapatan' => $data_pendapatan,
        'data_pengeluaran' => $data_pengeluaran
    ];

    // 2. METRICS UTAMA
    // Total Simpanan dalam periode
    $result_simpanan_periode = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total_simpanan
        FROM pembayaran 
        WHERE status_bayar = 'Lunas'
        AND tanggal_bayar BETWEEN '$start_date' AND '$end_date'
        AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
    ");
    $data['total_simpanan_periode'] = (float) $result_simpanan_periode->fetch_assoc()['total_simpanan'];

    // Total Simpanan Sukarela dalam periode
    $result_sukarela_periode = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total_sukarela
        FROM pembayaran 
        WHERE status_bayar = 'Lunas'
        AND tanggal_bayar BETWEEN '$start_date' AND '$end_date'
        AND jenis_simpanan = 'Simpanan Sukarela'
        AND jenis_transaksi = 'setor'
    ");
    $data['total_sukarela_periode'] = (float) $result_sukarela_periode->fetch_assoc()['total_sukarela'];

    // Total Penjualan dalam periode
    $result_penjualan_periode = $conn->query("
        SELECT COALESCE(SUM(pd.subtotal), 0) as total_penjualan
        FROM pemesanan_detail pd 
        INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
        WHERE p.status = 'Terkirim' 
        AND p.tanggal_pesan BETWEEN '$start_date' AND '$end_date'
    ");
    $data['total_penjualan_periode'] = (float) $result_penjualan_periode->fetch_assoc()['total_penjualan'];

    // Total Pengeluaran dalam periode
    $result_pengeluaran_periode = $conn->query("
        SELECT COALESCE(SUM(jumlah), 0) as total_pengeluaran
        FROM pengeluaran 
        WHERE status = 'approved'
        AND tanggal BETWEEN '$start_date' AND '$end_date'
    ");
    $data['total_pengeluaran_periode'] = (float) $result_pengeluaran_periode->fetch_assoc()['total_pengeluaran'];

    // Net Income
    $data['net_income_periode'] = $data['total_penjualan_periode'] - $data['total_pengeluaran_periode'];

    // 3. TOP 5 ANGGOTA periode ini
    $result_top_anggota = $conn->query("
        SELECT a.nama, a.no_anggota, a.saldo_total 
        FROM anggota a
        WHERE a.status_keanggotaan = 'Aktif'
        ORDER BY a.saldo_total DESC 
        LIMIT 5
    ");

    $top_anggota = [];
    while ($row = $result_top_anggota->fetch_assoc()) {
        $top_anggota[] = $row;
    }
    $data['top_anggota'] = $top_anggota;

    // 4. SUMMARY PINJAMAN
    $result_pinjaman = $conn->query("
        SELECT 
            COUNT(*) as total_pinjaman,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as pinjaman_aktif,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as pinjaman_ditolak,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as pinjaman_disetujui,
            COALESCE(SUM(jumlah_pinjaman), 0) as total_nilai_pinjaman
        FROM pinjaman
        WHERE tanggal_pengajuan BETWEEN '$start_date' AND '$end_date'
    ");
    $data['pinjaman_summary'] = $result_pinjaman->fetch_assoc();

    return $data;
}

function getSimpananData($conn, $start_date, $end_date)
{
    $data = [];

    // BREAKDOWN SIMPANAN PER JENIS
    $result_breakdown = $conn->query("
        SELECT 
            'Pokok' as jenis,
            COUNT(*) as jumlah_anggota,
            SUM(simpanan_pokok) as total,
            AVG(simpanan_pokok) as rata_rata
        FROM anggota 
        WHERE status_keanggotaan = 'Aktif'
        UNION ALL
        SELECT 
            'Wajib' as jenis,
            COUNT(*) as jumlah_anggota,
            SUM(simpanan_wajib) as total,
            AVG(simpanan_wajib) as rata_rata
        FROM anggota 
        WHERE status_keanggotaan = 'Aktif'
        UNION ALL
        SELECT 
            'Sukarela' as jenis,
            COUNT(*) as jumlah_anggota,
            SUM(saldo_sukarela) as total,
            AVG(saldo_sukarela) as rata_rata
        FROM anggota 
        WHERE status_keanggotaan = 'Aktif'
    ");

    $breakdown = [];
    while ($row = $result_breakdown->fetch_assoc()) {
        $breakdown[] = $row;
    }
    $data['simpanan_breakdown'] = $breakdown;

    // DETAIL TRANSAKSI SIMPANAN PERIODE INI
    $result_transaksi = $conn->query("
        SELECT 
            p.tanggal_bayar,
            a.nama,
            a.no_anggota,
            p.jenis_simpanan,
            p.jenis_transaksi,
            p.jumlah,
            p.metode,
            p.bank_tujuan
        FROM pembayaran p
        JOIN anggota a ON p.anggota_id = a.id
        WHERE p.status_bayar = 'Lunas'
        AND p.tanggal_bayar BETWEEN '$start_date' AND '$end_date'
        ORDER BY p.tanggal_bayar DESC
        LIMIT 50
    ");

    $transaksi = [];
    while ($row = $result_transaksi->fetch_assoc()) {
        $transaksi[] = $row;
    }
    $data['transaksi_simpanan'] = $transaksi;

    return $data;
}

function getKasBankData($conn, $start_date, $end_date)
{
    $data = [];
    $rekening_list = ['Kas Tunai', 'Bank MANDIRI', 'Bank BRI', 'Bank BNI'];
    $rekening_data = [];

    foreach ($rekening_list as $nama_rekening) {
        $saldo_rekening = 0;

        // SIMPANAN POKOK & WAJIB
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan IN ('Simpanan Pokok', 'Simpanan Wajib')
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening += (float) $result->fetch_assoc()['total'];

        // SIMPANAN SUKARELA (SETOR)
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'setor'
                AND metode = 'cash'
                AND jenis_simpanan = 'Simpanan Sukarela'
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'setor'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND jenis_simpanan = 'Simpanan Sukarela'
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening += (float) $result->fetch_assoc()['total'];

        // TARIK SUKARELA
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'tarik'
                AND metode = 'cash'
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE status_bayar = 'Lunas'
                AND jenis_transaksi = 'tarik'
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening -= (float) $result->fetch_assoc()['total'];

        // PENJUALAN SEMBAKO
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'cash'
                AND p.tanggal_pesan BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(pd.subtotal), 0) as total 
                FROM pemesanan_detail pd 
                INNER JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                WHERE p.status = 'Terkirim' 
                AND p.metode = 'transfer'
                AND p.bank_tujuan = ?
                AND p.tanggal_pesan BETWEEN ? AND ?
            ");
            $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening += (float) $result->fetch_assoc()['total'];

        // HIBAH
        if ($nama_rekening == 'Kas Tunai') {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'cash'
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(jumlah), 0) as total 
                FROM pembayaran 
                WHERE (jenis_simpanan = 'hibah' OR keterangan LIKE '%hibah%')
                AND metode = 'transfer' 
                AND bank_tujuan = ?
                AND tanggal_bayar BETWEEN ? AND ?
            ");
            $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening += (float) $result->fetch_assoc()['total'];

        // PENGELUARAN APPROVED
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(jumlah), 0) as total 
            FROM pengeluaran 
            WHERE status = 'approved' AND sumber_dana = ?
            AND tanggal BETWEEN ? AND ?
        ");
        $stmt->bind_param("sss", $nama_rekening, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $saldo_rekening -= (float) $result->fetch_assoc()['total'];

        $rekening_data[] = [
            'nama_rekening' => $nama_rekening,
            'saldo_sekarang' => $saldo_rekening,
            'nomor_rekening' => getNomorRekening($nama_rekening),
            'jenis' => ($nama_rekening == 'Kas Tunai') ? 'kas' : 'bank'
        ];
    }

    $data['rekening_data'] = $rekening_data;
    $data['total_kas_bank'] = array_sum(array_column($rekening_data, 'saldo_sekarang'));

    return $data;
}

function getPinjamanData($conn, $start_date, $end_date)
{
    $data = [];

    // SUMMARY PINJAMAN
    $result_summary = $conn->query("
        SELECT 
            COUNT(*) as total_pinjaman,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as pinjaman_aktif,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as pinjaman_ditolak,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as pinjaman_disetujui,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pinjaman_pending,
            COALESCE(SUM(jumlah_pinjaman), 0) as total_nilai_pinjaman,
            COALESCE(SUM(total_bunga), 0) as total_bunga,
            COALESCE(SUM(total_pengembalian), 0) as total_pengembalian
        FROM pinjaman
        WHERE tanggal_pengajuan BETWEEN '$start_date' AND '$end_date'
    ");
    $data['pinjaman_summary'] = $result_summary->fetch_assoc();

    // DETAIL PINJAMAN
    $result_detail = $conn->query("
        SELECT 
            p.*, 
            a.nama, 
            a.no_anggota,
            peng.nama as approved_by_name
        FROM pinjaman p
        JOIN anggota a ON p.id_anggota = a.id
        LEFT JOIN pengurus peng ON p.approved_by = peng.id
        WHERE p.tanggal_pengajuan BETWEEN '$start_date' AND '$end_date'
        ORDER BY p.tanggal_pengajuan DESC
        LIMIT 100
    ");

    $pinjaman_detail = [];
    while ($row = $result_detail->fetch_assoc()) {
        $pinjaman_detail[] = $row;
    }
    $data['pinjaman_detail'] = $pinjaman_detail;

    return $data;
}

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
?>