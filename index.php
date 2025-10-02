<?php
session_start();
if (isset($_SESSION['is_logged_in'])) {
    $role = $_SESSION['role'];
    // Perbaikan untuk 'wk.bid usaha' agar sesuai dengan folder
    $redirect_role = str_replace('.', '_', str_replace(' ', '_', strtolower($role)));
    if (file_exists($redirect_role . '/dashboard.php')) {
        header("Location: $redirect_role/dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KDMPS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="main-wrapper">
        <div class="header-text">
            <h1>PORTAL SISTEM INFORMASI</h1>
            <p>Koperasi Desa Merah Putih Ganjar Sabar</p>
        </div>

        <div class="login-container">
            <form action="proses_login.php" method="POST" class="login-form">
                <h2>Login</h2>

                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message">
                        Username atau password salah!
                    </div>
                <?php endif; ?>

                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="fas fa-eye-slash" id="togglePassword"></i>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Koperasi Desa Merah Putih Ganjar Sabar. All rights reserved.</p>
        </footer>
    </div>
    <script src="assets/script.js"></script>
</body>

</html>