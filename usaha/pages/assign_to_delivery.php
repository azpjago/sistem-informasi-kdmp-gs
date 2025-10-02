<?php
// pages/assign_to_delivery.php
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_ids = $_POST['order_ids'];
    $kurir_id = intval($_POST['kurir_id']);

    if (empty($order_ids) || !$kurir_id) {
        echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
        exit();
    }

    $id_list = implode(',', array_map('intval', $order_ids));

    // Update: set status_pengiriman = 'Belum Dikirim' dan assign kurir
    $query = "UPDATE pemesanan 
              SET id_kurir = $kurir_id, 
                  status_pengiriman = 'Belum Dikirim'
              WHERE id_pemesanan IN ($id_list) AND status = 'Disiapkan'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'updated' => mysqli_affected_rows($conn)]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}
?>