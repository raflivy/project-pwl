<?php
// login.php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login or register

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'register') {
        // Register logic
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($username) || empty($password) || empty($confirm_password)) {
            $error = 'Semua field harus diisi';
        } elseif ($password !== $confirm_password) {
            $error = 'Password dan konfirmasi password tidak sama';
        } elseif (strlen($password) < 5) {
            $error = 'Password minimal 5 karakter';
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username sudah digunakan';
                } else {
                    // Insert new admin
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admin (username, password, created_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$username, $hashed_password]);
                    
                    $success = 'Registrasi berhasil! Silakan login.';
                    $mode = 'login';
                }
            } catch(PDOException $e) {
                $error = 'Terjadi kesalahan sistem';
            }
        }
    } else {
        // Login logic
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['message'] = 'Login berhasil!';
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Username atau password salah';
                }
            } catch(PDOException $e) {
                $error = 'Terjadi kesalahan sistem';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode == 'register' ? 'Admin Register' : 'Admin Login'; ?> - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo $mode == 'register' ? 'Admin Register' : 'Admin Login'; ?></h1>
            <p><?php echo $mode == 'register' ? 'Daftar sebagai admin' : 'Masuk untuk mengelola produk'; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($mode == 'login'): ?>
            <!-- Login Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn">Login</button>
            </form>

            <div class="form-switch">
                <p><a href="?mode=register">Daftar</a></p>
            </div>

        <?php else: ?>
            <!-- Register Form -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn">Daftar</button>
            </form>

            <div class="form-switch">
                <p><a href="?mode=login">Login</a></p>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="index.php">← Kembali</a>
        </div>
    </div>
</body>
</html>