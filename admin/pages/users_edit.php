<?php

$pageTitle = 'Редактирование пользователя';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId === 0) { header('Location: users.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) { $_SESSION['admin_message']='Пользователь не найден'; $_SESSION['admin_message_type']='error'; header('Location: users.php'); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $fullName = trim($_POST['full_name']??''); $phone = trim($_POST['phone']??''); $role = (int)($_POST['role']??0); $newPassword = trim($_POST['new_password']??'');
    if ($user['role']==1 && $role==0) { $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role=1 AND id!=$userId")->fetchColumn(); if ($adminCount==0) { $_SESSION['admin_message']='Нельзя удалить последнего администратора!'; $_SESSION['admin_message_type']='error'; } }
    $updates=[]; $params=[];
    if ($fullName) { $updates[]="full_name=?"; $params[]=$fullName; }
    if ($phone) { $updates[]="phone=?"; $params[]=$phone; }
    $updates[]="role=?"; $params[]=$role;
    if (!empty($newPassword)) { if (strlen($newPassword)<6) { $_SESSION['admin_message']='Пароль должен быть не менее 6 символов'; $_SESSION['admin_message_type']='error'; } else { $updates[]="password_hash=?"; $params[]=password_hash($newPassword,PASSWORD_BCRYPT); } }
    if (!empty($updates)) { $updates[]="updated_at=NOW()"; $params[]=$userId; $sql="UPDATE users SET ".implode(', ',$updates)." WHERE id=?"; $stmt=$pdo->prepare($sql); try { $stmt->execute($params); $_SESSION['admin_message']='Данные обновлены'; $_SESSION['admin_message_type']='success'; header('Location: users.php'); exit; } catch(PDOException $e) { $_SESSION['admin_message']='Ошибка: '.$e->getMessage(); $_SESSION['admin_message_type']='error'; } }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--text:#fff;--text-muted:#999;--success:#2ecc71;--warning:#f39c12;--danger:#e74c3c}
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
.admin-main{flex:1;margin-left:240px;padding:30px}
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header p{color:var(--text-muted);font-size:14px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group input,.form-group select{width:100%;padding:12px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:14px;font-family:inherit}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--gold)}
.form-group input:disabled{opacity:0.6;cursor:not-allowed}
.form-group small{color:#666;font-size:11px;display:block;margin-top:5px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#27ae60}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff}
.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-top:20px}
.stat-card{background:rgba(0,0,0,0.2);padding:15px;border-radius:8px;text-align:center;border:1px solid rgba(255,255,255,0.05)}
.stat-card i{font-size:24px;color:var(--gold);margin-bottom:8px}
.stat-num{font-size:20px;font-weight:700;display:block;color:var(--text)}
.stat-label{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
#admin-toast{position:fixed;bottom:30px;right:30px;padding:15px 25px;background:var(--gray);border-left:4px solid var(--success);color:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:9999;transform:translateX(400px);transition:transform 0.3s;font-size:14px}
#admin-toast.show{transform:translateX(0)}
#admin-toast.error{border-left-color:var(--danger)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.admin-main{padding:20px}}
</style>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-user-edit"></i> Редактирование пользователя</h1>
            <p>ID: #<?= $user['id'] ?> | <?= htmlspecialchars($user['login']) ?></p>
        </div>
        <div class="card" style="max-width:700px">
            <form method="POST">
                <div class="form-group"><label>Логин</label><input type="text" value="<?= htmlspecialchars($user['login']) ?>" disabled><small>Логин нельзя изменить</small></div>
                <div class="form-row">
                    <div class="form-group"><label>ФИО</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']??'') ?>" placeholder="Иванов Иван Иванович"></div>
                    <div class="form-group"><label>Телефон</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" placeholder="+7 (999) 123-45-67"></div>
                </div>
                <div class="form-group"><label>Роль</label><select name="role"><option value="0" <?= $user['role']==0?'selected':'' ?>>Клиент</option><option value="1" <?= $user['role']==1?'selected':'' ?>>Администратор</option></select></div>
                <div class="form-group"><label>Новый пароль (оставьте пустым, чтобы не менять)</label><input type="password" name="new_password" placeholder="••••••••" autocomplete="new-password"><small>Минимум 6 символов</small></div>
                <div class="form-group"><label>Дата регистрации</label><input type="text" value="<?= date('d.m.Y H:i',strtotime($user['created_at'])) ?>" disabled></div>
                <div style="display:flex;gap:15px;margin-top:30px"><button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Сохранить</button><a href="users.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Назад</a></div>
            </form>
        </div>
        <div class="card" style="max-width:700px;margin-top:30px">
            <h3 style="margin-bottom:20px;font-size:18px"><i class="fas fa-chart-bar"></i> Активность пользователя</h3>
            <?php $stats=['orders'=>$pdo->query("SELECT COUNT(*) FROM orders WHERE user_id=$userId")->fetchColumn(),'orders_total'=>$pdo->query("SELECT COALESCE(SUM(final_amount),0) FROM orders WHERE user_id=$userId")->fetchColumn(),'reviews'=>$pdo->query("SELECT COUNT(*) FROM reviews WHERE user_id=$userId")->fetchColumn(),'favorites'=>$pdo->query("SELECT COUNT(*) FROM favorites WHERE user_id=$userId")->fetchColumn()]; ?>
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-receipt"></i><span class="stat-num"><?= $stats['orders'] ?></span><span class="stat-label">Заказов</span></div>
                <div class="stat-card"><i class="fas fa-ruble-sign"></i><span class="stat-num"><?= number_format($stats['orders_total'],0,'.',' ') ?> ₽</span><span class="stat-label">На сумму</span></div>
                <div class="stat-card"><i class="fas fa-comment-alt"></i><span class="stat-num"><?= $stats['reviews'] ?></span><span class="stat-label">Отзывов</span></div>
                <div class="stat-card"><i class="fas fa-heart"></i><span class="stat-num"><?= $stats['favorites'] ?></span><span class="stat-label">В избранном</span></div>
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
