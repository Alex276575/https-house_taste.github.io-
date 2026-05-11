<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Cart.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$user = new User();
$cart = new Cart();

$isLoggedIn = $user->isLoggedIn();
$currentUser = $user->getCurrentUser();
$userName = $isLoggedIn ? ($currentUser['full_name'] ?? 'Пользователь') : '';
$userAvatar = $isLoggedIn ? ($currentUser['avatar_url'] ?? '') : '';
$isAdmin = $isLoggedIn && ($currentUser['role'] ?? 0) == 1;

$initialCartCount = $cart->getCount($isLoggedIn ? $_SESSION['user_id'] : null);
$currentFile = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Дом Вкуса</title>
    <link rel="icon" type="image/png" href="/house_of_taste/public/img/favic_on.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="site-header" id="siteHeader">
    <div class="header-inner">
        <a href="/house_of_taste/" class="header-logo">
            <div class="logo-icon">Д</div>
            <div>
                <span style="color: #fff; font-weight: 800; font-size: 16px; display: block;">ДОМ</span>
                <span style="color: #c8a656; font-weight: 800; font-size: 16px; display: block;">ВКУСА</span>
            </div>
        </a>

        <nav class="header-nav">
            <a href="/house_of_taste/" class="<?= $currentFile === 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> <span>Главная</span>
            </a>
            <a href="/house_of_taste/pages/catalog.php" class="<?= $currentFile === 'catalog.php' ? 'active' : '' ?>">
                <i class="fas fa-utensils"></i> <span>Каталог</span>
            </a>
            <a href="/house_of_taste/pages/about.php" class="<?= $currentFile === 'about.php' ? 'active' : '' ?>">
                <i class="fas fa-info-circle"></i> <span>О ресторане</span>
            </a>
            <a href="/house_of_taste/pages/contacts.php" class="<?= $currentFile === 'contacts.php' ? 'active' : '' ?>">
                <i class="fas fa-headset"></i> <span>Поддержка</span>
            </a>
            <?php if ($isAdmin): // Только для админов ?>
            <a href="/house_of_taste/admin/index.php" class="<?= strpos($currentFile, 'admin') === 0 ? 'active' : '' ?> admin-link">
                <i class="fas fa-shield-halved"></i> <span>Админ-панель</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <a href="/house_of_taste/pages/favorites.php" class="header-btn" aria-label="Избранное">
                <i class="far fa-heart"></i>
                <span class="badge" id="fav-badge" style="background: #e74c3c; color: white;">0</span>
            </a>
            <button class="header-btn" onclick="toggleCart()" aria-label="Корзина">
                <i class="fas fa-shopping-basket"></i>
                <span class="badge" id="cart-badge"><?= $initialCartCount > 0 ? $initialCartCount : '' ?></span>
            </button>
            <?php if ($isLoggedIn): ?>
            <div class="user-profile-wrap" id="profileWrap">
                <div class="user-trigger" onclick="toggleProfileMenu()">
                    <div class="user-mini-avatar">
                        <?php
                        $showInitial = true;
                        if (!empty($userAvatar) && mb_strlen($userAvatar) <= 4 && !str_contains($userAvatar, '/')) {
                            $showInitial = false;
                            echo '<span>' . htmlspecialchars($userAvatar) . '</span>';
                        } elseif (!empty($userAvatar)) {
                            $showInitial = false;
                            $avatarSrc = strpos($userAvatar, '/house_of_taste') === 0 ? $userAvatar : '/house_of_taste' . $userAvatar;
                            echo '<img src="' . htmlspecialchars($avatarSrc) . '" alt="Ava" onerror="this.parentElement.innerHTML=\'<span>' . mb_substr(htmlspecialchars($userName), 0, 1) . '</span>\'">';
                        }
                        if ($showInitial) echo '<span>' . mb_substr(htmlspecialchars($userName), 0, 1) . '</span>';
                        ?>
                    </div>
                    <span class="user-mini-name"><?= htmlspecialchars($userName) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="user-dropdown">
                    <a href="/house_of_taste/pages/orders.php" class="dropdown-item"><i class="fas fa-list"></i> Мои заказы</a>
                    <?php if ($isAdmin): ?>
                    <a href="/house_of_taste/admin/index.php" class="dropdown-item admin-link"><i class="fas fa-shield-halved"></i> Админ-панель</a>
                    <?php endif; ?>
                    <a href="/house_of_taste/auth/logout.php" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i> Выйти</a>
                </div>
            </div>
            <?php else: ?>
                <a href="/house_of_taste/auth/login.php" class="auth-btn"><i class="fas fa-sign-in-alt"></i> Войти</a>
            <?php endif; ?>
            <button class="mobile-toggle" onclick="document.querySelector('.header-nav').classList.toggle('active')"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</header>

<!-- Cart Sidebar -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
        <h3>Корзина</h3>
        <button onclick="toggleCart()" style="background:none; border:none; color:#fff; font-size:24px; cursor:pointer;">&times;</button>
    </div>
    <div class="cart-items" id="cartItemsContainer">
        <div style="text-align:center; padding:40px; color:#666;">
            <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px;"></i>
            <p>Загрузка...</p>
        </div>
    </div>
    <div class="cart-footer">
        <div class="cart-total"><span>Итого:</span><span id="cartTotalSum">0 ₽</span></div>
        <button class="checkout-btn" onclick="window.location.href='/house_of_taste/pages/cart.php'">Далее</button>
    </div>
</div>
<div id="toast"></div>

<script>
// ===== ГЛОБАЛЬНЫЕ ФУНКЦИИ (защита от повторного объявления) =====
if (typeof window.headerInit === 'undefined') {
    window.headerInit = true;

    // ===== ПРОФИЛЬ =====
    function toggleProfileMenu() {
        var wrap = document.getElementById('profileWrap');
        if(wrap) wrap.classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('profileProfileWrap');
        if (wrap && !wrap.contains(e.target)) wrap.classList.remove('active');
    });

    // ===== ИЗБРАННОЕ =====
    function getFavorites() {
        try { return JSON.parse(localStorage.getItem('favorites') || '[]'); }
        catch(e) { return []; }
    }

    function updateFavBadge() {
        var count = getFavorites().length;
        var badge = document.getElementById('fav-badge');
        if (badge) {
            badge.innerText = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    function toggleFavorite(productId, btn) {
        var favs = getFavorites();
        var id = parseInt(productId);
        var index = favs.indexOf(id);

        if (index > -1) {
            // УДАЛЯЕМ
            favs.splice(index, 1);
            showToast('Удалено из избранного', 'error');
        } else {
            // ДОБАВЛЯЕМ
            favs.push(id);
            showToast('Добавлено в избранное', 'success');
        }

        localStorage.setItem('favorites', JSON.stringify(favs));
        updateFavBadge();

        if (btn) {
            var icon = btn.querySelector('i');
            if (icon) {
                if (index > -1) {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    icon.style.color = '';
                } else {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    icon.style.color = '#e74c3c';
                }
            }
        }
    }

    // ===== КОРЗИНА =====
    function toggleCart() {
        var overlay = document.getElementById('cartOverlay');
        var sidebar = document.getElementById('cartSidebar');
        if(overlay && sidebar) {
            overlay.classList.toggle('open');
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) loadCartItems();
        }
    }

    function updateCartBadge(count) {
        var badge = document.getElementById('cart-badge');
        if (badge) {
            badge.innerText = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    function loadCartItems() {
        fetch('/house_of_taste/api/cart_get.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var container = document.getElementById('cartItemsContainer');
            var totalEl = document.getElementById('cartTotalSum');
            if (!container) return;

            if (!data.items || data.items.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:40px 20px; color:#888;"><i class="fas fa-shopping-basket" style="font-size:40px; margin-bottom:15px; color:#444;"></i><p>Корзина пуста</p><a href="/house_of_taste/pages/catalog.php" onclick="toggleCart()" style="color:#c8a656; font-size:12px; margin-top:10px; display:inline-block;">Перейти в меню</a></div>';
                if(totalEl) totalEl.innerText = '0 ₽';
                return;
            }

            var html = '', total = 0;
            for (var i = 0; i < data.items.length; i++) {
                var item = data.items[i];
                var price = item.final_price ? parseFloat(item.final_price) : parseFloat(item.price);
                total += price * item.quantity;
                var imgSrc = item.image_url ? (item.image_url.startsWith('/') ? item.image_url : '/house_of_taste' + item.image_url) : '/house_of_taste/public/img/placeholder.png';

                html += '<div class="cart-item">' +
                    '<img src="' + imgSrc + '" alt="' + item.name + '">' +
                    '<div class="cart-item-info">' +
                        '<div class="cart-item-name">' + item.name + '</div>' +
                        '<div class="cart-item-price">' + Math.round(price) + ' ₽</div>' +
                        '<div class="qty-controls">' +
                            '<button class="qty-btn" onclick="updateQty(' + item.product_id + ', -1)">−</button>' +
                            '<span>' + item.quantity + '</span>' +
                            '<button class="qty-btn" onclick="updateQty(' + item.product_id + ', 1)">+</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }
            container.innerHTML = html;
            if(totalEl) totalEl.innerText = Math.round(total).toLocaleString('ru-RU') + ' ₽';
        })
        .catch(function(err) {
            console.error(err);
            var container = document.getElementById('cartItemsContainer');
            if(container) container.innerHTML = '<div style="text-align:center; padding:20px; color:#e74c3c;">Ошибка загрузки</div>';
        });
    }

    function updateQty(id, delta) {
        fetch('/house_of_taste/api/cart_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, delta: delta })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                loadCartItems();
                updateCartBadge(data.cart_count);
            }
        });
    }

    // ===== ДОБАВЛЕНИЕ В КОРЗИНУ (универсальное — для обычных товаров и комбо) =====
    window.simpleAddToCart = function(id, name) {
        var btn = event ? event.target.closest('.add-cart-btn, .combo-btn, button[onclick*="simpleAddToCart"]') : null;
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
        }

        fetch('/house_of_taste/api/cart_add.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, quantity: 1, action: 'add' })
        })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            try { return JSON.parse(text); }
            catch(e) { return { success: false, error: 'Server error' }; }
        })
        .then(function(data) {
            if (data.success) {
                showToast('' + name + ' добавлен!', 'success');
                if (typeof updateCartBadge === 'function' && data.cartCount !== undefined) {
                    updateCartBadge(data.cartCount);
                }
                var sb = document.getElementById('cartSidebar');
                if (sb && sb.classList.contains('open') && typeof loadCartItems === 'function') {
                    loadCartItems();
                }
            } else {
                showToast('Ошибка: ' + (data.error || data.message || 'Неизвестная'), 'error');
            }
        })
        .catch(function() { showToast('Ошибка соединения', 'error'); })
        .finally(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-shopping-basket"></i>';
                btn.disabled = false;
            }
        });
    };

    // ===== УВЕДОМЛЕНИЯ (TOAST) =====
    function showToast(msg, type) {
        if (type === undefined) type = 'success';
        var t = document.getElementById('toast');
        if (!t) { alert(msg); return; }
        t.innerText = msg;
        t.className = 'show ' + type;
        setTimeout(function() { t.classList.remove('show'); }, 3000);
    }

    // ===== ИНИЦИАЛИЗАЦИЯ =====
    document.addEventListener('DOMContentLoaded', function() {
        // Загружаем количество товаров в корзине
        fetch('/house_of_taste/api/cart_get_count.php')
            .then(function(r) { return r.json(); })
            .then(function(data) { updateCartBadge(data.count); })
            .catch(function() {});

        // Инициализация избранного
        updateFavBadge();
        var favs = getFavorites();
        var favBtns = document.querySelectorAll('[data-fav-id]');
        for (var i = 0; i < favBtns.length; i++) {
            var btn = favBtns[i];
            var id = parseInt(btn.dataset.favId);
            var icon = btn.querySelector('i');

            // Синхронизация иконки
            if (favs.indexOf(id) !== -1 && icon) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#e74c3c';
            }

            // Привязываем обработчик только один раз
            if (!btn.dataset.favBound) {
                btn.dataset.favBound = '1';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var id = parseInt(this.dataset.favId);
                    toggleFavorite(id, this);
                });
            }
        }
    });
}
</script>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Montserrat', sans-serif; background: #1a1a1a; color: #fff; min-height: 100vh; display: flex; flex-direction: column; }
    a { text-decoration: none; color: inherit; transition: color 0.3s; }
    ul { list-style: none; }
    img { max-width: 100%; display: block; }
    .gold { color: #c8a656; }

    /* HEADER */
    .site-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(26,26,26,0.95); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(200,166,86,0.15); height: 70px; }
    .header-inner { max-width: 1300px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; height: 100%; }
    .header-logo { display: flex; align-items: center; gap: 12px; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #c8a656, #e8c96a); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; color: #1a1a1a; }
    .header-nav { display: flex; gap: 25px; }
    .header-nav a { font-size: 11px; font-weight: 500; letter-spacing: 1.5px; text-transform: uppercase; color: #ccc; position: relative; padding: 5px 0; }
    .header-nav a:hover, .header-nav a.active { color: #c8a656; }
    .header-nav a::after { content: ''; position: absolute; bottom: 0; left: 0; width: 0; height: 2px; background: #c8a656; transition: width 0.3s; }
    .header-nav a:hover::after, .header-nav a.active::after { width: 100%; }
    .header-actions { display: flex; align-items: center; gap: 15px; }
    .header-btn { width: 40px; height: 40px; border: 1px solid rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; background: transparent; color: #fff; position: relative; transition: 0.3s; font-size: 16px; }
    .header-btn:hover { border-color: #c8a656; color: #c8a656; }
    .badge { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; background: #c8a656; color: #1a1a1a; border-radius: 50%; font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; padding: 0 4px; display: none; }

    /* USER PROFILE */
    .user-profile-wrap { position: relative; }
    .user-trigger { display: flex; align-items: center; gap: 10px; padding: 5px 10px 5px 5px; border: 1px solid rgba(255,255,255,0.1); border-radius: 30px; cursor: pointer; transition: 0.3s; background: rgba(255,255,255,0.02); }
    .user-trigger:hover { border-color: #c8a656; background: rgba(255,255,255,0.05); }
    .user-mini-avatar { width: 32px; height: 32px; border-radius: 50%; background: #333; display: flex; align-items: center; justify-content: center; font-size: 16px; overflow: hidden; border: 1px solid #c8a656; }
    .user-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .user-mini-name { font-size: 13px; font-weight: 600; color: #fff; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-trigger i { font-size: 10px; color: #888; margin-left: 5px; }
    .user-dropdown { position: absolute; top: 55px; right: 0; width: 200px; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); opacity: 0; visibility: hidden; transform: translateY(-10px); transition: 0.3s; z-index: 1001; overflow: hidden; }
    .user-profile-wrap.active .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
    .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: #ccc; font-size: 13px; transition: 0.2s; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: rgba(200,166,86,0.1); color: #c8a656; }
    .dropdown-item i { width: 20px; text-align: center; }
    .dropdown-item.logout { color: #e74c3c; }
    .dropdown-item.logout:hover { background: rgba(231,76,60,0.1); color: #e74c3c; }

    /* MOBILE */
    .mobile-toggle { display: none; background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; }
    @media (max-width: 992px) {
        .header-nav { display: none; position: absolute; top: 70px; left: 0; right: 0; background: #1a1a1a; flex-direction: column; padding: 20px; border-bottom: 1px solid #333; }
        .header-nav.active { display: flex; }
        .mobile-toggle { display: block; }
        .user-profile-wrap { display: none; }
    }
    /* Иконки в навигации */
.header-nav a { display: flex; align-items: center; gap: 6px; }
.header-nav a i { font-size: 12px; opacity: 0.9; transition: opacity 0.3s; }
.header-nav a:hover i, .header-nav a.active i { opacity: 1; color: #c8a656; }

/* Админ-ссылка */
.admin-link { color: #9b7cbd !important; }
.admin-link:hover, .admin-link.active { color: #c8a656 !important; }
.admin-link i { color: #9b7cbd; }
.admin-link:hover i, .admin-link.active i { color: #c8a656; }

/* Иконка в кнопке входа */
.auth-btn { display: flex; align-items: center; gap: 6px; font-size: 13px; }
.auth-btn i { font-size: 12px; }

/* Мобильное меню */
@media (max-width: 992px) {
    .header-nav a { padding: 10px 0; font-size: 14px; }
    .header-nav a i { margin-right: 8px; width: 20px; text-align: center; }
}
    /* CART SIDEBAR */
    .cart-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 2000; opacity: 0; visibility: hidden; transition: 0.3s; }
    .cart-overlay.open { opacity: 1; visibility: visible; }
    .cart-sidebar { position: fixed; top: 0; right: -420px; width: 100%; max-width: 400px; height: 100%; background: #1a1a1a; border-left: 1px solid rgba(200,166,86,0.15); z-index: 2001; transition: 0.3s ease-in-out; display: flex; flex-direction: column; box-shadow: -5px 0 30px rgba(0,0,0,0.5); }
    .cart-sidebar.open { right: 0; }
    .cart-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: #222; }
    .cart-header h3 { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
    .cart-items { flex: 1; overflow-y: auto; padding: 15px; }
    .cart-item { display: flex; gap: 12px; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); animation: fadeIn 0.3s; }
    .cart-item:last-child { border-bottom: none; }
    .cart-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #333; }
    .cart-item-info { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .cart-item-name { font-size: 13px; font-weight: 600; line-height: 1.3; color: #fff; }
    .cart-item-price { font-size: 14px; color: #c8a656; font-weight: 700; }
    .qty-controls { display: flex; align-items: center; gap: 10px; margin-top: 5px; }
    .qty-btn { width: 24px; height: 24px; border: 1px solid #444; background: #222; color: #fff; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-size: 12px; }
    .qty-btn:hover { border-color: #c8a656; color: #c8a656; }
    .cart-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); background: #222; }
    .cart-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; margin-bottom: 15px; color: #fff; }
    .cart-total span:last-child { color: #c8a656; }
    .checkout-btn { width: 100%; padding: 14px; background: #c8a656; color: #1a1a1a; border: none; font-weight: 700; text-transform: uppercase; cursor: pointer; border-radius: 6px; transition: 0.3s; letter-spacing: 1px; }
    .checkout-btn:hover { background: #e8c96a; }

    /* TOAST */
    #toast { visibility: hidden; min-width: 250px; background: #333; color: #fff; text-align: center; border-radius: 8px; padding: 12px 20px; position: fixed; z-index: 9999; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
    #toast.show { visibility: visible; animation: fadein 0.3s, fadeout 0.3s 2.7s forwards; }
    #toast.success { border-left: 4px solid #2ecc71; }
    #toast.error { border-left: 4px solid #e74c3c; }
    @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
    @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }
    @keyframes fadeIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
</style>
