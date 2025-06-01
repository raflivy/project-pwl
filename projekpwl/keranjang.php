<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$error = '';
$message = '';

// Handle updating quantity
if (isset($_POST['update_qty'])) {
    $product_id = $_POST['product_id'];
    $new_qty = (int)$_POST['new_qty'];
    $action = $_POST['action'] ?? ''; // 'increment' or 'decrement'

    if ($new_qty <= 0) {
        $error = 'Jumlah tidak valid.';
    } else {
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                // Re-validate stock from DB
                try {
                    $stmt = $pdo->prepare("SELECT stock FROM produk WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product_stock_db = $stmt->fetchColumn();

                    if ($action == 'increment' && $item['qty'] + 1 > $product_stock_db) {
                        $error = 'Stok tidak mencukupi untuk ' . htmlspecialchars($item['nama']) . '. Tersedia: ' . $product_stock_db . '.';
                        // Don't change quantity if it exceeds stock
                    } elseif ($action == 'decrement' && $item['qty'] - 1 < 1) {
                        // Don't allow quantity less than 1
                        $error = 'Jumlah pesanan tidak bisa kurang dari 1.';
                    } else {
                        // Apply the increment/decrement
                        if ($action == 'increment') {
                            $item['qty']++;
                        } elseif ($action == 'decrement') {
                            $item['qty']--;
                        } else {
                            // If no specific action, just update to new_qty, with stock check
                            if ($new_qty > $product_stock_db) {
                                $error = 'Stok tidak mencukupi untuk ' . htmlspecialchars($item['nama']) . '. Tersedia: ' . $product_stock_db . '.';
                                $item['qty'] = $product_stock_db; // Adjust quantity to max available
                            } else {
                                $item['qty'] = $new_qty;
                            }
                        }
                        if (empty($error)) {
                           $message = 'Kuantitas ' . htmlspecialchars($item['nama']) . ' diperbarui.';
                        }
                    }
                } catch(PDOException $e) {
                    $error = 'Terjadi kesalahan sistem saat memeriksa stok.';
                }
                break;
            }
        }
        unset($item); // Break the reference
    }
    // Redirect to prevent re-submission, but also allow error/message to show
    header('Location: keranjang.php');
    exit();
}

// Handle removing item
if (isset($_POST['remove_item'])) {
    $product_id_to_remove = $_POST['product_id_to_remove'];
    $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($product_id_to_remove) {
        return $item['id'] != $product_id_to_remove;
    });
    $_SESSION['message'] = 'Produk berhasil dihapus dari keranjang.';
    header('Location: keranjang.php');
    exit();
}

// Handle checkout
if (isset($_POST['checkout'])) {
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat_pelanggan = trim($_POST['alamat_pelanggan'] ?? '');

    if (empty($nama_pelanggan) || empty($no_telp) || empty($alamat_pelanggan)) {
        $error = 'Nama, nomor telepon, dan alamat harus diisi!';
    } elseif (empty($_SESSION['cart'])) {
        $error = 'Keranjang Anda kosong!';
    } else {
        try {
            $pdo->beginTransaction();
            $total_belanja_checkout = 0;
            $items_to_process = [];

            // Re-validate stock and calculate total
            foreach ($_SESSION['cart'] as $cart_item) {
                $stmt = $pdo->prepare("SELECT id, nama, harga, stock FROM produk WHERE id = ?");
                $stmt->execute([$cart_item['id']]);
                $produk_data = $stmt->fetch();

                if (!$produk_data) {
                    throw new Exception("Produk '" . htmlspecialchars($cart_item['nama']) . "' tidak ditemukan.");
                }
                if ($cart_item['qty'] > $produk_data['stock']) {
                    throw new Exception("Stok tidak mencukupi untuk '" . htmlspecialchars($produk_data['nama']) . "'. Tersedia: " . $produk_data['stock'] . ", Diminta: " . $cart_item['qty'] . ".");
                }
                $total_belanja_checkout += $produk_data['harga'] * $cart_item['qty'];
                $items_to_process[] = [
                    'id_produk' => $produk_data['id'],
                    'qty' => $cart_item['qty'],
                    'harga' => $produk_data['harga'],
                    'new_stock' => $produk_data['stock'] - $cart_item['qty']
                ];
            }

            // Insert into pesanan table
            $stmt = $pdo->prepare("INSERT INTO pesanan (nama_pelanggan, no_telp, alamat_pelanggan, total_belanja, waktu_pesanan, status) VALUES (?, ?, ?, ?, NOW(), 'menunggu')");
            $stmt->execute([$nama_pelanggan, $no_telp, $alamat_pelanggan, $total_belanja_checkout]);
            $id_pesanan = $pdo->lastInsertId();

            // Insert into item_pesanan and update produk stock
            foreach ($items_to_process as $item) {
                $stmt = $pdo->prepare("INSERT INTO item_pesanan (id_pesanan, id_produk, qty, harga) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id_pesanan, $item['id_produk'], $item['qty'], $item['harga']]);

                $stmt = $pdo->prepare("UPDATE produk SET stock = ? WHERE id = ?");
                $stmt->execute([$item['new_stock'], $item['id_produk']]);
            }

            $pdo->commit();

            // Clear cart from session
            unset($_SESSION['cart']);
            $_SESSION['message'] = 'Pesanan Anda berhasil diproses!';
            header('Location: berhasil.php?id_pesanan=' . $id_pesanan);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat memproses pesanan: ' . $e->getMessage();
        }
    }
}

// Recalculate cart items for display (in case of changes or errors)
$cart_items_display = [];
$total_cart_price = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $product_ids_in_cart = array_column($_SESSION['cart'], 'id');
    if (!empty($product_ids_in_cart)) {
        try {
            $placeholders = implode(',', array_fill(0, count($product_ids_in_cart), '?'));
            $stmt = $pdo->prepare("SELECT id, nama, harga, stock FROM produk WHERE id IN ($placeholders)");
            $stmt->execute($product_ids_in_cart);
            $products_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $products_map = [];
            foreach ($products_from_db as $p) {
                $products_map[$p['id']] = $p;
            }

            foreach ($_SESSION['cart'] as $s_item) {
                if (isset($products_map[$s_item['id']])) {
                    $db_product = $products_map[$s_item['id']];
                    $current_qty = min($s_item['qty'], $db_product['stock']); // Adjust qty if it exceeds current stock
                    // Ensure qty is at least 1, especially after decrementing
                    $current_qty = max(1, $current_qty);

                    $item_subtotal = $db_product['harga'] * $current_qty;
                    $total_cart_price += $item_subtotal;

                    $cart_items_display[] = [
                        'id' => $db_product['id'],
                        'nama' => $db_product['nama'],
                        'harga' => $db_product['harga'],
                        'qty' => $current_qty,
                        'stock_available' => $db_product['stock'],
                        'subtotal' => $item_subtotal
                    ];
                }
            }
            // Update session cart with reconciled quantities
            $_SESSION['cart'] = $cart_items_display;

        } catch(PDOException $e) {
            $error = 'Terjadi kesalahan saat memuat detail produk di keranjang.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/keranjang.css">
</head>
<body>
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
                <div class="produk-icon">🧺</div>
                <h2>Keranjang Anda</h2>
                <p>Periksa kembali pesanan Anda sebelum melanjutkan.</p>
            </div>

            <div class="produk-details">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div id="cart-items-display">
                    <?php if (empty($cart_items_display)): ?>
                        <div class="empty-cart">
                            <div class="icon">🧺</div>
                            <h3>Keranjang Anda Kosong</h3>
                            <p>Ayo tambahkan beberapa menu lezat ke keranjang Anda!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cart_items_display as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-info">
                                    <div class="cart-item-name"><?php echo htmlspecialchars($item['nama']); ?></div>
                                    <div class="cart-item-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?> / porsi</div>
                                    <div class="cart-item-stock">Stok Tersedia: <?php echo $item['stock_available']; ?></div>
                                </div>
                                <div class="qty-controls">
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="new_qty" value="<?php echo max(1, $item['qty'] - 1); ?>">
                                        <input type="hidden" name="action" value="decrement">
                                        <button type="submit" name="update_qty" class="qty-btn minus-btn" <?php echo ($item['qty'] <= 1) ? 'disabled' : ''; ?>>-</button>
                                    </form>
                                    <span class="current-qty"><?php echo $item['qty']; ?></span>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="new_qty" value="<?php echo min($item['stock_available'], $item['qty'] + 1); ?>">
                                        <input type="hidden" name="action" value="increment">
                                        <button type="submit" name="update_qty" class="qty-btn plus-btn" <?php echo ($item['qty'] >= $item['stock_available']) ? 'disabled' : ''; ?>>+</button>
                                    </form>
                                </div>
                                <div class="cart-item-total">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                                <form method="POST" style="margin-left: 1rem;">
                                    <input type="hidden" name="product_id_to_remove" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="remove_item" class="remove-item-btn">Hapus</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="cart-summary">
                    Total Belanja: <span>Rp <?php echo number_format($total_cart_price, 0, ',', '.'); ?></span>
                </div>

                <?php if (!empty($cart_items_display)): ?>
                    <div class="checkout-form">
                        <h3>Informasi Pengiriman</h3>
                        <form method="POST" action="">
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

                            <button type="submit" name="checkout" class="btn btn-success btn-block">
                                Pesan Sekarang
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>