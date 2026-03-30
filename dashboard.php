<?php
// ============================================================
// dashboard.php — Admin Panel (Menu CRUD + Order Management)
// ============================================================
require_once 'auth.php';
requireKitchenOrAdmin();
require_once 'db.php';

$db       = getDB();
$isAdmin  = ($_SESSION['admin_role'] ?? '') === 'admin';
$adminName= htmlspecialchars($_SESSION['admin_name'] ?? 'Staff');
$view     = $_GET['view'] ?? 'orders';

// ── HANDLE FORM ACTIONS (admin only) ──
$message = '';
$msgType = 'success';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $stmt = $db->prepare("INSERT INTO menu (category_id, name, description, price, is_available, is_featured) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            (int)$_POST['category_id'],
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            (float)$_POST['price'],
            isset($_POST['is_available']) ? 1 : 0,
            isset($_POST['is_featured']) ? 1 : 0,
        ]);
        $message = 'Menu item added successfully.';

    } elseif ($action === 'edit_item') {
        $stmt = $db->prepare("UPDATE menu SET category_id=?, name=?, description=?, price=?, is_available=?, is_featured=? WHERE id=?");
        $stmt->execute([
            (int)$_POST['category_id'],
            trim($_POST['name']),
            trim($_POST['description'] ?? ''),
            (float)$_POST['price'],
            isset($_POST['is_available']) ? 1 : 0,
            isset($_POST['is_featured']) ? 1 : 0,
            (int)$_POST['id'],
        ]);
        $message = 'Menu item updated.';

    } elseif ($action === 'delete_item') {
        $stmt = $db->prepare("DELETE FROM menu WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $message = 'Menu item deleted.';
    }

    // Order status update (both admin & kitchen)
    if ($action === 'update_order_status') {
        $allowed = ['pending','preparing','ready','fulfilled','cancelled'];
        $status  = $_POST['status'] ?? '';
        if (in_array($status, $allowed)) {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, (int)$_POST['order_id']]);
            $message = 'Order status updated.';
        }
    }
}

// ── FETCH DATA ──
$categories = $db->query("SELECT * FROM menu_categories WHERE is_active=1 ORDER BY display_order")->fetchAll();

$menuItems = $db->query("
    SELECT m.*, mc.name AS cat_name
    FROM menu m JOIN menu_categories mc ON m.category_id = mc.id
    ORDER BY mc.display_order, m.name
")->fetchAll();

$orders = $db->query("
    SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 200
")->fetchAll();

// Stats
$totalOrders   = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','preparing')")->fetchColumn();
$todayRevenue  = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)=CURDATE() AND payment_status='paid'")->fetchColumn();
$totalItems    = $db->query("SELECT COUNT(*) FROM menu")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – TAMCC Foodie Admin</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-layout">

<!-- SIDEBAR -->
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <img src="assets/logo.png" alt="Logo" class="logo">
        <h2>TAMCC</h2>
        <small>Foodie Admin</small>
    </div>
    <nav class="sidebar-nav">
        <a href="?view=orders" class="sidebar-link <?= $view==='orders'?'active':'' ?>">📋 Orders</a>
        <?php if ($isAdmin): ?>
        <a href="?view=menu"   class="sidebar-link <?= $view==='menu'  ?'active':'' ?>">🍔 Menu Items</a>
        <a href="?view=stats"  class="sidebar-link <?= $view==='stats' ?'active':'' ?>">📊 Stats</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div style="font-size:13px;color:var(--muted);margin-bottom:12px;">
            Signed in as<br>
            <strong style="color:var(--white)"><?= $adminName ?></strong>
            <span class="badge badge-ready" style="margin-left:6px;"><?= $_SESSION['admin_role'] ?></span>
        </div>
        <a href="logout.php" class="btn btn-ghost" style="width:100%;justify-content:center;">Sign Out</a>
    </div>
</aside>

<!-- MAIN -->
<main class="admin-main">

<?php if ($message): ?>
<div class="alert alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- ── STATS VIEW ── -->
<?php if ($view === 'stats' && $isAdmin): ?>
<div class="page-header"><h1>OVERVIEW</h1></div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-num"><?= $totalOrders ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card"><div class="stat-num"><?= $pendingOrders ?></div><div class="stat-label">Active Orders</div></div>
    <div class="stat-card"><div class="stat-num">$<?= number_format($todayRevenue,2) ?></div><div class="stat-label">Today's Revenue</div></div>
    <div class="stat-card"><div class="stat-num"><?= $totalItems ?></div><div class="stat-label">Menu Items</div></div>
</div>

<!-- ── ORDERS VIEW ── -->
<?php elseif ($view === 'orders'): ?>
<div class="page-header">
    <h1>ORDERS</h1>
    <span style="font-size:13px;color:var(--muted);">Auto-refreshes every 30s</span>
</div>
<div class="data-table-wrap">
<table class="data-table">
    <thead>
        <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
    <tr>
        <td><strong style="color:var(--gold)"><?= htmlspecialchars($order['order_number']) ?></strong></td>
        <td>
            <div><?= htmlspecialchars($order['customer_name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($order['customer_email']) ?></div>
        </td>
        <td><?= $order['item_count'] ?> item(s)</td>
        <td><strong>$<?= number_format($order['total_amount'],2) ?></strong></td>
        <td><span class="badge badge-<?= $order['payment_status'] ?>"><?= ucfirst($order['payment_status']) ?></span></td>
        <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
        <td style="font-size:12px;color:var(--muted)"><?= date('M j g:i A', strtotime($order['created_at'])) ?></td>
        <td>
            <form method="POST" style="display:flex;gap:6px;">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <select name="status" class="form-control" style="padding:6px;font-size:12px;background:var(--surface2);color:var(--white);border:1px solid var(--border);border-radius:6px;">
                    <?php foreach (['pending','preparing','ready','fulfilled','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-success" style="padding:6px 10px;font-size:12px;">✓</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ── MENU MANAGEMENT VIEW ── -->
<?php elseif ($view === 'menu' && $isAdmin): ?>
<div class="page-header">
    <h1>MENU ITEMS</h1>
    <button class="btn btn-primary" onclick="openModal('add-modal')">+ Add Item</button>
</div>

<div class="data-table-wrap">
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Available</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($menuItems as $item): ?>
    <tr>
        <td style="color:var(--muted)"><?= $item['id'] ?></td>
        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
        <td><?= htmlspecialchars($item['cat_name']) ?></td>
        <td style="color:var(--gold);font-family:var(--font-display)">$<?= number_format($item['price'],2) ?></td>
        <td>
            <span class="badge <?= $item['is_available']?'badge-ready':'badge-cancelled' ?>">
                <?= $item['is_available'] ? 'Yes' : 'No' ?>
            </span>
        </td>
        <td>
            <button class="btn btn-ghost" style="font-size:12px;padding:5px 10px"
                onclick='editItem(<?= json_encode($item) ?>)'>✏️ Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this item?')">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button type="submit" class="btn btn-danger" style="font-size:12px;padding:5px 10px">🗑️ Delete</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ADD ITEM MODAL -->
<div class="modal-overlay" id="add-modal">
<div class="modal">
    <h2>ADD MENU ITEM</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add_item">
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control" required>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" class="form-control">
        </div>
        <div class="form-group">
            <label>Price (XCD $)</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0" required>
        </div>
        <div style="display:flex;gap:20px;margin-bottom:16px;">
            <label><input type="checkbox" name="is_available" checked> Available</label>
            <label><input type="checkbox" name="is_featured"> Featured</label>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('add-modal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Item</button>
        </div>
    </form>
</div>
</div>

<!-- EDIT ITEM MODAL -->
<div class="modal-overlay" id="edit-modal">
<div class="modal">
    <h2>EDIT MENU ITEM</h2>
    <form method="POST">
        <input type="hidden" name="action" value="edit_item">
        <input type="hidden" name="id" id="edit-id">
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" id="edit-cat" class="form-control" required>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" id="edit-name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" id="edit-desc" class="form-control">
        </div>
        <div class="form-group">
            <label>Price (XCD $)</label>
            <input type="number" name="price" id="edit-price" class="form-control" step="0.01" min="0" required>
        </div>
        <div style="display:flex;gap:20px;margin-bottom:16px;">
            <label><input type="checkbox" name="is_available" id="edit-avail"> Available</label>
            <label><input type="checkbox" name="is_featured" id="edit-feat"> Featured</label>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('edit-modal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</div>

<?php endif; ?>
</main>
</div>

<script src="script.js"></script>
<script>
function editItem(item) {
    document.getElementById('edit-id').value    = item.id;
    document.getElementById('edit-name').value  = item.name;
    document.getElementById('edit-price').value = item.price;
    document.getElementById('edit-cat').value   = item.category_id;
    document.getElementById('edit-desc').value  = item.description || '';
    document.getElementById('edit-avail').checked = item.is_available == 1;
    document.getElementById('edit-feat').checked  = item.is_featured == 1;
    openModal('edit-modal');
}
// Auto-refresh on orders view
<?php if ($view === 'orders'): ?>
setTimeout(() => location.reload(), 30000);
<?php endif; ?>
</script>
</body>
</html>
