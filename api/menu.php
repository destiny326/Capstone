<?php
// ============================================================
// api/menu.php — Menu CRUD REST Endpoint (Admin only)
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
requireAdminLogin('admin');
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

$db = getDB();

switch ($action) {

    case 'list':
        $items = $db->query("
            SELECT m.*, mc.name AS category_name
            FROM menu m
            JOIN menu_categories mc ON m.category_id = mc.id
            ORDER BY mc.display_order, m.name
        ")->fetchAll();
        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'get':
        $id   = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("SELECT m.*, mc.name AS category_name FROM menu m JOIN menu_categories mc ON m.category_id = mc.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        echo json_encode($item ? ['success' => true, 'item' => $item] : ['error' => 'Not found']);
        break;

    case 'create':
        $stmt = $db->prepare("
            INSERT INTO menu (category_id, name, description, price, is_available, is_featured)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)($body['category_id'] ?? 0),
            trim($body['name'] ?? ''),
            trim($body['description'] ?? ''),
            (float)($body['price'] ?? 0),
            isset($body['is_available']) ? (int)$body['is_available'] : 1,
            isset($body['is_featured'])  ? (int)$body['is_featured']  : 0,
        ]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update':
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("
            UPDATE menu SET category_id=?, name=?, description=?, price=?, is_available=?, is_featured=?
            WHERE id=?
        ");
        $stmt->execute([
            (int)($body['category_id'] ?? 0),
            trim($body['name'] ?? ''),
            trim($body['description'] ?? ''),
            (float)($body['price'] ?? 0),
            (int)($body['is_available'] ?? 1),
            (int)($body['is_featured']  ?? 0),
            $id,
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id   = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM menu WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'toggle_availability':
        $id   = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("UPDATE menu SET is_available = NOT is_available WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
