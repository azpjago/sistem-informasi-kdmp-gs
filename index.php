<!DOCTYPE html>
<html lang="id">
<?php
session_start();
if (isset($_SESSION['is_logged_in'])) {
    $role = $_SESSION['role'];
    $redirect_role = str_replace('.', '_', str_replace(' ', '_', strtolower($role)));
    if (file_exists($redirect_role . '/dashboard.php')) {
        header("Location: $redirect_role/dashboard.php");
        exit();
    }
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KDMPGS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #16a9ffff;
            --primary-dark: #2474ffff;
            --text: #333333;
            --text-light: #6c757d;
            --error: #dc3545;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 2px solid var(--primary);
            padding: 5px;
        }

        .logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-fallback {
            width: 100%;
            height: 100%;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 35px;
        }

        .logo-section h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .logo-section p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .login-form h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            z-index: 1;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(22, 169, 255, 0.2);
        }

        .input-group #togglePassword {
            left: auto;
            right: 15px;
            cursor: pointer;
            z-index: 1;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .login-btn:hover {
            background: var(--primary-dark);
        }

        .error-message {
            background: rgba(220, 53, 69, 0.1);
            color: var(--error);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 3px solid var(--error);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        footer {
            text-align: center;
            margin-top: 30px;
            color: var(--text-light);
            font-size: 0.8rem;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .logo-container {
                width: 80px;
                height: 80px;
            }
            
            .logo-fallback {
                font-size: 25px;
            }
            
            .logo-section h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-container">
                <img src="assets/logo.png" alt="Logo Koperasi" class="logo" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';">
                <div id="logo-fallback" class="logo-fallback" style="display: none;">
                    <i class="fas fa-handshake"></i>
                </div>
            </div>
            <h1>KDMPGS</h1>
            <p>Koperasi Desa Merah Putih Ganjar Sabar</p>
        </div>

        <form action="proses_login.php" method="POST" class="login-form">
            <h2>Masuk ke Sistem</h2>

            <?php if (isset($_GET['error'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        Username atau password salah
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

        <footer>
            <p>&copy; <?php echo date("Y"); ?> KDMPGS</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            // Toggle password visibility
            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>