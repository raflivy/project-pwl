<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Handle delete order
if (isset($_POST['delete_order'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete order items first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM item_pesanan WHERE id_pesanan = ?");
        $stmt->execute([$id_pesanan]);
        
        // Delete the order
        $stmt = $pdo->prepare("DELETE FROM pesanan WHERE id = ?");
        $stmt->execute([$id_pesanan]);
        
        // Commit transaction
        $pdo->commit();
        $_SESSION['message'] = "Pesanan berhasil dihapus!";
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $_SESSION['error'] = "Error deleting order: " . $e->getMessage();
    }
    
    header('Location: pesanan.php');
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    $status = trim($_POST['status']);
    
    // Validate status
    $allowed_statuses = ['menunggu', 'selesai', 'dibatalkan'];
    if (in_array($status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id_pesanan]);
            $_SESSION['message'] = "Status pesanan berhasil diupdate!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating status: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Status tidak valid!";
    }
    
    header('Location: pesanan.php');
    exit();
}

// Initialize variables
$pesanan = [];
$error = null;
$pending_count = 0;
$total_count = 0;
$completed_count = 0;

try {
    // Get all orders with basic info - NO JOIN to avoid duplicates
    $stmt = $pdo->prepare("
        SELECT id, nama_pelanggan, no_telp, alamat_pelanggan, 
               total_belanja, status, waktu_pesanan
        FROM pesanan 
        ORDER BY waktu_pesanan DESC
    ");
    $stmt->execute();
    $pesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed items for each order separately
    if (!empty($pesanan)) {
        $stmt_items = $pdo->prepare("
            SELECT pr.nama, ip.harga, ip.qty, (ip.harga * ip.qty) as subtotal
            FROM item_pesanan ip 
            JOIN produk pr ON ip.id_produk = pr.id 
            WHERE ip.id_pesanan = ?
            ORDER BY pr.nama
        ");
        
        foreach ($pesanan as &$order) {
            $stmt_items->execute([$order['id']]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals from items
            $total_produk = count($items);
            $total_item_qty = 0;
            foreach ($items as $item) {
                $total_item_qty += $item['qty'];
            }
            
            $order['items'] = $items;
            $order['total_produk'] = $total_produk;
            $order['total_item_qty'] = $total_item_qty;
        }
        unset($order); // Break reference
    }
    
    // Get statistics with separate queries to avoid any issues
    $stmt_pending = $pdo->prepare("SELECT COUNT(*) as count FROM pesanan WHERE status = 'menunggu'");
    $stmt_pending->execute();
    $pending_count = (int)$stmt_pending->fetchColumn();
    
    $stmt_total = $pdo->prepare("SELECT COUNT(*) as count FROM pesanan");
    $stmt_total->execute();
    $total_count = (int)$stmt_total->fetchColumn();
    
    $stmt_completed = $pdo->prepare("SELECT COUNT(*) as count FROM pesanan WHERE status = 'selesai'");
    $stmt_completed->execute();
    $completed_count = (int)$stmt_completed->fetchColumn();
    
} catch(PDOException $e) {
    $error = "Error loading orders: " . $e->getMessage();
}

// Handle delete confirmation
$show_delete_modal = false;
$delete_order_data = null;
if (isset($_GET['confirm_delete']) && isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    // Get order details for confirmation
    try {
        $stmt = $pdo->prepare("SELECT id, nama_pelanggan FROM pesanan WHERE id = ?");
        $stmt->execute([$order_id]);
        $delete_order_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($delete_order_data) {
            $show_delete_modal = true;
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error loading order data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - Wartech Bu Freya</title>
    <link rel="stylesheet" href="includes/pesanan.css">
    <style>
        /* Additional styles for delete button */
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            margin-left: 8px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            text-decoration: none;
            color: white;
        }
        
        .order-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        
        /* Confirmation modal styles - Pure CSS */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
            margin: 0;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.5;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
            text-decoration: none;
            color: white;
        }
        
        .highlight-warning {
            color: #dc3545;
            font-weight: bold;
        }
    </style>

</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1>Wartech Bu Freya</h1>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="pesanan.php" class="active">Pesanan</a>
                <a href="?logout=1">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>📋 Daftar Pesanan</h2>
            <p>Kelola semua pesanan pelanggan</p>
        </div>

        <?php if (isset($_SESSION['message'])) : ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])) : ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error) : ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="number"><?php echo $total_count; ?></div>
                <div class="label">Total Pesanan</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $pending_count; ?></div>
                <div class="label">Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $completed_count; ?></div>
                <div class="label">Selesai</div>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="orders-section">
            <?php if (empty($pesanan)) : ?>
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <h3>Belum Ada Pesanan</h3>
                    <p>Pesanan akan muncul di sini setelah pelanggan melakukan pemesanan.</p>
                </div>
            <?php else : ?>
                <div class="orders-grid">
                    <?php foreach ($pesanan as $order) : ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <div class="order-id">Pesanan #<?php echo $order['id']; ?></div>
                                    <div class="order-date"><?php echo date('d M Y H:i', strtotime($order['waktu_pesanan'])); ?></div>
                                </div>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>

                            <div class="customer-info">
                                <div class="info-item">
                                    <div class="info-label">Nama Pelanggan</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['nama_pelanggan']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Nomor Telepon</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['no_telp']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Alamat</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['alamat_pelanggan']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Total Produk</div>
                                    <div class="info-value"><?php echo $order['total_produk']; ?> jenis</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Total Item</div>
                                    <div class="info-value"><?php echo $order['total_item_qty']; ?> item</div>
                                </div>
                            </div>

                            <div class="order-items">
                                <div class="items-label">📋 Detail Pesanan:</div>
                                <?php if (!empty($order['items'])) : ?>
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Nama Produk</th>
                                                <th>Harga Satuan</th>
                                                <th>Jumlah</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order['items'] as $item) : ?>
                                                <tr>
                                                    <td class="product-name"><?php echo htmlspecialchars($item['nama']); ?></td>
                                                    <td class="product-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                                    <td class="product-qty"><?php echo $item['qty']; ?>x</td>
                                                    <td class="product-subtotal">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else : ?>
                                    <p style="color: #666; font-style: italic;">Tidak ada detail item</p>
                                <?php endif; ?>
                            </div>

                            <div class="order-summary">
                                <div class="summary-item">
                                    <div class="summary-label">Total Produk</div>
                                    <div class="summary-value"><?php echo $order['total_produk']; ?> jenis</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Total Item</div>
                                    <div class="summary-value"><?php echo $order['total_item_qty']; ?> item</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Total Belanja</div>
                                    <div class="summary-value total-harga">Rp <?php echo number_format($order['total_belanja'], 0, ',', '.'); ?></div>
                                </div>
                            </div>

                            <div class="order-actions">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="id_pesanan" value="<?php echo $order['id']; ?>">
                                    <select name="status" class="status-select" required>
                                        <option value="">Pilih Status</option>
                                        <option value="menunggu" <?php echo ($order['status'] == 'menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="selesai" <?php echo ($order['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                        <option value="dibatalkan" <?php echo ($order['status'] == 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-success">
                                        Update Status
                                    </button>
                                </form>
                                
                                <a href="?confirm_delete=1&order_id=<?php echo $order['id']; ?>" 
                                   class="btn-danger"> Hapus
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal (Pure PHP/CSS) -->
    <?php if ($show_delete_modal && $delete_order_data) : ?>
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">⚠️ Konfirmasi Hapus Pesanan</h3>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pesanan ini?</p>
                    <p><strong>Pelanggan:</strong> <?php echo htmlspecialchars($delete_order_data['nama_pelanggan']); ?></p>
                    <p><strong>Pesanan #</strong><?php echo $delete_order_data['id']; ?></p>
                    <p class="highlight-warning">Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-actions">
                    <a href="pesanan.php" class="btn-cancel">Batal</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="id_pesanan" value="<?php echo $delete_order_data['id']; ?>">
                        <button type="submit" name="delete_order" class="btn-danger">Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>