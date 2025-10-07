<?php
// ajax/get_saldo_anggota.php
session_start();
if (!isset($_SESSION['role'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header('Content-Type: application/json');

try {
    if (!isset($_POST['id_anggota']) || empty($_POST['id_anggota'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ID anggota tidak valid'
        ]);
        exit;
    }

    $id_anggota = (int) $_POST['id_anggota'];

    // Include function
    require_once '../functions/saldo_functions.php'; // Sesuaikan path

    // Get saldo data
    $result = getSaldoData($id_anggota);

    if ($result['success']) {
        echo json_encode([
            'status' => 'success',
            'data' => $result['data']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => $result['message']
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>