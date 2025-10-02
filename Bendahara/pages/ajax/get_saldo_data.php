<?php
// get_saldo_data.php - REAL SALDO VERSION
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

try {
    // 1. DATA REKENING - Ambil langsung dari kolom saldo_sekarang
    $rekening = [];
    $result = $conn->query("SELECT * FROM rekening WHERE is_active = TRUE");

    while ($row = $result->fetch_assoc()) {
        $rekening[] = [
            'id' => $row['id'],
            'nama_rekening' => $row['nama_rekening'],
            'jenis' => $row['jenis'],
            'nomor_rekening' => $row['nomor_rekening'],
            'saldo_sekarang' => (float) $row['saldo_sekarang']
        ];
    }

    // 2. SALDO UTAMA = total saldo_sekarang semua rekening
    $saldo_utama = 0;
    foreach ($rekening as $rek) {
        $saldo_utama += $rek['saldo_sekarang'];
    }

    // 3. BREAKDOWN PER SUMBER (untuk informasi tambahan)
    // Simpanan Anggota
    $result = $conn->query("SELECT SUM(saldo_total) as total FROM anggota");
    $simpanan_data = $result->fetch_assoc();
    $saldo_simpanan = $simpanan_data['total'] ? (float) $simpanan_data['total'] : 0;

    // Penjualan Sembako
    $result = $conn->query("SELECT SUM(pd.subtotal) as total 
                           FROM pemesanan_detail pd 
                           JOIN pemesanan p ON pd.id_pemesanan = p.id_pemesanan
                           WHERE p.status = 'Terkirim'");
    $penjualan_data = $result->fetch_assoc();
    $saldo_penjualan = $penjualan_data['total'] ? (float) $penjualan_data['total'] : 0;

    // Hibah
    $result = $conn->query("SELECT SUM(jumlah) as total FROM hibah");
    $hibah_data = $result->fetch_assoc();
    $saldo_hibah = $hibah_data['total'] ? (float) $hibah_data['total'] : 0;

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

$conn->close();
?>