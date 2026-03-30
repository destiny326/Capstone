<?php
// ============================================================
// order_success.php — Post-Payment Order Confirmation
// ============================================================
require_once 'auth.php';
requireUserLogin();
require_once 'config.php';
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$sessionId = $_GET['session_id'] ?? '';
$orderPlaced = false;
$orderNumber = '';
$error = '';

if ($sessionId && !empty($_SESSION['stripe_session_id']) && $sessionId === $_SESSION['stripe_session_id']) {
    try {
        $stripeSession = \Stripe\Checkout\Session::retrieve($sessionId);

        if ($stripeSession->payment_status === 'paid') {
            $db = getDB();
            $cart = json_decode($_SESSION['pending_cart'] ?? '[]', true);
            $instructions = $_SESSION['pending_instructions'] ?? '';
            $userId = $_SESSION['user_id'];

            // Validate cart against DB and compute total
            $total = 0;
            $validatedItems = [];
            foreach ($cart as $item) {
                $id  = (int)($item['id'] ?? 0);
                $qty = (int)($item['qty'] ?? 1);
                $stmt = $db->prepare("SELECT id, name, price FROM menu WHERE id = ? AND is_available = 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) continue;
                $subtotal = $row['price'] * $qty;
                $total += $subtotal;
                $validatedItems[] = ['menu_id'=>$row['id'],'qty'=>$qty,'price'=>$row['price'],'subtotal'=>$subtotal];
            }

            if (!empty($validatedItems)) {
                $orderNumber = 'TF-' . strtoupper(substr(uniqid(),-6)) . '-' . date('ymd');
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO orders (order_number,user_id,total_amount,payment_status,stripe_payment_intent,stripe_session_id,special_instructions) VALUES (?,?,?,'paid',?,?,?)");
                $stmt->execute([$orderNumber, $userId, $total, $stripeSession->payment_intent, $sessionId, $instructions]);
                $orderId = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO order_items (order_id,menu_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?)");
                foreach ($validatedItems as $vi) {
                    $stmt->execute([$orderId,$vi['menu_id'],$vi['qty'],$vi['price'],$vi['subtotal']]);
                }
                $db->commit();

                // Clear pending session data
                unset($_SESSION['stripe_session_id'],$_SESSION['pending_cart'],$_SESSION['pending_instructions']);
                $orderPlaced = true;
            }
        } else {
            $error = 'Payment not completed. Please try again.';
        }
    } catch (Exception $e) {
        $error = 'Error confirming order. Please contact support.';
    }
} else {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmed – TAMCC Foodie</title>
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
        <a href="my_orders.php" class="btn btn-ghost">My Orders</a>
        <a href="index.php" class="btn btn-accent">← Back to Menu</a>
    </div>
</nav>

<div style="max-width:520px;margin:80px auto;padding:0 24px;text-align:center;">
    <?php if ($orderPlaced): ?>
    <div style="font-size:72px;margin-bottom:16px;">✅</div>
    <h1 style="font-family:var(--font-display);font-size:40px;letter-spacing:3px;color:var(--gold);margin-bottom:12px;">ORDER CONFIRMED!</h1>
    <p style="color:var(--muted);margin-bottom:24px;">Your payment was successful. We're preparing your order.</p>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:32px;">
        <p style="font-size:13px;color:var(--muted);margin-bottom:4px;">ORDER NUMBER</p>
        <p style="font-family:var(--font-display);font-size:32px;color:var(--gold);letter-spacing:3px;"><?= htmlspecialchars($orderNumber) ?></p>
    </div>
    <p style="color:var(--muted);margin-bottom:24px;">You'll be notified when your order is ready for pickup.</p>
    <a href="my_orders.php" class="btn btn-primary" style="margin-right:8px;">Track Order</a>
    <a href="index.php" class="btn btn-ghost">Order More</a>
    <?php else: ?>
    <div style="font-size:72px;margin-bottom:16px;">❌</div>
    <h1 style="font-family:var(--font-display);font-size:36px;color:var(--danger);">PAYMENT ERROR</h1>
    <p style="color:var(--muted);margin:12px 0 24px;"><?= htmlspecialchars($error) ?></p>
    <a href="index.php" class="btn btn-primary">← Back to Menu</a>
    <?php endif; ?>
</div>
<script>
// Clear cart from localStorage on success
<?php if ($orderPlaced): ?>
localStorage.removeItem('tamcc_cart');
<?php endif; ?>
</script>
</body>
</html>
