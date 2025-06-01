<?php
// ==================== EDIT_PRODUCT.PHP ====================
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$product = null;

// Get product ID
$product_id = $_GET['id'] ?? 0;
if (!is_numeric($product_id) || $product_id <= 0) {
    $_SESSION['error'] = 'ID produk tidak valid!';
    header('Location: index.php');
    exit();
}

// Get product data
try {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = 'Produk tidak ditemukan!';
        header('Location: index.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = $_POST['harga'] ?? '';
    $stock = $_POST['stock'] ?? '';
    
    // Handle image upload
    $image_path = $product['image']; // Keep existing image by default
    $new_image_uploaded = false;
    
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
            $new_image_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $new_image_path)) {
                $image_path = $new_image_path;
                $new_image_uploaded = true;
            } else {
                $error = 'Gagal mengupload gambar!';
            }
        }
    }
    
    // Handle remove image option
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        $image_path = null;
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
            $stmt = $pdo->prepare("UPDATE produk SET nama = ?, deskripsi = ?, harga = ?, stock = ?, image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nama, $deskripsi, $harga, $stock, $image_path, $product_id]);
            
            // Delete old image if new one was uploaded successfully
            if ($new_image_uploaded && $product['image'] && file_exists($product['image'])) {
                unlink($product['image']);
            }
            
            // Delete old image if user chose to remove it
            if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && $product['image'] && file_exists($product['image'])) {
                unlink($product['image']);
            }
            
            $_SESSION['message'] = 'Produk berhasil diperbarui!';
            header('Location: index.php');
            exit();
        } catch(PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
            // Delete uploaded image if database update fails
            if ($new_image_uploaded && $image_path && file_exists($image_path)) {
                unlink($image_path);
            }
        }
    } else {
        // Delete uploaded image if validation fails
        if ($new_image_uploaded && $image_path && file_exists($image_path)) {
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
    <title>Edit Produk - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/eproduk.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>🍽️ Warung Online</h1>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="pesanan.php">Pesanan</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <div class="form-header">
            <h2>Edit Produk</h2>
            <p>Perbarui informasi produk</p>
        </div>

        <div class="product-preview">
            <h3>📝 Data Saat Ini:</h3>
            <?php if ($product['image'] && file_exists($product['image'])): ?>
                <div class="current-image">
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['nama']) ?>">
                    <p><small>Gambar saat ini</small></p>
                </div>
            <?php else: ?>
                <div class="current-image">
                    <p><em>Tidak ada gambar</em></p>
                </div>
            <?php endif; ?>
            <p><strong><?= htmlspecialchars($product['nama']) ?></strong></p>
            <div class="product-meta">
                <div class="meta-item">
                    <span class="meta-label">Harga:</span> Rp <?= number_format($product['harga'], 0, ',', '.') ?>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Stok:</span> <?= $product['stock'] ?> porsi
                </div>
                <div class="meta-item">
                    <span class="meta-label">Dibuat:</span> <?= date('d/m/Y H:i', strtotime($product['created_at'])) ?>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama">Nama Produk *</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($_POST['nama'] ?? $product['nama']) ?>" required>
            </div>

            <div class="form-group">
                <label for="deskripsi">Deskripsi *</label>
                <textarea id="deskripsi" name="deskripsi" required><?= htmlspecialchars($_POST['deskripsi'] ?? $product['deskripsi']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="harga">Harga (Rp) *</label>
                <input type="number" id="harga" name="harga" min="1" value="<?= htmlspecialchars($_POST['harga'] ?? $product['harga']) ?>" required>
            </div>

            <div class="form-group">
                <label for="stock">Stok *</label>
                <input type="number" id="stock" name="stock" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="image">Gambar Produk Baru</label>
                <input type="file" id="image" name="image" accept="image/*">    
                <?php if ($product['image'] && file_exists($product['image'])): ?>
                    <div class="remove-image-option">
                        <label>
                            <input type="checkbox" name="remove_image" value="1">Hapus gambar
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-success">Perbarui Produk</button>
            <a href="index.php" class="btn btn-danger">Batal</a>
        </form>
    </div>
</body>
</html>