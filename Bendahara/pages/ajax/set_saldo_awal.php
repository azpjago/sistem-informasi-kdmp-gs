<?php
// set_saldo_awal.php - WITH CASH/TRANSFER LOGIC
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

try {
    $created_by = 1; // admin
    
    // Set Saldo Kas Tunai - pakai metode CASH
    if ($_POST['kas'] > 0) {
        $stmt = $conn->prepare("
            INSERT INTO pembayaran 
            (anggota_id, jenis_simpanan, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_by) 
            VALUES (0, 'saldo_awal', ?, 'cash', 'Kas Tunai', 'Lunas', 'Set Saldo Awal - Kas Tunai', ?)
        ");
        $stmt->bind_param("di", $_POST['kas'], $created_by);
        $stmt->execute();
    }
    
    // Set Saldo Bank MANDIRI - pakai metode TRANSFER
    if ($_POST['mandiri'] > 0) {
        $stmt = $conn->prepare("
            INSERT INTO pembayaran 
            (anggota_id, jenis_simpanan, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_by) 
            VALUES (0, 'saldo_awal', ?, 'transfer', 'Bank MANDIRI', 'Lunas', 'Set Saldo Awal - Bank MANDIRI', ?)
        ");
        $stmt->bind_param("di", $_POST['mandiri'], $created_by);
        $stmt->execute();
    }
    
    // Set Saldo Bank BRI - pakai metode TRANSFER
    if ($_POST['bri'] > 0) {
        $stmt = $conn->prepare("
            INSERT INTO pembayaran 
            (anggota_id, jenis_simpanan, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_by) 
            VALUES (0, 'saldo_awal', ?, 'transfer', 'Bank BRI', 'Lunas', 'Set Saldo Awal - Bank BRI', ?)
        ");
        $stmt->bind_param("di", $_POST['bri'], $created_by);
        $stmt->execute();
    }
    
    // Set Saldo Bank BNI - pakai metode TRANSFER
    if ($_POST['bni'] > 0) {
        $stmt = $conn->prepare("
            INSERT INTO pembayaran 
            (anggota_id, jenis_simpanan, jumlah, metode, bank_tujuan, status_bayar, keterangan, created_by) 
            VALUES (0, 'saldo_awal', ?, 'transfer', 'Bank BNI', 'Lunas', 'Set Saldo Awal - Bank BNI', ?)
        ");
        $stmt->bind_param("di", $_POST['bni'], $created_by);
        $stmt->execute();
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Saldo awal berhasil diset dengan logika cash/transfer!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>