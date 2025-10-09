<?php
// Include history log functions
require_once 'functions/history_log.php';

// FUNGSI GENERATE INVOICE
function generateInvoiceNumber($conn)
{
    $prefix = "INV-PO-";
    $year = date('Y');
    $month = date('m');

    $query = "SELECT COUNT(*) as total FROM purchase_order 
              WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month";
    $result = $conn->query($query);
    $data = $result->fetch_assoc();

    $sequence = $data['total'] + 1;
    return $prefix . $year . $month . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// FUNGSI VALIDASI PERUBAHAN STATUS
function validateStatusChange($current_status, $new_status)
{
    // Jika status saat ini adalah "disetujui", hanya boleh diubah ke "selesai"
    if ($current_status == 'disetujui' && $new_status != 'selesai') {
        return false;
    }

    // Jika status saat ini adalah "selesai", tidak boleh diubah ke status apapun
    if ($current_status == 'selesai') {
        return false;
    }

    // Jika status saat ini adalah "ditolak", tidak boleh diubah ke status apapun
    if ($current_status == 'ditolak') {
        return false;
    }

    return true;
}

// PROSES ORDER BARU
if (isset($_POST['buat_order'])) {
    error_log("POST data: " . print_r($_POST, true));

    $conn->begin_transaction();

    try {
        $id_supplier = $conn->real_escape_string($_POST['id_supplier']);
        $tanggal_pengiriman = $conn->real_escape_string($_POST['tanggal_pengiriman']);
        $status_po = $conn->real_escape_string($_POST['status_po'] ?? '');
        $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');
        $discount = $conn->real_escape_string($_POST['discount'] ?? 0);

        // Generate nomor invoice
        $no_invoice = generateInvoiceNumber($conn);

        // Ambil nama supplier untuk log
        $query_supplier = "SELECT nama_supplier FROM supplier WHERE id_supplier = '$id_supplier'";
        $result_supplier = $conn->query($query_supplier);
        $supplier_data = $result_supplier->fetch_assoc();
        $nama_supplier = $supplier_data['nama_supplier'];

        // Insert order
        $query_order = "INSERT INTO purchase_order (no_invoice, id_supplier, tanggal_order, tanggal_pengiriman, discount, status, keterangan, created_by) 
                        VALUES ('$no_invoice', '$id_supplier', NOW(), '$tanggal_pengiriman', '$discount', '$status_po', '$keterangan', '{$_SESSION['user_id']}')";

        if ($conn->query($query_order)) {
            $id_po = $conn->insert_id;
            $total_po = 0;

            // Panggil fungsi history log untuk pembuatan PO
            log_po_creation($id_po, $no_invoice, $nama_supplier, 'gudang');

            // Insert detail order
            if (isset($_POST['produk']) && is_array($_POST['produk'])) {
                $produk = $_POST['produk'];
                $jumlah = $_POST['jumlah'];
                $sat = $_POST['satuan'];
                $harga_satuan = $_POST['harga_satuan'];
                $qty_kecil = $_POST['qty_kecil'];
                $satuan_kecil = $_POST['satuan_kecil'];

                for ($i = 0; $i < count($produk); $i++) {
                    if (!empty($produk[$i]) && !empty($jumlah[$i]) && !empty($harga_satuan[$i])) {
                        $id_produk = $conn->real_escape_string($produk[$i]);
                        $qty = $conn->real_escape_string($jumlah[$i]);
                        $satuan = $conn->real_escape_string($sat[$i]);
                        $harga = $conn->real_escape_string($harga_satuan[$i]);
                        $total_harga = $qty * $harga;

                        // Validasi qty_kecil
                        $qty_kecil_val = $conn->real_escape_string($qty_kecil[$i]);
                        $satuan_kecil_val = $conn->real_escape_string($satuan_kecil[$i]);

                        if (empty($qty_kecil_val) || $qty_kecil_val <= 0) {
                            throw new Exception("Qty kecil harus diisi dan lebih besar dari 0 untuk produk baris " . ($i + 1));
                        }

                        // Query insert detail
                        $query_detail = "INSERT INTO purchase_order_items 
                                    (id_po, id_supplier_produk, qty, satuan, qty_kecil, satuan_kecil, harga_satuan, total_harga) 
                                    VALUES ('$id_po', '$id_produk', '$qty', '$satuan', '$qty_kecil_val', '$satuan_kecil_val', '$harga', '$total_harga')";

                        if ($conn->query($query_detail)) {
                            $total_po += $total_harga;
                        } else {
                            throw new Exception("Error detail: " . $conn->error);
                        }
                    }
                }
            }

            // Update total PO setelah discount
            $total_setelah_discount = $total_po - ($total_po * ($discount / 100));
            $query_update = "UPDATE purchase_order SET total_po = '$total_setelah_discount' WHERE id_po = '$id_po'";
            $conn->query($query_update);

            $conn->commit();
            $_SESSION['success'] = "Purchase Order berhasil dibuat dengan invoice: $no_invoice";

            // Redirect ke halaman print
            // header("Location: pages/print_po.php?id=" . $id_po);
            exit;

        } else {
            throw new Exception("Error order: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: dashboard.php?page=purchase_order");
        exit;
    }
}

?>