<?php
session_start();
if (!isset($_SESSION['id'])) {
  header("Location: index.php");
  exit();
}

$conn = new mysqli('localhost', 'root', '', 'kdmpgs - v2');
if ($conn->connect_error)
  die("Connection failed: " . $conn->connect_error);

// Ambil ID produk dari URL
$id_produk = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Query data produk
$query = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = $id_produk");
$produk = mysqli_fetch_assoc($query);

// Jika produk tidak ditemukan, redirect ke beranda
if (!$produk) {
  header("Location: beranda.php");
  exit();
}

// Hitung jumlah terjual
$queryTerjual = mysqli_query(
  $conn,
  "SELECT COALESCE(SUM(pd.jumlah), 0) as total_terjual 
     FROM pemesanan_detail pd 
     LEFT JOIN pemesanan pm ON pd.id_pemesanan = pm.id_pemesanan 
     WHERE pd.id_produk = $id_produk 
     AND (pm.status = 'Selesai' OR pm.status = 'Selesai')"
);

$totalTerjual = 0;
if ($queryTerjual) {
  $dataTerjual = mysqli_fetch_assoc($queryTerjual);
  $totalTerjual = $dataTerjual['total_terjual'];
}

// Tentukan satuan
$satuan = "pcs";
if (isset($produk['satuan']) && !empty($produk['satuan'])) {
  $satuan = $produk['satuan'];
} else {
  // Default satuan berdasarkan kategori
  if (stripos($produk['kategori'] ?? '', 'sembako') !== false)
    $satuan = "kg";
  if (stripos($produk['kategori'] ?? '', 'pupuk') !== false)
    $satuan = "karung";
  if (stripos($produk['kategori'] ?? '', 'minuman') !== false)
    $satuan = "botol";
  if (stripos($produk['kategori'] ?? '', 'lpg') !== false)
    $satuan = "tabung";
}

// Query review produk dengan pagination
$limit = 5; // Jumlah review per halaman
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Hitung total review
$totalReviewQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM review WHERE id_produk = $id_produk");
$totalReview = mysqli_fetch_assoc($totalReviewQuery)['total'];
$totalPages = ceil($totalReview / $limit);

// Query review dengan pagination
$queryReview = mysqli_query($conn, "SELECT * FROM review WHERE id_produk = $id_produk ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$reviews = [];
while ($row = mysqli_fetch_assoc($queryReview)) {
  $reviews[] = $row;
}

// Hitung rata-rata rating
$avgRating = 0;
$ratingDistribution = [0, 0, 0, 0, 0]; // Untuk menyimpan distribusi rating (1-5)

if ($totalReview > 0) {
  $totalRating = 0;
  $ratingQuery = mysqli_query($conn, "SELECT rating, COUNT(*) as count FROM review WHERE id_produk = $id_produk GROUP BY rating");
  while ($row = mysqli_fetch_assoc($ratingQuery)) {
    $rating = $row['rating'];
    $count = $row['count'];
    $totalRating += $rating * $count;
    $ratingDistribution[$rating - 1] = $count;
  }
  $avgRating = round($totalRating / $totalReview, 1);
}

// Ambil nama pengguna jika tersedia
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $produk['nama_produk']; ?> - Koperasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link href="assets/css/detail_produk.css" rel="stylesheet">
</head>

<body>
  <!-- Header -->
  <header class="app-header">
    <div class="container">
      <div class="d-flex justify-content-between align-items-center py-3">
        <div class="d-flex align-items-center">
          <a href="beranda.php" class="text-white me-3">
            <i class="bi bi-arrow-left"></i>
          </a>
          <div>
            <h1 class="h5 mb-0">Detail Produk</h1>
            <small class="text-white-50">Koperasi Kita</small>
          </div>
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
      <!-- Gambar Produk -->
      <div class="product-image-large mb-4">
        <img src="assets/produk/<?php echo $produk['gambar']; ?>" alt="<?php echo $produk['nama_produk']; ?>"
          class="w-100 rounded-3 shadow-sm">
      </div>

      <!-- Informasi Produk -->
      <div class="product-info-card card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h2 class="h4 product-title"><?php echo $produk['nama_produk']; ?></h2>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="product-price">Rp <?php echo number_format($produk['harga']); ?> <small
                class="text-muted">/<?php echo $satuan; ?></small></div>
            <?php if ($produk['stok'] > 0): ?>
              <span class="badge bg-success">Tersedia</span>
            <?php else: ?>
              <span class="badge bg-danger">Habis</span>
            <?php endif; ?>
          </div>

          <div class="product-meta mb-3">
            <div class="d-flex justify-content-between">
              <span class="text-muted">Stok: <?php echo $produk['stok']; ?> <?php echo $satuan; ?></span>
              <span class="text-muted">Terjual: <?php echo $totalTerjual; ?></span>
            </div>
          </div>

          <div class="d-grid gap-2">
            <?php if ($produk['stok'] > 0): ?>
              <form method="POST" action="keranjang.php">
                <input type="hidden" name="id_produk" value="<?php echo $produk['id_produk']; ?>">
                <input type="hidden" name="jumlah" value="1">
                <button type="submit" name="tambah_keranjang" class="btn btn-primary btn-add-cart">
                  <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                </button>
              </form>
            <?php else: ?>
              <button class="btn btn-secondary" disabled>
                <i class="bi bi-x-circle"></i> Stok Habis
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Deskripsi Produk -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h3 class="h5 mb-3">Deskripsi Produk</h3>
          <p class="product-description">
            <?php echo !empty($produk['deskripsi']) ? nl2br($produk['deskripsi']) : 'Tidak ada deskripsi produk.'; ?>
          </p>
        </div>
      </div>

      <!-- Review Produk -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h5 mb-0">Ulasan Pembeli</h3>
            <div class="rating-overview">
              <div class="avg-rating"><?php echo $avgRating; ?></div>
              <div class="stars">
                <?php
                $fullStars = floor($avgRating);
                $halfStar = ($avgRating - $fullStars) >= 0.5;
                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

                for ($i = 0; $i < $fullStars; $i++) {
                  echo '<i class="bi bi-star-fill text-warning"></i> ';
                }
                if ($halfStar) {
                  echo '<i class="bi bi-star-half text-warning"></i> ';
                }
                for ($i = 0; $i < $emptyStars; $i++) {
                  echo '<i class="bi bi-star text-warning"></i> ';
                }
                ?>
              </div>
              <small class="text-muted">(<?php echo $totalReview; ?> ulasan)</small>
            </div>
          </div>

          <!-- Distribusi Rating -->
          <?php if ($totalReview > 0): ?>
            <div class="rating-distribution mb-4">
              <?php for ($i = 5; $i >= 1; $i--):
                $percentage = $totalReview > 0 ? ($ratingDistribution[$i - 1] / $totalReview) * 100 : 0;
                ?>
                <div class="rating-bar d-flex align-items-center mb-2">
                  <div class="me-2" style="width: 20px;"><?php echo $i; ?> <i
                      class="bi bi-star-fill text-warning small"></i></div>
                  <div class="progress flex-grow-1 me-2" style="height: 8px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percentage; ?>%"
                      aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <div class="text-muted" style="width: 40px; font-size: 0.8rem;"><?php echo $ratingDistribution[$i - 1]; ?>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          <?php endif; ?>

          <?php if ($totalReview > 0): ?>
            <div class="reviews-list">
              <?php foreach ($reviews as $review): ?>
                <div class="review-item border-bottom py-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong><?php echo $review['nama_user']; ?></strong>
                    <div class="review-rating">
                      <?php
                      for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $review['rating']) {
                          echo '<i class="bi bi-star-fill text-warning small"></i> ';
                        } else {
                          echo '<i class="bi bi-star text-warning small"></i> ';
                        }
                      }
                      ?>
                      <small class="text-muted ms-1"><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                    </div>
                  </div>
                  <p class="mb-1"><?php echo $review['komentar']; ?></p>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
              <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination pagination-sm justify-content-center">
                  <?php if ($page > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="detail_produk.php?id=<?php echo $id_produk; ?>&page=<?php echo $page - 1; ?>"
                        aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                      </a>
                    </li>
                  <?php endif; ?>

                  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                      <a class="page-link"
                        href="detail_produk.php?id=<?php echo $id_produk; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>

                  <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                      <a class="page-link" href="detail_produk.php?id=<?php echo $id_produk; ?>&page=<?php echo $page + 1; ?>"
                        aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                      </a>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
            <?php endif; ?>

          <?php else: ?>
            <div class="text-center py-4">
              <i class="bi bi-chat-quote display-4 text-muted"></i>
              <p class="text-muted mt-3">Belum ada ulasan untuk produk ini.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="beranda.php" class="bottom-nav-item">
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
        document.querySelectorAll('.btn-add-cart').forEach(form => {
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