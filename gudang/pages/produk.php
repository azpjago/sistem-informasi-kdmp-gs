<?php
// Koneksi ke database
$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

// Query inventory yang tersedia untuk produk eceran
$query_inventory = "SELECT ir.id_inventory, ir.nama_produk, ir.satuan_kecil, ir.jumlah_tersedia 
                   FROM inventory_ready ir 
                   WHERE ir.status = 'available' AND ir.jumlah_tersedia > 0 
                   ORDER BY ir.nama_produk";
$result_inventory = $conn->query($query_inventory);

// Query produk eceran untuk paket
$query_produk_eceran = "SELECT p.*, ir.nama_produk as nama_inventory, ir.jumlah_tersedia 
                       FROM produk p 
                       LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
                       WHERE p.is_paket = 0 AND p.status = 'aktif' 
                       ORDER BY p.nama_produk";
$result_produk_eceran = $conn->query($query_produk_eceran);

// PERBAIKAN: Simpan hasil query ke array untuk digunakan berulang
$produk_eceran_list = [];
if ($result_produk_eceran && $result_produk_eceran->num_rows > 0) {
    while ($produk = $result_produk_eceran->fetch_assoc()) {
        $produk_eceran_list[] = $produk;
    }
}

// Fungsi helper untuk mendapatkan path upload
function getUploadPath()
{
    $upload_dir = realpath($_SERVER['DOCUMENT_ROOT'] . '/kdmpgs2/uploads/produk/');
    if (!$upload_dir) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/kdmpgs2/uploads/produk/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
    }
    return $upload_dir;
}

// Fungsi untuk get image URL
function getProductImageUrl($filename)
{
    if (empty($filename))
        return '/kdmpgs2/assets/img/no-image.jpg';
    return '/kdmpgs2/uploads/produk/' . $filename;
}

// Menangani aksi hapus
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar, nama_produk FROM produk WHERE id_produk=$id"));

    $upload_dir = getUploadPath();
    if ($row && $row['gambar'] && file_exists($upload_dir . '/' . $row['gambar'])) {
        unlink($upload_dir . '/' . $row['gambar']);
    }

    mysqli_query($conn, "DELETE FROM produk WHERE id_produk=$id");

    header("Location: dashboard.php?page=produk");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_products'])) {
    $action = $_POST['bulk_action'];
    $selected = $_POST['selected_products'];

    if (!is_array($selected)) {
        $selected = [$selected];
    }

    $successCount = 0;
    if ($action == 'delete') {
        foreach ($selected as $id) {
            $id = intval($id);
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM produk WHERE id_produk=$id"));
            $upload_dir = getUploadPath();
            if ($row && $row['gambar'] && file_exists($upload_dir . '/' . $row['gambar'])) {
                unlink($upload_dir . '/' . $row['gambar']);
            }
            if (mysqli_query($conn, "DELETE FROM produk WHERE id_produk=$id")) {
                $successCount++;
            }
        }
        $_SESSION['success'] = "$successCount produk berhasil dihapus.";
    } elseif ($action == 'activate') {
        foreach ($selected as $id) {
            $id = intval($id);
            if (mysqli_query($conn, "UPDATE produk SET status='aktif' WHERE id_produk=$id")) {
                $successCount++;
            }
        }
        $_SESSION['success'] = "$successCount produk diaktifkan.";
    } elseif ($action == 'deactivate') {
        foreach ($selected as $id) {
            $id = intval($id);
            if (mysqli_query($conn, "UPDATE produk SET status='non-aktif' WHERE id_produk=$id")) {
                $successCount++;
            }
        }
        $_SESSION['success'] = "$successCount produk dinonaktifkan.";
    }

    header("Location: dashboard.php?page=produk");
    exit();
}

// Handle Buat Paket
if (isset($_POST['buat_paket'])) {
    $nama = $conn->real_escape_string($_POST['nama_produk']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $satuan = $conn->real_escape_string($_POST['satuan']);
    $harga = intval($_POST['harga']);
    $merk = $conn->real_escape_string($_POST['merk']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $gambar = '';
    $status = $conn->real_escape_string($_POST['status']);
    $is_paket = 1;
    $jumlah = 0;
    $id_inventory = "NULL";

    // Upload gambar
    if (isset($_FILES['gambar']['name']) && $_FILES['gambar']['name'] != '') {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = uniqid('img_') . '.' . strtolower($ext);
        $upload_dir = getUploadPath();
        $upload_path = $upload_dir . '/' . $gambar;
        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            $gambar = '';
        }
    }

    // Insert produk paket
    $query = "INSERT INTO produk 
             (nama_produk, kategori, satuan, harga, jumlah, merk, keterangan_paket, gambar, status, is_paket, id_inventory) 
             VALUES 
             ('$nama', '$kategori', '$satuan', $harga, $jumlah, '$merk', '$keterangan', '$gambar','$status', '$is_paket', $id_inventory)";

    if ($conn->query($query)) {
        $id_produk_baru = $conn->insert_id;

        // Simpan komposisi produk paket
        if (isset($_POST['komposisi_produk'])) {
            $komposisi = $_POST['komposisi_produk'];
            $quantities = $_POST['komposisi_quantity'];

            foreach ($komposisi as $index => $id_produk_komposisi) {
                if (!empty($id_produk_komposisi) && !empty($quantities[$index])) {
                    $quantity = floatval($quantities[$index]);

                    // Dapatkan id_inventory dari produk komponen
                    $result = $conn->query("SELECT id_inventory FROM produk WHERE id_produk = $id_produk_komposisi");
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $id_inventory_komponen = $row['id_inventory'];

                        $conn->query("INSERT INTO produk_paket_items 
                             (id_produk_paket, id_produk_komposisi, id_inventory_komponen, quantity_komponen) 
                             VALUES 
                             ($id_produk_baru, $id_produk_komposisi, $id_inventory_komponen, $quantity)");
                    }
                }
            }
        }
        $_SESSION['success'] = "Produk paket berhasil dibuat";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=produk");
    exit();
}

// Menangani aksi tambah/edit produk ECERAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_produk'])) {
    $nama = $conn->real_escape_string($_POST['nama_produk']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $satuan = $conn->real_escape_string($_POST['satuan']);
    $harga = intval($_POST['harga']);
    $merk = $conn->real_escape_string($_POST['merk']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $gambar = '';
    $status = $conn->real_escape_string($_POST['status']);

    $id_inventory = isset($_POST['id_inventory']) ? intval($_POST['id_inventory']) : 0;
    $jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
    $is_paket = 0;

    // VALIDASI
    if ($id_inventory == 0) {
        $_SESSION['error'] = "Harus memilih inventory!";
        header("Location: dashboard.php?page=produk");
        exit();
    }
    if ($jumlah <= 0) {
        $_SESSION['error'] = "Jumlah konversi harus lebih dari 0!";
        header("Location: dashboard.php?page=produk");
        exit();
    }

    // Validasi kapasitas inventory
    $inventory_check = $conn->query("SELECT jumlah_tersedia FROM inventory_ready WHERE id_inventory = $id_inventory");
    if ($inventory_check && $inventory_check->num_rows > 0) {
        $inventory_data = $inventory_check->fetch_assoc();
        if ($jumlah > $inventory_data['jumlah_tersedia']) {
            $_SESSION['error'] = "Jumlah konversi melebihi stok inventory yang tersedia!";
            header("Location: dashboard.php?page=produk");
            exit();
        }
    }

    $edit = isset($_POST['id_produk']) && $_POST['id_produk'] != '';

    // Upload gambar
    if (isset($_FILES['gambar']['name']) && $_FILES['gambar']['name'] != '') {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = uniqid('img_') . '.' . strtolower($ext);

        $upload_dir = getUploadPath();
        $upload_path = $upload_dir . '/' . $gambar;

        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            $gambar = '';
        }
    }

    if ($edit) {
        // EDIT PRODUK
        $id = intval($_POST['id_produk']);
        $gambar_sql = '';

        if ($gambar) {
            $upload_dir = getUploadPath();
            $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM produk WHERE id_produk=$id"));
            if ($old && $old['gambar'] && file_exists($upload_dir . '/' . $old['gambar'])) {
                unlink($upload_dir . '/' . $old['gambar']);
            }
            $gambar_sql = ", gambar='$gambar'";
        }

        $query = "UPDATE produk SET 
                 nama_produk='$nama', kategori='$kategori', satuan='$satuan', 
                 harga=$harga, jumlah=$jumlah, merk='$merk', keterangan='$keterangan', 
                 status='$status', is_paket='$is_paket', id_inventory='$id_inventory'
                 $gambar_sql 
                 WHERE id_produk=$id";
    } else {
        // TAMBAH PRODUK BARU
        $query = "INSERT INTO produk 
                 (nama_produk, kategori, satuan, harga, jumlah, merk, keterangan, gambar, status, is_paket, id_inventory) 
                 VALUES 
                 ('$nama', '$kategori', '$satuan', $harga, $jumlah, '$merk', '$keterangan', '$gambar','$status', '$is_paket', '$id_inventory')";
    }

    if ($conn->query($query)) {
        $_SESSION['success'] = "Produk berhasil " . ($edit ? "diupdate" : "ditambahkan");
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=produk");
    exit();
}

// Handle Edit Paket
if (isset($_POST['edit_paket'])) {
    $id_produk = intval($_POST['id_produk']);
    $nama = $conn->real_escape_string($_POST['nama_produk']);
    $kategori = $conn->real_escape_string($_POST['kategori']);
    $satuan = $conn->real_escape_string($_POST['satuan']);
    $harga = intval($_POST['harga']);
    $merk = $conn->real_escape_string($_POST['merk']);
    $keterangan = $conn->real_escape_string($_POST['keterangan']);
    $status = $conn->real_escape_string($_POST['status']);
    $gambar = '';
    $gambar_sql = '';

    // Upload gambar jika ada
    if (isset($_FILES['gambar']['name']) && $_FILES['gambar']['name'] != '') {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = uniqid('img_') . '.' . strtolower($ext);
        $upload_dir = getUploadPath();
        $upload_path = $upload_dir . '/' . $gambar;
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
            // Hapus gambar lama jika upload baru berhasil
            $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM produk WHERE id_produk=$id_produk"));
            if ($old && $old['gambar'] && file_exists($upload_dir . '/' . $old['gambar'])) {
                unlink($upload_dir . '/' . $old['gambar']);
            }
            $gambar_sql = ", gambar='$gambar'";
        }
    }

    // Update data produk paket
    $query = "UPDATE produk SET 
             nama_produk='$nama', kategori='$kategori', satuan='$satuan', 
             harga=$harga, merk='$merk', keterangan_paket='$keterangan', 
             status='$status' $gambar_sql 
             WHERE id_produk=$id_produk AND is_paket=1";

    if ($conn->query($query)) {
        // Hapus komposisi lama dan simpan yang baru
        $conn->query("DELETE FROM produk_paket_items WHERE id_produk_paket = $id_produk");

        // Simpan komposisi baru
        if (isset($_POST['komposisi_produk'])) {
            $komposisi = $_POST['komposisi_produk'];
            $quantities = $_POST['komposisi_quantity'];

            foreach ($komposisi as $index => $id_produk_komposisi) {
                if (!empty($id_produk_komposisi) && !empty($quantities[$index])) {
                    $quantity = floatval($quantities[$index]);

                    // Dapatkan id_inventory dari produk komponen
                    $result = $conn->query("SELECT id_inventory FROM produk WHERE id_produk = $id_produk_komposisi");
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $id_inventory_komponen = $row['id_inventory'];

                        $conn->query("INSERT INTO produk_paket_items 
                             (id_produk_paket, id_produk_komposisi, id_inventory_komponen, quantity_komponen) 
                             VALUES 
                             ($id_produk, $id_produk_komposisi, $id_inventory_komponen, $quantity)");
                    }
                }
            }
        }
        $_SESSION['success'] = "Produk paket berhasil diupdate";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: dashboard.php?page=produk");
    exit();
}

// Ambil produk jika edit
$edit = null;
$komposisi_paket = [];
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    if ($id > 0) {
        $res = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk=$id");
        $edit = mysqli_fetch_assoc($res) ?: null;

        // PERBAIKAN: Jika edit produk paket, ambil data komposisi dari tabel produk_paket_items
        if ($edit && $edit['is_paket'] == 1) {
            $komposisi_result = $conn->query("
                SELECT ppi.*, p.nama_produk, p.satuan, p.jumlah 
                FROM produk_paket_items ppi 
                JOIN produk p ON ppi.id_produk_komposisi = p.id_produk 
                WHERE ppi.id_produk_paket = $id
            ");

            // DEBUG: Cek hasil query
            if (!$komposisi_result) {
                echo "<!-- Debug: Query error: " . $conn->error . " -->";
            } else {
                echo "<!-- Debug: Jumlah komposisi ditemukan: " . $komposisi_result->num_rows . " -->";
            }

            if ($komposisi_result && $komposisi_result->num_rows > 0) {
                while ($komposisi = $komposisi_result->fetch_assoc()) {
                    $komposisi_paket[] = $komposisi;
                    echo "<!-- Debug: Komposisi - id: {$komposisi['id_produk_komposisi']}, qty: {$komposisi['quantity_komponen']} -->";
                }
            } else {
                echo "<!-- Debug: Tidak ada komposisi ditemukan untuk produk paket id: $id -->";
            }
        }
    }
}

// Query produk dengan join untuk mendapatkan info inventory
$query = "SELECT p.*, ir.nama_produk as nama_inventory, ir.jumlah_tersedia, ir.satuan_kecil 
          FROM produk p 
          LEFT JOIN inventory_ready ir ON p.id_inventory = ir.id_inventory 
          ORDER BY p.is_paket ASC, p.nama_produk ASC";
$produk = mysqli_query($conn, $query);

// Get quick stats
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$produk_eceran = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE is_paket = 0"))['total'];
$produk_paket = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE is_paket = 1"))['total'];
$total_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT kategori) as total FROM produk"))['total'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Produk</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <h2 class="mb-4">👩🏻‍🌾👨🏻‍🌾 Manajemen Produk</h2>

        <!-- Quick Stats Dashboard -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-cubes"></i>
                        <h5>Total Produk</h5>
                        <h3><?= $total_produk ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-navicon"></i>
                        <h5>Kategori</h5>
                        <h3><?= $total_kategori ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-cube"></i>
                        <h5>Produk Eceran</h5>
                        <h3><?= $produk_eceran ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-shopping-basket"></i>
                        <h5>Produk Paket</h5>
                        <h3><?= $produk_paket ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions & Buttons -->
        <form method="POST" id="bulkForm" class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <select class="form-select" style="width: 150px" name="bulk_action" id="bulkAction">
                        <option value="">Aksi Massal</option>
                        <option value="delete">Hapus Terpilih</option>
                        <option value="activate">Aktifkan</option>
                        <option value="deactivate">Non-Aktifkan</option>
                    </select>
                    <button type="button" class="btn btn-primary ms-2" onclick="applyBulkAction()">Terapkan</button>
                </div>
                <div>
                    <button type="button" class="btn btn-success" onclick="toggleForm('eceran')">
                        <i class="fas fa-plus"></i> Tambah Produk Eceran
                    </button>
                    <button type="button" class="btn btn-warning ms-2" onclick="toggleForm('paket')">
                        <i class="fas fa-shopping-basket"></i> Buat Produk Paket
                    </button>
                </div>
            </div>
            <div id="selectedProductsContainer"></div>
        </form>

        <!-- Modal Crop -->
        <div class="modal fade" id="modalCrop" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-crop me-2"></i>Crop Gambar Produk
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <div class="crop-preview-container mb-3">
                            <img id="imagePreview" class="img-fluid" style="max-height: 400px;">
                        </div>

                        <div class="alert alert-info py-2">
                            <small>
                                <i class="fas fa-mouse-pointer me-1"></i>
                                Drag untuk memilih area. Geser tepian untuk menyesuaikan.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-arrow-left me-1"></i>Batal
                        </button>
                        <button type="button" class="btn btn-success" id="cropButton">
                            <i class="fas fa-check me-1"></i>Simpan Crop
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Tambah Produk Eceran -->
        <div class="card mb-4" id="formEceran" style="display: <?= ($edit && $edit['is_paket'] == 0) ? 'block' : 'none' ?>;">
            <div class="card-header">Form <?= $edit ? 'Edit' : 'Tambah' ?> Produk Eceran</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id_produk" value="<?= $edit['id_produk'] ?? '' ?>">
                    <input type="hidden" name="simpan_produk" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Nama Produk</label>
                            <input type="text" name="nama_produk" value="<?= $edit['nama_produk'] ?? '' ?>"
                                class="form-control" placeholder="Nama Produk" required>
                        </div>
                        <div class="col-md-2">
                            <label>Kategori</label>
                            <select name="kategori" class="form-select" required>
                                <option value="">Kategori</option>
                                <?php
                                foreach (["Sembako", "LPG", "Pupuk", "Elektronik", "Perabotan"] as $k) {
                                    $sel = isset($edit['kategori']) && $edit['kategori'] == $k ? 'selected' : '';
                                    echo "<option value='$k' $sel>$k</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <option value="">Satuan</option>
                                <?php
                                foreach (["Kg", "Gr", "Ltr", "Tbg", "Pcs", "Renteng", "Pack", "Pouch", "Botol", "Kardus"] as $k) {
                                    $sel = isset($edit['satuan']) && $edit['satuan'] == $k ? 'selected' : '';
                                    echo "<option value='$k' $sel>$k</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Harga Jual</label>
                            <input type="number" name="harga" value="<?= $edit['harga'] ?? '' ?>" class="form-control"
                                placeholder="Harga" required min="0">
                        </div>
                        <div class="col-md-2">
                            <label>Merk</label>
                            <input type="text" name="merk" value="<?= $edit['merk'] ?? '' ?>" class="form-control"
                                placeholder="Merk Produk">
                        </div>
                        <div class="col-md-10">
                            <label>Inventory <span class="text-danger">*</span></label>
                            <select name="id_inventory" class="form-select" id="selectInventory" required>
                                <option value="">Pilih Inventory</option>
                                <?php
                                if ($result_inventory && $result_inventory->num_rows > 0) {
                                    $result_inventory->data_seek(0);
                                    while ($inventory = $result_inventory->fetch_assoc()) {
                                        $selected = (isset($edit['id_inventory']) && $edit['id_inventory'] == $inventory['id_inventory']) ? 'selected' : '';
                                        $stok_info = number_format($inventory['jumlah_tersedia'], 2) . ' ' . $inventory['satuan_kecil'];
                                        echo "<option value='{$inventory['id_inventory']}' $selected data-stok='{$inventory['jumlah_tersedia']}'>
                                                {$inventory['nama_produk']} (Stok: $stok_info)
                                            </option>";
                                    }
                                }
                                ?>
                            </select>
                            <small class="text-muted">Pilih inventory yang akan dikonversi menjadi produk eceran</small>
                        </div>
                        <div class="col-md-2">
                            <label>Jumlah Konversi <span class="text-danger">*</span></label>
                            <input type="number" name="jumlah" value="<?= $edit['jumlah'] ?? '' ?>" class="form-control"
                                placeholder="Jumlah" step="0.001" min="0.001" required onchange="validateJumlah()">
                            <small class="text-muted">Berapa banyak inventory yang digunakan per produk</small>
                        </div>
                        <div class="col-md-6">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control"
                                placeholder="Keterangan/deskripsi"><?= $edit['keterangan'] ?? '' ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label>Gambar</label>
                            <input type="file" name="gambar" accept="image/*" class="form-control"
                                id="gambarInputEceran">
                            <?php if ($edit && !empty($edit['gambar'])): ?>
                                <div class="mt-2">
                                    <img src="<?= getProductImageUrl($edit['gambar']) ?>" style="height:60px"
                                        class="img-thumbnail">
                                    <br>
                                    <small class="text-muted">Gambar saat ini</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-select" required>
                                <option value="">Status</option>
                                <option value="aktif" <?= (isset($edit['status']) && $edit['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                <option value="non-aktif" <?= (isset($edit['status']) && $edit['status'] == 'non-aktif') ? 'selected' : '' ?>>Non-Aktif</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Simpan Produk</button>
                            <?php if ($edit): ?>
                                <a href="?page=produk" class="btn btn-secondary ms-2">Batal</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary ms-2"
                                    onclick="toggleForm('none')">Batal</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form Buat/Edit Produk Paket -->
        <div class="card mb-4" id="formPaket"
            style="display: <?= ($edit && $edit['is_paket'] == 1) ? 'block' : 'none' ?>;">
            <div class="card-header"><?= $edit ? 'Edit' : 'Buat' ?> Produk Paket</div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="<?= $edit ? 'edit_paket' : 'buat_paket' ?>" value="1">
                    <?php if ($edit): ?>
                        <input type="hidden" name="id_produk" value="<?= $edit['id_produk'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Nama Paket</label>
                            <input type="text" name="nama_produk" value="<?= $edit['nama_produk'] ?? '' ?>"
                                class="form-control" placeholder="Nama Paket" required>
                        </div>
                        <div class="col-md-2">
                            <label>Kategori</label>
                            <select name="kategori" class="form-select" required>
                                <option value="">Kategori</option>
                                <?php
                                foreach (["Sembako", "LPG", "Pupuk", "Elektronik", "Perabotan"] as $k) {
                                    $sel = isset($edit['kategori']) && $edit['kategori'] == $k ? 'selected' : '';
                                    echo "<option value='$k' $sel>$k</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Satuan</label>
                            <select name="satuan" class="form-select" required>
                                <option value="">Satuan</option>
                                <?php
                                foreach (["Paket", "Set", "Dus", "Box"] as $k) {
                                    $sel = isset($edit['satuan']) && $edit['satuan'] == $k ? 'selected' : '';
                                    echo "<option value='$k' $sel>$k</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Harga Paket</label>
                            <input type="number" name="harga" value="<?= $edit['harga'] ?? '' ?>" class="form-control"
                                placeholder="Harga Paket" required min="0">
                        </div>
                        <div class="col-md-2">
                            <label>Merk</label>
                            <input type="text" name="merk" value="<?= $edit['merk'] ?? '' ?>" class="form-control"
                                placeholder="Merk Paket">
                        </div>
                        <div class="col-md-6">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control"
                                placeholder="Keterangan paket"><?= $edit['keterangan_paket'] ?? '' ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label>Gambar Paket</label>
                            <input type="file" name="gambar" accept="image/*" class="form-control"
                                id="gambarInputPaket">
                            <?php if ($edit && !empty($edit['gambar'])): ?>
                                <div class="mt-2">
                                    <img src="<?= getProductImageUrl($edit['gambar']) ?>" style="height:60px"
                                        class="img-thumbnail">
                                    <br>
                                    <small class="text-muted">Gambar saat ini</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-select" required>
                                <option value="">Status</option>
                                <option value="aktif" <?= (isset($edit['status']) && $edit['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                <option value="non-aktif" <?= (isset($edit['status']) && $edit['status'] == 'non-aktif') ? 'selected' : '' ?>>Non-Aktif</option>
                            </select>
                        </div>

                        <!-- Komposisi Paket -->
                        <div class="col-12 mt-3 p-3 border rounded">
                            <h6>Komposisi Produk Paket</h6>
                            <div id="komposisiContainer">
                                <!-- Item komposisi akan ditambahkan di sini -->
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                                onclick="tambahKomposisi()">
                                <i class="fas fa-plus"></i> Tambah Item
                            </button>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-warning"><?= $edit ? 'Update' : 'Buat' ?>
                                Paket</button>
                            <a href="?page=produk" class="btn btn-secondary ms-2">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Produk -->
        <div class="table-responsive">
            <table id="tabelProduk" class="table table-bordered table-striped align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th width="20"><input type="checkbox" id="selectAll"></th>
                        <th>Gambar</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Konversi</th>
                        <th>Inventory</th>
                        <th>Satuan</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    mysqli_data_seek($produk, 0);
                    while ($row = mysqli_fetch_assoc($produk)):
                        ?>
                        <tr>
                            <td><input type="checkbox" class="product-checkbox" name="selected_products[]"
                                    value="<?= $row['id_produk'] ?>"></td>
                            <td>
                                <?php if ($row['gambar']): ?>
                                    <img src="<?= getProductImageUrl($row['gambar']) ?>" style="height:40px">
                                <?php else: ?>
                                    <span class="text-muted">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                            <td><?= htmlspecialchars($row['kategori']) ?></td>
                            <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                            <td>
                                <?php if ($row['is_paket'] == 0): ?>
                                    <?= number_format($row['jumlah'], 2) ?>
                                    <br><small class="text-muted"><?= $row['satuan_kecil'] ?? '' ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['is_paket'] == 0 && $row['nama_inventory']): ?>
                                    <?= htmlspecialchars($row['nama_inventory']) ?>
                                    <br><small class="text-info">Stok: <?= number_format($row['jumlah_tersedia'], 2) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['satuan']) ?></td>
                            <td>
                                <?php if ($row['is_paket'] == 0): ?>
                                    <span class="badge bg-success">Eceran</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Paket</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $row['status'] == 'aktif' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=produk&edit=<?= $row['id_produk'] ?>" class="btn btn-info btn-sm">Edit</a>
                                <a href="?page=produk&hapus=<?= $row['id_produk'] ?>"
                                    onclick="return confirm('Yakin ingin menghapus produk ini?')"
                                    class="btn btn-danger btn-sm">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Fungsi toggle form
        window.toggleForm = function (type) {
            document.getElementById('formEceran').style.display = 'none';
            document.getElementById('formPaket').style.display = 'none';

            if (type === 'eceran') {
                document.getElementById('formEceran').style.display = 'block';
                <?php if (!$edit || ($edit && $edit['is_paket'] == 0)): ?>
                    document.querySelector('#formEceran form').reset();
                <?php endif; ?>
            } else if (type === 'paket') {
                document.getElementById('formPaket').style.display = 'block';
                <?php if (!$edit || ($edit && $edit['is_paket'] == 1)): ?>
                    document.querySelector('#formPaket form').reset();
                    // Reset komposisi hanya jika buat baru
                    <?php if (!$edit): ?>
                        document.getElementById('komposisiContainer').innerHTML = '';
                        tambahKomposisi();
                    <?php endif; ?>
                <?php endif; ?>
            }
        };

        // Load komposisi saat edit paket
        // Load komposisi saat edit paket - PERBAIKAN
        <?php if ($edit && $edit['is_paket'] == 1): ?>
            document.addEventListener('DOMContentLoaded', function () {
                console.log('Loading komposisi untuk produk paket id: <?= $edit['id_produk'] ?>');
                console.log('Jumlah komposisi: <?= count($komposisi_paket) ?>');

                <?php if (!empty($komposisi_paket)): ?>
                    <?php foreach ($komposisi_paket as $index => $komposisi): ?>
                        console.log('Komposisi <?= $index ?>: id=<?= $komposisi['id_produk_komposisi'] ?>, qty=<?= $komposisi['quantity_komponen'] ?>');
                        tambahKomposisiEdit(<?= $komposisi['id_produk_komposisi'] ?>, <?= $komposisi['quantity_komponen'] ?>);
                    <?php endforeach; ?>
                <?php else: ?>
                    console.log('Tidak ada komposisi, menambahkan item kosong');
                    tambahKomposisi();
                <?php endif; ?>
            });
        <?php endif; ?>

        // Fungsi tambah komposisi dengan nilai edit - PERBAIKAN
        // Fungsi tambah komposisi dengan nilai edit - PERBAIKAN
        window.tambahKomposisiEdit = function (id_produk_komposisi, quantity) {
            const container = document.getElementById('komposisiContainer');

            const html = `
        <div class="row mb-2 komposisi-item">
            <div class="col-md-6">
                <select name="komposisi_produk[]" class="form-select" required>
                    <option value="">Pilih Produk Eceran</option>
                    <?php
                    if (!empty($produk_eceran_list)) {
                        foreach ($produk_eceran_list as $produk) {
                            $selected = ''; // Akan di-set via JavaScript
                            $nama_produk = htmlspecialchars($produk['nama_produk']);
                            $jumlah = htmlspecialchars($produk['jumlah']);
                            $satuan = htmlspecialchars($produk['satuan']);
                            $id_produk = htmlspecialchars($produk['id_produk']);

                            echo "<option value='{$id_produk}'>
                                    {$nama_produk} - {$jumlah} {$satuan}
                                </option>";
                        }
                    } else {
                        echo "<option value=''>Tidak ada produk eceran tersedia</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" name="komposisi_quantity[]" class="form-control" 
                       placeholder="Quantity" step="1" min="1" value="${quantity}" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm" onclick="hapusKomposisi(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;

            container.insertAdjacentHTML('beforeend', html);

            // Set selected value setelah element dibuat - PERBAIKAN
            setTimeout(() => {
                const select = container.lastElementChild.querySelector('select');
                if (select) {
                    select.value = id_produk_komposisi;
                    console.log('Set select value to:', id_produk_komposisi, 'Success:', select.value == id_produk_komposisi);
                }
            }, 100);
        };

        // Validasi jumlah konversi
        window.validateJumlah = function () {
            const jumlahInput = document.querySelector('#formEceran [name="jumlah"]');
            const inventorySelect = document.querySelector('#formEceran [name="id_inventory"]');
            const selectedOption = inventorySelect.options[inventorySelect.selectedIndex];

            if (selectedOption && selectedOption.value) {
                const maxStok = parseFloat(selectedOption.getAttribute('data-stok'));
                const jumlah = parseFloat(jumlahInput.value);

                if (jumlah > maxStok) {
                    alert('Jumlah konversi melebihi stok inventory yang tersedia!');
                    jumlahInput.value = maxStok;
                }
            }
        };

        $(document).ready(function () {
            // Inisialisasi DataTables
            if ($('#tabelProduk').length && !$.fn.DataTable.isDataTable('#tabelProduk')) {
                $('#tabelProduk').DataTable({
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: {
                        search: "Cari Produk : ",
                        lengthMenu: "Tampilkan _MENU_ data per halaman",
                        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                        zeroRecords: "Tidak ada data yang cocok",
                        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                        infoFiltered: "(disaring dari _MAX_ total data)",
                        paginate: { first: "Awal", last: "Akhir", next: "Berikutnya", previous: "Sebelumnya" }
                    }
                });
            }
        });

        // FUNGSI TAMBAH ITEM KOMPOSISI UNTUK PAKET - PERBAIKAN
        window.tambahKomposisi = function () {
            const container = document.getElementById('komposisiContainer');
            const index = container.children.length;

            const html = `
        <div class="row mb-2 komposisi-item">
            <div class="col-md-6">
                <select name="komposisi_produk[]" class="form-select" required>
                    <option value="">Pilih Produk Eceran</option>
                    <?php
                    // PERBAIKAN: Gunakan array yang sudah disimpan
                    if (!empty($produk_eceran_list)) {
                        foreach ($produk_eceran_list as $produk) {
                            echo "<option value='{$produk['id_produk']}'>
                                    {$produk['nama_produk']} - {$produk['jumlah']} {$produk['satuan']}
                                </option>";
                        }
                    } else {
                        echo "<option value=''>Tidak ada produk eceran tersedia</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" name="komposisi_quantity[]" class="form-control" 
                       placeholder="Quantity" step="1" min="1" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm" onclick="hapusKomposisi(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>`;

            container.insertAdjacentHTML('beforeend', html);
        };

        // FUNGSI HAPUS ITEM KOMPOSISI
        window.hapusKomposisi = function (button) {
            if (document.querySelectorAll('.komposisi-item').length > 1) {
                button.closest('.komposisi-item').remove();
            }
        };

        // CropperJS untuk form eceran
        let cropperEceran = null;
        const fileInputEceran = document.getElementById('gambarInputEceran');
        if (fileInputEceran) {
            fileInputEceran.addEventListener('change', function (e) {
                handleImageCrop(e, 'eceran');
            });
        }

        // CropperJS untuk form paket
        let cropperPaket = null;
        const fileInputPaket = document.getElementById('gambarInputPaket');
        if (fileInputPaket) {
            fileInputPaket.addEventListener('change', function (e) {
                handleImageCrop(e, 'paket');
            });
        }

        function handleImageCrop(e, type) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                document.getElementById('imagePreview').src = event.target.result;

                // Hancurkan cropper lama
                if (type === 'eceran' && cropperEceran) {
                    cropperEceran.destroy();
                } else if (type === 'paket' && cropperPaket) {
                    cropperPaket.destroy();
                }

                // Tampilkan modal
                const modalEl = document.getElementById('modalCrop');
                if (!window.modalCropInstance) {
                    window.modalCropInstance = new bootstrap.Modal(modalEl);
                }
                window.modalCropInstance.show();

                // Inisialisasi cropper
                const cropper = new Cropper(document.getElementById('imagePreview'), {
                    aspectRatio: 1,
                    viewMode: 1
                });

                // Simpan reference berdasarkan type
                if (type === 'eceran') {
                    cropperEceran = cropper;
                } else {
                    cropperPaket = cropper;
                }
            };
            reader.readAsDataURL(file);
        }

        // Fungsi crop button
        document.getElementById('cropButton').addEventListener('click', function () {
            let cropper = cropperEceran || cropperPaket;
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });
            canvas.toBlob(function (blob) {
                const fileInput = cropperEceran ? document.getElementById('gambarInputEceran') : document.getElementById('gambarInputPaket');
                const file = new File([blob], "cropped_image.jpg", { type: 'image/jpeg' });

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;

                if (window.modalCropInstance) {
                    window.modalCropInstance.hide();
                }
            }, 'image/jpeg', 0.95);
        });

        // Bulk actions
        document.getElementById('selectAll').addEventListener('change', function () {
            document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        function applyBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
            const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

            if (selectedIds.length === 0) {
                alert('Pilih produk terlebih dahulu!');
                return;
            }

            if (!action) {
                alert('Pilih aksi terlebih dahulu!');
                return;
            }

            if (action === 'delete') {
                if (!confirm(`Yakin hapus ${selectedIds.length} produk?`)) {
                    return;
                }
            }

            const existingInputs = document.querySelectorAll('#bulkForm input[name="selected_products[]"]');
            existingInputs.forEach(input => input.remove());

            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_products[]';
                input.value = id;
                document.getElementById('bulkForm').appendChild(input);
            });

            document.getElementById('bulkForm').submit();
        }

        // Auto close form ketika edit
        <?php if ($edit): ?>
            toggleForm('<?= $edit['is_paket'] == 0 ? "eceran" : "paket" ?>');
        <?php endif; ?>
    </script>
</body>

</html>