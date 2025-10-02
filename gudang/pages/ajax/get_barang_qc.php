<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    exit('Akses ditolak');
}

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

if (isset($_GET['id_barang_masuk'])) {
    $id_barang_masuk = intval($_GET['id_barang_masuk']);

    try {
        // 1. Dapatkan ID PO dari barang_masuk
        $query_po = "SELECT id_po FROM barang_masuk WHERE id_barang_masuk = ?";
        $stmt = $conn->prepare($query_po);
        $stmt->bind_param("i", $id_barang_masuk);
        $stmt->execute();
        $result_po = $stmt->get_result();

        if ($result_po->num_rows === 0) {
            echo json_encode(['error' => 'Data barang masuk tidak ditemukan']);
            exit;
        }

        $barang_masuk = $result_po->fetch_assoc();
        $id_po = $barang_masuk['id_po'];

        // 2. Ambil items dari purchase_order_items dengan JOIN ke supplier_produk
        $query_items = "SELECT 
                        poi.id_item, 
                        sp.nama_produk, 
                        poi.qty, 
                        poi.satuan,
                        poi.qty_kecil,              
                        poi.satuan_kecil,
                        poi.harga_satuan,
                        poi.id_supplier_produk
                    FROM purchase_order_items poi
                    LEFT JOIN supplier_produk sp ON poi.id_supplier_produk = sp.id_supplier_produk
                    WHERE poi.id_po = ?";

        $stmt_items = $conn->prepare($query_items);
        $stmt_items->bind_param("i", $id_po);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        $items = [];
        while ($row = $result_items->fetch_assoc()) {
            // Generate kode_produk dari prefix + id_supplier_produk
            $kode_produk = 'SP' . str_pad($row['id_supplier_produk'], 6, '0', STR_PAD_LEFT);

            $items[] = [
                'id_item' => $row['id_item'] ?? '',
                'nama_produk' => $row['nama_produk'] ?? 'Unknown',
                'qty_kecil' => $row['qty_kecil'] ?? 0,
                'satuan_kecil' => $row['satuan_kecil'] ?? '',
                'kode_produk' => $kode_produk, // Generated kode
                'harga_satuan' => $row['harga_satuan'] ?? 0,
                'id_supplier_produk' => $row['id_supplier_produk'] ?? ''
            ];
        }

        echo json_encode($items);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Parameter id_barang_masuk tidak ditemukan']);
}

$conn->close();
?>