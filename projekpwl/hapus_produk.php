<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get product ID
$product_id = $_GET['id'] ?? 0;
if (!is_numeric($product_id) || $product_id <= 0) {
    $_SESSION['error'] = 'ID produk tidak valid!';
    header('Location: index.php');
    exit();
}

$product = null;
$error = '';

// Get product data first for confirmation
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Check if product has any orders first (optional safety check)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id = ?");
        $stmt->execute([$product_id]);
        $order_count = $stmt->fetchColumn();
        
        if ($order_count > 0) {
            $error = 'Tidak dapat menghapus produk yang sudah memiliki pesanan!';
        } else {
            // Delete the product
            $stmt = $pdo->prepare("DELETE FROM produk WHERE id = ?");
            $stmt->execute([$product_id]);
            
            $_SESSION['message'] = 'Produk "' . $product['nama'] . '" berhasil dihapus!';
            header('Location: index.php');
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Produk - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/dproduk.css">
    
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <h1>Wartech Bu Freya</h1>
            <div class="nav-links">
                <a href="index.php">Dashboard</a>
                <a href="tambah_produk.php">Tambah Produk</a>
                <a href="orders.php">Pesanan</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="delete-container">
        <div class="delete-header">
            <div class="warning-icon">⚠️</div>
            <h2>Konfirmasi Hapus Produk</h2>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <div class="product-info">
                <h3><?php echo htmlspecialchars($product['nama']); ?></h3>
                <p><?php echo htmlspecialchars($product['deskripsi']); ?></p>
                
                <div class="product-meta">
                    <div class="meta-item">
                        <div class="meta-label">Harga</div>
                        <div class="meta-value price">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Deskripsi</div>
                        <div class="meta-value"><?php echo htmlspecialchars($product['deskripsi']); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Stok</div>
                        <div class="meta-value"><?php echo $product['stock']; ?></div>
                    </div>
                </div>
            </div>

            <div class="warning-text">
                <p>⚠️ Peringatan!</p>
                <small>Tindakan ini tidak dapat dibatalkan. Produk akan dihapus secara permanen dari sistem.</small>
            </div>

            <form method="POST" style="margin: 0;">
                <div class="button-group">
                    <button type="submit" name="confirm_delete" class="btn btn-danger">
                        🗑️ Ya, Hapus Produk
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        ❌ Batal
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>