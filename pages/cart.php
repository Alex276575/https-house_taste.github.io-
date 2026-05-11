<?php

$pageTitle = 'Корзина';
require_once __DIR__ . '/../includes/header.php';

if (!class_exists('User') || !class_exists('Cart')) {
    die('Ошибка: Классы User или Cart не найдены.');
}

$user = new User();
$cart = new Cart();
$userId = $user->isLoggedIn() ? $_SESSION['user_id'] : null;

$cartItems = $cart->getItems($userId);
$subtotalPrice = $cart->getTotal($userId);
$totalCount = $cart->getCount($userId);

// === Функция расчёта цены ===
function calculateFinalPrice($price, $oldPrice, $discountPercent) {
    $price = (float)($price ?? 0);
    $oldPrice = !empty($oldPrice) ? (float)$oldPrice : null;
    $discountPercent = (float)($discountPercent ?? 0);

    if ($oldPrice && $oldPrice > $price) {
        return ['final' => $price, 'original' => $oldPrice, 'hasDiscount' => true, 'discountPercent' => round((($oldPrice - $price) / $oldPrice) * 100)];
    }
    if ($discountPercent > 0) {
        $final = $price * (1 - $discountPercent / 100);
        return ['final' => $final, 'original' => $price, 'hasDiscount' => true, 'discountPercent' => $discountPercent];
    }
    return ['final' => $price, 'original' => null, 'hasDiscount' => false, 'discountPercent' => 0];
}

// Проверка первого заказа
$isFirstOrder = false;
if ($userId) {
    try {
        $stmtOrder = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status != 'cancelled'");
        $stmtOrder->execute([':uid' => $userId]);
        $isFirstOrder = $stmtOrder->fetchColumn() == 0;
    } catch (Exception $e) { $isFirstOrder = false; }
}

// Расчёт доставки
$deliveryMethod = $_POST['delivery_method'] ?? 'delivery';
$deliveryPrice = 0;
$deliveryText = 'Бесплатно';

if ($deliveryMethod === 'delivery') {
    if ($isFirstOrder && $subtotalPrice >= 1500) {
        $deliveryPrice = 0;
        $deliveryText = 'Бесплатно (первый заказ)';
    } else {
        $deliveryPrice = 150;
        $deliveryText = '150 ₽';
    }
} else {
    $deliveryPrice = 0;
    $deliveryText = 'Самовывоз';
}

$totalPrice = max(0, $subtotalPrice - 0 + $deliveryPrice);

// === РЕКОМЕНДУЕМЫЕ ТОВАРЫ ===
$recommendedProducts = [];
try {
    $excludeIds = array_filter(array_column($cartItems, 'product_id'), fn($id) => $id > 0);
    $sqlRec = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1";

    if (!empty($excludeIds)) {
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $sqlRec .= " AND p.id NOT IN ($placeholders)";
    }
    $sqlRec .= " ORDER BY p.is_hit DESC, RAND() LIMIT 4";

    $recommendStmt = $pdo->prepare($sqlRec);
    $recommendStmt->execute(!empty($excludeIds) ? $excludeIds : []);
    $recommendedProducts = $recommendStmt->fetchAll();
} catch (PDOException $e) { $recommendedProducts = []; }

// === UPSSELL ТОВАРЫ ===
$upsellItems = [];
try {
    $stmtUpsell = $pdo->query("SELECT * FROM upsell_items WHERE is_active = 1 ORDER BY sort_order ASC");
    $upsellItems = $stmtUpsell->fetchAll();
} catch (PDOException $e) { $upsellItems = []; }

// === УМНОЕ СООБЩЕНИЕ ===
$MIN_ORDER = 500;
$canCheckout = $subtotalPrice >= $MIN_ORDER;
$smartMessage = $canCheckout
    ? 'Вы можете дополнить свой заказ'
    : 'Дополните корзину на ' . ($MIN_ORDER - $subtotalPrice) . ' ₽, чтобы перейти к оформлению';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Дом Вкуса</title>
</head>
<body>

<main class="cart-container">
    <!-- Заголовок -->
    <div class="page-header-main">
        <h1><i class="fas fa-shopping-basket"></i> Ваша <span>корзина</span></h1>
        <a href="/house_of_taste/pages/catalog.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Вернуться к покупкам
        </a>
    </div>

    <?php if (empty($cartItems)): ?>
        <!-- ПУСТАЯ КОРЗИНА — АККУРАТНЫЙ СТИЛЬ -->
        <div class="cart-empty">
            <div class="icon-wrap">
                <i class="fas fa-shopping-basket"></i>
            </div>
            <h2>Ваша корзина пуста</h2>
            <p>Добавьте изысканные блюда из нашего меню, чтобы оформить заказ</p>
            <a href="/house_of_taste/pages/catalog.php" class="btn-primary">
                <i class="fas fa-fire"></i> Перейти в меню
            </a>
        </div>
    <?php else: ?>

        <div class="cart-layout">
            <!-- Список товаров (без чекбоксов) -->
            <div class="cart-items">
                <div class="cart-header">
                    <span>Товар</span>
                    <span class="unit-price-label">Цена</span>
                    <span>Кол-во</span>
                    <span style="text-align:right">Сумма</span>
                    <span></span>
                </div>

                <?php
                $upsellIdsInCart = array_filter(array_column($cartItems, 'product_id'), fn($id) => $id < 0);
                $upsellDataMap = [];

                if (!empty($upsellIdsInCart)) {
                    $positiveIds = array_map('abs', $upsellIdsInCart);
                    $placeholders = implode(',', array_fill(0, count($positiveIds), '?'));
                    try {
                        $stmtUpsellCart = $pdo->prepare("SELECT * FROM upsell_items WHERE id IN ($placeholders)");
                        $stmtUpsellCart->execute($positiveIds);
                        foreach ($stmtUpsellCart->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $upsellDataMap[-$row['id']] = $row;
                        }
                    } catch (PDOException $e) {}
                }

                foreach ($cartItems as $item):
                    $productId = $item['product_id'] ?? 0;
                    $isUpsellItem = $productId < 0;

                    if ($isUpsellItem) {
                        $upsellInfo = $upsellDataMap[$productId] ?? null;
                        if ($upsellInfo) {
                            $productName = $upsellInfo['name'];
                            $productPrice = (float)$upsellInfo['price'];
                            $productImage = $upsellInfo['image_url'];
                            $priceData = ['final' => $productPrice, 'original' => null, 'hasDiscount' => false];
                        } else {
                            $productName = 'Доп. товар #' . abs($productId);
                            $productPrice = 0;
                            $productImage = '';
                            $priceData = ['final' => 0, 'original' => null, 'hasDiscount' => false];
                        }
                    } else {
                        $productName = $item['name'] ?? 'Товар';
                        $productPrice = $item['price'] ?? 0;
                        $productOldPrice = $item['old_price'] ?? null;
                        $productDiscount = $item['discount_percent'] ?? 0;
                        $productImage = $item['image_url'] ?? '';
                        $priceData = calculateFinalPrice($productPrice, $productOldPrice, $productDiscount);
                    }

                    $productQty = $item['quantity'] ?? 1;
                    $itemTotal = $priceData['final'] * $productQty;
                    $imgPath = !empty($productImage)
                        ? (strpos($productImage, '/house_of_taste') === 0 ? $productImage : '/house_of_taste' . $productImage)
                        : '/house_of_taste/public/img/placeholder.png';
                ?>
                <div class="cart-item" data-id="<?= $productId ?>">
                    <div class="item-content">
                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($productName) ?>" class="item-img" loading="lazy">
                        <div class="item-info">
                            <div class="item-name"><?= htmlspecialchars($productName) ?></div>
                            <?php if ($priceData['hasDiscount'] && $priceData['original']): ?>
                                <div class="item-price-block">
                                    <span class="item-discount-badge">−<?= $priceData['discountPercent'] ?>%</span>
                                    <span class="item-price-original"><?= number_format($priceData['original'], 0, '.', ' ') ?> ₽</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="unit-price-col">
                        <span class="unit-price-label">за шт.</span>
                        <span class="item-price-final"><?= number_format($priceData['final'], 0, '.', ' ') ?> ₽</span>
                    </div>
                    <div class="item-controls">
                        <button class="qty-btn" onclick="updateQty(<?= $productId ?>, -1)" aria-label="−">−</button>
                        <span class="item-qty"><?= $productQty ?></span>
                        <button class="qty-btn" onclick="updateQty(<?= $productId ?>, 1)" aria-label="+">+</button>
                    </div>
                    <div class="item-total <?= $priceData['hasDiscount'] ? 'discounted' : '' ?>">
                        <?= number_format($itemTotal, 0, '.', ' ') ?> ₽
                    </div>
                    <button class="remove-btn" onclick="removeItem(<?= $productId ?>)" title="Удалить">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Блок оформления -->
            <div class="checkout-summary">
                <h3><i class="fas fa-receipt"></i> Детали заказа</h3>

                <!-- Способ получения -->
                <form method="POST" id="deliveryForm" class="delivery-method-selector">
                    <label><i class="fas fa-truck"></i> Способ получения</label>
                    <div class="delivery-options">
                        <label class="delivery-option">
                            <input type="radio" name="delivery_method" value="delivery" <?= $deliveryMethod === 'delivery' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="delivery-option-label"><i class="fas fa-motorcycle"></i> Доставка</span>
                        </label>
                        <label class="delivery-option">
                            <input type="radio" name="delivery_method" value="pickup" <?= $deliveryMethod === 'pickup' ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span class="delivery-option-label"><i class="fas fa-store"></i> Самовывоз</span>
                        </label>
                    </div>
                </form>

                <!-- Сводка цен -->
                <div class="summary-row">
                    <span>Товары (<?= $totalCount ?>)</span>
                    <strong id="summarySubtotal"><?= number_format($subtotalPrice, 0, '.', ' ') ?> ₽</strong>
                </div>
                <div class="summary-row delivery-info">
                    <span><i class="fas fa-truck"></i> Доставка</span>
                    <strong><?= $deliveryText ?></strong>
                </div>

                <?php if ($deliveryMethod === 'delivery' && !$isFirstOrder && $subtotalPrice < 1500): ?>
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px; padding: 8px 12px; background: rgba(200,166,86,0.1); border-radius: 8px;">
                        <i class="fas fa-info-circle"></i> Бесплатная доставка при заказе от 1500 ₽ (только для первого заказа)
                    </div>
                <?php endif; ?>

                <!-- УМНОЕ СООБЩЕНИЕ -->
                <div class="smart-message <?= $canCheckout ? 'can-checkout' : 'need-more' ?>">
                    <i class="fas <?= $canCheckout ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <span><?= $smartMessage ?></span>
                </div>

                <!-- Итого -->
                <div class="summary-total">
                    <span>Итого к оплате</span>
                    <span id="summaryTotal"><?= number_format($totalPrice, 0, '.', ' ') ?> ₽</span>
                </div>

                <!-- Кнопка оформления -->
                <?php if ($user->isLoggedIn()): ?>
                    <button class="checkout-btn" onclick="goToCheckout()" <?= $canCheckout ? '' : 'disabled' ?>>
                        <i class="fas fa-check-circle"></i> Оформить заказ
                    </button>
                <?php else: ?>
                    <div class="login-alert">
                        <i class="fas fa-shield-alt"></i>
                        <span>Для оформления необходимо <a href="/house_of_taste/auth/login.php?redirect=cart">войти</a> или <a href="/house_of_taste/auth/register.php?redirect=cart">зарегистрироваться</a></span>
                    </div>
                    <button class="checkout-btn" disabled>
                        <i class="fas fa-user-lock"></i> Требуется авторизация
                    </button>
                <?php endif; ?>

                <div class="back-link-container">
                    <a href="/house_of_taste/pages/catalog.php" class="back-link">
                        <i class="fas fa-utensils"></i> Продолжить выбор блюд
                    </a>
                </div>
            </div>
        </div>

        <!-- === БЛОК ДОПОЛНИТЕЛЬНЫХ ТОВАРОВ (всегда виден) === -->
        <?php if (!empty($upsellItems)): ?>
        <div class="upsell-section">
            <h2>Дополните свой заказ</h2>
            <div class="upsell-grid">
                <?php foreach ($upsellItems as $item):
                    $imgPath = !empty($item['image_url'])
                        ? (strpos($item['image_url'], '/house_of_taste') === 0 ? $item['image_url'] : '/house_of_taste' . $item['image_url'])
                        : '';
                ?>
                <div class="upsell-card">
                    <?php if (!empty($imgPath)): ?>
                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="upsell-image" loading="lazy">
                    <?php else: ?>
                        <div class="upsell-image" style="display:flex;align-items:center;justify-content:center;background:var(--darker);border-radius:10px;">
                            <i class="<?= htmlspecialchars($item['icon_class'] ?? 'fas fa-gift') ?>" style="font-size:48px;color:var(--gold);"></i>
                        </div>
                    <?php endif; ?>
                    <h4><?= htmlspecialchars($item['name']) ?></h4>
                    <p><?= htmlspecialchars($item['description']) ?></p>
                    <span class="price <?= empty($item['price']) ? 'free' : '' ?>">
                        <?= empty($item['price']) ? 'Бесплатно' : number_format($item['price'], 0, '.', ' ') . ' ₽' ?>
                    </span>
                    <button class="btn-add-upsell" onclick="addToCartUpsell(<?= $item['id'] ?>)">
                        <i class="fas fa-shopping-basket"></i> Добавить
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- === РЕКОМЕНДУЕМЫЕ ТОВАРЫ === -->
        <?php if (!empty($recommendedProducts)): ?>
        <div class="recommended-section">
            <h2>Рекомендуем попробовать</h2>
            <div class="recommended-grid">
                <?php foreach ($recommendedProducts as $prod):
                    $priceData = calculateFinalPrice((float)$prod['price'], $prod['old_price'] ?? null, (float)($prod['discount_percent'] ?? 0));
                    $imgPathRec = !empty($prod['image_url'])
                        ? (strpos($prod['image_url'], '/house_of_taste') === 0 ? $prod['image_url'] : '/house_of_taste' . $prod['image_url'])
                        : '/house_of_taste/public/img/placeholder.png';
                    $hasDiscount = $priceData['hasDiscount'];
                    $isHit = !empty($prod['is_hit']);
                    $isNew = !$isHit && !$hasDiscount && (strtotime($prod['created_at'] ?? time()) > strtotime('-7 days'));
                ?>
                <div class="recommended-card">
                    <div class="rc-image">
                        <img src="<?= htmlspecialchars($imgPathRec) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">
                        <?php if ($isHit): ?><span class="rc-badge hit">Хит</span><?php endif; ?>
                        <?php if ($hasDiscount): ?><span class="rc-badge sale">-<?= $priceData['discountPercent'] ?>%</span><?php endif; ?>
                        <?php if ($isNew): ?><span class="rc-badge new">Новинка</span><?php endif; ?>
                        <div class="rc-actions">
                            <button class="rc-action-btn" onclick="toggleFavorite(<?= $prod['id'] ?>)" title="В избранное">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="rc-info">
                        <div class="rc-category"><?= htmlspecialchars($prod['category_name'] ?? 'Блюдо') ?></div>
                        <h4 class="rc-name"><?= htmlspecialchars($prod['name']) ?></h4>
                        <p class="rc-desc"><?= htmlspecialchars(mb_substr($prod['description'] ?? '', 0, 70)) ?>...</p>
                        <div class="rc-bottom">
                            <div class="rc-price-wrap">
                                <span class="rc-price"><?= number_format($priceData['final'], 0, '.', ' ') ?> ₽</span>
                                <?php if ($hasDiscount && $priceData['original']): ?>
                                    <span class="rc-old-price"><?= number_format($priceData['original'], 0, '.', ' ') ?> ₽</span>
                                <?php endif; ?>
                            </div>
                            <div class="rc-btns">
                                <a href="/house_of_taste/pages/product.php?id=<?= $prod['id'] ?>" class="rc-details">Подробнее</a>
                                <button class="rc-add-btn" onclick="addToCart(<?= $prod['id'] ?>, '<?= addslashes($prod['name']) ?>', <?= $priceData['final'] ?>, '<?= htmlspecialchars($prod['image_url'] ?? '') ?>')" title="В корзину">
                                    <i class="fas fa-shopping-basket"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

<script>
// ===== УПРАВЛЕНИЕ КОРЗИНОЙ =====
function updateQty(id, change) {
    const itemRow = document.querySelector(`.cart-item[data-id="${id}"]`);
    if (!itemRow) return;
    const qtySpan = itemRow.querySelector('.item-qty');
    let currentQty = parseInt(qtySpan?.innerText) || 1;
    let newQty = Math.max(1, currentQty + change);
    qtySpan.innerText = newQty;

    fetch('/house_of_taste/api/cart_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: id, quantity: newQty, action: 'update' })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            qtySpan.innerText = currentQty;
            showNotification('Ошибка обновления', 'error');
        } else {
            location.reload();
        }
    })
    .catch(() => {
        qtySpan.innerText = currentQty;
        showNotification('Ошибка соединения', 'error');
    });
}

function removeItem(id) {
    if (!confirm('Удалить товар?')) return;
    const itemRow = document.querySelector(`.cart-item[data-id="${id}"]`);
    if (itemRow) itemRow.style.opacity = '0.5';

    fetch('/house_of_taste/api/cart_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: id, action: 'remove' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else if (itemRow) itemRow.style.opacity = '1';
    });
}

// ===== ДОБАВЛЕНИЕ ТОВАРОВ =====
function addToCart(productId, productName, productPrice, productImage) {
    fetch('/house_of_taste/api/cart_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: productId,
            name: productName,
            price: productPrice,
            image: productImage,
            quantity: 1,
            action: 'add'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Добавлено в корзину!', 'success');
            if (typeof updateCartBadge === 'function' && data.cartCount !== undefined) {
                updateCartBadge(data.cartCount);
            }
        } else {
            showNotification('' + (data.message || 'Не удалось добавить'), 'error');
        }
    })
    .catch(() => showNotification('Ошибка соединения', 'error'));
}

function addToCartUpsell(upsellId) {
    fetch('/house_of_taste/api/cart_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ upsell_id: upsellId, quantity: 1, action: 'add_upsell' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Добавлено!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showNotification('' + (data.error || 'Не удалось добавить'), 'error');
        }
    })
    .catch(() => showNotification('Ошибка соединения', 'error'));
}

// ===== ИЗБРАННОЕ =====
function toggleFavorite(productId) {
    if (typeof window.toggleFavorite === 'function') {
        window.toggleFavorite(productId, null);
    } else {
        fetch('/house_of_taste/api/favorites_toggle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_id: productId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.added ? '❤️ В избранном' : 'Удалено из избранного', 'success');
                if (typeof updateFavBadge === 'function') updateFavBadge();
            }
        })
        .catch(() => showNotification('Ошибка', 'error'));
    }
}

// ===== ОФОРМЛЕНИЕ ЗАКАЗА =====
function goToCheckout() {
    const delivery = document.querySelector('input[name="delivery_method"]:checked')?.value || 'delivery';
    window.location.href = '/house_of_taste/pages/checkout.php?delivery=' + delivery;
}

// ===== УВЕДОМЛЕНИЯ =====
function showNotification(message, type = 'success') {
    const old = document.querySelector('.cart-notification');
    if (old) old.remove();

    const n = document.createElement('div');
    n.className = 'cart-notification' + (type === 'error' ? ' error' : '');
    n.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(n);

    setTimeout(() => {
        n.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => n.remove(), 300);
    }, 2500);
}
</script>

<style>
    :root {
        --gold: #c8a656;
        --gold-hover: #e8c96a;
        --dark: #1a1a1a;
        --darker: #111;
        --card-bg: #222;
        --border: rgba(255,255,255,0.08);
        --text-primary: #fff;
        --text-secondary: #aaa;
        --text-muted: #666;
        --error: #e74c3c;
        --success: #2ecc71;
        --warning: #f39c12;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    a { color: inherit; text-decoration: none; transition: color 0.3s; }
    a:hover { color: var(--gold); }
    button { font-family: inherit; cursor: pointer; }
    img { max-width: 100%; display: block; }

    /* ===== КОНТЕЙНЕР ===== */
    .cart-container {
        max-width: 1400px;
        margin: 100px auto 50px;
        padding: 0 24px;
        min-height: 60vh;
    }

    /* ===== ПУСТАЯ КОРЗИНА — АККУРАТНЫЙ СТИЛЬ ===== */
    .cart-empty {
        text-align: center;
        padding: 70px 30px;
        background: var(--card-bg);
        border-radius: 20px;
        border: 1px solid var(--border);
        max-width: 520px;
        margin: 0 auto;
    }

    .cart-empty .icon-wrap {
        width: 90px;
        height: 90px;
        background: linear-gradient(135deg, rgba(200,166,86,0.15), rgba(200,166,86,0.05));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        border: 2px solid var(--gold);
    }

    .cart-empty .icon-wrap i {
        font-size: 42px;
        color: var(--gold);
    }

    .cart-empty h2 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
    }

    .cart-empty p {
        color: var(--text-secondary);
        margin-bottom: 28px;
        line-height: 1.5;
    }

    .cart-empty .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 32px;
        background: linear-gradient(135deg, var(--gold), #b89646);
        color: var(--darker);
        border: none;
        border-radius: 12px;
        font-weight: 700;
        font-size: 15px;
        transition: all 0.3s;
        box-shadow: 0 8px 25px rgba(200,166,86,0.3);
    }

    .cart-empty .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(200,166,86,0.45);
        color: var(--darker);
    }

    /* ===== ЗАГОЛОВОК СТРАНИЦЫ ===== */
    .page-header-main {
        text-align: center;
        margin-bottom: 35px;
    }

    .page-header-main h1 {
        font-size: 34px;
        font-weight: 400;
        margin: 0 0 18px 0;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
    }

    .page-header-main h1 i {
        color: var(--gold);
        font-size: 36px;
    }

    .page-header-main h1 span {
        color: var(--gold);
        font-weight: 700;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text-secondary);
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-back:hover {
        border-color: var(--gold);
        color: var(--gold);
        background: rgba(200,166,86,0.08);
    }

    /* ===== СЕТКА КОРЗИНЫ ===== */
    .cart-layout {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 28px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .cart-layout { grid-template-columns: 1fr; }
    }

    /* ===== СПИСОК ТОВАРОВ ===== */
    .cart-items {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 24px;
        border: 1px solid var(--border);
    }

    .cart-header {
        display: grid;
        grid-template-columns: 2fr 120px 100px 90px 40px;
        gap: 16px;
        padding: 12px 16px;
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .cart-item {
        display: grid;
        grid-template-columns: 2fr 120px 100px 90px 40px;
        gap: 16px;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid var(--border);
        transition: background 0.2s;
    }

    .cart-item:last-child { border-bottom: none; }
    .cart-item:hover { background: rgba(255,255,255,0.02); border-radius: 12px; }

    .item-content {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }

    .item-img {
        width: 70px;
        height: 70px;
        border-radius: 12px;
        object-fit: cover;
        background: var(--darker);
        border: 1px solid var(--border);
        flex-shrink: 0;
    }

    .item-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .item-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .item-price-block {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .item-price-final {
        font-size: 16px;
        font-weight: 700;
        color: var(--gold);
    }

    .item-price-original {
        font-size: 12px;
        color: var(--text-muted);
        text-decoration: line-through;
    }

    .item-discount-badge {
        font-size: 10px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--error), #c0392b);
        padding: 2px 8px;
        border-radius: 12px;
    }

    .unit-price-col {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }

    .unit-price-label {
        font-size: 9px;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .item-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: var(--darker);
        padding: 3px;
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .qty-btn {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-size: 16px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .qty-btn:hover {
        background: var(--gold);
        color: var(--darker);
    }

    .item-qty {
        font-size: 14px;
        font-weight: 600;
        width: 32px;
        text-align: center;
        color: var(--text-primary);
    }

    .item-total {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        text-align: right;
    }

    .item-total.discounted { color: var(--gold); }

    .remove-btn {
        color: var(--text-muted);
        background: none;
        border: none;
        font-size: 14px;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .remove-btn:hover {
        color: var(--error);
        background: rgba(231,76,60,0.1);
    }

    /* ===== БЛОК ОФОРМЛЕНИЯ ===== */
    .checkout-summary {
        background: linear-gradient(145deg, var(--card-bg), var(--darker));
        border-radius: 20px;
        padding: 26px;
        border: 1px solid var(--border);
        position: sticky;
        top: 100px;
    }

    .checkout-summary h3 {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ===== СПОСОБ ПОЛУЧЕНИЯ ===== */
    .delivery-method-selector { margin-bottom: 22px; }
    .delivery-method-selector label {
        font-size: 11px;
        color: var(--text-muted);
        display: block;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .delivery-options { display: flex; gap: 10px; }
    .delivery-option { flex: 1; position: relative; }
    .delivery-option input { position: absolute; opacity: 0; }

    .delivery-option-label {
        display: block;
        padding: 12px;
        background: var(--darker);
        border: 2px solid var(--border);
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 600;
        font-size: 12px;
    }

    .delivery-option input:checked + .delivery-option-label {
        border-color: var(--gold);
        background: rgba(200,166,86,0.1);
        color: var(--gold);
    }

    .delivery-option-label i {
        display: block;
        font-size: 18px;
        margin-bottom: 4px;
    }

    /* ===== СВОДКА ЦЕН ===== */
    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        color: var(--text-secondary);
        font-size: 14px;
        padding: 6px 0;
    }

    .summary-row.delivery-info {
        font-size: 13px;
        color: var(--text-muted);
        background: rgba(255,255,255,0.03);
        padding: 8px 12px;
        border-radius: 8px;
        margin-bottom: 14px;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .summary-total span:last-child {
        color: var(--gold);
        font-size: 26px;
    }

    /* ===== УМНОЕ СООБЩЕНИЕ ===== */
    .smart-message {
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13px;
        margin: 16px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .smart-message.can-checkout {
        background: rgba(46,204,113,0.12);
        border-left: 3px solid var(--success);
        color: var(--success);
    }

    .smart-message.need-more {
        background: rgba(243,156,18,0.12);
        border-left: 3px solid var(--warning);
        color: var(--warning);
    }

    .smart-message i { font-size: 16px; }

    /* ===== КНОПКА ОФОРМЛЕНИЯ ===== */
    .checkout-btn {
        width: 100%;
        padding: 16px 20px;
        background: linear-gradient(135deg, var(--gold), #b89646);
        color: var(--darker);
        border: none;
        border-radius: 12px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 14px;
        margin-top: 20px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        letter-spacing: 0.5px;
        box-shadow: 0 8px 25px rgba(200,166,86,0.3);
    }

    .checkout-btn:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 14px 40px rgba(200,166,86,0.45);
    }

    .checkout-btn:disabled {
        background: var(--darker);
        color: var(--text-muted);
        cursor: not-allowed;
        box-shadow: none;
        border: 2px solid var(--border);
    }

    /* ===== ПРЕДУПРЕЖДЕНИЕ О ВХОДЕ ===== */
    .login-alert {
        background: linear-gradient(135deg, rgba(231,76,60,0.15), rgba(231,76,60,0.08));
        border: 1px solid var(--error);
        color: #fff;
        padding: 14px 18px;
        border-radius: 12px;
        margin-top: 20px;
        font-size: 13px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .login-alert a { color: var(--gold); font-weight: 600; }

    .back-link-container {
        text-align: center;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }

    .back-link {
        color: var(--text-secondary);
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
        padding: 8px 16px;
        border-radius: 8px;
    }

    .back-link:hover {
        color: var(--gold);
        background: rgba(200,166,86,0.1);
    }

    /* ===== БЛОК ДОПОЛНИТЕЛЬНЫХ ТОВАРОВ ===== */
    .upsell-section {
        margin: 45px 0 25px;
        padding: 28px;
        background: linear-gradient(145deg, var(--card-bg), var(--darker));
        border-radius: 20px;
        border: 1px solid var(--border);
    }

    .upsell-section h2 {
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 22px;
        color: var(--text-primary);
    }

    .upsell-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }

    @media (max-width: 1200px) { .upsell-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .upsell-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px)  { .upsell-grid { grid-template-columns: 1fr; } }

    .upsell-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 18px;
        border: 1px solid var(--border);
        text-align: center;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .upsell-card:hover {
        border-color: var(--gold);
        transform: translateY(-4px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.3);
    }

    .upsell-image {
        width: 100%;
        height: 130px;
        object-fit: contain;
        margin-bottom: 14px;
        border-radius: 10px;
        background: var(--darker);
        padding: 8px;
    }

    .upsell-card h4 {
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 6px 0;
        color: var(--text-primary);
        min-height: 34px;
    }

    .upsell-card p {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0 0 10px 0;
        line-height: 1.4;
        min-height: 30px;
        flex: 1;
    }

    .upsell-card .price {
        font-size: 18px;
        font-weight: 700;
        color: var(--gold);
        margin-bottom: 10px;
        display: block;
    }

    .upsell-card .price.free { color: var(--success); }

    .btn-add-upsell {
        width: 100%;
        padding: 9px;
        background: transparent;
        border: 2px solid var(--gold);
        color: var(--gold);
        border-radius: 10px;
        font-weight: 600;
        font-size: 12px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-top: auto;
    }

    .btn-add-upsell:hover {
        background: var(--gold);
        color: var(--darker);
        transform: translateY(-2px);
    }

    /* ===== РЕКОМЕНДУЕМЫЕ ТОВАРЫ ===== */
    .recommended-section {
        margin: 45px 0 25px;
        padding: 28px;
        background: linear-gradient(145deg, var(--card-bg), var(--darker));
        border-radius: 20px;
        border: 1px solid var(--border);
    }

    .recommended-section h2 {
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 22px;
        color: var(--text-primary);
    }

    .recommended-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }

    @media (max-width: 1200px) { .recommended-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .recommended-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px)  { .recommended-grid { grid-template-columns: 1fr; } }

    .recommended-card {
        background: var(--card-bg);
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--border);
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .recommended-card:hover {
        border-color: var(--gold);
        transform: translateY(-4px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.3);
    }

    .rc-image {
        position: relative;
        height: 170px;
        overflow: hidden;
    }

    .rc-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s;
    }

    .recommended-card:hover .rc-image img { transform: scale(1.08); }

    .rc-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 3px 9px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        border-radius: 4px;
        z-index: 2;
    }

    .rc-badge.sale { background: var(--error); color: #fff; }
    .rc-badge.new { background: var(--gold); color: var(--darker); }
    .rc-badge.hit { background: var(--success); color: var(--darker); }

    .rc-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 2;
    }

    .recommended-card:hover .rc-actions { opacity: 1; }

    .rc-action-btn {
        width: 32px;
        height: 32px;
        background: rgba(0,0,0,0.75);
        border: 1px solid rgba(255,255,255,0.15);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: all 0.3s;
        border-radius: 50%;
    }

    .rc-action-btn:hover {
        background: var(--gold);
        color: var(--darker);
        border-color: var(--gold);
    }

    .rc-info { padding: 14px; flex: 1; display: flex; flex-direction: column; }

    .rc-category {
        font-size: 9px;
        color: var(--gold);
        letter-spacing: 1.2px;
        text-transform: uppercase;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .rc-name {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--text-primary);
        line-height: 1.3;
        min-height: 38px;
    }

    .rc-desc {
        font-size: 11px;
        color: var(--text-muted);
        line-height: 1.4;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
    }

    .rc-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 10px;
        border-top: 1px solid var(--border);
        margin-top: auto;
    }

    .rc-price-wrap { display: flex; flex-direction: column; }
    .rc-price { font-size: 16px; font-weight: 700; color: var(--gold); }
    .rc-old-price { font-size: 10px; color: var(--text-muted); text-decoration: line-through; }

    .rc-btns { display: flex; align-items: center; gap: 6px; }

    .rc-details {
        font-size: 11px;
        color: var(--text-secondary);
        transition: color 0.3s;
    }
    .rc-details:hover { color: var(--gold); }

    .rc-add-btn {
        width: 34px;
        height: 34px;
        border: 1px solid rgba(200,166,86,0.4);
        background: transparent;
        color: var(--gold);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: all 0.3s;
        border-radius: 4px;
    }

    .rc-add-btn:hover {
        background: var(--gold);
        color: var(--darker);
    }

    /* ===== АДАПТИВ ===== */
    @media (max-width: 768px) {
        .page-header-main h1 { font-size: 26px; flex-direction: column; gap: 8px; }
        .cart-header { display: none; }
        .cart-item {
            grid-template-columns: 1fr 80px 40px;
            grid-template-areas: "img price remove" "info price remove" "info qty remove" "info total remove";
            gap: 12px;
            padding: 14px;
        }
        .item-content { grid-area: img; }
        .item-img { width: 60px; height: 60px; }
        .item-info { grid-area: info; }
        .unit-price-col { grid-area: price; text-align: right; align-items: flex-end; }
        .item-controls { grid-area: qty; justify-content: flex-end; }
        .item-total { grid-area: total; text-align: right; font-size: 18px; }
        .remove-btn { grid-area: remove; }
        .delivery-options { flex-direction: column; }
        .checkout-summary { position: static; }
    }

    /* ===== УВЕДОМЛЕНИЯ ===== */
    .cart-notification {
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        color: #fff;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        z-index: 10000;
        font-weight: 600;
        font-size: 13px;
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cart-notification.error {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }

    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
