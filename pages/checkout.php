<?php

$pageTitle = 'Оформление заказа';
$currentPage = 'checkout';

// === 1. ПРОВЕРКА СЕССИИ ВНАЧАЛЕ ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../classes/Order.php';

// === 2. ПРОВЕРКА АВТОРИЗАЦИИ ===
if (!$isLoggedIn) {
    // Сохраняем текущий метод доставки для редиректа
    $delivery = $_GET['delivery'] ?? 'delivery';
    $_SESSION['redirect_after_login'] = '/house_of_taste/pages/checkout.php?delivery=' . $delivery;

    // Показываем сообщение и перенаправляем
    $_SESSION['flash_message'] = [
        'type' => 'info',
        'text' => 'Для оформления заказа необходимо войти в аккаунт'
    ];
    header('Location: /house_of_taste/auth/login.php');
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$userId = $_SESSION['user_id'];
$order = new Order($pdo);

// === 3. ПРОВЕРКА КОРЗИНЫ ===
$cartStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM cart
    WHERE user_id = :uid AND (product_id > 0 OR product_id < 0)
");
$cartStmt->execute([':uid' => $userId]);
$cartCount = $cartStmt->fetchColumn();

if ($cartCount == 0) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'text' => 'Ваша корзина пуста. Добавьте товары перед оформлением заказа.'
    ];
    header('Location: /house_of_taste/pages/catalog.php');
    exit;
}

// === Получаем данные пользователя ===
$stmt = $pdo->prepare("SELECT full_name, phone FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// === Получаем товары корзины (обычные + upsell) ===
$cartItems = [];

// 1. Обычные товары
$cartStmt = $pdo->prepare("
    SELECT c.quantity, c.product_id, p.name, p.price, p.old_price, p.discount_percent, p.image_url, p.category_id, 'product' as type
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = :uid AND p.is_available = 1
    ORDER BY c.added_at DESC
");
$cartStmt->execute([':uid' => $userId]);
$regularItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
if ($regularItems) {
    $cartItems = array_merge($cartItems, $regularItems);
}

// 2. Upsell товары
$upsellStmt = $pdo->prepare("
    SELECT c.quantity, c.product_id, u.id AS upsell_id, u.name, u.price, u.image_url, u.category, 'upsell' as type
    FROM cart c
    JOIN upsell_items u ON ABS(c.product_id) = u.id
    WHERE c.user_id = :uid AND c.product_id < 0 AND u.is_active = 1
    ORDER BY c.added_at DESC
");
$upsellStmt->execute([':uid' => $userId]);
$upsellItems = $upsellStmt->fetchAll(PDO::FETCH_ASSOC);
if ($upsellItems) {
    $cartItems = array_merge($cartItems, $upsellItems);
}

// === Расчёт суммы корзины ===
$subtotalPrice = 0;
foreach ($cartItems as $item) {
    $price = (float)($item['price'] ?? 0);
    $qty = (int)($item['quantity'] ?? 1);
    $subtotalPrice += $price * $qty;
}

// === Способ доставки ===
$deliveryMethod = $_GET['delivery'] ?? $_POST['delivery_method'] ?? 'delivery';

// === Расчёт доставки ===
$isFirstOrderStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status != 'cancelled'");
$isFirstOrderStmt->execute([':uid' => $userId]);
$isFirstOrder = $isFirstOrderStmt->fetchColumn() == 0;

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
}

// === Адреса пользователя ===
$addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = :uid ORDER BY is_default DESC");
$addrStmt->execute([':uid' => $userId]);
$userAddresses = $addrStmt->fetchAll();

// === ПРОМОКОД ===
$promoFromCart = $_SESSION['applied_promo'] ?? null;
$promoError = '';
$promoSuccess = '';
$appliedPromo = null;
$promoDiscount = 0;

if ($promoFromCart && $deliveryMethod !== 'pickup') {
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = :code AND is_active = 1 AND valid_from <= CURDATE() AND valid_to >= CURDATE()");
    $stmt->execute([':code' => $promoFromCart]);
    $promo = $stmt->fetch();

    if ($promo) {
        $valid = true;
        if (!empty($promo['is_first_order_only']) && !$isFirstOrder) $valid = false;
        if (!empty($promo['min_order_amount']) && $subtotalPrice < $promo['min_order_amount']) $valid = false;
        if ($promo['max_uses'] !== null && $promo['current_uses'] >= $promo['max_uses']) $valid = false;

        if ($valid) {
            $appliedPromo = $promo;
            $promoDiscount = $promo['discount_type'] === 'percent'
                ? $subtotalPrice * ($promo['discount_value'] / 100)
                : min($promo['discount_value'], $subtotalPrice);
            $promoSuccess = 'Промокод ' . htmlspecialchars($promo['code']) . ' применён';
        }
    }
    unset($_SESSION['applied_promo']);
}

// Обработка нового промокода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_promo']) && $deliveryMethod !== 'pickup' && !$appliedPromo) {
    $code = trim(strtoupper($_POST['promo_code'] ?? ''));
    if ($code) {
        $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = :code AND is_active = 1 AND valid_from <= CURDATE() AND valid_to >= CURDATE()");
        $stmt->execute([':code' => $code]);
        $promo = $stmt->fetch();

        if ($promo) {
            if (!empty($promo['is_first_order_only']) && !$isFirstOrder) {
                $promoError = 'Промокод действует только на первый заказ';
            } elseif (!empty($promo['min_order_amount']) && $subtotalPrice < $promo['min_order_amount']) {
                $promoError = 'Минимальная сумма: ' . number_format($promo['min_order_amount'], 0, '.', ' ') . ' ₽';
            } elseif ($promo['max_uses'] !== null && $promo['current_uses'] >= $promo['max_uses']) {
                $promoError = 'Промокод больше не действует';
            } else {
                $appliedPromo = $promo;
                $promoDiscount = $promo['discount_type'] === 'percent'
                    ? $subtotalPrice * ($promo['discount_value'] / 100)
                    : min($promo['discount_value'], $subtotalPrice);
                $promoSuccess = 'Применён: −' . ($promo['discount_type'] === 'percent' ? $promo['discount_value'].'%' : number_format($promo['discount_value'],0,'.',' ').' ₽');
            }
        } else {
            $promoError = 'Промокод не найден';
        }
    }
}

// === Итоговая сумма ===
$totalPrice = max(0, $subtotalPrice - $promoDiscount + $deliveryPrice);

// === Время доставки ===
$estimatedTime = date('H:i', strtotime('+50 minutes'));
$estimatedDate = date('d.m.Y');
if ($deliveryMethod === 'pickup') {
    $estimatedTime = date('H:i', strtotime('+25 minutes'));
}

// === ОБРАБОТКА ЗАКАЗА ===
$orderError = '';
$orderSuccess = false;
$orderId = null;
$successOrderIdFormatted = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $tipAmount = floatval($_POST['tip_amount'] ?? 0);
    $finalTotal = $totalPrice + $tipAmount;

    $data = [
        'delivery_method' => $_POST['delivery_method'] ?? 'delivery',
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'address_id' => $_POST['address_id'] ?? null,
        'promo_code' => $appliedPromo ? $appliedPromo['code'] : (isset($_POST['promo_code']) ? $_POST['promo_code'] : ''),
        'tip_amount' => $tipAmount,
        'customer_comment' => trim($_POST['customer_comment'] ?? ''),
        'recipient_name' => !empty($_POST['order_for_other']) ? trim($_POST['recipient_name'] ?? '') : null,
        'recipient_phone' => !empty($_POST['order_for_other']) ? trim($_POST['recipient_phone'] ?? '') : null,
        'total_amount' => $subtotalPrice,
        'final_amount' => $finalTotal
    ];

    // Новый адрес
    if ($data['delivery_method'] === 'delivery' && ($data['address_id'] ?? 0) == -1) {
        $newStreet = trim($_POST['new_street'] ?? '');
        $newHouse = trim($_POST['new_house'] ?? '');
        if (empty($newStreet) || empty($newHouse)) {
            $orderError = 'Пожалуйста, заполните улицу и дом';
        } else {
            try {
                $stmtAddr = $pdo->prepare("INSERT INTO user_addresses (user_id, city, street, house, apartment, entrance, floor, comment)
                    VALUES (:uid, :city, :street, :house, :apt, :entr, :floor, :comm)");
                $stmtAddr->execute([
                    ':uid' => $userId, ':city' => trim($_POST['new_city'] ?? 'Москва'),
                    ':street' => $newStreet, ':house' => $newHouse,
                    ':apt' => trim($_POST['new_apartment'] ?? ''), ':entr' => trim($_POST['new_entrance'] ?? ''),
                    ':floor' => trim($_POST['new_floor'] ?? ''), ':comm' => trim($_POST['new_comment'] ?? '')
                ]);
                $data['address_id'] = $pdo->lastInsertId();
            } catch (PDOException $e) {
                $orderError = 'Ошибка адреса: ' . $e->getMessage();
            }
        }
    }

    if (empty($orderError)) {
        $result = $order->create($userId, $data);
        if ($result['success']) {
            $orderSuccess = true;
            $orderId = $result['order_id'];
            $successOrderIdFormatted = str_pad($orderId, 6, '0', STR_PAD_LEFT);

            // Очищаем корзину
            $pdo->prepare("DELETE FROM cart WHERE user_id = :uid")->execute([':uid' => $userId]);
        } else {
            $orderError = $result['error'];
        }
    }
}
?>

<main class="checkout-container">

    <!-- Модальное окно успеха -->
    <?php if ($orderSuccess): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="success-modal">
            <i class="fas fa-check-circle success-icon-large"></i>
            <h2 class="success-title">Заказ успешно оформлен!</h2>
            <p class="success-text">Номер вашего заказа:</p>
            <div class="success-order-id">#<?= $successOrderIdFormatted ?></div>
            <p class="success-text">Мы уже начали его готовить.</p>
            <a href="/house_of_taste/pages/orders.php" class="btn-modal">
                <i class="fas fa-receipt"></i> Перейти к моим заказам
            </a>
        </div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = '/house_of_taste/pages/orders.php';
        }, 3000);
    </script>
    <?php endif; ?>

    <?php if (!$orderSuccess): ?>
        <div class="checkout-header">
            <h1>Оформление <span>заказа</span></h1>
            <a href="/house_of_taste/pages/cart.php" class="btn-back"><i class="fas fa-arrow-left"></i> Вернуться в корзину</a>
        </div>

        <div class="checkout-grid">
            <form method="POST" class="checkout-form" novalidate>

                <!-- Контакты -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-user"></i> Контактные данные</div>

                    <div class="order-for-other-toggle" onclick="toggleOrderForOther()">
                        <input type="checkbox" id="orderForOther" name="order_for_other">
                        <label for="orderForOther" style="cursor: pointer; margin: 0; font-weight: 500;">Заказ для другого человека</label>
                    </div>

                    <div id="mainContactFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Имя *</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($userData['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Телефон *</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" required pattern="[0-9\+\-\(\)\s]{10,}">
                            </div>
                        </div>
                    </div>

                    <div id="otherContactFields" class="order-for-other-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Имя получателя *</label>
                                <input type="text" name="recipient_name" class="form-control" placeholder="Введите имя">
                            </div>
                            <div class="form-group">
                                <label>Телефон получателя *</label>
                                <input type="tel" name="recipient_phone" class="form-control" placeholder="+7 (___) ___-__-__">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Доставка -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-truck"></i> Способ получения</div>

                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="delivery_method" value="delivery" <?= $deliveryMethod === 'delivery' ? 'checked' : '' ?> onchange="toggleDelivery()">
                            <span><i class="fas fa-motorcycle"></i> Доставка</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="delivery_method" value="pickup" <?= $deliveryMethod === 'pickup' ? 'checked' : '' ?> onchange="toggleDelivery()">
                            <span><i class="fas fa-store"></i> Самовывоз</span>
                        </label>
                    </div>

                    <div id="deliveryBlock" style="<?= $deliveryMethod === 'pickup' ? 'display:none' : '' ?>">
                        <?php if (!empty($userAddresses)): ?>
                            <?php foreach ($userAddresses as $addr): ?>
                                <label class="address-card" onclick="selectAddress(this)">
                                    <input type="radio" name="address_id" value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'checked' : '' ?>>
                                    <strong><?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['street']) ?>, <?= htmlspecialchars($addr['house']) ?></strong>
                                    <?php if($addr['apartment']): ?><br><small>Кв. <?= htmlspecialchars($addr['apartment']) ?></small><?php endif; ?>
                                    <?php if($addr['comment']): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($addr['comment']) ?></small><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <label class="address-card" onclick="showNewAddressForm()">
                            <input type="radio" name="address_id" value="-1">
                            <i class="fas fa-plus"></i> Добавить новый адрес
                        </label>

                        <div class="new-address-form" id="newAddressForm">
                            <div class="form-row">
                                <input type="text" name="new_city" class="form-control" placeholder="Город" value="Москва">
                                <input type="text" name="new_street" class="form-control" placeholder="Улица *" data-required="true">
                            </div>
                            <div class="form-row">
                                <input type="text" name="new_house" class="form-control" placeholder="Дом *" data-required="true">
                                <input type="text" name="new_apartment" class="form-control" placeholder="Квартира">
                            </div>
                            <div class="form-row">
                                <input type="text" name="new_entrance" class="form-control" placeholder="Подъезд">
                                <input type="text" name="new_floor" class="form-control" placeholder="Этаж">
                            </div>
                            <input type="text" name="new_comment" class="form-control" placeholder="Комментарий курьеру">
                        </div>

                        <?php if ($deliveryMethod === 'delivery' && !$isFirstOrder && $subtotalPrice < 1500): ?>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 15px; padding: 10px; background: rgba(200,166,86,0.05); border-radius: 8px;">
                                <i class="fas fa-info-circle"></i> Бесплатная доставка при заказе от 1500 ₽ (только для первого заказа)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="pickupBlock" style="<?= $deliveryMethod === 'pickup' ? '' : 'display:none' ?>; padding: 15px; background: rgba(200,166,86,0.05); border-radius: 12px; margin-top: 10px;">
                        <i class="fas fa-map-marker-alt" style="color: var(--gold); margin-right: 8px;"></i>
                        <strong>Москва, ул. Тверская, 15</strong><br>
                        <small style="color: var(--text-muted);">Ежедневно 10:00–23:00</small>
                    </div>
                </div>

                <!-- Оплата -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-credit-card"></i> Оплата</div>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">
                        <i class="fas fa-info-circle" style="color: var(--gold);"></i> Оплата производится при получении заказа
                    </p>

                    <div class="payment-methods">
                        <label class="payment-card selected" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="cash" checked>
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Наличные курьеру</span>
                        </label>
                        <label class="payment-card" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="card">
                            <i class="fas fa-credit-card"></i>
                            <span>Картой курьеру</span>
                        </label>
                    </div>

                    <div class="tip-section">
                        <i class="fas fa-mug-hot"></i>
                        <div style="flex: 1;">
                            <div style="font-weight: 600;">Чаевые курьеру</div>
                            <div style="font-size: 12px; color: var(--text-muted);">По желанию</div>
                        </div>
                        <input type="number" name="tip_amount" class="form-control tip-amount" value="0" min="0" max="5000" oninput="calcTotal()">
                        <span>₽</span>
                    </div>
                </div>

                <!-- Промокод -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-tag"></i> Промокод</div>
                    <?php if ($deliveryMethod === 'pickup'): ?>
                        <div class="promo-message promo-disabled">
                            <i class="fas fa-ban"></i> Промокоды действуют только при доставке
                        </div>
                    <?php elseif ($appliedPromo): ?>
                        <div class="promo-readonly">
                            <i class="fas fa-check-circle"></i>
                            <strong><?= htmlspecialchars($appliedPromo['code']) ?></strong>
                            <span style="color: var(--success); margin-left: auto;">
                                −<?= $appliedPromo['discount_type'] === 'percent' ? $appliedPromo['discount_value'].'%' : number_format($appliedPromo['discount_value'],0,'.',' ').' ₽' ?>
                            </span>
                        </div>
                        <input type="hidden" name="promo_code" value="<?= htmlspecialchars($appliedPromo['code']) ?>">
                    <?php else: ?>
                        <div class="promo-form">
                            <div class="promo-input">
                                <input type="text" name="promo_code" id="promoInput" class="form-control" placeholder="WELCOME10" value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>" <?= $appliedPromo ? 'disabled' : '' ?>>
                                <button type="submit" name="apply_promo" <?= $appliedPromo ? 'disabled' : '' ?>>Применить</button>
                            </div>
                            <?php if ($promoSuccess): ?>
                                <div class="promo-message promo-success"><i class="fas fa-check-circle"></i> <?= $promoSuccess ?></div>
                            <?php endif; ?>
                            <?php if ($promoError): ?>
                                <div class="promo-message promo-error"><i class="fas fa-exclamation-circle"></i> <?= $promoError ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Комментарий -->
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-comment"></i> Комментарий к заказу</div>
                    <textarea name="customer_comment" class="form-control" rows="3" placeholder="Пожелания по приготовлению, времени доставки и т.д."></textarea>
                </div>

                <?php if ($orderError): ?>
                    <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($orderError) ?></div>
                <?php endif; ?>

                <button type="submit" name="place_order" class="checkout-btn">
                    <i class="fas fa-check-circle"></i> Подтвердить заказ
                </button>

            </form>

            <!-- Итого -->
            <div class="order-summary">
                <h3 style="margin: 0 0 20px; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-receipt" style="color: var(--gold);"></i> Ваш заказ
                </h3>

                <div class="cart-items-preview">
                    <?php foreach ($cartItems as $item):
                        $price = $item['price'];
                        if (!empty($item['image_url'])) {
                            $img = (strpos($item['image_url'], '/house_of_taste') === 0) ? $item['image_url'] : '/house_of_taste' . $item['image_url'];
                        } else {
                            $img = '/house_of_taste/public/img/placeholder.png';
                        }
                    ?>
                    <div class="cart-item-mini">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        <div class="info">
                            <div class="name">
                                <?= htmlspecialchars($item['name']) ?>
                                <?php if ($item['type'] === 'upsell'): ?>
                                    <span class="type-badge"><i class="fas fa-gift"></i> Доп.</span>
                                <?php endif; ?>
                            </div>
                            <div class="meta"><?= $item['quantity'] ?> × <?= number_format($price, 0, '.', ' ') ?> ₽</div>
                        </div>
                        <div class="price"><?= number_format($price * $item['quantity'], 0, '.', ' ') ?> ₽</div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-row">
                    <span>Товары (<?= array_sum(array_column($cartItems, 'quantity')) ?>)</span>
                    <span><?= number_format($subtotalPrice, 0, '.', ' ') ?> ₽</span>
                </div>

                <?php if ($promoDiscount > 0 && $appliedPromo): ?>
                    <div class="summary-row savings">
                        <span><i class="fas fa-tag"></i> Скидка (<?= htmlspecialchars($appliedPromo['code']) ?>)</span>
                        <span>−<?= number_format($promoDiscount, 0, '.', ' ') ?> ₽</span>
                    </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span><i class="fas fa-truck"></i> Доставка</span>
                    <span><?= $deliveryText ?></span>
                </div>

                <div class="summary-row">
                    <span><i class="fas fa-mug-hot"></i> Чаевые</span>
                    <span id="tipDisplay">0 ₽</span>
                </div>

                <div class="summary-row total">
                    <span>К оплате</span>
                    <span id="finalAmount"><?= number_format($totalPrice, 0, '.', ' ') ?> ₽</span>
                </div>

                <div class="delivery-time-badge">
                    <i class="fas fa-clock"></i>
                    <?= $deliveryMethod === 'delivery' ? 'Доставка' : 'Готов к выдаче' ?>:
                    <strong><?= $estimatedDate ?> в <?= $estimatedTime ?></strong>
                </div>

                <div style="margin-top: 20px; font-size: 12px; color: var(--text-muted); text-align: center;">
                    <i class="fas fa-shield-alt"></i> Оплата при получении • Гарантия качества
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function toggleOrderForOther() {
    const isChecked = document.getElementById('orderForOther').checked;
    document.getElementById('otherContactFields').classList.toggle('active', isChecked);
    document.getElementById('mainContactFields').style.opacity = isChecked ? '0.6' : '1';
    document.getElementById('mainContactFields').style.pointerEvents = isChecked ? 'none' : 'auto';
}

function toggleDelivery() {
    const isDelivery = document.querySelector('input[name="delivery_method"]:checked').value === 'delivery';
    document.getElementById('deliveryBlock').style.display = isDelivery ? 'block' : 'none';
    document.getElementById('pickupBlock').style.display = isDelivery ? 'none' : 'block';

    const promoInput = document.getElementById('promoInput');
    if (promoInput) {
        promoInput.disabled = !isDelivery;
        const btn = promoInput.closest('.promo-input')?.querySelector('button');
        if (btn) btn.disabled = !isDelivery;
    }
}

function selectAddress(card) {
    document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    const radio = card.querySelector('input');
    radio.checked = true;
    if (radio.value !== '-1') {
        hideNewAddressForm();
    }
}

function showNewAddressForm() {
    document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
    const form = document.getElementById('newAddressForm');
    form.classList.add('active');
    form.querySelectorAll('input[data-required="true"]').forEach(input => {
        input.setAttribute('required', 'required');
    });
}

function hideNewAddressForm() {
    const form = document.getElementById('newAddressForm');
    form.classList.remove('active');
    form.querySelectorAll('input[data-required="true"]').forEach(input => {
        input.removeAttribute('required');
    });
}

function selectPayment(card) {
    document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input').checked = true;
}

function calcTotal() {
    const tip = parseFloat(document.querySelector('input[name="tip_amount"]').value) || 0;
    const base = <?= json_encode($totalPrice) ?>;
    document.getElementById('tipDisplay').textContent = tip.toLocaleString('ru-RU') + ' ₽';
    document.getElementById('finalAmount').textContent = (base + tip).toLocaleString('ru-RU') + ' ₽';
}

document.addEventListener('DOMContentLoaded', function() {
    const defaultAddr = document.querySelector('.address-card input[checked]');
    if (defaultAddr) {
        defaultAddr.closest('.address-card').classList.add('selected');
        if (defaultAddr.value === '-1') {
            showNewAddressForm();
        }
    }
    const defaultPay = document.querySelector('.payment-card input[checked]');
    if (defaultPay) defaultPay.closest('.payment-card').classList.add('selected');
    calcTotal();
});
</script>

<style>
    :root { --gold:#c8a656; --gold-hover:#e8c96a; --dark:#1a1a1a; --darker:#111; --card-bg:#222; --border:rgba(255,255,255,0.08); --text-primary:#fff; --text-secondary:#aaa; --text-muted:#666; --error:#e74c3c; --success:#2ecc71; }
    * { box-sizing: border-box; text-decoration: none !important; }
    a { color: inherit; transition: color 0.3s; }
    a:hover { color: var(--gold); }
    button { font-family: inherit; border: none; outline: none; cursor: pointer; }
    input, select, textarea { font-family: inherit; outline: none; }

    .checkout-container { max-width: 1000px; margin: 100px auto 50px; padding: 0 20px; }
    .checkout-header { text-align: center; margin-bottom: 40px; }
    .checkout-header h1 { font-size: 32px; font-weight: 300; margin: 0 0 10px; }
    .checkout-header h1 span { color: var(--gold); font-weight: 700; }
    .checkout-header .btn-back { color: var(--text-secondary); font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
    .checkout-header .btn-back:hover { color: var(--gold); }

    .checkout-grid { display: grid; grid-template-columns: 1fr 380px; gap: 30px; align-items: start; }

    .checkout-form { background: var(--card-bg); border-radius: 20px; padding: 30px; border: 1px solid var(--border); }
    .form-section { margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid var(--border); }
    .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .section-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .section-title i { color: var(--gold); }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
    .form-control { width: 100%; background: var(--darker); border: 1px solid var(--border); padding: 12px 15px; border-radius: 10px; color: var(--text-primary); font-size: 14px; transition: border-color 0.3s; }
    .form-control:focus { border-color: var(--gold); }
    .form-control:disabled { opacity: 0.6; cursor: not-allowed; }

    .radio-group { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
    .radio-option { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; background: var(--darker); border-radius: 10px; border: 2px solid var(--border); transition: all 0.3s; }
    .radio-option:hover { border-color: var(--gold); }
    .radio-option input { accent-color: var(--gold); }
    .radio-option input:checked + span { color: var(--gold); }
    .radio-option span { font-size: 14px; font-weight: 500; }

    .address-card { background: var(--darker); padding: 15px; border-radius: 12px; margin-bottom: 10px; border: 2px solid var(--border); cursor: pointer; transition: all 0.3s; }
    .address-card:hover, .address-card.selected { border-color: var(--gold); background: rgba(200,166,86,0.05); }
    .address-card input { margin-right: 10px; accent-color: var(--gold); }

    .new-address-form { background: var(--darker); padding: 20px; border-radius: 12px; margin-top: 15px; display: none; animation: slideDown 0.3s ease; }
    .new-address-form.active { display: block; }

    .order-for-other-toggle { display: flex; align-items: center; gap: 10px; margin: 15px 0; padding: 12px; background: rgba(200,166,86,0.05); border-radius: 10px; cursor: pointer; }
    .order-for-other-toggle input { accent-color: var(--gold); }
    .order-for-other-fields { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border); animation: slideDown 0.3s ease; }
    .order-for-other-fields.active { display: block; }

    .payment-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .payment-card { background: var(--darker); padding: 15px; border-radius: 12px; border: 2px solid var(--border); cursor: pointer; text-align: center; transition: all 0.3s; }
    .payment-card:hover, .payment-card.selected { border-color: var(--gold); background: rgba(200,166,86,0.05); }
    .payment-card i { font-size: 28px; color: var(--gold); margin-bottom: 8px; display: block; }
    .payment-card span { font-size: 13px; font-weight: 500; }
    .payment-card input { display: none; }

    .tip-section { display: flex; align-items: center; gap: 15px; padding: 15px; background: rgba(200,166,86,0.05); border-radius: 12px; }
    .tip-section i { font-size: 24px; color: var(--gold); }
    .tip-amount { width: 100px; text-align: center; }

    .cart-items-preview { background: var(--darker); border-radius: 12px; padding: 15px; max-height: 300px; overflow-y: auto; margin-bottom: 15px; }
    .cart-item-mini { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
    .cart-item-mini:last-child { border-bottom: none; }
    .cart-item-mini img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: var(--card-bg); }
    .cart-item-mini .info { flex: 1; }
    .cart-item-mini .name { font-size: 13px; font-weight: 500; margin-bottom: 4px; }
    .cart-item-mini .type-badge { font-size: 10px; color: var(--gold); background: rgba(200,166,86,0.1); padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
    .cart-item-mini .meta { font-size: 11px; color: var(--text-muted); }
    .cart-item-mini .price { font-size: 13px; font-weight: 600; color: var(--gold); white-space: nowrap; }

    .order-summary { background: linear-gradient(145deg, var(--card-bg), var(--darker)); border-radius: 20px; padding: 25px; border: 1px solid var(--border); position: sticky; top: 110px; }
    .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; font-size: 14px; color: var(--text-secondary); }
    .summary-row.total { border-top: 2px solid var(--border); padding-top: 20px; margin-top: 10px; font-size: 20px; font-weight: 700; color: var(--text-primary); }
    .summary-row.total span:last-child { color: var(--gold); font-size: 24px; }
    .summary-row.savings { color: var(--success); }
    .promo-applied { background: rgba(46,204,113,0.1); padding: 8px 12px; border-radius: 8px; margin: 10px 0; font-size: 13px; display: flex; align-items: center; gap: 8px; }
    .promo-applied i { color: var(--success); }
    .delivery-time-badge { background: rgba(200,166,86,0.15); color: var(--gold); padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; }

    .checkout-btn { width: 100%; padding: 18px; background: linear-gradient(135deg, var(--gold), #b89646); color: var(--darker); border: none; border-radius: 12px; font-weight: 800; text-transform: uppercase; font-size: 15px; margin-top: 20px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 8px 25px rgba(200,166,86,0.3); }
    .checkout-btn:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(200,166,86,0.5); }
    .checkout-btn:disabled { background: var(--darker); color: var(--text-muted); cursor: not-allowed; transform: none; box-shadow: none; border: 2px solid var(--border); }

    /* --- Стили для модального окна успеха --- */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); z-index: 9999;
        display: none; align-items: center; justify-content: center;
        backdrop-filter: blur(5px);
    }
    .modal-overlay.active { display: flex; animation: fadeIn 0.3s ease; }
    .success-modal {
        background: var(--card-bg); padding: 40px; border-radius: 20px;
        text-align: center; max-width: 450px; width: 90%;
        border: 1px solid var(--gold); box-shadow: 0 0 30px rgba(200,166,86,0.2);
        transform: scale(0.9); animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .success-icon-large { font-size: 60px; color: var(--success); margin-bottom: 20px; }
    .success-title { font-size: 24px; color: var(--text-primary); margin-bottom: 10px; }
    .success-order-id { font-size: 32px; color: var(--gold); font-weight: 700; margin: 15px 0; letter-spacing: 2px; }
    .success-text { color: var(--text-secondary); margin-bottom: 30px; font-size: 14px; }
    .btn-modal {
        display: inline-block; padding: 15px 30px; background: var(--gold);
        color: var(--darker); font-weight: 700; border-radius: 10px;
        text-decoration: none; transition: transform 0.2s;
    }
    .btn-modal:hover { transform: scale(1.05); background: var(--gold-hover); }

    @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .error-box { background: rgba(231,76,60,0.1); border: 1px solid var(--error); color: var(--error); padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

    .promo-form { margin: 15px 0; }
    .promo-input { display: flex; gap: 10px; }
    .promo-input input { flex: 1; }
    .promo-input button { background: var(--gold); color: var(--darker); padding: 0 20px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
    .promo-input button:hover { background: var(--gold-hover); }
    .promo-message { font-size: 13px; margin-top: 10px; padding: 8px 12px; border-radius: 8px; }
    .promo-success { color: var(--success); background: rgba(46,204,113,0.12); }
    .promo-error { color: var(--error); background: rgba(231,76,60,0.12); }
    .promo-disabled { color: var(--text-muted); background: rgba(255,255,255,0.05); }
    .promo-readonly { background: rgba(200,166,86,0.1); padding: 10px 15px; border-radius: 10px; font-size: 14px; display: flex; align-items: center; gap: 8px; border-left: 3px solid var(--gold); }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    @media (max-width: 900px) {
        .checkout-grid { grid-template-columns: 1fr; }
        .order-summary { position: static; }
        .form-row { grid-template-columns: 1fr; }
        .payment-methods { grid-template-columns: 1fr; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
