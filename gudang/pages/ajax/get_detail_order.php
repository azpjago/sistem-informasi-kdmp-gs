<?php
session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] != 'gudang') {
    exit('Akses ditolak');
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

if (isset($_GET['id_order'])) {
    $id_order = $conn->real_escape_string($_GET['id_order']);

    $query = "SELECT od.*, p.nama_produk 
              FROM order_detail od 
              JOIN produk p ON od.id_produk = p.id_produk 
              WHERE od.id_order = '$id_order'";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['nama_produk']}</td>
                    <td>{$row['jumlah']}</td>
                    <td>{$row['satuan']}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='3' class='text-center'>Tidak ada data produk</td></tr>";
    }
}
?>
