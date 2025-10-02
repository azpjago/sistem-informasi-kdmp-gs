<!-- index.php (Login Page) -->
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Koperasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    .login-box {
      width: 90%;
      max-width: 400px;
    }
  </style>
</head>

<body>
  <div class="login-box text-center">
    <img src="assets/logo.png" alt="Logo" class="img-fluid mb-4" style="max-height: 120px;">
    <h2>Login</h2>
    <form action="proses_login.php" method="POST">
      <div class="form-group mb-3">
        <input type="number" name="no_anggota" class="form-control" placeholder="Nomor Anggota" required>
      </div>
      <div class="form-group mb-3">
        <input type="number" name="nik" class="form-control" placeholder="Nomor NIK" required>
      </div>
      <div class="form-group mb-3">
        <input type="date" name="tanggal_lahir" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <small class="d-block mt-3">Koperasi Desa Merah Putih Ganjar Sabar</small>
  </div>
</body>

</html>