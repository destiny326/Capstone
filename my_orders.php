<?php
// ============================================================
// my_orders.php — Student/Staff Order Tracker
// ============================================================
require_once 'auth.php';
requireUserLogin();
require_once 'db.php';

$db = getDB();
$userId = $_SESSION['user_id'];

$orders = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 50
");
$orders->execute([$userId]);
$orders = $orders->fetchAll();

// Fetch items for each order
foreach ($orders as &$order) {
    $stmt = $db->prepare("
        SELECT oi.quantity, oi.unit_price, oi.subtotal, m.name
        FROM order_items oi
        JOIN menu m ON oi.menu_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();
}
unset($order);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders – TAMCC Foodie</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="topnav">
    <div class="nav-brand">
        <img src="assets/logo.png" alt="TAMCC Foodie" class="nav-logo">
        <span class="nav-title">TAMCC Foodie</span>
    </div>
    <div class="nav-user">
        <a href="index.php" class="btn btn-ghost">← Menu</a>
        <a href="logout.php" class="btn btn-ghost">Sign Out</a>
    </div>
</nav>

<div class="orders-page">
    <div class="page-header">
        <h1 style="font-family:var(--font-display);font-size:36px;letter-spacing:3px;">MY ORDERS</h1>
    </div>

    <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:60px;color:var(--muted);">
        <div style="font-size:64px;margin-bottom:16px;">🛒</div>
        <p>No orders yet. Go place your first order!</p>
        <a href="index.php" class="btn btn-primary" style="margin-top:16px;">Browse Menu</a>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $order): ?>
    <div class="order-card">
        <div class="order-card-header">
            <span class="order-num"><?= htmlspecialchars($order['order_number']) ?></span>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                <span class="badge badge-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span>
            </div>
        </div>
        <ul class="order-items-list">
            <?php foreach ($order['items'] as $item): ?>
            <li>
                <span><?= $item['quantity'] ?>× <?= htmlspecialchars($item['name']) ?></span>
                <span style="color:var(--gold);">$<?= number_format($item['subtotal'], 2) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div style="display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
            <span style="color:var(--muted);font-size:13px;"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
            <span style="font-family:var(--font-display);font-size:18px;color:var(--gold);">Total: $<?= number_format($order['total_amount'], 2) ?></span>
        </div>
        <?php if ($order['status'] === 'ready'): ?>
        <div style="background:rgba(46,204,113,0.1);border:1px solid var(--success);border-radius:8px;padding:10px;margin-top:12px;text-align:center;color:var(--success);font-weight:600;">
            🎉 Your order is ready for pickup!
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Auto-refresh every 30 seconds to show updated status
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
