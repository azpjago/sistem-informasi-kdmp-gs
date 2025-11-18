<?php
// ajax/get_saldo_anggota.php
session_start();
if (!isset($_SESSION['role'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

require 'koneksi/koneksi.php';

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
    require_once '../functions/saldo_functions.php';

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
