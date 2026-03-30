<?php
// ============================================================
// index.php — Menu & Ordering Page (Students/Staff)
// ============================================================
require_once 'auth.php';
requireUserLogin();
require_once 'db.php';

$db = getDB();

// Fetch categories
$cats = $db->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// Fetch all available menu items
$items = $db->query("
    SELECT m.*, mc.name AS category_name, mc.slug AS category_slug
    FROM menu m
    JOIN menu_categories mc ON m.category_id = mc.id
    WHERE m.is_available = 1
    ORDER BY mc.display_order, m.name
")->fetchAll();

// Group items by category slug
$menu = [];
foreach ($items as $item) {
    $menu[$item['category_slug']][] = $item;
}

$userName = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TAMCC Foodie – Menu</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
<script>
    // Pass PHP data to JS
    window.STRIPE_KEY = <?= json_encode(STRIPE_PUBLISHABLE_KEY) ?>;
    window.MENU_DATA  = <?= json_encode($items) ?>;
</script>
</head>
<body>

<!-- NAV -->
<nav class="topnav">
    <div class="nav-brand">
        <img src="assets/logo.png" alt="TAMCC Foodie" class="nav-logo">
        <span class="nav-title">TAMCC Foodie</span>
    </div>
    <div class="nav-user">
        <span class="user-badge">👤 <?= $userName ?></span>
        <a href="my_orders.php" class="btn btn-ghost">My Orders</a>
        <button id="cart-toggle" class="btn btn-accent cart-btn">
            🛒 Cart <span class="cart-count" id="cart-count">0</span>
        </button>
        <a href="logout.php" class="btn btn-ghost">Sign Out</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <h1>Marryshow's<br><span class="accent">Mealhouse</span></h1>
        <p>Fresh meals, made for TAMCC students & staff</p>
    </div>
</section>

<!-- CATEGORY TABS -->
<div class="category-tabs" id="category-tabs">
    <button class="tab-btn active" data-cat="all">All</button>
    <?php foreach ($cats as $cat): ?>
    <button class="tab-btn" data-cat="<?= $cat['slug'] ?>">
        <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- SEARCH BAR -->
<div class="search-wrap">
    <input type="text" id="menu-search" placeholder="🔍 Search menu items..." class="search-input">
</div>

<!-- MENU GRID -->
<main class="menu-main">
    <?php foreach ($cats as $cat): ?>
    <section class="menu-section" data-cat="<?= $cat['slug'] ?>" id="section-<?= $cat['slug'] ?>">
        <h2 class="section-title"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></h2>
        <div class="menu-grid">
            <?php foreach ($menu[$cat['slug']] ?? [] as $item): ?>
            <div class="menu-card" 
                 data-id="<?= $item['id'] ?>" 
                 data-name="<?= htmlspecialchars($item['name']) ?>" 
                 data-price="<?= $item['price'] ?>"
                 data-cat="<?= $cat['slug'] ?>">
                <div class="card-img-wrap">
                    <?php if ($item['image_url']): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="card-img-placeholder"><?= $cat['icon'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h3 class="card-name"><?= htmlspecialchars($item['name']) ?></h3>
                    <?php if ($item['description']): ?>
                    <p class="card-desc"><?= htmlspecialchars($item['description']) ?></p>
                    <?php endif; ?>
                    <div class="card-footer">
                        <span class="card-price">$<?= number_format($item['price'], 2) ?></span>
                        <button class="btn btn-add" onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">+ Add</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</main>

<!-- CART SIDEBAR -->
<aside class="cart-sidebar" id="cart-sidebar">
    <div class="cart-header">
        <h2>Your Order</h2>
        <button id="cart-close" class="cart-close">✕</button>
    </div>
    <div class="cart-items" id="cart-items">
        <p class="cart-empty">Your cart is empty.</p>
    </div>
    <div class="cart-footer" id="cart-footer" style="display:none">
        <div class="cart-total-row">
            <span>Total</span>
            <span id="cart-total">$0.00</span>
        </div>
        <textarea id="special-instructions" placeholder="Special instructions (optional)..." class="special-input"></textarea>
        <button class="btn btn-primary btn-full" id="checkout-btn">Proceed to Checkout</button>
    </div>
</aside>
<div class="cart-overlay" id="cart-overlay"></div>

<script src="script.js"></script>
</body>
</html>
