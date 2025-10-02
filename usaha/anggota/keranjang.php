<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

// Koneksi database - GUNAKAN SATU STYLE KONEKSI
$conn = mysqli_connect('localhost', 'root', '', 'kdmpgs - v2'); // Ganti spasi dengan underscore

// Cek koneksi
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
$id_anggota = $_SESSION['id'];
// Handle tambah/update keranjang HANYA jika ada data POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug information
    error_log("POST data received: " . print_r($_POST, true));

    if (isset($_POST['tambah_keranjang'])) {
        $id_produk = intval($_POST['id_produk']);
        $jumlah = intval($_POST['jumlah']);

        // Debug
        error_log("Attempting to add product $id_produk with quantity $jumlah for user $id_anggota");

        // Cek apakah produk sudah ada di keranjang
        $cekQuery = mysqli_query($conn, "SELECT * FROM keranjang WHERE id_anggota = $id_anggota AND id_produk = $id_produk");

        if (!$cekQuery) {
            error_log("Error checking cart: " . mysqli_error($conn));
            $_SESSION['error'] = "Error checking cart: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($cekQuery) > 0) {
            // Update jumlah jika sudah ada
            $updateQuery = mysqli_query($conn, "UPDATE keranjang SET jumlah = jumlah + $jumlah WHERE id_anggota = $id_anggota AND id_produk = $id_produk");

            if (!$updateQuery) {
                error_log("Error updating cart: " . mysqli_error($conn));
                $_SESSION['error'] = "Error updating cart: " . mysqli_error($conn);
            } else {
                error_log("Cart updated successfully");
                $_SESSION['success'] = "Produk berhasil ditambahkan ke keranjang";
            }
        } else {
            // Tambah baru jika belum ada
            $insertQuery = mysqli_query($conn, "INSERT INTO keranjang (id_anggota, id_produk, jumlah) VALUES ($id_anggota, $id_produk, $jumlah)");

            if (!$insertQuery) {
                error_log("Error inserting to cart: " . mysqli_error($conn));
                $_SESSION['error'] = "Error inserting to cart: " . mysqli_error($conn);
            } else {
                error_log("Product added to cart successfully");
                $_SESSION['success'] = "Produk berhasil ditambahkan ke keranjang";
            }
        }
    }
    // Handle update jumlah
    if (isset($_POST['update_jumlah'])) {
        $id_keranjang = intval($_POST['id_keranjang']);
        $jumlah = intval($_POST['jumlah']);

        if ($jumlah > 0) {
            mysqli_query($conn, "UPDATE keranjang SET jumlah = $jumlah WHERE id_keranjang = $id_keranjang AND id_anggota = $id_anggota");
        } else {
            mysqli_query($conn, "DELETE FROM keranjang WHERE id_keranjang = $id_keranjang AND id_anggota = $id_anggota");
        }

        $_SESSION['success'] = "Keranjang berhasil diperbarui";
    }

    // Handle hapus item
    if (isset($_POST['hapus_item'])) {
        $id_keranjang = intval($_POST['id_keranjang']);
        mysqli_query($conn, "DELETE FROM keranjang WHERE id_keranjang = $id_keranjang AND id_anggota = $id_anggota");

        $_SESSION['success'] = "Item berhasil dihapus dari keranjang";
    }

    // Redirect ke keranjang.php setelah semua processing
    header("Location: keranjang.php");
    exit();
}
// Ambil data keranjang
$query = mysqli_query(
    $conn,
    "SELECT k.*, p.nama_produk, p.harga, p.gambar, p.stok, p.satuan 
     FROM keranjang k 
     JOIN produk p ON k.id_produk = p.id_produk 
     WHERE k.id_anggota = $id_anggota 
     ORDER BY k.created_at DESC"
);

// Check for query errors
if (!$query) {
    error_log("Error fetching cart: " . mysqli_error($conn));
}

$items = [];
$total_harga = 0;
$total_items = 0;

if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $subtotal = $row['harga'] * $row['jumlah'];
        $total_harga += $subtotal;
        $total_items += $row['jumlah'];

        // Tentukan satuan
        $satuan = "pcs";
        if (isset($row['satuan']) && !empty($row['satuan'])) {
            $satuan = $row['satuan'];
        } else {
            if (stripos($row['kategori'] ?? '', 'sembako') !== false)
                $satuan = "kg";
            if (stripos($row['kategori'] ?? '', 'pupuk') !== false)
                $satuan = "karung";
            if (stripos($row['kategori'] ?? '', 'minuman') !== false)
                $satuan = "botol";
            if (stripos($row['kategori'] ?? '', 'lpg') !== false)
                $satuan = "tabung";
        }

        $row['satuan'] = $satuan;
        $row['subtotal'] = $subtotal;
        $items[] = $row;
    }
}

// Ambil nama pengguna jika tersedia
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Keranjang - Koperasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="assets/css/keranjang.css" rel="stylesheet">
</head>

<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center py-3">
        <div>
          <h1 class="h5 mb-0">Keranjang Belanja</h1>
          <small class="text-white-50">Koperasi Desa Merah Putih Ganjar Sabar</small>
        </div>
        <div class="notification-icon">
          <i class="bi bi-bell"></i>
          <span class="badge">3</span>
        </div>
      </div>
    </div>
  </header>

  <main class="app-main">
    <div class="container py-3">
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (count($items) > 0): ?>
        <!-- Daftar Item Keranjang -->
        <div class="cart-items mb-4">
          <?php foreach ($items as $item): ?>
            <div class="cart-item card border-0 shadow-sm mb-3">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-3">
                    <img src="assets/produk/<?php echo $item['gambar']; ?>" alt="<?php echo $item['nama_produk']; ?>" class="img-fluid rounded">
                  </div>
                  <div class="col-6">
                    <h6 class="mb-1"><?php echo $item['nama_produk']; ?></h6>
                    <p class="mb-1 text-primary">Rp <?php echo number_format($item['harga']); ?> /<?php echo $item['satuan']; ?></p>
                    <small class="text-muted">Stok: <?php echo $item['stok']; ?></small>
                  </div>
                  <div class="col-3">
                    <form method="POST" class="update-form">
                      <input type="hidden" name="id_keranjang" value="<?php echo $item['id_keranjang']; ?>">
                      <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary minus-btn" type="button">-</button>
                        <input type="number" name="jumlah" class="form-control text-center" value="<?php echo $item['jumlah']; ?>" min="1" max="<?php echo $item['stok']; ?>">
                        <button class="btn btn-outline-secondary plus-btn" type="button">+</button>
                      </div>
                      <button type="submit" name="update_jumlah" class="btn btn-link btn-sm p-0 mt-1 d-none">Update</button>
                    </form>
                  </div>
                </div>
                <div class="row mt-2">
                  <div class="col-9">
                    <strong>Subtotal: Rp <?php echo number_format($item['subtotal']); ?></strong>
                  </div>
                  <div class="col-3 text-end">
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="id_keranjang" value="<?php echo $item['id_keranjang']; ?>">
                      <button type="submit" name="hapus_item" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Ringkasan Belanja -->
        <div class="cart-summary card border-0 shadow-sm sticky-bottom">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span>Total Items:</span>
              <strong><?php echo $total_items; ?> item</strong>
            </div>
            <div class="d-flex justify-content-between mb-3">
              <span>Total Harga:</span>
              <strong class="text-primary">Rp <?php echo number_format($total_harga); ?></strong>
            </div>
            <a href="checkout.php" class="btn btn-primary w-100">
              <i class="bi bi-bag-check"></i> Lanjut ke Checkout
            </a>
          </div>
        </div>

      <?php else: ?>
        <!-- Keranjang Kosong -->
        <div class="text-center py-5">
          <i class="bi bi-cart-x display-1 text-muted"></i>
          <h5 class="mt-3">Keranjang belanja kosong</h5>
          <p class="text-muted">Silakan tambahkan produk ke keranjang belanja Anda</p>
          <a href="beranda.php" class="btn btn-primary">
            <i class="bi bi-arrow-left"></i> Belanja Sekarang
          </a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="beranda.php" class="bottom-nav-item">
      <i class="bi bi-house"></i>
      <span>Home</span>
    </a>
    <a href="keranjang.php" class="bottom-nav-item active">
      <i class="bi bi-cart"></i>
      <span>Keranjang</span>
    </a>
    <a href="riwayat.php" class="bottom-nav-item">
      <i class="bi bi-clock-history"></i>
      <span>Riwayat</span>
    </a>
    <a href="profil.php" class="bottom-nav-item">
      <i class="bi bi-person"></i>
      <span>Profil</span>
    </a>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Script untuk tombol +/- quantity
    document.querySelectorAll('.plus-btn').forEach(button => {
      button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        const max = parseInt(input.getAttribute('max'));
        if (input.value < max) {
          input.value = parseInt(input.value) + 1;
          input.parentElement.nextElementSibling.click(); // Trigger update
        }
      });
    });

    document.querySelectorAll('.minus-btn').forEach(button => {
      button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        if (input.value > 1) {
          input.value = parseInt(input.value) - 1;
          input.parentElement.nextElementSibling.click(); // Trigger update
        }
      });
    });

    // Auto update ketika quantity diubah manual
    document.querySelectorAll('input[name="jumlah"]').forEach(input => {
      input.addEventListener('change', function() {
        this.parentElement.nextElementSibling.click(); // Trigger update
      });
    });
  </script>
</body>

</html>