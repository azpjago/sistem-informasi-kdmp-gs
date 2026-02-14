<?php
function hapusDataKecualiPengurus($db_name)
{
    $conn = new mysqli('localhost', 'root', '', $db_name);

    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    echo "Menghapus data dari semua tabel KECUALI 'pengurus' dan 'kategori_pengeluaran'<br><br>";

    // Nonaktifkan foreign key check
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Dapatkan semua nama tabel
    $result = $conn->query("SHOW TABLES");
    $tables = [];

    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    // Kosongkan setiap tabel KECUALI 'pengurus'
    foreach ($tables as $table) {
        if ($table !== 'pengurus' && $table !== 'kategori_pengeluaran' && $table !== 'kurir') {
            $conn->query("TRUNCATE TABLE `$table`");
            echo "✓ Tabel `$table` berhasil dikosongkan<br>";
        } else {
            echo "➡ Tabel `$table` DILEWATI (tidak dihapus)<br>";
        }
    }

    // Aktifkan kembali foreign key check
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<br>✅ Semua data berhasil dihapus kecuali tabel 'pengurus'! dan 'kategori_pengeluaran'";
    $conn->close();
}

// Contoh penggunaan
hapusDataKecualiPengurus('kdmpgs');
?>
