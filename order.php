<?php
// ============================================================
// order.php — Place an Order (called after Stripe payment)
// ============================================================
require_once 'auth.php';
requireUserLogin();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$items           = $body['items']           ?? [];
$instructions    = $body['instructions']    ?? '';
$paymentIntentId = $body['payment_intent']  ?? '';
$sessionId       = $body['session_id']      ?? '';

if (empty($items)) {
    echo json_encode(['error' => 'Cart is empty.']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Verify items and compute total
$total = 0;
$validatedItems = [];
foreach ($items as $item) {
    $id  = (int)($item['id'] ?? 0);
    $qty = (int)($item['qty'] ?? 1);
    if ($id <= 0 || $qty <= 0) continue;

    $stmt = $db->prepare("SELECT id, name, price FROM menu WHERE id = ? AND is_available = 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) continue;

    $subtotal = $row['price'] * $qty;
    $total += $subtotal;
    $validatedItems[] = ['menu_id' => $row['id'], 'qty' => $qty, 'price' => $row['price'], 'subtotal' => $subtotal];
}

if (empty($validatedItems)) {
    echo json_encode(['error' => 'No valid items found.']);
    exit;
}

// Generate unique order number
$orderNum = 'TF-' . strtoupper(substr(uniqid(), -6)) . '-' . date('ymd');

try {
    $db->beginTransaction();

    // Insert order
    $stmt = $db->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, payment_status, stripe_payment_intent, stripe_session_id, special_instructions)
        VALUES (?, ?, ?, 'paid', ?, ?, ?)
    ");
    $stmt->execute([$orderNum, $userId, $total, $paymentIntentId, $sessionId, $instructions]);
    $orderId = $db->lastInsertId();

    // Insert order items
    $stmt = $db->prepare("
        INSERT INTO order_items (order_id, menu_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($validatedItems as $vi) {
        $stmt->execute([$orderId, $vi['menu_id'], $vi['qty'], $vi['price'], $vi['subtotal']]);
    }

    $db->commit();

    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'order_number' => $orderNum,
        'total'        => number_format($total, 2),
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to place order. Please contact support.']);
}
