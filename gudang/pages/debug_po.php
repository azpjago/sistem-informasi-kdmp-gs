<?php
require 'koneksi/koneksi.php';
error_log("=== DEBUG PO CHECK ===");

// Cek data PO terakhir
$query = "SELECT * FROM purchase_order ORDER BY id_po DESC LIMIT 5";
$result = $conn->query($query);

error_log("Last 5 POs:");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        error_log("PO ID: {$row['id_po']}, Invoice: {$row['no_invoice']}, Status: {$row['status']}, Created: {$row['created_at']}");
    }
} else {
    error_log("No POs found in database");
}

// Cek auto increment
$query_ai = "SELECT AUTO_INCREMENT 
             FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'purchase_order'";
$result_ai = $conn->query($query_ai);
if ($result_ai) {
    $ai = $result_ai->fetch_assoc();
    error_log("Auto increment next ID: " . ($ai['AUTO_INCREMENT'] ?? 'Unknown'));
}

error_log("=== END DEBUG PO CHECK ===");
echo "Debug completed - check error_log";
?>
