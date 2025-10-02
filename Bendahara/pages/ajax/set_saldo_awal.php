<?php
// set_saldo_awal.php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

try {
    // Update saldo Kas Tunai
    $stmt = $conn->prepare("UPDATE rekening SET saldo_sekarang = ? WHERE nama_rekening = 'Kas Tunai'");
    $stmt->bind_param("d", $_POST['kas']);
    $stmt->execute();

    // Update saldo Bank BCA
    $stmt = $conn->prepare("UPDATE rekening SET saldo_sekarang = ? WHERE nama_rekening = 'Bank BCA'");
    $stmt->bind_param("d", $_POST['bca']);
    $stmt->execute();

    // Update saldo Bank BRI  
    $stmt = $conn->prepare("UPDATE rekening SET saldo_sekarang = ? WHERE nama_rekening = 'Bank BRI'");
    $stmt->bind_param("d", $_POST['bri']);
    $stmt->execute();

    // Update saldo Bank BNI
    $stmt = $conn->prepare("UPDATE rekening SET saldo_sekarang = ? WHERE nama_rekening = 'Bank BNI'");
    $stmt->bind_param("d", $_POST['bni']);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Saldo awal berhasil disimpan!'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>