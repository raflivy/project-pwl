<?php
require_once 'config/database.php';
$order_id = $_GET['id_pesanan'] ?? 0;
if (!$order_id) {
    header("Location: index.php");
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new Exception("Pesanan tidak ditemukan.");
    }
    $stmt = $pdo->prepare("SELECT ip.*, p.nama FROM item_pesanan ip JOIN produk p ON ip.id_produk = p.id WHERE ip.id_pesanan = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    die("Terjadi kesalahan: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Belanja</title>
    <link rel="stylesheet" href="includes/berhasil.css">
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="store-name">Wartech Bu Freya</div>
            <div class="store-info">
                Jalan In Aja Dulu<br>
                Telp: (021) 911<br>
                s.id/wartechbufreya.com
            </div>
        </div>
        
        <div class="order-info">
            <div><strong>No. Pesanan:</strong> #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div><strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now')); ?></div>
            <div><strong>Pelanggan:</strong> <?php echo htmlspecialchars($order['nama_pelanggan']); ?></div>
            <div><strong>Kasir:</strong> Admin Ganteng </div>
        </div>
        
        <div class="items">
            <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                <span>Item</span>
                <span style="width: 30px; text-align: center;">Qty</span>
                <span style="width: 80px; text-align: right;">Harga</span>
            </div>
            
            <?php foreach ($items as $item): ?>
            <div class="item">
                <div class="item-name"><?php echo htmlspecialchars($item['nama']); ?></div>
                <div class="item-qty"><?php echo $item['qty']; ?>x</div>
                <div class="item-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="total-section">
            <?php 
            $subtotal = $order['total_belanja'];
            $tax = 0; // Jika ada pajak
            $discount = 0; // Jika ada diskon
            ?>
            
            <div class="total-line">
                <span>Subtotal:</span>
                <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
            </div>
            
            <?php if ($tax > 0): ?>
            <div class="total-line">
                <span>Pajak (10%):</span>
                <span>Rp <?php echo number_format($tax, 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($discount > 0): ?>
            <div class="total-line">
                <span>Diskon:</span>
                <span>-Rp <?php echo number_format($discount, 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-line grand-total">
                <span>TOTAL:</span>
                <span>Rp <?php echo number_format($order['total_belanja'], 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <div class="footer">
            ═══════════════════════════<br>
            TERIMA KASIH ATAS PEMBELIAN ANDA<br>
            PASTI RASANYA ENAK BANGET<br>
            JANGAN LUPA BERSYUKUR<br>
            ═══════════════════════════<br>
            <small>Simpan struk ini sebagai bukti pembelian</small>
            <img src="qr.jpg" width="150 px">
        </div>
        
        <a href="index.php" class="btn">← Kembali ke Menu</a>
    </div>
    
    <script>
    // Auto print function (optional)
    function printReceipt() {
        window.print();
    }
    
    // Uncomment line below to auto-print when page loads
    // window.onload = printReceipt;
    </script>
</body>
</html>