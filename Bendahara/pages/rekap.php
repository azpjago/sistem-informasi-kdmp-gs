<?php
// Koneksi database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error) die("Connection failed: ". $conn->connect_error);

$anggota_list = [];
$search_term = '';

// Cek apakah form pencarian sudah disubmit
if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
    $search_term = trim($_GET['keyword']);
    $search_param = "%" . str_replace('.', '', $search_term) . "%";
    
    // Query untuk mencari anggota berdasarkan nama atau no_anggota tanpa titik
    $sql = "
        SELECT id, no_anggota, nama, jenis_kelamin, alamat
        FROM anggota
        WHERE
            REPLACE(no_anggota, '.', '') LIKE ? OR nama LIKE ?
        LIMIT 20
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $anggota_list = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<h3 class="mb-4">🧾 Riwayat Transaksi Anggota</h3>

<div class="card mb-4">
    <div class="card-header">
        <strong>Cari Anggota</strong>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="rekap">
            <div class="row">
                <div class="col-md-8">
                    <label for="keyword" class="form-label">Masukkan Nomor Anggota atau Nama</label>
                    <input type="text" class="form-control" name="keyword" id="keyword" value="<?= htmlspecialchars($search_term) ?>" placeholder="Cari berdasarkan nomor atau nama anggota..." required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Cari Anggota</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['keyword'])): ?>
    <hr>
    <?php if (!empty($anggota_list)): ?>
        <h4 class="mt-4">Hasil Pencarian untuk "<?= htmlspecialchars($search_term) ?>"</h4>
        
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th>No Anggota</th>
                        <th>Nama Anggota</th>
                        <th>Jenis Kelamin</th>
                        <th>Alamat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anggota_list as $anggota): ?>
                        <tr>
                            <td><?= htmlspecialchars($anggota['no_anggota']) ?></td>
                            <td><?= htmlspecialchars($anggota['nama']) ?></td>
                            <td><?= htmlspecialchars($anggota['jenis_kelamin']) ?></td>
                            <td><?= htmlspecialchars($anggota['alamat']) ?></td>
                            <td class="text-center">
                                <a href="?page=riwayat_anggota&id=<?= $anggota['id'] ?>" class="btn btn-info btn-sm">Lihat Riwayat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4">
            Anggota dengan kata kunci "<strong><?= htmlspecialchars($search_term) ?></strong>" tidak ditemukan.
        </div>
    <?php endif; ?>
<?php endif; ?>