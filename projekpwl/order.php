<?php
// order.php
session_start();
require_once 'config/database.php';

// Get produk ID
$id_produk = $_GET['id'] ?? 0;

if (!$id_produk) {
    header('Location: index.php');
    exit();
}

// Get produk details
try {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->execute([$id_produk]);
    $produk = $stmt->fetch();
    
    if (!$produk) {
        $_SESSION['message'] = 'Produk tidak ditemukan';
        header('Location: index.php');
        exit();
    }
    
    if ($produk['stock'] <= 0) {
        $_SESSION['message'] = 'Stok produk habis';
        header('Location: index.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['message'] = 'Terjadi kesalahan sistem';
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $qty = (int)($_POST['qty'] ?? 0);
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat_pelanggan = trim($_POST['alamat_pelanggan'] ?? '');
    
    // Validation
    if ($qty <= 0) {
        $error = 'Jumlah harus lebih dari 0';
    } elseif ($qty > $produk['stock']) {
        $error = 'Jumlah pesanan melebihi stok yang tersedia';
    } elseif (empty($nama_pelanggan)) {
        $error = 'Nama pelanggan harus diisi';
    } elseif (empty($no_telp)) {
        $error = 'Nomor telepon harus diisi';
    } elseif (empty($alamat_pelanggan)) {
        $error = 'Alamat harus diisi';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Create order
            $total_belanja = $produk['harga'] * $qty;
            $stmt = $pdo->prepare("INSERT INTO pesanan (nama_pelanggan, no_telp, alamat_pelanggan, total_belanja) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_pelanggan, $no_telp, $alamat_pelanggan, $total_belanja]);
            $id_pesanan = $pdo->lastInsertId();
            
            // Create order item
            $stmt = $pdo->prepare("INSERT INTO item_pesanan (id_pesanan, id_produk, qty, harga) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_pesanan, $produk['id'], $qty, $produk['harga']]);
            
            // Update produk stock
            $new_stock = $produk['stock'] - $qty;
            $stmt = $pdo->prepare("UPDATE produk SET stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $produk['id']]);
            
            $pdo->commit();
            
            // Redirect to success page
            header('Location: berhasil.php?id_pesanan=' . $id_pesanan);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan <?php echo htmlspecialchars($produk['nama']); ?> - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/order.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <h1>Wartech Bu Freya</h1>
            <div class="nav-links">
                <a href="index.php">← Kembali</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="back-link">
            <a href="index.php" class="btn">← Kembali ke Menu</a>
        </div>

        <div class="order-container">
            <div class="produk-header">
                <div class="produk-icon">🍽️</div>
                <h2><?php echo htmlspecialchars($produk['nama']); ?></h2>
                <p><?php echo htmlspecialchars($produk['deskripsi']); ?></p>
            </div>

            <div class="produk-details">
                <div class="produk-info">
                    <div class="summary-row">
                        <span>Harga per porsi:</span>
                        <span><strong>Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></strong></span>
                    </div>
                    <div class="summary-row">
                        <span>Stok tersedia:</span>
                        <span><strong><?php echo $produk['stock']; ?> porsi</strong></span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="qty">Jumlah Pesanan:</label>
                        <input type="number" id="qty" name="qty" 
                               min="1" max="<?php echo $produk['stock']; ?>" 
                               value="<?php echo htmlspecialchars($_POST['qty'] ?? '1'); ?>" 
                               required onchange="updateTotal()">
                    </div>

                    <div class="form-group">
                        <label for="nama_pelanggan">Nama Lengkap:</label>
                        <input type="text" id="nama_pelanggan" name="nama_pelanggan" 
                               value="<?php echo htmlspecialchars($_POST['nama_pelanggan'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="no_telp">Nomor Telepon:</label>
                        <input type="tel" id="no_telp" name="no_telp" 
                               value="<?php echo htmlspecialchars($_POST['no_telp'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="alamat_pelanggan">Alamat Pengiriman:</label>
                        <textarea id="alamat_pelanggan" name="alamat_pelanggan" 
                                  required><?php echo htmlspecialchars($_POST['alamat_pelanggan'] ?? ''); ?></textarea>
                    </div>

                    <div class="order-summary">
                        <h3>Ringkasan Pesanan</h3>
                        <div class="summary-row">
                            <span id="item-nama"><?php echo htmlspecialchars($produk['nama']); ?></span>
                            <span id="item-qty">x 1</span>
                        </div>
                        <div class="summary-row">
                            <span>Harga per item:</span>
                            <span>Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Pembayaran:</span>
                            <span id="total-harga">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        Pesan Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateTotal() {
            const qty = document.getElementById('qty').value || 1;
            const harga = <?php echo $produk['harga']; ?>;
            const total = qty * harga;
            
            document.getElementById('item-qty').textContent = 'x ' + qty;
            document.getElementById('total-harga').textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        // Trigger on page load
        window.addEventListener('DOMContentLoaded', updateTotal);
    </script>
</body>
</html>