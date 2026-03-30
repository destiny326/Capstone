<?php
// ============================================================
// stripe_webhook.php — Stripe Webhook Handler
// Add this URL in your Stripe Dashboard under Webhooks.
// ============================================================

require_once 'config.php';
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

// Stripe sends a raw body — read it before any output/session
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

$db = getDB();

switch ($event->type) {

    case 'checkout.session.completed':
        $session   = $event->data->object;
        $sessionId = $session->id;
        $userId    = $session->metadata->user_id ?? null;
        $instructions = $session->metadata->instructions ?? '';
        $paymentIntent = $session->payment_intent ?? '';

        if (!$userId) break;

        // Retrieve line items from Stripe (server-side, trusted)
        $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId, ['limit' => 100]);

        // We need to reconstruct items — store cart in a pending table or use a different approach.
        // For this implementation we match by looking up the order placed during session creation.
        // The order was created at order_success.php after redirect — this webhook confirms payment.

        // Mark any existing order with this session as paid
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'pending' WHERE stripe_session_id = ?");
        $stmt->execute([$sessionId]);
        break;

    case 'payment_intent.payment_failed':
        $pi = $event->data->object;
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'unpaid', status = 'cancelled' WHERE stripe_payment_intent = ?");
        $stmt->execute([$pi->id]);
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
