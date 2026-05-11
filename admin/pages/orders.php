<?php

$pageTitle = 'Управление заказами';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === СМЕНА СТАТУСА ЗАКАЗА (AJAX) ===
if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['order_id'], $_POST['status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];
    $allowed = ['new','confirmed','cooking','ready_pickup','delivering','delivered','cancelled'];

    if (in_array($newStatus, $allowed)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Неверный статус']);
    }
    exit;
}

// === ФИЛЬТРАЦИЯ И ПОИСК ===
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

$allowedSort = ['id', 'created_at', 'total_amount', 'final_amount', 'status'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';
if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';

$sql = "SELECT o.*, u.login, u.full_name, u.phone,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (o.id LIKE ? OR u.login LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($statusFilter) { $sql .= " AND o.status = ?"; $params[] = $statusFilter; }
if ($dateFrom) { $sql .= " AND DATE(o.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $sql .= " AND DATE(o.created_at) <= ?"; $params[] = $dateTo; }

$sql .= " ORDER BY o.$sortBy $sortOrder";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Статистика
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'new' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM orders WHERE status!='cancelled'")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--text:#fff;--text-muted:#999;--success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;--info:#3498db}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);line-height:1.6}
.admin-wrapper{display:flex;min-height:100vh}
.admin-sidebar{width:240px;background:var(--darker);border-right:1px solid rgba(200,166,86,0.15);position:fixed;height:100vh;overflow-y:auto;z-index:100}
.sidebar-header{padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px}
.sidebar-header i{color:var(--gold);font-size:20px}
.sidebar-nav{padding:15px 0}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--text-muted);font-size:13px;text-decoration:none;transition:0.2s;border-left:3px solid transparent}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(200,166,86,0.1);color:var(--gold);border-left-color:var(--gold)}
.sidebar-nav a i{width:20px;text-align:center}
.sidebar-divider{padding:15px 20px 10px;font-size:10px;text-transform:uppercase;letter-spacing:2px;color:#555;font-weight:600}
.admin-main{flex:1;margin-left:240px;padding:30px}
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header p{color:var(--text-muted);font-size:14px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--gray);padding:20px;border-radius:12px;display:flex;align-items:center;gap:15px;border:1px solid rgba(255,255,255,0.05)}
.stat-card i{font-size:28px;color:var(--gold);width:40px;text-align:center}
.stat-card.success i{color:var(--success)}.stat-card.warning i{color:var(--warning)}.stat-card.danger i{color:var(--danger)}
.stat-num{font-size:24px;font-weight:700;display:block}.stat-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.filters-bar{display:flex;gap:15px;flex-wrap:wrap;align-items:center;margin-bottom:20px}
.filters-bar input,.filters-bar select{padding:10px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:13px}
.filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--gold)}
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-primary{background:var(--gold);color:#1a1a1a}.btn-primary:hover{background:var(--gold-light)}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-sm{padding:5px 10px;font-size:11px}.btn-icon{width:32px;height:32px;padding:0;justify-content:center}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table tr:hover{background:rgba(200,166,86,0.05)}
.status-badge{padding:4px 12px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;display:inline-block}
.status-new{background:rgba(52,152,219,0.2);color:#3498db}.status-confirmed{background:rgba(46,204,113,0.2);color:#2ecc71}
.status-cooking{background:rgba(243,156,18,0.2);color:#f39c12}.status-ready_pickup{background:rgba(155,89,182,0.2);color:#9b59b6}
.status-delivering{background:rgba(52,152,219,0.2);color:#3498db}.status-delivered{background:rgba(46,204,113,0.2);color:#27ae60}
.status-cancelled{background:rgba(231,76,60,0.2);color:#e74c3c}
.status-select{padding:6px 10px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:4px;color:#fff;font-size:11px}
.status-select:focus{outline:none;border-color:var(--gold)}
.order-items-preview{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#admin-toast{position:fixed;bottom:30px;right:30px;padding:15px 25px;background:var(--gray);border-left:4px solid var(--success);color:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:9999;transform:translateX(400px);transition:transform 0.3s;font-size:14px}
#admin-toast.show{transform:translateX(0)}#admin-toast.error{border-left-color:var(--danger)}#admin-toast.warning{border-left-color:var(--warning)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}.data-table{font-size:12px}}
</style>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-receipt"></i> Управление заказами</h1>
            <p>Просмотр, фильтрация и изменение статусов заказов</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-shopping-bag"></i><div><span class="stat-num"><?= $stats['total'] ?></span><span class="stat-label">Всего заказов</span></div></div>
            <div class="stat-card warning"><i class="fas fa-clock"></i><div><span class="stat-num"><?= $stats['new'] ?></span><span class="stat-label">Новые</span></div></div>
            <div class="stat-card success"><i class="fas fa-calendar-day"></i><div><span class="stat-num"><?= $stats['today'] ?></span><span class="stat-label">Сегодня</span></div></div>
            <div class="stat-card"><i class="fas fa-ruble-sign"></i><div><span class="stat-num"><?= number_format($stats['revenue'],0,'.',' ') ?> ₽</span><span class="stat-label">Выручка</span></div></div>
        </div>

        <div class="card">
            <form method="GET" class="filters-bar">
                <input type="text" name="search" placeholder="Поиск по ID, имени, телефону..." value="<?= htmlspecialchars($search) ?>" style="min-width:200px">
                <select name="status">
                    <option value="">Все статусы</option>
                    <option value="new" <?= $statusFilter==='new'?'selected':'' ?>>Новый</option>
                    <option value="confirmed" <?= $statusFilter==='confirmed'?'selected':'' ?>>Подтверждён</option>
                    <option value="cooking" <?= $statusFilter==='cooking'?'selected':'' ?>>Готовится</option>
                    <option value="ready_pickup" <?= $statusFilter==='ready_pickup'?'selected':'' ?>>Готов к выдаче</option>
                    <option value="delivering" <?= $statusFilter==='delivering'?'selected':'' ?>>Доставляется</option>
                    <option value="delivered" <?= $statusFilter==='delivered'?'selected':'' ?>>Доставлен</option>
                    <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Отменён</option>
                </select>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="С даты">
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="По дату">
                <select name="sort">
                    <option value="created_at" <?= $sortBy==='created_at'?'selected':'' ?>>По дате</option>
                    <option value="id" <?= $sortBy==='id'?'selected':'' ?>>По ID</option>
                    <option value="final_amount" <?= $sortBy==='final_amount'?'selected':'' ?>>По сумме</option>
                </select>
                <select name="order">
                    <option value="DESC" <?= $sortOrder==='DESC'?'selected':'' ?>>По убыванию</option>
                    <option value="ASC" <?= $sortOrder==='ASC'?'selected':'' ?>>По возрастанию</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Найти</button>
                <a href="orders.php" class="btn btn-outline">Сбросить</a>
            </form>
        </div>

        <div class="card">
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Клиент</th><th>Товары</th><th>Сумма</th><th>Доставка</th><th>Статус</th><th>Дата</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= $order['id'] ?></strong></td>
                            <td>
                                <div><?= htmlspecialchars($order['full_name']??$order['login']??'Гость') ?></div>
                                <?php if($order['phone']): ?><small style="color:#666"><?= htmlspecialchars($order['phone']) ?></small><?php endif; ?>
                            </td>
                            <td class="order-items-preview"><?= $order['items_count'] ?> поз.</td>
                            <td>
                                <strong style="color:var(--gold)"><?= number_format($order['final_amount'],0,'.',' ') ?> ₽</strong>
                                <?php if($order['discount_amount']>0): ?><br><small style="color:#666;text-decoration:line-through"><?= number_format($order['total_amount'],0,'.',' ') ?> ₽</small><?php endif; ?>
                            </td>
                            <td><?= $order['delivery_method']=='pickup'?'<span style="color:#9b59b6">Самовывоз</span>':'<span style="color:#3498db">Доставка</span>' ?></td>
                            <td>
                                <select class="status-select" data-order-id="<?= $order['id'] ?>" onchange="updateStatus(this)">
                                    <option value="new" <?= $order['status']=='new'?'selected':'' ?>>Новый</option>
                                    <option value="confirmed" <?= $order['status']=='confirmed'?'selected':'' ?>>Подтверждён</option>
                                    <option value="cooking" <?= $order['status']=='cooking'?'selected':'' ?>>Готовится</option>
                                    <option value="ready_pickup" <?= $order['status']=='ready_pickup'?'selected':'' ?>>Готов к выдаче</option>
                                    <option value="delivering" <?= $order['status']=='delivering'?'selected':'' ?>>Доставляется</option>
                                    <option value="delivered" <?= $order['status']=='delivered'?'selected':'' ?>>Доставлен</option>
                                    <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>Отменён</option>
                                </select>
                            </td>
                            <td><small><?= date('d.m.Y H:i',strtotime($order['created_at'])) ?></small></td>
                            <td><a href="orders_view.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Просмотр"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(empty($orders)): ?>
                <div style="text-align:center;padding:60px;color:#666"><i class="fas fa-receipt" style="font-size:48px;margin-bottom:20px;opacity:0.3"></i><p>Заказы не найдены</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function updateStatus(select){
    const orderId = select.dataset.orderId;
    const newStatus = select.value;
    fetch('api/orders_update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'update_status', order_id: orderId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Статус заказа #' + orderId + ' обновлён', 'success');
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная'), 'error');
            select.value = select.dataset.oldValue || select.value;
        }
    })
    .catch(() => { showToast('Ошибка соединения', 'error'); select.value = select.dataset.oldValue || select.value; });
    select.dataset.oldValue = newStatus;
}
<?php if(isset($_SESSION['admin_message'])): ?>
function showToast(msg,type){const toast=document.createElement('div');toast.id='admin-toast';toast.className=type;toast.textContent=msg;document.body.appendChild(toast);setTimeout(()=>toast.classList.add('show'),10);setTimeout(()=>{toast.classList.remove('show');setTimeout(()=>toast.remove(),300)},3000)}
showToast('<?= $_SESSION['admin_message'] ?>','<?= $_SESSION['admin_message_type']??'success' ?>');
<?php unset($_SESSION['admin_message'],$_SESSION['admin_message_type']); endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
