<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = $_POST['harga'] ?? '';
    $stock = $_POST['stock'] ?? '';
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $error = 'Format gambar tidak didukung! Gunakan JPG, PNG, GIF, atau WebP.';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $error = 'Ukuran gambar terlalu besar! Maksimal 5MB.';
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('product_') . '.' . $file_extension;
            $image_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $error = 'Gagal mengupload gambar!';
                $image_path = null;
            }
        }
    }
    
    if (empty($nama) || empty($deskripsi) || empty($harga) || empty($stock)) {
        $error = 'Semua field harus diisi!';
    } elseif (!is_numeric($harga) || $harga <= 0) {
        $error = 'Harga harus berupa angka positif!';
    } elseif (!is_numeric($stock) || $stock < 0) {
        $error = 'Stok harus berupa angka non-negatif!';
    }
    
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO produk (nama, deskripsi, harga, stock, image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nama, $deskripsi, $harga, $stock, $image_path]);
            
            $_SESSION['message'] = 'Produk berhasil ditambahkan!';
            header('Location: index.php');
            exit();
        } catch(PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
            // Delete uploaded image if database insert fails
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
        }
    } else {
        // Delete uploaded image if validation fails
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk - Warung Makan Online</title>
    <link rel="stylesheet" href="includes/tproduk.css">

</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>🍽️ Warung Online</h1>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="admin_produk.php">Kelola Produk</a>
                <a href="daftar_pesanan.php">Pesanan</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="form-container">
        <div class="form-header">
            <h2>Tambah Produk Baru</h2>
            <p>Masukkan informasi produk yang akan dijual</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama">Nama Produk *</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="deskripsi">Deskripsi *</label>
                <textarea id="deskripsi" name="deskripsi" required><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="harga">Harga (Rp) *</label>
                <input type="number" id="harga" name="harga" min="1" value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stok *</label>
                <input type="number" id="stock" name="stock" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Gambar Produk</label>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
            </div>
            
            
            <button type="submit" class="btn btn-success">Tambah Produk</button>
            <a href="index.php" class="btn btn-danger">Batal</a>
        </form>
    </div>
</body>
</html>