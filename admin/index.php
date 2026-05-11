<?php

$pageTitle = 'Дашборд';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/paths.php';
// === СТАТИСТИКА ===
$stats = [
    'orders_total' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'orders_today' => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'orders_pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(),
    'users_total' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 0")->fetchColumn(),
    'products_total' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'reviews_pending' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
    'complaints_new' => $pdo->query("SELECT COUNT(*) FROM chip_complaints WHERE status = 'new'")->fetchColumn(),
    'revenue_today' => $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn(),
];

// === ПОСЛЕДНИЕ ЗАКАЗЫ ===
$recentOrders = $pdo->query("
    SELECT o.id, o.created_at, o.total_amount, o.status, o.final_amount,
           u.login, u.full_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC LIMIT 10
")->fetchAll();

// === ПОСЛЕДНИЕ ОТЗЫВЫ НА МОДЕРАЦИИ ===
$pendingReviews = $pdo->query("
    SELECT r.id, r.rating, r.title, r.created_at, u.full_name, p.name as product_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Дашборд</h1>
            <p>Обзор активности ресторана «Дом Вкуса»</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <div>
                    <span class="stat-num"><?= $stats['orders_total'] ?></span>
                    <span class="stat-label">Всего заказов</span>
                </div>
            </div>
            <div class="stat-card success">
                <i class="fas fa-calendar-day"></i>
                <div>
                    <span class="stat-num"><?= $stats['orders_today'] ?></span>
                    <span class="stat-label">Заказов сегодня</span>
                </div>
            </div>
            <div class="stat-card warning">
                <i class="fas fa-clock"></i>
                <div>
                    <span class="stat-num"><?= $stats['orders_pending'] ?></span>
                    <span class="stat-label">Новые заказы</span>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ruble-sign"></i>
                <div>
                    <span class="stat-num"><?= number_format($stats['revenue_today'], 0, '.', ' ') ?> ₽</span>
                    <span class="stat-label">Выручка сегодня</span>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div>
                    <span class="stat-num"><?= $stats['users_total'] ?></span>
                    <span class="stat-label">Клиентов</span>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-utensils"></i>
                <div>
                    <span class="stat-num"><?= $stats['products_total'] ?></span>
                    <span class="stat-label">Блюд в меню</span>
                </div>
            </div>
            <div class="stat-card warning">
                <i class="fas fa-comment-alt"></i>
                <div>
                    <span class="stat-num"><?= $stats['reviews_pending'] ?></span>
                    <span class="stat-label">Отзывов на модерации</span>
                </div>
            </div>
            <div class="stat-card danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <span class="stat-num"><?= $stats['complaints_new'] ?></span>
                    <span class="stat-label">Новых жалоб</span>
                </div>
            </div>
        </div>

        <!-- Последние заказы -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Последние заказы</h3>
                <a href="pages/orders.php" class="btn btn-primary btn-sm">Все заказы</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Клиент</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['full_name'] ?? $order['login'] ?? 'Гость') ?></td>
                            <td><?= number_format($order['final_amount'], 0, '.', ' ') ?> ₽</td>
                            <td><span class="status-badge status-<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                            <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                            <td>
                                <a href="pages/orders_view.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Просмотр">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Отзывы на модерации -->
        <?php if ($stats['reviews_pending'] > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-comment-alt"></i> Отзывы на модерации</h3>
                <a href="pages/reviews.php" class="btn btn-primary btn-sm">Управление</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Блюдо</th>
                            <th>Оценка</th>
                            <th>Заголовок</th>
                            <th>Дата</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingReviews as $rev): ?>
                        <tr>
                            <td><?= htmlspecialchars($rev['full_name'] ?? 'Аноним') ?></td>
                            <td><?= htmlspecialchars($rev['product_name'] ?? '—') ?></td>
                            <td>
                                <?php for($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="color: <?= $i <= $rev['rating'] ? '#c8a656' : '#444' ?>; font-size: 11px;"></i>
                                <?php endfor; ?>
                            </td>
                            <td><?= htmlspecialchars(mb_substr($rev['title'] ?? '', 0, 30)) ?>...</td>
                            <td><?= date('d.m.Y', strtotime($rev['created_at'])) ?></td>
                            <td>
                                <a href="pages/reviews.php?action=edit&id=<?= $rev['id'] ?>" class="btn btn-warning btn-sm btn-icon" title="Модерировать">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<style>
:root {
    --gold: #c8a656;
    --gold-light: #e8c96a;
    --dark: #1a1a1a;
    --darker: #0f0f0f;
    --gray: #2a2a2a;
    --text: #ffffff;
    --text-muted: #999999;
    --success: #2ecc71;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Montserrat', sans-serif;
    background: var(--dark);
    color: var(--text);
    line-height: 1.6;
}

/* ===== LAYOUT ===== */
.admin-wrapper { display: flex; min-height: 100vh; }

/* ===== SIDEBAR ===== */
.admin-sidebar {
    width: 240px;
    background: var(--darker);
    border-right: 1px solid rgba(200,166,86,0.15);
    padding: 20px 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 16px;
    letter-spacing: 1px;
}
.sidebar-header i { color: var(--gold); font-size: 20px; }

.sidebar-nav { padding: 15px 0; }
.sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: var(--text-muted);
    font-size: 13px;
    transition: 0.2s;
    border-left: 3px solid transparent;
    text-decoration: none;
}
.sidebar-nav a:hover,
.sidebar-nav a.active {
    background: rgba(200,166,86,0.1);
    color: var(--gold);
    border-left-color: var(--gold);
}
.sidebar-nav a i { width: 20px; text-align: center; }

.sidebar-divider {
    padding: 15px 20px 10px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #555;
    font-weight: 600;
}

.sidebar-logout {
    margin-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 15px;
    color: var(--danger);
}
.sidebar-logout:hover { color: #c0392b; }

/* ===== MAIN CONTENT ===== */
.admin-main {
    flex: 1;
    margin-left: 240px;
    padding: 30px;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.page-header h1 {
    font-size: 28px;
    font-weight: 300;
    letter-spacing: 2px;
    margin-bottom: 5px;
}
.page-header p { color: var(--text-muted); font-size: 14px; }

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: var(--gray);
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid rgba(255,255,255,0.05);
    transition: 0.3s;
}
.stat-card:hover {
    border-color: var(--gold);
    transform: translateY(-3px);
}
.stat-card i {
    font-size: 28px;
    color: var(--gold);
    width: 40px;
    text-align: center;
}
.stat-card.warning i { color: var(--warning); }
.stat-card.danger i { color: var(--danger); }
.stat-card.success i { color: var(--success); }

.stat-num {
    font-size: 24px;
    font-weight: 700;
    display: block;
    color: var(--text);
}
.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* ===== TABLES ===== */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--gray);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}

.data-table th,
.data-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 13px;
}

.data-table th {
    background: rgba(0,0,0,0.2);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 11px;
    color: var(--text-muted);
}

.data-table tr:hover {
    background: rgba(200,166,86,0.05);
}

.data-table tr:last-child td { border-bottom: none; }

/* ===== STATUS BADGES ===== */
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: inline-block;
}
.status-new { background: rgba(52,152,219,0.2); color: #3498db; }
.status-confirmed { background: rgba(46,204,113,0.2); color: #2ecc71; }
.status-cooking { background: rgba(243,156,18,0.2); color: #f39c12; }
.status-ready { background: rgba(155,89,182,0.2); color: #9b59b6; }
.status-delivered { background: rgba(52,152,219,0.2); color: #3498db; }
.status-cancelled { background: rgba(231,76,60,0.2); color: #e74c3c; }
.status-pending { background: rgba(243,156,18,0.2); color: #f39c12; }
.status-approved { background: rgba(46,204,113,0.2); color: #2ecc71; }
.status-rejected { background: rgba(231,76,60,0.2); color: #e74c3c; }

/* ===== BUTTONS ===== */
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}
.btn-primary { background: var(--gold); color: #1a1a1a; }
.btn-primary:hover { background: var(--gold-light); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #27ae60; }
.btn-warning { background: var(--warning); color: #1a1a1a; }
.btn-warning:hover { background: #e67e22; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-sm { padding: 5px 10px; font-size: 11px; }
.btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; }

/* ===== FORMS ===== */
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    background: #222;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
    transition: 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,166,86,0.1);
}
.form-group textarea { resize: vertical; min-height: 100px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* ===== CARDS ===== */
.card {
    background: var(--gray);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.card-header h3 {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 1px;
}

/* ===== FILTERS ===== */
.filters-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
    align-items: center;
}
.filters-bar input,
.filters-bar select {
    padding: 10px 15px;
    background: #222;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #fff;
    font-size: 13px;
}

/* ===== TOAST ===== */
#admin-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 15px 25px;
    background: var(--gray);
    border-left: 4px solid var(--success);
    color: #fff;
    border-radius: 6px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 9999;
    transform: translateX(400px);
    transition: transform 0.3s;
    font-size: 14px;
}
#admin-toast.show { transform: translateX(0); }
#admin-toast.error { border-left-color: var(--danger); }
#admin-toast.warning { border-left-color: var(--warning); }

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .admin-sidebar { width: 70px; }
    .sidebar-header span,
    .sidebar-nav a span,
    .sidebar-divider { display: none; }
    .sidebar-nav a { justify-content: center; padding: 15px; }
    .sidebar-nav a i { margin: 0; }
    .admin-main { margin-left: 70px; }
}

@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .filters-bar { flex-direction: column; align-items: stretch; }
    .admin-main { padding: 20px; }
}
</style>
