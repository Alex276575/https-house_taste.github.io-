<?php

$pageTitle = 'Просмотр заказа';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId === 0) { header('Location: orders.php'); exit; }

// Получаем заказ
$stmt = $pdo->prepare("SELECT o.*, u.login, u.full_name, u.phone, u.avatar_url, a.*
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_addresses a ON o.address_id = a.id
    WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) { $_SESSION['admin_message']='Заказ не найден'; $_SESSION['admin_message_type']='error'; header('Location: orders.php'); exit; }

// Получаем позиции заказа
$items = $pdo->prepare("SELECT oi.*, p.image_url FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$items->execute([$orderId]);
$items = $items->fetchAll();

// Получаем промокод если есть
$promo = null;
if ($order['promo_code_id']) {
    $stmt = $pdo->prepare("SELECT code, discount_type, discount_value FROM promo_codes WHERE id = ?");
    $stmt->execute([$order['promo_code_id']]);
    $promo = $stmt->fetch();
}

// Обработка смены статуса
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['status'])) {
    $newStatus = $_POST['status'];
    $allowed = ['new','confirmed','cooking','ready_pickup','delivering','delivered','cancelled'];
    if (in_array($newStatus, $allowed)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        $_SESSION['admin_message'] = 'Статус заказа обновлён';
        $_SESSION['admin_message_type'] = 'success';
        header('Location: orders_view.php?id='.$orderId);
        exit;
    }
}

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
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;align-items:center}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;display:flex;align-items:center;gap:10px}
.page-header p{color:var(--text-muted);font-size:14px}
.order-header{display:flex;justify-content:space-between;align-items:flex-start;gap:30px;margin-bottom:30px;flex-wrap:wrap}
.order-id{font-size:24px;font-weight:700;color:var(--gold)}
.order-status{padding:8px 20px;border-radius:20px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px}
.status-new{background:rgba(52,152,219,0.2);color:#3498db}.status-confirmed{background:rgba(46,204,113,0.2);color:#2ecc71}
.status-cooking{background:rgba(243,156,18,0.2);color:#f39c12}.status-ready_pickup{background:rgba(155,89,182,0.2);color:#9b59b6}
.status-delivering{background:rgba(52,152,219,0.2);color:#3498db}.status-delivered{background:rgba(46,204,113,0.2);color:#27ae60}
.status-cancelled{background:rgba(231,76,60,0.2);color:#e74c3c}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid rgba(255,255,255,0.1)}
.card-header h3{font-size:18px;font-weight:600;letter-spacing:1px}
.customer-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
.info-item label{display:block;font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.info-item .value{font-size:14px;color:var(--text)}
.info-item .value a{color:var(--gold);text-decoration:none}
.info-item .value a:hover{text-decoration:underline}
.avatar-mini{width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid var(--gold)}
.avatar-placeholder{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-light));display:flex;align-items:center;justify-content:center;color:#1a1a1a;font-weight:700;font-size:18px}
.items-table{width:100%;border-collapse:collapse}
.items-table th,.items-table td{padding:12px 15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.items-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.item-img{width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #333}
.totals{display:flex;justify-content:flex-end;gap:40px;margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1)}
.total-item{display:flex;flex-direction:column;align-items:flex-end}
.total-item .label{font-size:12px;color:var(--text-muted);margin-bottom:5px}
.total-item .value{font-size:18px;font-weight:700;color:var(--gold)}
.total-item .value.final{font-size:24px;color:var(--success)}
.status-form{display:flex;gap:15px;align-items:center}
.status-form select{padding:10px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:13px}
.status-form select:focus{outline:none;border-color:var(--gold)}
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-primary{background:var(--gold);color:#1a1a1a}.btn-primary:hover{background:var(--gold-light)}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#27ae60}
.btn-sm{padding:5px 10px;font-size:11px}
.timeline{position:relative;padding-left:30px;margin:20px 0}
.timeline::before{content:'';position:absolute;left:8px;top:0;bottom:0;width:2px;background:rgba(255,255,255,0.1)}
.timeline-item{position:relative;padding-left:25px;padding-bottom:20px}
.timeline-item::before{content:'';position:absolute;left:-6px;top:4px;width:14px;height:14px;border-radius:50%;background:var(--gold);border:3px solid var(--dark)}
.timeline-item .time{font-size:11px;color:var(--text-muted);margin-bottom:3px}
.timeline-item .status{font-size:13px;font-weight:600}
.timeline-item.active::before{background:var(--success);box-shadow:0 0 0 3px rgba(46,204,113,0.3)}
#admin-toast{position:fixed;bottom:30px;right:30px;padding:15px 25px;background:var(--gray);border-left:4px solid var(--success);color:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:9999;transform:translateX(400px);transition:transform 0.3s;font-size:14px}
#admin-toast.show{transform:translateX(0)}#admin-toast.error{border-left-color:var(--danger)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.order-header{flex-direction:column}.customer-info{grid-template-columns:1fr}.totals{flex-direction:column;align-items:flex-end}.admin-main{padding:20px}}
</style>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-receipt"></i> Заказ #<?= $order['id'] ?></h1>
            <div style="display:flex;gap:10px">
                <a href="orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Назад</a>
                <a href="tel:<?= preg_replace('/[^0-9]/','',$order['phone']??'') ?>" class="btn btn-primary"><i class="fas fa-phone"></i> Позвонить</a>
            </div>
        </div>

        <div class="order-header">
            <div>
                <span class="order-id">Заказ #<?= $order['id'] ?></span>
                <div style="color:var(--text-muted);font-size:13px;margin-top:5px">
                    <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                </div>
            </div>
            <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap">
                <span class="order-status status-<?= $order['status'] ?>"><?= $order['status'] ?></span>
                <form method="POST" class="status-form">
                    <select name="status" onchange="this.form.submit()">
                        <option value="new" <?= $order['status']=='new'?'selected':'' ?>>Новый</option>
                        <option value="confirmed" <?= $order['status']=='confirmed'?'selected':'' ?>>Подтверждён</option>
                        <option value="cooking" <?= $order['status']=='cooking'?'selected':'' ?>>Готовится</option>
                        <option value="ready_pickup" <?= $order['status']=='ready_pickup'?'selected':'' ?>>Готов к выдаче</option>
                        <option value="delivering" <?= $order['status']=='delivering'?'selected':'' ?>>Доставляется</option>
                        <option value="delivered" <?= $order['status']=='delivered'?'selected':'' ?>>Доставлен</option>
                        <option value="cancelled" <?= $order['status']=='cancelled'?'selected':'' ?>>Отменён</option>
                    </select>
                </form>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
            <div>
                <!-- Информация о клиенте -->
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-user"></i> Клиент</h3></div>
                    <div class="customer-info">
                        <div class="info-item">
                            <label>Аватар</label>
                            <?php if($order['avatar_url']): ?>
                                <img src="/house_of_taste<?= htmlspecialchars($order['avatar_url']) ?>" class="avatar-mini" alt="">
                            <?php else: ?>
                                <div class="avatar-placeholder"><?= mb_strtoupper(mb_substr($order['full_name']??$order['login']??'G',0,1,'UTF-8'),'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="info-item">
                            <label>Имя</label>
                            <div class="value"><?= htmlspecialchars($order['full_name']??$order['login']??'Гость') ?></div>
                        </div>
                        <div class="info-item">
                            <label>Логин</label>
                            <div class="value"><?= htmlspecialchars($order['login']??'—') ?></div>
                        </div>
                        <div class="info-item">
                            <label>Телефон</label>
                            <div class="value"><?= $order['phone'] ? '<a href="tel:'.preg_replace('/[^0-9]/','',$order['phone']).'">'.htmlspecialchars($order['phone']).'</a>' : '—' ?></div>
                        </div>
                        <div class="info-item">
                            <label>Способ доставки</label>
                            <div class="value"><?= $order['delivery_method']=='pickup'?'<span style="color:#9b59b6"><i class="fas fa-store"></i> Самовывоз</span>':'<span style="color:#3498db"><i class="fas fa-truck"></i> Доставка</span>' ?></div>
                        </div>
                        <div class="info-item">
                            <label>Оплата</label>
                            <div class="value"><?= $order['payment_method']=='card'?'<i class="fas fa-credit-card"></i> Карта':'<i class="fas fa-money-bill-wave"></i> Наличные' ?></div>
                        </div>
                    </div>
                </div>

                <!-- Адрес доставки -->
                <?php if($order['delivery_method']=='delivery' && $order['street']): ?>
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-map-marker-alt"></i> Адрес доставки</h3></div>
                    <div class="customer-info">
                        <div class="info-item"><label>Город</label><div class="value"><?= htmlspecialchars($order['city']??'Москва') ?></div></div>
                        <div class="info-item"><label>Улица</label><div class="value"><?= htmlspecialchars($order['street']) ?></div></div>
                        <div class="info-item"><label>Дом</label><div class="value"><?= htmlspecialchars($order['house']) ?></div></div>
                        <?php if($order['apartment']): ?><div class="info-item"><label>Квартира</label><div class="value"><?= htmlspecialchars($order['apartment']) ?></div></div><?php endif; ?>
                        <?php if($order['entrance']): ?><div class="info-item"><label>Подъезд</label><div class="value"><?= htmlspecialchars($order['entrance']) ?></div></div><?php endif; ?>
                        <?php if($order['floor']): ?><div class="info-item"><label>Этаж</label><div class="value"><?= htmlspecialchars($order['floor']) ?></div></div><?php endif; ?>
                        <?php if($order['comment']): ?><div class="info-item" style="grid-column:1/-1"><label>Комментарий</label><div class="value"><?= htmlspecialchars($order['comment']) ?></div></div><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Позиции заказа -->
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-list"></i> Позиции заказа</h3></div>
                    <div style="overflow-x:auto">
                        <table class="items-table">
                            <thead><tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td style="display:flex;align-items:center;gap:12px">
                                        <?php if($item['image_url']): ?><img src="/house_of_taste<?= htmlspecialchars($item['image_url']) ?>" class="item-img" alt=""><?php endif; ?>
                                        <div>
                                            <div style="font-weight:600"><?= htmlspecialchars($item['product_name_snapshot']) ?></div>
                                            <?php if($item['product_name_snapshot']!==($pdo->query("SELECT name FROM products WHERE id=".$item['product_id'])->fetchColumn()??'')): ?>
                                                <small style="color:#666">(цена на момент заказа)</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= number_format($item['price_at_moment'],0,'.',' ') ?> ₽</td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><strong><?= number_format($item['total_price'],0,'.',' ') ?> ₽</strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Комментарий клиента -->
                <?php if($order['customer_comment']): ?>
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-comment"></i> Комментарий клиента</h3></div>
                    <p style="color:#aaa;line-height:1.6"><?= nl2br(htmlspecialchars($order['customer_comment'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <!-- Итоги -->
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-calculator"></i> Итоги</h3></div>
                    <div class="totals" style="flex-direction:column;gap:15px">
                        <div class="total-item"><span class="label">Сумма товаров</span><span class="value"><?= number_format($order['total_amount'],0,'.',' ') ?> ₽</span></div>
                        <?php if($order['discount_amount']>0): ?>
                            <div class="total-item"><span class="label">Скидка</span><span class="value" style="color:var(--danger)">-<?= number_format($order['discount_amount'],0,'.',' ') ?> ₽</span></div>
                        <?php endif; ?>
                        <?php if($order['delivery_amount']>0): ?>
                            <div class="total-item"><span class="label">Доставка</span><span class="value"><?= number_format($order['delivery_amount'],0,'.',' ') ?> ₽</span></div>
                        <?php endif; ?>
                        <?php if($order['tip_amount']>0): ?>
                            <div class="total-item"><span class="label">Чаевые</span><span class="value"><?= number_format($order['tip_amount'],0,'.',' ') ?> ₽</span></div>
                        <?php endif; ?>
                        <?php if($promo): ?>
                            <div class="total-item"><span class="label">Промокод</span><span class="value" style="color:var(--info)"><?= htmlspecialchars($promo['code']) ?> (<?= $promo['discount_type']=='percent'?'-'.$promo['discount_value'].'%':'-'.number_format($promo['discount_value'],0,'.',' ').'₽' ?>)</span></div>
                        <?php endif; ?>
                        <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:15px;width:100%">
                            <div class="total-item"><span class="label">Итого к оплате</span><span class="value final"><?= number_format($order['final_amount'],0,'.',' ') ?> ₽</span></div>
                        </div>
                    </div>
                </div>

                <!-- История статусов -->
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-history"></i> История</h3></div>
                    <div class="timeline">
                        <div class="timeline-item <?= $order['status']!='cancelled'?'active':'' ?>">
                            <div class="time"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                            <div class="status">Заказ создан</div>
                        </div>
                        <?php if(strtotime($order['updated_at']) > strtotime($order['created_at'])): ?>
                        <div class="timeline-item <?= in_array($order['status'],['confirmed','cooking','ready_pickup','delivering','delivered'])?'active':'' ?>">
                            <div class="time"><?= date('d.m.Y H:i', strtotime($order['updated_at'])) ?></div>
                            <div class="status">Статус изменён: <strong><?= $order['status'] ?></strong></div>
                        </div>
                        <?php endif; ?>
                        <?php if($order['status']=='delivered'): ?>
                        <div class="timeline-item active">
                            <div class="time"><?= date('d.m.Y H:i', strtotime($order['updated_at'])) ?></div>
                            <div class="status">Заказ доставлен</div>
                        </div>
                        <?php elseif($order['status']=='cancelled'): ?>
                        <div class="timeline-item active">
                            <div class="time"><?= date('d.m.Y H:i', strtotime($order['updated_at'])) ?></div>
                            <div class="status" style="color:var(--danger)">Заказ отменён</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Действия -->
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-cog"></i> Действия</h3></div>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <a href="#" class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Печать чека</a>
                        <a href="users_edit.php?id=<?= $order['user_id'] ?>" class="btn btn-outline"><i class="fas fa-user-edit"></i> Редактировать клиента</a>
                        <?php if($order['status']!='delivered' && $order['status']!='cancelled'): ?>
                            <button class="btn btn-danger" onclick="if(confirm('Отменить заказ #<?= $order['id'] ?>?')){fetch('api/orders_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_status',order_id:<?= $order['id'] ?>,status:'cancelled'})}).then(r=>r.json()).then(d=>{if(d.success){location.reload()}else{alert('Ошибка: '+d.error)}})}"><i class="fas fa-times"></i> Отменить заказ</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if(isset($_SESSION['admin_message'])): ?>
<script>
function showToast(msg,type){const toast=document.createElement('div');toast.id='admin-toast';toast.className=type;toast.textContent=msg;document.body.appendChild(toast);setTimeout(()=>toast.classList.add('show'),10);setTimeout(()=>{toast.classList.remove('show');setTimeout(()=>toast.remove(),300)},3000)}
showToast('<?= $_SESSION['admin_message'] ?>','<?= $_SESSION['admin_message_type']??'success' ?>');
</script>
<?php unset($_SESSION['admin_message'],$_SESSION['admin_message_type']); endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
