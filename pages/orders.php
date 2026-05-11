<?php
/**
 * Мои заказы - отслеживание статуса
 */
$pageTitle = 'Мои заказы';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../classes/Order.php';

if (!$isLoggedIn) {
    header('Location: /house_of_taste/auth/login.php?redirect=orders');
    exit;
}

$successMsg = $_SESSION['order_success_msg'] ?? null;
$successTime = $_SESSION['order_success_time'] ?? null;
if ($successMsg) {
    unset($_SESSION['order_success_msg'], $_SESSION['order_success_time']);
}

$userId = $_SESSION['user_id'];
$order = new Order($pdo);

// Получаем данные текущего пользователя для подстановки в получателя
$userData = null;
try {
    $stmtUser = $pdo->prepare("SELECT full_name, phone FROM users WHERE id = :uid");
    $stmtUser->execute([':uid' => $userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $userData = null; }

// Детали конкретного заказа
$viewOrderId = $_GET['id'] ?? null;
$viewOrder = null;

if ($viewOrderId) {
    $viewOrder = $order->getOrderDetails($viewOrderId, $userId);
}

// Список заказов
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$userOrders = $order->getUserOrders($userId, $perPage, $offset);

// Обработка отмены
$cancelError = '';
$cancelSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = (int)($_POST['order_id'] ?? 0);
    $result = $order->cancelOrder($oid, $userId);

    if ($result['success']) {
        $cancelSuccess = true;
        if ($viewOrderId == $oid) {
            $viewOrder = $order->getOrderDetails($oid, $userId);
        }
    } else {
        $cancelError = $result['error'];
    }
}

// === ЛЕЙБЛЫ СТАТУСОВ ===
$statusLabels = [
    'new' => ['label' => 'Принят', 'class' => 'status-new', 'icon' => 'fas fa-clipboard-check'],
    'confirmed' => ['label' => 'Подтверждён', 'class' => 'status-confirmed', 'icon' => 'fas fa-check-circle'],
    'cooking' => ['label' => 'Готовится', 'class' => 'status-cooking', 'icon' => 'fas fa-utensils'],
    'ready_pickup' => ['label' => 'Готов', 'class' => 'status-ready', 'icon' => 'fas fa-box-open'],
    'delivering' => ['label' => 'В пути', 'class' => 'status-delivering', 'icon' => 'fas fa-motorcycle'],
    'delivered' => ['label' => 'Доставлен', 'class' => 'status-delivered', 'icon' => 'fas fa-flag-checkered'],
    'cancelled' => ['label' => 'Отменён', 'class' => 'status-cancelled', 'icon' => 'fas fa-times-circle'],
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Дом Вкуса</title>
</head>
<body>

<main class="orders-container">
    <div class="page-header">
        <h1>Мои <span>заказы</span></h1>
        <?php if ($viewOrder): ?>
            <a href="/house_of_taste/pages/orders.php" class="btn-back"><i class="fas fa-arrow-left"></i> Все заказы</a>
        <?php else: ?>
            <a href="/house_of_taste/pages/catalog.php" class="btn-back"><i class="fas fa-utensils"></i> В меню</a>
        <?php endif; ?>
    </div>

    <?php if ($successMsg): ?>
    <div class="message-box success">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong><?= htmlspecialchars($successMsg) ?></strong><br>
            <?php if ($successTime): ?>
                <small><i class="fas fa-clock"></i> Ожидаемое время: <?= htmlspecialchars($successTime) ?></small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($cancelSuccess): ?>
        <div class="message-box success"><i class="fas fa-check-circle"></i> Заказ успешно отменён</div>
    <?php endif; ?>
    <?php if ($cancelError): ?>
        <div class="message-box error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($cancelError) ?></div>
    <?php endif; ?>

    <?php if ($viewOrder): ?>
        <!-- ДЕТАЛИ ЗАКАЗА -->
        <div class="order-detail">
            <div class="order-detail-header">
                <div>
                    <div class="order-detail-title">Заказ #<?= str_pad($viewOrder['id'], 6, '0', STR_PAD_LEFT) ?></div>
                    <div class="order-date"><i class="far fa-calendar"></i> <?= date('d.m.Y в H:i', strtotime($viewOrder['created_at'])) ?></div>
                </div>
                <span class="status-badge <?= $statusLabels[$viewOrder['status']]['class'] ?>">
                    <i class="<?= $statusLabels[$viewOrder['status']]['icon'] ?>"></i>
                    <?= $statusLabels[$viewOrder['status']]['label'] ?>
                </span>
            </div>

            <div class="order-info-grid">
                <!-- Получатель: если заказ другому — показываем recipient_name, иначе данные из users -->
                <div class="info-block">
                    <h4><i class="fas fa-user"></i> Получатель</h4>
                    <?php
                    $recipientName = !empty($viewOrder['recipient_name'])
                        ? htmlspecialchars($viewOrder['recipient_name'])
                        : htmlspecialchars($userData['full_name'] ?? '—');
                    $recipientPhone = !empty($viewOrder['recipient_phone'])
                        ? htmlspecialchars($viewOrder['recipient_phone'])
                        : htmlspecialchars($userData['phone'] ?? '—');
                    ?>
                    <p><strong><?= $recipientName ?></strong></p>
                    <p><i class="fas fa-phone"></i> <?= $recipientPhone ?></p>
                </div>

                <div class="info-block">
                    <h4><i class="fas fa-truck"></i> Доставка</h4>
                    <p><strong><?= $viewOrder['delivery_method'] === 'delivery' ? 'Доставка' : 'Самовывоз' ?></strong></p>
                    <p><?= htmlspecialchars($viewOrder['delivery_address'] ?: '—') ?></p>
                </div>
                <div class="info-block">
                    <h4><i class="fas fa-credit-card"></i> Оплата</h4>
                    <p><strong><?= $viewOrder['payment_method'] === 'cash' ? 'Наличные' : 'Карта' ?> курьеру</strong></p>
                    <?php if (!empty($viewOrder['promo_code'])): ?>
                        <p><i class="fas fa-tag"></i> Промокод: <strong><?= htmlspecialchars($viewOrder['promo_code']) ?></strong></p>
                    <?php endif; ?>
                </div>
                <div class="info-block">
                    <h4><i class="fas fa-comment"></i> Комментарий</h4>
                    <p><?= htmlspecialchars($viewOrder['customer_comment'] ?: '—') ?></p>
                </div>
            </div>

            <!-- ТАЙМЛАЙН СТАТУСА (с поддержкой cancelled) -->
            <div class="order-timeline">
                <div class="timeline-title"><i class="fas fa-route"></i> Статус заказа</div>
                <?php
                $timelineSteps = [
                    'new' => ['label' => 'Принят', 'icon' => 'fas fa-clipboard-check'],
                    'confirmed' => ['label' => 'Подтверждён', 'icon' => 'fas fa-check-circle'],
                    'cooking' => ['label' => 'Готовится', 'icon' => 'fas fa-utensils'],
                    'ready_pickup' => ['label' => 'Готов', 'icon' => 'fas fa-box-open'],
                    'delivering' => ['label' => 'В пути', 'icon' => 'fas fa-motorcycle'],
                    'delivered' => ['label' => 'Доставлен', 'icon' => 'fas fa-flag-checkered']
                ];
                $activeStatuses = ['new', 'confirmed', 'cooking', 'ready_pickup', 'delivering', 'delivered'];
                $currentStatus = $viewOrder['status'];
                $isCancelled = ($currentStatus === 'cancelled');
                $currentIdx = $isCancelled ? -1 : array_search($currentStatus, $activeStatuses);
                if ($currentIdx === false) $currentIdx = 0;
                $progress = $isCancelled ? 0 : ($currentIdx * (100 / 5));
                ?>
                <div class="timeline <?= $isCancelled ? 'cancelled' : '' ?>" style="--progress: <?= $progress ?>%">
                    <?php foreach ($timelineSteps as $status => $step):
                        $isCompleted = !$isCancelled && (array_search($status, $activeStatuses) < $currentIdx);
                        $isActive = !$isCancelled && (array_search($status, $activeStatuses) == $currentIdx);
                    ?>
                    <div class="timeline-step <?= $isCompleted ? 'completed' : ($isActive ? 'active' : ($isCancelled ? 'cancelled' : '')) ?>">
                        <div class="timeline-dot">
                            <i class="<?= $step['icon'] ?>"></i>
                        </div>
                        <div class="timeline-label"><?= $step['label'] ?></div>
                        <?php if ($isCompleted): ?>
                            <div class="timeline-time">✓</div>
                        <?php elseif ($isActive): ?>
                            <div class="timeline-time">Сейчас</div>
                        <?php elseif ($isCancelled): ?>
                            <div class="timeline-time">✗</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($isCancelled): ?>
                    <p style="text-align:center; color:var(--error); font-size:13px; margin-top:10px;">
                        <i class="fas fa-info-circle"></i> Этот заказ был отменён. Средства не списывались.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Состав заказа -->
            <h4 style="margin: 25px 0 15px; font-size: 18px; color: var(--text-primary);"><i class="fas fa-list"></i> Состав заказа</h4>
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Цена</th>
                        <th>Кол-во</th>
                        <th style="text-align: right">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($viewOrder['items'])): ?>
                        <?php foreach ($viewOrder['items'] as $item): ?>
                        <tr>
                            <td class="item-name"><?= htmlspecialchars($item['product_name_snapshot']) ?></td>
                            <td class="item-price"><?= number_format($item['price_at_moment'], 0, '.', ' ') ?> ₽</td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format($item['total_price'], 0, '.', ' ') ?> ₽</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Нет товаров</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Итого -->
            <div class="order-totals">
                <div class="total-row"><span>Товары</span><span><?= number_format($viewOrder['total_amount'], 0, '.', ' ') ?> ₽</span></div>
                <?php if (!empty($viewOrder['discount_amount']) && $viewOrder['discount_amount'] > 0): ?>
                    <div class="total-row" style="color: var(--success);"><span>Скидка</span><span>−<?= number_format($viewOrder['discount_amount'], 0, '.', ' ') ?> ₽</span></div>
                <?php endif; ?>
                <?php if (!empty($viewOrder['delivery_amount']) && $viewOrder['delivery_amount'] > 0): ?>
                    <div class="total-row"><span>Доставка</span><span><?= number_format($viewOrder['delivery_amount'], 0, '.', ' ') ?> ₽</span></div>
                <?php endif; ?>
                <?php if (!empty($viewOrder['tip_amount']) && $viewOrder['tip_amount'] > 0): ?>
                    <div class="total-row"><span>Чаевые</span><span><?= number_format($viewOrder['tip_amount'], 0, '.', ' ') ?> ₽</span></div>
                <?php endif; ?>
                <div class="total-row final"><span>Итого</span><span><?= number_format($viewOrder['final_amount'], 0, '.', ' ') ?> ₽</span></div>
            </div>

            <!-- Действия -->
            <div class="order-actions">
                <?php if (!$isCancelled && in_array($viewOrder['status'], ['new', 'confirmed'])): ?>
                    <form method="POST" class="cancel-form" onsubmit="return confirm('Отменить заказ? Это действие нельзя отменить.')">
                        <input type="hidden" name="order_id" value="<?= (int)$viewOrder['id'] ?>">
                        <p><i class="fas fa-exclamation-triangle"></i> Отмена возможна, пока заказ не передан в приготовление</p>
                        <button type="submit" name="cancel_order" class="btn-order danger"><i class="fas fa-times"></i> Отменить заказ</button>
                    </form>
                <?php endif; ?>

                <?php if ($viewOrder['status'] === 'delivered'): ?>
                    <a href="/house_of_taste/pages/reviews.php?order=<?= (int)$viewOrder['id'] ?>" class="btn-order primary">
                        <i class="fas fa-star"></i> Оставить отзыв
                    </a>
                <?php endif; ?>

                <a href="/house_of_taste/pages/catalog.php" class="btn-order secondary">
                    <i class="fas fa-plus"></i> Новый заказ
                </a>
            </div>
        </div>

    <?php elseif (empty($userOrders)): ?>
        <!-- Нет заказов -->
        <div class="empty-state">
            <i class="fas fa-shopping-bag"></i>
            <h2>У вас пока нет заказов</h2>
            <p>Оформите первый заказ и отслеживайте его статус здесь</p>
            <a href="/house_of_taste/pages/catalog.php" class="btn-order primary" style="padding: 14px 35px; font-size: 15px;">
                <i class="fas fa-utensils"></i> Перейти в меню
            </a>
        </div>

    <?php else: ?>
        <!-- СПИСОК ЗАКАЗОВ -->
        <div class="orders-list">
            <?php foreach ($userOrders as $o): ?>
            <div class="order-card" onclick="location.href='/house_of_taste/pages/orders.php?id=<?= (int)$o['id'] ?>'">
                <div class="order-card-header">
                    <div>
                        <div class="order-number">#<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></div>
                        <div class="order-date"><i class="far fa-clock"></i> <?= date('d.m.Y в H:i', strtotime($o['created_at'])) ?></div>
                    </div>
                    <span class="status-badge <?= $statusLabels[$o['status']]['class'] ?>">
                        <i class="<?= $statusLabels[$o['status']]['icon'] ?>"></i>
                        <?= $statusLabels[$o['status']]['label'] ?>
                    </span>
                </div>

                <div class="order-summary">
                    <div class="order-summary-item">
                        <strong><?= number_format($o['final_amount'], 0, '.', ' ') ?> ₽</strong>
                        <span>Сумма</span>
                    </div>
                    <div class="order-summary-item">
                        <strong><?= $o['delivery_method'] === 'delivery' ? 'Доставка' : 'Самовывоз' ?></strong>
                        <span><?= htmlspecialchars(mb_strimwidth($o['delivery_address'] ?? '', 0, 25, '…')) ?></span>
                    </div>
                    <div class="order-summary-item">
                        <strong><?= isset($o['items']) ? count($o['items']) : 0 ?> товаров</strong>
                        <span>в заказе</span>
                    </div>
                </div>

                <div class="order-items-preview">
                    <?php
                    $stmt = $pdo->prepare("SELECT product_name_snapshot FROM order_items WHERE order_id = :oid LIMIT 3");
                    $stmt->execute([':oid' => $o['id']]);
                    $previewItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($previewItems as $iname): ?>
                        <span class="order-item-tag"><?= htmlspecialchars($iname) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($previewItems) < (isset($o['items']) ? count($o['items']) : 0)): ?>
                        <span class="order-items-more">+<?= (isset($o['items']) ? count($o['items']) : 0) - count($previewItems) ?> ещё</span>
                    <?php endif; ?>
                </div>

                <div class="order-actions" onclick="event.stopPropagation()">
                    <a href="/house_of_taste/pages/orders.php?id=<?= (int)$o['id'] ?>" class="btn-order primary">
                        <i class="fas fa-eye"></i> Детали
                    </a>
                        <?php if (in_array($o['status'], ['new', 'confirmed'])): ?>
                        <form method="POST" onsubmit="return confirm('Отменить заказ?')" style="display:inline">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <button type="submit" name="cancel_order" class="btn-order danger">
                                <i class="fas fa-times"></i> Отменить
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ПАГИНАЦИЯ -->
        <?php
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid");
        $totalStmt->execute([':uid' => $userId]);
        $totalOrders = (int)$totalStmt->fetchColumn();
        $totalPages = ceil($totalOrders / $perPage);
        if ($totalPages > 1):
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="page-link" disabled><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
                <a href="?page=<?= $p ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="page-link" disabled><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<style>
    :root {
        --gold:#c8a656; --gold-hover:#e8c96a;
        --dark:#1a1a1a; --darker:#111; --card-bg:#222;
        --border:rgba(255,255,255,0.08);
        --text-primary:#fff; --text-secondary:#aaa; --text-muted:#666;
        --error:#e74c3c; --success:#2ecc71; --warning:#f1c40f; --info:#3498db;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    a { color: inherit; text-decoration: none; transition: color 0.3s; }
    a:hover { color: var(--gold); }
    button { font-family: inherit; border: none; outline: none; cursor: pointer; }

    .orders-container { max-width: 1100px; margin: 100px auto 50px; padding: 0 20px; }

    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
    .page-header h1 { font-size: 28px; font-weight: 400; margin: 0; color: var(--text-primary); }
    .page-header h1 span { color: var(--gold); font-weight: 700; }

    .btn-back { color: var(--text-secondary); font-size: 14px; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: var(--darker); border: 1px solid var(--border); transition: all 0.3s; }
    .btn-back:hover { color: var(--gold); border-color: var(--gold); background: rgba(200,166,86,0.1); }

    /* Карточка заказа */
    .orders-list { display: flex; flex-direction: column; gap: 15px; }
    .order-card { background: var(--card-bg); border-radius: 16px; padding: 20px; border: 1px solid var(--border); transition: all 0.3s; cursor: pointer; }
    .order-card:hover { border-color: var(--gold); transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .order-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
    .order-number { font-size: 18px; font-weight: 600; color: var(--text-primary); }
    .order-number span { color: var(--gold); }
    .order-date { font-size: 13px; color: var(--text-muted); }

    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 25px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-new { background: rgba(241,196,15,0.15); color: #f1c40f; }
    .status-confirmed { background: rgba(52,152,219,0.15); color: #3498db; }
    .status-cooking { background: rgba(155,89,182,0.15); color: #9b59b6; }
    .status-ready { background: rgba(46,204,113,0.15); color: #2ecc71; }
    .status-delivering { background: rgba(230,126,34,0.15); color: #e67e22; }
    .status-delivered { background: rgba(46,204,113,0.15); color: #2ecc71; }
    .status-cancelled { background: rgba(231,76,60,0.15); color: #e74c3c; }

    .order-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
    .order-summary-item { font-size: 13px; color: var(--text-secondary); }
    .order-summary-item strong { display: block; font-size: 16px; color: var(--gold); margin-bottom: 3px; }

    .order-items-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
    .order-item-tag { background: var(--darker); padding: 5px 12px; border-radius: 8px; font-size: 12px; color: var(--text-secondary); }
    .order-items-more { color: var(--gold); font-size: 12px; cursor: pointer; }

    .order-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-order { padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
    .btn-order.primary { background: var(--gold); color: var(--darker); }
    .btn-order.secondary { background: var(--darker); color: var(--text-primary); border: 1px solid var(--border); }
    .btn-order.danger { background: rgba(231,76,60,0.1); color: var(--error); border: 1px solid var(--error); }
    .btn-order:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    .btn-order:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    /* Детали заказа */
    .order-detail { background: var(--card-bg); border-radius: 20px; padding: 30px; border: 1px solid var(--border); }
    .order-detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 15px; }
    .order-detail-title { font-size: 24px; font-weight: 600; color: var(--text-primary); }

    .order-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
    .info-block { background: var(--darker); padding: 20px; border-radius: 12px; }
    .info-block h4 { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-weight: 600; }
    .info-block p { margin: 5px 0; font-size: 14px; color: var(--text-primary); }
    .info-block p strong { color: var(--gold); }
    .info-block p i { color: var(--text-muted); margin-right: 6px; }

    .order-items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .order-items-table th { text-align: left; padding: 15px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--border); font-weight: 600; }
    .order-items-table td { padding: 15px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-primary); }
    .order-items-table tr:last-child td { border-bottom: none; }
    .order-items-table .item-name { font-weight: 500; }
    .order-items-table .item-price { color: var(--gold); font-weight: 600; }

    .order-totals { background: var(--darker); border-radius: 12px; padding: 20px; margin-bottom: 25px; }
    .total-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; color: var(--text-secondary); }
    .total-row.final { border-top: 2px solid var(--border); padding-top: 15px; margin-top: 10px; font-size: 20px; font-weight: 700; color: var(--text-primary); }
    .total-row.final span:last-child { color: var(--gold); font-size: 24px; }

    /* ТАЙМЛАЙН СТАТУСА */
    .order-timeline { margin-bottom: 30px; }
    .timeline-title { font-size: 16px; font-weight: 600; margin-bottom: 25px; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }

    .timeline { display: flex; justify-content: space-between; position: relative; padding: 20px 0; }
    .timeline::before {
        content: ''; position: absolute; top: 25px; left: 0; right: 0; height: 3px;
        background: var(--border); z-index: 1; border-radius: 3px;
    }
    .timeline::after {
        content: ''; position: absolute; top: 25px; left: 0; height: 3px;
        background: var(--gold); z-index: 2; border-radius: 3px;
        width: calc((100% / 6) * var(--progress, 0));
        transition: width 0.5s ease;
    }
    .timeline.cancelled::after {
        background: var(--error);
        width: 100%;
    }

    .timeline-step {
        position: relative; z-index: 3; text-align: center; flex: 1;
        display: flex; flex-direction: column; align-items: center; gap: 10px;
    }

    .timeline-dot {
        width: 50px; height: 50px; border-radius: 50%;
        background: var(--card-bg); border: 3px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        transition: all 0.3s;
    }
    .timeline-step.completed .timeline-dot {
        background: var(--success); border-color: var(--success);
        box-shadow: 0 0 20px rgba(46,204,113,0.4);
    }
    .timeline-step.active .timeline-dot {
        background: var(--gold); border-color: var(--gold);
        box-shadow: 0 0 25px rgba(200,166,86,0.6);
        animation: pulse 2s infinite;
    }
    .timeline-step.cancelled .timeline-dot {
        background: var(--error); border-color: var(--error);
        box-shadow: 0 0 25px rgba(231,76,60,0.5);
    }

    .timeline-dot i { font-size: 18px; color: var(--text-muted); }
    .timeline-step.completed .timeline-dot i,
    .timeline-step.active .timeline-dot i,
    .timeline-step.cancelled .timeline-dot i { color: var(--darker); }

    .timeline-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-align: center; }
    .timeline-step.completed .timeline-label,
    .timeline-step.active .timeline-label,
    .timeline-step.cancelled .timeline-label { color: var(--text-primary); }

    .timeline-time { font-size: 11px; color: var(--text-muted); }

    @keyframes pulse {
        0%, 100% { box-shadow: 0 0 25px rgba(200,166,86,0.6); }
        50% { box-shadow: 0 0 35px rgba(200,166,86,0.9); }
    }

    /* Форма отмены */
    .cancel-form { background: rgba(231,76,60,0.08); border: 1px solid var(--error); border-radius: 12px; padding: 20px; margin-top: 20px; }
    .cancel-form p { margin: 0 0 15px; font-size: 14px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }

    .empty-state { text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); }
    .empty-state i { font-size: 60px; color: var(--gold); margin-bottom: 20px; }
    .empty-state h2 { color: var(--text-primary); margin-bottom: 10px; }
    .empty-state p { color: var(--text-muted); margin-bottom: 25px; }

    .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; flex-wrap: wrap; }
    .page-link {
        min-width: 40px; padding: 10px 16px; border-radius: 10px;
        background: var(--card-bg); border: 1px solid var(--border);
        color: var(--text-primary); font-weight: 500;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.3s;
    }
    .page-link:hover, .page-link.active {
        background: var(--gold); color: var(--darker); border-color: var(--gold);
        transform: translateY(-2px);
    }
    .page-link:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .message-box { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideDown 0.4s ease; }
    .message-box.success { background: rgba(46,204,113,0.12); border: 1px solid var(--success); color: var(--success); }
    .message-box.error { background: rgba(231,76,60,0.12); border: 1px solid var(--error); color: var(--error); }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 900px) {
        .order-summary { grid-template-columns: 1fr; }
        .order-info-grid { grid-template-columns: 1fr; }
        .order-detail-header { flex-direction: column; align-items: flex-start; }
        .timeline { flex-wrap: wrap; gap: 20px; }
        .timeline::before, .timeline::after { display: none; }
        .timeline-step { flex: 0 0 45%; }
    }
    @media (max-width: 500px) {
        .timeline-step { flex: 0 0 100%; }
        .order-actions { flex-direction: column; }
        .btn-order { width: 100%; justify-content: center; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
