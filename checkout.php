<?php
// ============================================================
// checkout.php — Stripe Payment Integration
//
// SETUP INSTRUCTIONS:
// 1. Install Stripe PHP SDK: composer require stripe/stripe-php
// 2. Set environment variables:
//      STRIPE_SECRET_KEY=sk_live_...
//      STRIPE_PUBLISHABLE_KEY=pk_live_...
//      STRIPE_WEBHOOK_SECRET=whsec_...
// 3. In Stripe Dashboard → Developers → Webhooks, add endpoint:
//      https://yourdomain.com/stripe_webhook.php
//      Listen for: payment_intent.succeeded, checkout.session.completed
// ============================================================

require_once 'auth.php';
requireUserLogin();
require_once 'config.php';
require_once 'db.php';

// Load Stripe PHP library (installed via Composer)
require_once __DIR__ . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// ── Build line items from session cart ──
$cartJson    = $_POST['cart_json']    ?? '';
$instructions= $_POST['instructions'] ?? '';
$cart = json_decode($cartJson, true);

if (empty($cart)) {
    header('Location: index.php');
    exit;
}

// Validate cart items against DB prices (never trust client-side prices!)
$db = getDB();
$lineItems = [];
foreach ($cart as $item) {
    $id  = (int)($item['id'] ?? 0);
    $qty = (int)($item['qty'] ?? 1);
    if ($id <= 0 || $qty <= 0) continue;

    $stmt = $db->prepare("SELECT name, price FROM menu WHERE id = ? AND is_available = 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) continue;

    $lineItems[] = [
        'price_data' => [
            'currency'     => STRIPE_CURRENCY,
            'unit_amount'  => (int)round($row['price'] * 100), // cents
            'product_data' => ['name' => $row['name']],
        ],
        'quantity' => $qty,
    ];
}

if (empty($lineItems)) {
    header('Location: index.php');
    exit;
}

// ── Create Stripe Checkout Session ──
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items'           => $lineItems,
        'mode'                 => 'payment',
        'success_url'          => APP_URL . '/order_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => APP_URL . '/index.php',
        'metadata'             => [
            'user_id'      => $_SESSION['user_id'],
            'instructions' => substr($instructions, 0, 500),
        ],
        'customer_email' => $_SESSION['user_email'] ?? null,
    ]);

    // Store session ID for webhook matching
    $_SESSION['stripe_session_id'] = $session->id;
    $_SESSION['pending_cart']      = json_encode($cart);
    $_SESSION['pending_instructions'] = $instructions;

    // Redirect to Stripe Hosted Checkout
    header('Location: ' . $session->url, true, 303);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    $error = htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout – TAMCC Foodie</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="checkout-page" style="text-align:center;padding-top:80px;">
    <img src="assets/logo.png" alt="TAMCC Foodie" style="height:64px;margin:0 auto 16px;">
    <h2 style="color:var(--danger)">Payment Error</h2>
    <p style="color:var(--muted);margin:12px 0 24px;"><?= $error ?? 'Unable to start checkout session.' ?></p>
    <a href="index.php" class="btn btn-primary">← Back to Menu</a>
</div>
</body>
</html>
