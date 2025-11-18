<?php
// saldo_helper.php
function updateSaldoRekening($rekening_id, $jumlah, $type = 'tambah')
{
    require 'koneksi/koneksi.php';

    // Update saldo_sekarang (manual)
    $operator = ($type === 'tambah') ? '+' : '-';
    $stmt = $conn->prepare("UPDATE rekening SET saldo_sekarang = saldo_sekarang $operator ? WHERE id = ?");
    $stmt->bind_param("di", $jumlah, $rekening_id);
    $stmt->execute();
    $stmt->close();

    // Update saldo_real_time (otomatis dari procedure)
    $stmt = $conn->prepare("CALL UpdateSaldoRealTime(?)");
    $stmt->bind_param("i", $rekening_id);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    return true;
}

function getSaldoRealTime($rekening_id)
{
    $conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

    if ($conn->connect_error) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT saldo_real_time as saldo FROM rekening WHERE id = ?");
    $stmt->bind_param("i", $rekening_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $saldo = $result['saldo'] ?? 0;

    $stmt->close();
    $conn->close();

    return $saldo;
}
?>
