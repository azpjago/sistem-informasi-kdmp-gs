<?php
session_start();
if (!isset($_SESSION['id'])) {
  header("Location: index.php");
  exit();
}

// Ambil nama pengguna jika tersedia
$nama = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Pengguna';

// Koneksi database - GUNAKAN SATU STYLE
$conn = mysqli_connect('localhost', 'root', '', 'kdmpgs - v2'); // Gunakan underscore

// Cek koneksi
if (!$conn) {
  die("Koneksi database gagal: " . mysqli_connect_error());
}

// Handle filter
$orderBy = "ORDER BY id_produk DESC";
if (isset($_GET['filter'])) {
  switch ($_GET['filter']) {
    case 'stok_terbanyak':
      $orderBy = "ORDER BY stok DESC";
      break;
    case 'stok_terendah':
      $orderBy = "ORDER BY stok ASC";
      break;
    case 'harga_tertinggi':
      $orderBy = "ORDER BY harga DESC";
      break;
    case 'harga_terendah':
      $orderBy = "ORDER BY harga ASC";
      break;
  }
}

// Filter kategori
$whereClause = "";
if (isset($_GET['kategori']) && $_GET['kategori'] != 'semua') {
  $kategori = mysqli_real_escape_string($conn, $_GET['kategori']);
  $whereClause = "WHERE kategori = '$kategori'";
}

// Query produk
$query = mysqli_query($conn, "SELECT * FROM produk $whereClause $orderBy");

// Ambil kategori unik untuk filter
$kategoriQuery = mysqli_query($conn, "SELECT DISTINCT kategori FROM produk WHERE kategori IS NOT NULL AND kategori != ''");
$kategories = [];
while ($row = mysqli_fetch_assoc($kategoriQuery)) {
  $kategories[] = $row['kategori'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beranda - Koperasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center py-3">
        <div>
          <h1 class="h5 mb-0">Koperasi Desa Merah Putih Ganjar Sabar</h1>
          <small class="text-muted">Wilujeung Sumping, <?php echo htmlspecialchars($nama); ?></small>
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
      <!-- Search Bar (Sticky) -->
      <div class="sticky-search">
        <div class="search-bar mb-3">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" placeholder="Cari produk..." id="searchInput">
          </div>
        </div>

        <!-- Filter Options -->
        <div class="filter-options mb-3">
          <div class="row g-2">
            <div class="col-6">
              <select class="form-select form-select-sm" id="sortFilter">
                <option value="terbaru" <?php echo (!isset($_GET['filter']) || $_GET['filter'] == 'terbaru') ? 'selected' : ''; ?>>Terbaru</option>
                <option value="stok_terbanyak" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'stok_terbanyak') ? 'selected' : ''; ?>>Stok Terbanyak</option>
                <option value="stok_terendah" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'stok_terendah') ? 'selected' : ''; ?>>Stok Terendah</option>
                <option value="harga_tertinggi" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'harga_tertinggi') ? 'selected' : ''; ?>>Harga Tertinggi</option>
                <option value="harga_terendah" <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'harga_terendah') ? 'selected' : ''; ?>>Harga Terendah</option>
              </select>
            </div>
            <div class="col-6">
              <select class="form-select form-select-sm" id="categoryFilter">
                <option value="semua" <?php echo (!isset($_GET['kategori']) || $_GET['kategori'] == 'semua') ? 'selected' : ''; ?>>Semua Kategori</option>
                <?php foreach ($kategories as $kategori): ?>
                  <option value="<?php echo $kategori; ?>" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $kategori) ? 'selected' : ''; ?>>
                    <?php echo ucfirst($kategori); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Carousel Banner -->
      <div id="bannerCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-inner rounded-3 shadow-sm">
          <div class="carousel-item active">
            <img src="assets/banner/banner1.png" class="d-block w-100" alt="Banner 1">
          </div>
          <div class="carousel-item">
            <img src="assets/banner/banner2.png" class="d-block w-100" alt="Banner 2">
          </div>
          <div class="carousel-item">
            <img src="assets/banner/banner3.png" class="d-block w-100" alt="Banner 3">
          </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>
      </div>

      <!-- Products Grid -->
      <h6 class="section-title mb-3">Daftar Produk</h6>
      <div class="row g-3" id="productGrid">
        <?php
        if (mysqli_num_rows($query) > 0) {
          while ($row = mysqli_fetch_assoc($query)) {
            // Tentukan satuan berdasarkan kategori atau default
            $satuan = "pcs";
            if (isset($row['satuan']) && !empty($row['satuan'])) {
              $satuan = $row['satuan'];
            } else {
              // Default satuan berdasarkan kategori
              if (stripos($row['kategori'] ?? '', 'sembako') !== false)
                $satuan = "kg";
              if (stripos($row['kategori'] ?? '', 'pupuk') !== false)
                $satuan = "karung";
              if (stripos($row['kategori'] ?? '', 'minuman') !== false)
                $satuan = "botol";
            }

            echo '
            <div class="col-6 product-item">
              <div class="product-card">
                <a href="detail_produk.php?id=' . $row['id_produk'] . '" class="text-decoration-none">
                  <div class="product-image">
                    <img src="assets/produk/' . $row['gambar'] . '" alt="' . $row['nama_produk'] . '">
                    ' . ($row['stok'] <= 5 ? '<span class="badge bg-warning">Stok Terbatas</span>' : '') . '
                  </div>
                </a>
                <div class="product-details">
                  <a href="detail_produk.php?id=' . $row['id_produk'] . '" class="text-decoration-none text-dark">
                    <h6 class="product-title">' . $row['nama_produk'] . '</h6>
                  </a>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <p class="product-price">Rp ' . number_format($row['harga']) . ' <small class="text-muted">/' . $satuan . '</small></p>
                    <small class="text-muted">Stok: ' . $row['stok'] . '</small>
                  </div>
                  <form method="POST" action="keranjang.php">
                    <input type="hidden" name="id_produk" value="' . $row['id_produk'] . '">
                    <input type="hidden" name="jumlah" value="1">
                    <button type="submit" name="tambah_keranjang" class="btn btn-primary btn-sm w-100">
                      <i class="bi bi-cart-plus"></i> Tambah
                    </button>
                  </form>
                </div>
              </div>
            </div>
            ';
          }
        } else {
          echo '<div class="col-12 text-center py-4"><p>Tidak ada produk ditemukan</p></div>';
        }
        ?>
      </div>
    </div>
  </main>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="beranda.php" class="bottom-nav-item active">
      <i class="bi bi-house"></i>
      <span>Home</span>
    </a>
    <a href="keranjang.php" class="bottom-nav-item">
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
    // Fungsi untuk filter produk
    document.getElementById('sortFilter').addEventListener('change', function() {
      applyFilters();
    });

    document.getElementById('categoryFilter').addEventListener('change', function() {
      applyFilters();
    });

    function applyFilters() {
      const sortFilter = document.getElementById('sortFilter').value;
      const categoryFilter = document.getElementById('categoryFilter').value;
      
      // Redirect dengan parameter filter
      window.location.href = `beranda.php?filter=${sortFilter}&kategori=${categoryFilter}`;
    }

    // Fungsi untuk search produk (client-side)
    document.getElementById('searchInput').addEventListener('input', function() {
      const searchText = this.value.toLowerCase();
      const productItems = document.querySelectorAll('.product-item');
      
      productItems.forEach(item => {
        const productName = item.querySelector('.product-title').textContent.toLowerCase();
        if (productName.includes(searchText)) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });
    // Animasi untuk tombol tambah keranjang dengan fallback
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Biarkan form submit secara normal
            // Hanya tambahkan animasi saja
            
            const button = this.querySelector('.add-to-cart');
            const originalText = button.innerHTML;
            
            // Animasi tombol
            button.innerHTML = '<i class="bi bi-check"></i> Menambah...';
            button.classList.add('added');
            button.disabled = true;
            
            // Fallback: reset tombol setelah 5 detik jika redirect gagal
            setTimeout(() => {
                if (!document.hidden) { // Jika halaman masih terbuka
                    button.innerHTML = originalText;
                    button.classList.remove('added');
                    button.disabled = false;
                    alert('Redirect gagal. Silakan coba lagi atau cek keranjang manual.');
                }
            }, 5000);
        });
    });
</script>
</body>

</html>