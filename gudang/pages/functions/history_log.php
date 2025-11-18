<?php
function log_activity($user_id, $user_type, $activity_type, $description, $table_affected = null, $record_id = null)
{
    require 'koneksi/koneksi.php';

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $query = "INSERT INTO history_activity 
              (user_id, user_type, activity_type, description, table_affected, record_id, user_agent, ip_address, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        $conn->close();
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'issssiss', $user_id, $user_type, $activity_type, $description, $table_affected, $record_id, $user_agent, $ip_address);
    $result = mysqli_stmt_execute($stmt);
    $insert_id = mysqli_insert_id($conn);

    mysqli_stmt_close($stmt);
    $conn->close();

    return $result ? $insert_id : false;
}

// Helper function untuk mendapatkan user info yang konsisten
function get_session_user_info()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = 0;
    $user_role = 'system';

    // Cek berbagai kemungkinan session variable
    if (isset($_SESSION['id'])) {
        $user_id = $_SESSION['id'];
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    if (isset($_SESSION['role'])) {
        $user_role = $_SESSION['role'];
    } elseif (isset($_SESSION['user_role'])) {
        $user_role = $_SESSION['user_role'];
    }

    return ['user_id' => $user_id, 'user_role' => $user_role];
}

// ================== FUNGSI UNTUK MODUL GUDANG ==================

// Fungsi untuk log aktivitas inventory
function log_inventory_activity($inventory_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'inventory_ready', $inventory_id);
}

// Fungsi untuk log perubahan status inventory
function log_inventory_status_change($inventory_id, $old_status, $new_status, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status inventory #$inventory_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'inventory_ready', $inventory_id);
}

// Fungsi untuk log rencana penjualan
function log_sales_plan_activity($inventory_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'rencana_penjualan', $inventory_id);
}

// ================== FUNGSI UNTUK MODUL SUPPLIER ==================

// Fungsi untuk log aktivitas supplier
function log_supplier_activity($supplier_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'supplier', $supplier_id);
}

// Fungsi untuk log aktivitas produk supplier
function log_supplier_product_activity($supplier_product_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'supplier_produk', $supplier_product_id);
}

// Fungsi untuk log perubahan harga supplier
function log_supplier_price_change($supplier_product_id, $old_price, $new_price, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah harga produk supplier #$supplier_product_id dari Rp " . number_format($old_price, 0, ',', '.') . " menjadi Rp " . number_format($new_price, 0, ',', '.');
    return log_activity($user_id, $user_role, 'price_change', $description, 'supplier_produk', $supplier_product_id);
}

// ================== FUNGSI UNTUK MODUL PURCHASE ORDER ==================

// Fungsi untuk log aktivitas purchase order
function log_purchase_order_activity($po_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'purchase_order', $po_id);
}

// Fungsi untuk log perubahan status PO
function log_po_status_change($po_id, $old_status, $new_status, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status PO #$po_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'purchase_order', $po_id);
}

// Fungsi untuk log pembuatan PO baru
function log_po_creation($po_id, $invoice_number, $supplier_name, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Membuat Purchase Order $invoice_number untuk supplier $supplier_name";
    return log_activity($user_id, $user_role, 'create', $description, 'purchase_order', $po_id);
}

// ================== FUNGSI EXISTING (DIPERTAHANKAN) ==================

function log_order_activity($order_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pemesanan', $order_id);
}

function log_product_activity($product_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'produk', $product_id);
}

function log_user_activity($user_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id_session = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id_session, $user_role, $activity_type, $description, 'anggota', $user_id);
}

function log_delivery_activity($delivery_id, $activity_type, $description, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'pengiriman', $delivery_id);
}

function log_auth_activity($user_id, $user_type, $activity_type, $description)
{
    return log_activity($user_id, $user_type, $activity_type, $description, 'users', $user_id);
}

function log_order_status_change($order_id, $old_status, $new_status, $user_type = 'pengurus')
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status pesanan #$order_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'pemesanan', $order_id);
}
function log_barang_masuk_activity($barang_masuk_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'barang_masuk', $barang_masuk_id);
}

// Fungsi untuk log perubahan status barang masuk
function log_barang_masuk_status_change($barang_masuk_id, $old_status, $new_status, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status barang masuk #$barang_masuk_id dari $old_status menjadi $new_status";
    return log_activity($user_id, $user_role, 'status_change', $description, 'barang_masuk', $barang_masuk_id);
}
function log_barang_masuk_qc($barang_masuk_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'barang_masuk_qc', $barang_masuk_id);
}
function log_stock_opname_activity($so_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'stock_opname_header', $so_id);
}

function log_stock_opname_detail_activity($so_detail_id, $activity_type, $description, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    return log_activity($user_id, $user_role, $activity_type, $description, 'stock_opname_detail', $so_detail_id);
}
function log_so_creation($so_id, $no_so, $periode_so, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Membuat Stock Opname $no_so (Periode: " . ucfirst($periode_so) . ")";
    return log_activity($user_id, $user_role, 'create', $description, 'stock_opname_header', $so_id);
}
function log_so_status_change($so_id, $old_status, $new_status, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Mengubah status Stock Opname #$so_id dari " . strtoupper(str_replace('_', ' ', $old_status)) . " menjadi " . strtoupper(str_replace('_', ' ', $new_status));
    return log_activity($user_id, $user_role, 'status_change', $description, 'stock_opname_header', $so_id);
}
function log_so_approval($so_id, $no_so, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menyetujui Stock Opname $no_so dan memperbarui inventory";
    return log_activity($user_id, $user_role, 'approval', $description, 'stock_opname_header', $so_id);
}
function log_so_rejection($so_id, $no_so, $alasan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menolak Stock Opname $no_so - Alasan: $alasan";
    return log_activity($user_id, $user_role, 'rejection', $description, 'stock_opname_header', $so_id);
}
function log_so_inventory_movement($so_id, $inventory_id, $jenis_movement, $jumlah, $alasan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "$jenis_movement inventory #$inventory_id sebanyak $jumlah - $alasan";
    return log_activity($user_id, $user_role, 'inventory_adjustment', $description, 'stock_opname_header', $so_id);
}
function log_so_barang_rusak($so_id, $inventory_id, $jumlah_rusak, $jenis_kerusakan, $user_type = null)
{
    $session_info = get_session_user_info();
    $user_id = $session_info['user_id'];
    $user_role = $user_type ?: $session_info['user_role'];

    $description = "Menambahkan barang rusak dari SO #$so_id - Inventory #$inventory_id: $jumlah_rusak unit ($jenis_kerusakan)";
    return log_activity($user_id, $user_role, 'damaged_goods', $description, 'stock_opname_header', $so_id);
}
function logProdukActivity($action, $produk_id, $nama_produk, $additional_info = '')
{
    $descriptions = [
        'tambah_eceran' => "Menambah produk eceran: $nama_produk (ID: $produk_id)",
        'edit_eceran' => "Mengedit produk eceran: $nama_produk (ID: $produk_id)",
        'tambah_paket' => "Membuat produk paket: $nama_produk (ID: $produk_id)",
        'edit_paket' => "Mengedit produk paket: $nama_produk (ID: $produk_id)",
        'hapus' => "Menghapus produk: $nama_produk (ID: $produk_id)",
        'bulk_delete' => "Menghapus multiple produk: $additional_info",
        'bulk_activate' => "Mengaktifkan multiple produk: $additional_info",
        'bulk_deactivate' => "Menonaktifkan multiple produk: $additional_info"
    ];

    $description = $descriptions[$action] ?? "Aksi produk: $action - $nama_produk";

    log_activity(
        $_SESSION['user_id'] ?? 0,
        $_SESSION['role'] ?? 'gudang',
        $action,
        $description,
        'produk',
        $produk_id
    );
}
// Fungsi untuk log aktivitas barang reject
function logBarangRejectActivity($action, $reject_id, $additional_info = '')
{
    $descriptions = [
        'update_status' => "Mengupdate status tindakan barang reject #$reject_id",
        'delete' => "Menghapus data barang reject #$reject_id",
        'view_detail' => "Melihat detail barang reject #$reject_id"
    ];

    $description = $descriptions[$action] ?? "Aksi barang reject: $action - ID: $reject_id";

    if ($additional_info) {
        $description .= " - $additional_info";
    }

    log_activity(
        $_SESSION['user_id'] ?? 0,
        $_SESSION['role'] ?? 'system',
        $action,
        $description,
        'barang_reject',
        $reject_id
    );
}
?>
