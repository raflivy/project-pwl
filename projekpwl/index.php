<?php
session_start();
require_once 'config/database.php'; // Pastikan file ini ada dan $pdo terdefinisi

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle adding to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    try {
        $stmt = $pdo->prepare("SELECT id, nama, harga, stock FROM produk WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product && $product['stock'] > 0) {
            $found_in_cart = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['id'] == $product_id) {
                    if ($item['qty'] < $product['stock']) {
                        $item['qty']++;
                        $_SESSION['message'] = htmlspecialchars($product['nama']) . ' kuantitas ditambahkan di keranjang!';
                    } else {
                        $_SESSION['error'] = 'Stok ' . htmlspecialchars($product['nama']) . ' habis atau maksimal di keranjang!';
                    }
                    $found_in_cart = true;
                    break;
                }
            }
            unset($item);

            if (!$found_in_cart) {
                $_SESSION['cart'][] = [
                    'id' => $product['id'],
                    'nama' => $product['nama'],
                    'harga' => $product['harga'],
                    'qty' => 1,
                    'stock_available' => $product['stock']
                ];
                $_SESSION['message'] = htmlspecialchars($product['nama']) . ' berhasil ditambahkan ke keranjang!';
            }
        } else {
            $_SESSION['error'] = 'Produk tidak ditemukan atau stok habis!';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
    $redirect_url = 'index.php';
    if (isset($_GET['sort'])) {
        $redirect_url .= '?sort=' . urlencode($_GET['sort']);
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// --- AWAL MODIFIKASI UNTUK PENGAMBILAN PRODUK DAN TERJUAL ---
$sort_option = $_GET['sort'] ?? 'created_at_desc'; // Default sort
$orderBySQL = "p.created_at DESC"; // Default order, p merujuk ke alias tabel produk

switch ($sort_option) {
    case 'terlaris':
        // 'terjual' akan menjadi alias dari SUM(ip.qty)
        $orderBySQL = "terjual DESC, p.nama ASC";
        break;
    case 'harga_asc':
        $orderBySQL = "p.harga ASC, p.nama ASC";
        break;
    case 'harga_desc':
        $orderBySQL = "p.harga DESC, p.nama ASC";
        break;
    case 'created_at_desc':
    default:
        $orderBySQL = "p.created_at DESC";
        break;
}

// Get all produk with sorting and calculated sold count
try {
    // Query untuk mengambil produk beserta jumlah terjual dari item_pesanan
    // yang status pesanannya 'selesai'
    $sql = "SELECT
                p.id,
                p.nama,
                p.deskripsi,
                p.harga,
                p.stock,
                p.image,
                p.created_at,
                COALESCE(SUM(CASE WHEN ps.status = 'selesai' THEN ip.qty ELSE 0 END), 0) AS terjual
            FROM
                produk p
            LEFT JOIN
                item_pesanan ip ON p.id = ip.id_produk
            LEFT JOIN
                pesanan ps ON ip.id_pesanan = ps.id 
                -- Kita bisa menambahkan 'AND ps.status = 'selesai'' di sini, 
                -- namun CASE WHEN di SUM lebih eksplisit menangani jika produk ada di pesanan yang belum selesai
            GROUP BY
                p.id, p.nama, p.deskripsi, p.harga, p.stock, p.image, p.created_at
            ORDER BY " . $orderBySQL;

    $stmt = $pdo->query($sql);
    $produk = $stmt->fetchAll(PDO::FETCH_ASSOC); // Menggunakan FETCH_ASSOC lebih baik saat ada alias

} catch(PDOException $e) {
    $produk = [];
    $error = "Error loading produk: " . $e->getMessage();
    // Log error untuk debugging
    error_log("SQL Error: " . $e->getMessage() . " | SQL: " . $sql);
}
// --- AKHIR MODIFIKASI UNTUK PENGAMBILAN PRODUK DAN TERJUAL ---


$isLoggedIn = isset($_SESSION['admin_id']);
$adminUsername = '';

if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM admin WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        if ($admin) {
            $adminUsername = htmlspecialchars($admin['username']);
        }
    } catch (PDOException $e) {
        error_log("Error fetching admin username: " . $e->getMessage());
    }
}

$cart_item_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['qty'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/style.css">
    <style>
        .sort-options { margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border-radius: 8px; text-align: right; }
        .sort-options label { margin-right: 10px; font-weight: bold; }
        .sort-options select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background-color: white; }
        .product-sold { font-size: 0.9em; color: #555; margin-bottom: 5px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>Wartech Bu Freya</h1>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <?php if ($isLoggedIn): ?>
                    <a href="pesanan.php">Pesanan</a>
                    <a href="?logout=1">Logout</a>
                <?php else: ?>
                    <a href="keranjang.php">Keranjang (<?php echo $cart_item_count; ?>)</a>
                    <a href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container" id="home">
        <div class="hero">
            <?php if ($isLoggedIn): ?>
                <h2>Halo, <?php echo $adminUsername; ?>!</h2>
            <?php else: ?>
                <h2>Selamat Datang di Wartech Bu Freya</h2>
                <p>Nikmati makanan Indonesia dengan kualitas terbaik yang dibuat dengan cinta, pesan sekarang dan dapatkan pengalaman kuliner yang terangi harimu dengan senyuman karamelku!</p>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="menu-section" id="produk">
            <h2>Menu Makanan</h2>

            <div class="sort-options">
                <label for="sort-select">Urutkan berdasarkan:</label>
                <select id="sort-select" onchange="window.location.href='index.php?sort='+this.value">
                    <option value="created_at_desc" <?php if ($sort_option == 'created_at_desc') echo 'selected'; ?>>Terbaru</option>

                    <option value="terlaris" <?php if ($sort_option == 'terlaris') echo 'selected'; ?>>Terlaris</option>
                    <option value="harga_asc" <?php if ($sort_option == 'harga_asc') echo 'selected'; ?>>Harga (Termurah)</option>
                    <option value="harga_desc" <?php if ($sort_option == 'harga_desc') echo 'selected'; ?>>Harga (Termahal)</option>
                </select>
            </div>

            <div class="produk-grid">
                <?php if (empty($produk) && !isset($error)): ?>
                    <p>Belum ada produk yang tersedia.</p>
                <?php elseif (!empty($produk)): ?>
                    <?php foreach ($produk as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                                         alt="<?php echo htmlspecialchars($product['nama']); ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="image-overlay"></div>
                                <?php else: ?>
                                    <div class="default-icon">🍽️</div>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($product['nama']); ?></h3>
                                <p><?php echo htmlspecialchars($product['deskripsi'] ?? ''); // Tambahkan null coalescing untuk deskripsi jika bisa NULL ?></p>

                                <div class="product-sold">
                                    <?php
                                    // 'terjual' sekarang datang dari alias SUM() di query SQL
                                    $terjual_count = isset($product['terjual']) ? (int)$product['terjual'] : 0;
                                    echo 'Terjual: ' . $terjual_count . ' porsi';
                                    ?>
                                </div>

                                <div class="product-harga">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></div>
                                <div class="product-stock">
                                    <?php if ($product['stock'] > 0): ?>
                                        Stok: <?php echo $product['stock']; ?> porsi
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Stok Habis</span>
                                    <?php endif; ?>
                                </div>

                                <div class="btn-group">
                                    <?php if (!$isLoggedIn): ?>
                                        <?php if ($product['stock'] > 0): ?>
                                            <form method="POST" action="index.php<?php echo isset($_GET['sort']) ? '?sort='.urlencode($_GET['sort']) : ''; ?>" style="display:inline-block;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="add_to_cart" class="btn btn-primary">🧺</button>
                                            </form>
                                            <a href="order.php?id=<?php echo $product['id']; ?>" class="btn btn-success">Pesan Sekarang</a>
                                        <?php else: ?>
                                            <button class="btn" disabled>Stok Habis</button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($isLoggedIn): ?>
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?><?php echo isset($_GET['sort']) ? '&ref_sort='.urlencode($_GET['sort']) : ''; ?>" class="btn">Edit</a>
                                        <a href="hapus_produk.php?id=<?php echo $product['id']; ?><?php echo isset($_GET['sort']) ? '&ref_sort='.urlencode($_GET['sort']) : ''; ?>" class="btn btn-danger" onclick="return confirm('Anda yakin ingin menghapus produk ini?')">Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <div class="product-card add-product-card">
                        <div class="add-product-content">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">➕</div>
                            <h3 style="margin-bottom: 1rem;">Tambah Produk Baru</h3>
                            <a href="tambah_produk.php" class="btn btn-success">Tambah</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const currentSort = new URLSearchParams(window.location.search).get('sort');
        if (currentSort) {
            const sortSelect = document.getElementById('sort-select');
            if (sortSelect) {
                sortSelect.value = currentSort;
            }
        }
    </script>
</body>
</html>