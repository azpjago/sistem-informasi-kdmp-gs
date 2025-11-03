<?php
$anggota_info = null;
$search_term = '';

// Cek apakah form pencarian sudah disubmit
if (isset($_GET['no_anggota']) && !empty($_GET['no_anggota'])) {
    $search_term = trim($_GET['no_anggota']);
    
    // Cari anggota di tabel 'anggota'
    $stmt_anggota = $conn->prepare("SELECT id, no_anggota, nama, alamat FROM anggota WHERE REPLACE(no_anggota, '.', '') = ?");
    $stmt_anggota->bind_param("s", $search_term);
    $stmt_anggota->execute();
    $result_anggota = $stmt_anggota->get_result();
    
    if ($result_anggota->num_rows > 0) {
        $anggota_info = $result_anggota->fetch_assoc();
    }
    $stmt_anggota->close();
}
?>

<h3 class="mb-4">📝 Kartu Nota Simpanan Anggota</h3>

<div class="card mb-4">
    <div class="card-header">
        <strong>Cari Anggota</strong>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="nota">
            <div class="row">
                <div class="col-md-8">
                    <label for="no_anggota" class="form-label">Masukkan Nomor Anggota</label>
                    <input type="text" class="form-control" name="no_anggota" id="no_anggota" value="<?= htmlspecialchars($search_term) ?>" placeholder="Cari berdasarkan nomor anggota tanpa titik..." required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Cari</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['no_anggota'])): ?>
    <hr>
    <?php if ($anggota_info): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Data Anggota Ditemukan</strong>
                <div>
                    <a href="cetak_nota.php?id=<?= $anggota_info['id'] ?>" target="_blank" class="btn btn-danger">Cetak Nota</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>No Anggota:</strong><br><?= htmlspecialchars($anggota_info['no_anggota']) ?></p>
                        <p><strong>Nama:</strong><br><?= htmlspecialchars($anggota_info['nama']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Alamat:</strong><br><?= htmlspecialchars($anggota_info['alamat']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4">
            Anggota dengan nomor <strong><?= htmlspecialchars($search_term) ?></strong> tidak ditemukan.
        </div>
    <?php endif; ?>
<?php endif; ?>