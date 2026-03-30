// ============================================================
// script.js — TAMCC Foodie Frontend Logic
// ============================================================

'use strict';

// ── CART STATE ──
let cart = JSON.parse(localStorage.getItem('tamcc_cart') || '[]');

// ── CART FUNCTIONS ──
function saveCart() {
    localStorage.setItem('tamcc_cart', JSON.stringify(cart));
}

function addToCart(id, name, price) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id, name, price: parseFloat(price), qty: 1 });
    }
    saveCart();
    renderCart();
    showToast(`${name} added to cart ✓`, 'success');
    openCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    saveCart();
    renderCart();
}

function changeQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) removeFromCart(id);
    else { saveCart(); renderCart(); }
}

function getTotal() {
    return cart.reduce((sum, i) => sum + i.price * i.qty, 0);
}

function renderCart() {
    const itemsEl  = document.getElementById('cart-items');
    const footerEl = document.getElementById('cart-footer');
    const countEl  = document.getElementById('cart-count');
    const totalEl  = document.getElementById('cart-total');

    if (!itemsEl) return;

    const totalQty = cart.reduce((s, i) => s + i.qty, 0);
    if (countEl) countEl.textContent = totalQty;

    if (cart.length === 0) {
        itemsEl.innerHTML = '<p class="cart-empty">Your cart is empty.</p>';
        if (footerEl) footerEl.style.display = 'none';
        return;
    }

    if (footerEl) footerEl.style.display = 'flex';
    if (totalEl) totalEl.textContent = '$' + getTotal().toFixed(2);

    itemsEl.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-name">${escHtml(item.name)}</div>
            <div class="cart-qty">
                <button class="qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
                <span class="qty-val">${item.qty}</span>
                <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
            </div>
            <div class="cart-item-price">$${(item.price * item.qty).toFixed(2)}</div>
        </div>
    `).join('');
}

// ── CART SIDEBAR TOGGLE ──
function openCart() {
    document.getElementById('cart-sidebar')?.classList.add('open');
    document.getElementById('cart-overlay')?.classList.add('visible');
}
function closeCart() {
    document.getElementById('cart-sidebar')?.classList.remove('open');
    document.getElementById('cart-overlay')?.classList.remove('visible');
}

document.getElementById('cart-toggle')?.addEventListener('click', openCart);
document.getElementById('cart-close')?.addEventListener('click', closeCart);
document.getElementById('cart-overlay')?.addEventListener('click', closeCart);

// ── CHECKOUT ──
document.getElementById('checkout-btn')?.addEventListener('click', () => {
    if (cart.length === 0) { showToast('Your cart is empty!', 'error'); return; }
    const instructions = document.getElementById('special-instructions')?.value || '';
    // Store cart in sessionStorage for checkout page
    sessionStorage.setItem('checkout_cart', JSON.stringify(cart));
    sessionStorage.setItem('checkout_instructions', instructions);
    window.location.href = 'checkout.php';
});

// ── CATEGORY TABS ──
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        document.querySelectorAll('.menu-section').forEach(section => {
            if (cat === 'all') {
                section.style.display = '';
            } else {
                section.style.display = section.dataset.cat === cat ? '' : 'none';
            }
        });
    });
});

// ── SEARCH ──
document.getElementById('menu-search')?.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.menu-card').forEach(card => {
        const name = card.querySelector('.card-name')?.textContent.toLowerCase() || '';
        card.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
    // Show/hide sections based on visible cards
    document.querySelectorAll('.menu-section').forEach(section => {
        const visible = [...section.querySelectorAll('.menu-card')].some(c => c.style.display !== 'none');
        section.style.display = visible ? '' : 'none';
    });
});

// ── TOAST NOTIFICATIONS ──
function showToast(message, type = 'info') {
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'toast-wrap';
        document.body.appendChild(wrap);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    wrap.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
}

// ── UTILITY ──
function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── INIT ──
renderCart();

// ============================================================
// Admin Dashboard Logic (only runs on dashboard.php)
// ============================================================

// Modal helpers
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

// Admin: Edit menu item
function editMenuItem(item) {
    document.getElementById('edit-id').value     = item.id;
    document.getElementById('edit-name').value   = item.name;
    document.getElementById('edit-price').value  = item.price;
    document.getElementById('edit-cat').value    = item.category_id;
    document.getElementById('edit-desc').value   = item.description || '';
    document.getElementById('edit-avail').checked= item.is_available == 1;
    openModal('edit-modal');
}

// Admin: Confirm delete
function confirmDelete(id, name) {
    if (confirm(`Delete "${name}"? This cannot be undone.`)) {
        fetch('api/menu.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'delete', id })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast('Item deleted.', 'success'); location.reload(); }
            else showToast(d.error || 'Delete failed.', 'error');
        });
    }
}

// Admin: Update order status
function updateOrderStatus(orderId, status) {
    fetch('api/orders.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'update_status', id: orderId, status })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast('Order updated!', 'success'); location.reload(); }
        else showToast(d.error || 'Update failed.', 'error');
    });
}
