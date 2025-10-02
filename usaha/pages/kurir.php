<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "UPDATE kurir SET status = 'Non-Aktif' WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: ?page=kurir&success=Kurir berhasil dinonaktifkan");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $no_hp = $conn->real_escape_string($_POST['no_hp']);
    $kendaraan = $conn->real_escape_string($_POST['kendaraan']);
    $plat_nomor = $conn->real_escape_string($_POST['plat_nomor']);

    if (isset($_POST['edit_id'])) {
        // Edit existing kurir
        $id = intval($_POST['edit_id']);
        $query = "UPDATE kurir SET nama='$nama', no_hp='$no_hp', kendaraan='$kendaraan', plat_nomor='$plat_nomor' WHERE id=$id";
        $success_msg = "Kurir berhasil diupdate";
    } else {
        // Add new kurir
        $query = "INSERT INTO kurir (nama, no_hp, kendaraan, plat_nomor) VALUES ('$nama', '$no_hp', '$kendaraan', '$plat_nomor')";
        $success_msg = "Kurir berhasil ditambahkan";
    }

    if (mysqli_query($conn, $query)) {
        header("Location: ?page=kurir&success=" . urlencode($success_msg));
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get all active kurir
$query = "SELECT * FROM kurir WHERE status = 'Aktif' ORDER BY nama";
$kurir_list = mysqli_query($conn, $query);
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-motorcycle me-2"></i>Management Kurir
    </div>
    <div class="card-body">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-5">
                <!-- Form Tambah/Edit Kurir -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-plus me-2"></i>
                            <?= isset($_GET['edit']) ? 'Edit Kurir' : 'Tambah Kurir Baru' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php
                            $edit_kurir = null;
                            if (isset($_GET['edit'])) {
                                $edit_id = intval($_GET['edit']);
                                $edit_query = "SELECT * FROM kurir WHERE id = $edit_id";
                                $edit_result = mysqli_query($conn, $edit_query);
                                $edit_kurir = mysqli_fetch_assoc($edit_result);
                            }
                            ?>

                            <?php if ($edit_kurir): ?>
                                <input type="hidden" name="edit_id" value="<?= $edit_kurir['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nama Kurir *</label>
                                <input type="text" class="form-control" name="nama"
                                    value="<?= $edit_kurir['nama'] ?? '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">No. HP *</label>
                                <input type="text" class="form-control" name="no_hp"
                                    value="<?= $edit_kurir['no_hp'] ?? '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jenis Kendaraan *</label>
                                <select class="form-select" name="kendaraan" required>
                                    <option value="">-- Pilih Kendaraan --</option>
                                    <option value="Motor" <?= ($edit_kurir['kendaraan'] ?? '') == 'Motor' ? 'selected' : '' ?>>Motor</option>
                                    <option value="Mobil" <?= ($edit_kurir['kendaraan'] ?? '') == 'Mobil' ? 'selected' : '' ?>>Mobil</option>
                                    <option value="Sepeda" <?= ($edit_kurir['kendaraan'] ?? '') == 'Sepeda' ? 'selected' : '' ?>>Sepeda</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Plat Nomor</label>
                                <input type="text" class="form-control" name="plat_nomor"
                                    value="<?= $edit_kurir['plat_nomor'] ?? '' ?>">
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                <?= $edit_kurir ? 'Update Kurir' : 'Tambah Kurir' ?>
                            </button>

                            <?php if ($edit_kurir): ?>
                                <a href="?page=kurir" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <!-- Daftar Kurir -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-list me-2"></i>Daftar Kurir Aktif
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>No. HP</th>
                                        <th>Kendaraan</th>
                                        <th>Plat Nomor</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($kurir_list) > 0): ?>
                                        <?php while ($kurir = mysqli_fetch_assoc($kurir_list)): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($kurir['nama']) ?></td>
                                                <td><?= htmlspecialchars($kurir['no_hp']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= $kurir['kendaraan'] ?></span>
                                                </td>
                                                <td><?= $kurir['plat_nomor'] ?: '-' ?></td>
                                                <td>
                                                    <a href="?page=kurir&edit=<?= $kurir['id'] ?>"
                                                        class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?page=kurir&delete=<?= $kurir['id'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Nonaktifkan kurir ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                Belum ada kurir yang terdaftar
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>