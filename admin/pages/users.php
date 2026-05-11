<?php

$pageTitle = 'Управление пользователями';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ ===
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $userId = (int)$_POST['id'];

    if ($userId === $_SESSION['user_id']) {
        $_SESSION['admin_message'] = 'Нельзя удалить самого себя!';
        $_SESSION['admin_message_type'] = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['admin_message'] = 'Пользователь успешно удалён';
            $_SESSION['admin_message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['admin_message'] = 'Ошибка при удалении: ' . $e->getMessage();
            $_SESSION['admin_message_type'] = 'error';
        }
    }
    header('Location: users.php');
    exit;
}

// === ФИЛЬТРАЦИЯ И ПОИСК ===
$search = $_GET['search'] ?? '';
$roleFilter = isset($_GET['role']) && $_GET['role'] !== '' ? (int)$_GET['role'] : null;
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

$allowedSort = ['id', 'login', 'full_name', 'phone', 'created_at', 'role'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'created_at';
if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as orders_count,
        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as reviews_count,
        (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count
        FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.login LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($roleFilter !== null) { $sql .= " AND u.role = ?"; $params[] = $roleFilter; }
$sql .= " ORDER BY u.$sortBy $sortOrder";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 0")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 1")->fetchColumn();
$newUsersToday = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();

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
.sidebar-logout{margin-top:20px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.1);color:var(--danger);display:flex;align-items:center;gap:10px;padding:12px 20px;text-decoration:none;font-size:13px}
.sidebar-logout:hover{color:#c0392b}
.admin-main{flex:1;margin-left:240px;padding:30px}
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header p{color:var(--text-muted);font-size:14px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--gray);padding:20px;border-radius:12px;display:flex;align-items:center;gap:15px;border:1px solid rgba(255,255,255,0.05);transition:0.3s}
.stat-card:hover{border-color:var(--gold);transform:translateY(-3px)}
.stat-card i{font-size:28px;color:var(--gold);width:40px;text-align:center}
.stat-card.success i{color:var(--success)}
.stat-card.warning i{color:var(--warning)}
.stat-card.danger i{color:var(--danger)}
.stat-num{font-size:24px;font-weight:700;display:block;color:var(--text)}
.stat-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.filters-bar{display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.filters-bar input,.filters-bar select{padding:10px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:13px}
.filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--gold)}
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-primary{background:var(--gold);color:#1a1a1a}
.btn-primary:hover{background:var(--gold-light)}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff}
.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#27ae60}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{background:#c0392b}
.btn-sm{padding:5px 10px;font-size:11px}
.btn-icon{width:32px;height:32px;padding:0;justify-content:center}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table tr:hover{background:rgba(200,166,86,0.05)}
.status-badge{padding:4px 12px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;display:inline-block}
.status-approved{background:rgba(46,204,113,0.2);color:#2ecc71}
.status-cancelled{background:rgba(231,76,60,0.2);color:#e74c3c}
.status-pending{background:rgba(243,156,18,0.2);color:#f39c12}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:14px;font-family:inherit}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--gold)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
#admin-toast{position:fixed;bottom:30px;right:30px;padding:15px 25px;background:var(--gray);border-left:4px solid var(--success);color:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:9999;transform:translateX(400px);transition:transform 0.3s;font-size:14px}
#admin-toast.show{transform:translateX(0)}
#admin-toast.error{border-left-color:var(--danger)}
#admin-toast.warning{border-left-color:var(--warning)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}}
</style>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Управление пользователями</h1>
            <p>Просмотр, поиск и управление аккаунтами клиентов</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-user"></i><div><span class="stat-num"><?= $totalUsers ?></span><span class="stat-label">Клиентов</span></div></div>
            <div class="stat-card success"><i class="fas fa-user-shield"></i><div><span class="stat-num"><?= $totalAdmins ?></span><span class="stat-label">Администраторов</span></div></div>
            <div class="stat-card"><i class="fas fa-calendar-day"></i><div><span class="stat-num"><?= $newUsersToday ?></span><span class="stat-label">Новых сегодня</span></div></div>
        </div>

        <div class="card">
            <form method="GET" class="filters-bar">
                <input type="text" name="search" placeholder="Поиск по логину, имени или телефону..." value="<?= htmlspecialchars($search) ?>" style="min-width:250px">
                <select name="role">
                    <option value="">Все роли</option>
                    <option value="0" <?= $roleFilter===0?'selected':'' ?>>Клиент</option>
                    <option value="1" <?= $roleFilter===1?'selected':'' ?>>Администратор</option>
                </select>
                <select name="sort">
                    <option value="created_at" <?= $sortBy==='created_at'?'selected':'' ?>>По дате</option>
                    <option value="login" <?= $sortBy==='login'?'selected':'' ?>>По логину</option>
                    <option value="full_name" <?= $sortBy==='full_name'?'selected':'' ?>>По имени</option>
                    <option value="phone" <?= $sortBy==='phone'?'selected':'' ?>>По телефону</option>
                </select>
                <select name="order">
                    <option value="DESC" <?= $sortOrder==='DESC'?'selected':'' ?>>По убыванию</option>
                    <option value="ASC" <?= $sortOrder==='ASC'?'selected':'' ?>>По возрастанию</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Найти</button>
                <a href="users.php" class="btn btn-outline">Сбросить</a>
            </form>
        </div>

        <div class="card">
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Аватар</th><th>Логин</th><th>ФИО</th><th>Телефон</th><th>Роль</th><th>Заказы</th><th>Отзывы</th><th>Избранное</th><th>Регистрация</th><th>Действия</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td><?php if($user['avatar_url']): ?><img src="/house_of_taste<?= htmlspecialchars($user['avatar_url']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%;border:2px solid #c8a656"><?php else: ?><div style="width:40px;height:40px;background:linear-gradient(135deg,#c8a656,#e8c96a);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#1a1a1a;font-weight:700;font-size:14px"><?= mb_strtoupper(mb_substr($user['full_name']??'U',0,1,'UTF-8'),'UTF-8') ?></div><?php endif; ?></td>
                            <td><strong><?= htmlspecialchars($user['login']) ?></strong><?php if($user['id']===$_SESSION['user_id']): ?><span class="status-badge" style="background:rgba(46,204,113,0.2);color:#2ecc71;margin-left:5px">Вы</span><?php endif; ?></td>
                            <td><?= htmlspecialchars($user['full_name']??'—') ?></td>
                            <td><?php if($user['phone']): ?><a href="tel:<?= preg_replace('/[^0-9]/','',$user['phone']) ?>" style="color:#c8a656"><?= htmlspecialchars($user['phone']) ?></a><?php else: ?><span style="color:#666">—</span><?php endif; ?></td>
                            <td><?php if($user['role']==1): ?><span class="status-badge status-approved"><i class="fas fa-shield-alt"></i> Админ</span><?php else: ?><span class="status-badge" style="background:rgba(52,152,219,0.2);color:#3498db"><i class="fas fa-user"></i> Клиент</span><?php endif; ?></td>
                            <td><span style="color:<?= $user['orders_count']>0?'#2ecc71':'#666' ?>;font-weight:600"><?= $user['orders_count'] ?></span></td>
                            <td><span style="color:<?= $user['reviews_count']>0?'#3498db':'#666' ?>;font-weight:600"><?= $user['reviews_count'] ?></span></td>
                            <td><span style="color:<?= $user['favorites_count']>0?'#f39c12':'#666' ?>;font-weight:600"><?= $user['favorites_count'] ?></span></td>
                            <td><div style="font-size:12px"><?= date('d.m.Y',strtotime($user['created_at'])) ?><div style="color:#666;font-size:11px"><?= date('H:i',strtotime($user['created_at'])) ?></div></div></td>
                            <td><div style="display:flex;gap:5px"><a href="users_edit.php?id=<?= $user['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Редактировать"><i class="fas fa-edit"></i></a><?php if($user['id']!==$_SESSION['user_id']): ?><form method="POST" style="display:inline" onsubmit="return confirm('Удалить пользователя <?= htmlspecialchars($user['login']) ?>?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $user['id'] ?>"><button type="submit" class="btn btn-danger btn-sm btn-icon" title="Удалить"><i class="fas fa-trash"></i></button></form><?php endif; ?></div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(empty($users)): ?>
                <div style="text-align:center;padding:60px;color:#666"><i class="fas fa-users" style="font-size:48px;margin-bottom:20px;opacity:0.3"></i><p style="font-size:16px;margin-bottom:10px">Пользователи не найдены</p></div>
            <?php else: ?>
                <div style="padding:15px;background:rgba(0,0,0,0.2);border-radius:8px;margin-top:20px;display:flex;justify-content:space-between;align-items:center"><span style="color:#999;font-size:13px">Найдено: <strong style="color:#c8a656"><?= count($users) ?></strong> пользователей</span><button onclick="exportToCSV()" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Экспорт в CSV</button></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function exportToCSV(){const rows=document.querySelectorAll('.data-table tbody tr');let csv=[];csv.push(['ID','Логин','ФИО','Телефон','Роль','Заказы','Отзывы','Избранное','Дата']);rows.forEach(row=>{const cells=row.querySelectorAll('td');csv.push([cells[0].textContent.trim(),cells[2].textContent.trim(),cells[3].textContent.trim(),cells[4].textContent.trim(),cells[5].textContent.trim(),cells[6].textContent.trim(),cells[7].textContent.trim(),cells[8].textContent.trim(),cells[9].textContent.trim()])});const csvContent=csv.map(row=>row.join(';')).join('\n');const blob=new Blob(['\ufeff'+csvContent],{type:'text/csv;charset=utf-8;'});const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='users_'+new Date().toISOString().split('T')[0]+'.csv';link.click()}
<?php if(isset($_SESSION['admin_message'])): ?>
function showToast(msg,type){const toast=document.createElement('div');toast.id='admin-toast';toast.className=type;toast.textContent=msg;document.body.appendChild(toast);setTimeout(()=>toast.classList.add('show'),10);setTimeout(()=>{toast.classList.remove('show');setTimeout(()=>toast.remove(),300)},3000)}
showToast('<?= $_SESSION['admin_message'] ?>','<?= $_SESSION['admin_message_type']??'success' ?>');
<?php unset($_SESSION['admin_message'],$_SESSION['admin_message_type']); endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
