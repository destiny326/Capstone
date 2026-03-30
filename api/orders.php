<?php
// ============================================================
// api/orders.php — Order Management REST Endpoint
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
requireKitchenOrAdmin();
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

$db = getDB();

switch ($action) {

    case 'list':
        $status = $body['status'] ?? null;
        $sql = "
            SELECT o.*, u.full_name AS customer_name, u.email AS customer_email
            FROM orders o JOIN users u ON o.user_id = u.id
        ";
        if ($status) {
            $stmt = $db->prepare($sql . " WHERE o.status = ? ORDER BY o.created_at DESC LIMIT 200");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query($sql . " ORDER BY o.created_at DESC LIMIT 200");
        }
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $s = $db->prepare("SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?");
            $s->execute([$order['id']]);
            $order['items'] = $s->fetchAll();
        }
        echo json_encode(['success' => true, 'orders' => $orders]);
        break;

    case 'update_status':
        $allowed = ['pending','preparing','ready','fulfilled','cancelled'];
        $status  = $body['status'] ?? '';
        $id      = (int)($body['id'] ?? 0);
        if (!in_array($status, $allowed)) {
            echo json_encode(['error' => 'Invalid status']); break;
        }
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        break;

    case 'get':
        $id   = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("SELECT o.*, u.full_name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order) {
            $s = $db->prepare("SELECT oi.*, m.name FROM order_items oi JOIN menu m ON oi.menu_id = m.id WHERE oi.order_id = ?");
            $s->execute([$order['id']]);
            $order['items'] = $s->fetchAll();
        }
        echo json_encode($order ? ['success' => true, 'order' => $order] : ['error' => 'Not found']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
