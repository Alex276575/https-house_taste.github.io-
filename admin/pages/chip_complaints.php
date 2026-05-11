<?php

$pageTitle = 'Жалобы на Чипа';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php'); exit;
}

$message = ''; $msgType = '';

// === ОБРАБОТКА ДЕЙСТВИЙ (AJAX & POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = (int)$_POST['id'];

    if ($_POST['action'] === 'update_status' && isset($_POST['status'])) {
        $allowed = ['new','in_progress','resolved','rejected'];
        if (in_array($_POST['status'], $allowed)) {
            $stmt = $pdo->prepare("UPDATE chip_complaints SET status = ?, admin_response = COALESCE(?, admin_response), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['response'] ?? null, $id]);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false, 'error' => 'Неверный статус']); }
        exit;
    }
    if ($_POST['action'] === 'get_complaint' && $id) {
        $stmt = $pdo->prepare("SELECT c.*, u.full_name, u.login, o.id as order_num FROM chip_complaints c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN orders o ON c.order_id = o.id WHERE c.id = ?");
        $stmt->execute([$id]);
        $comp = $stmt->fetch();
        if($comp) echo json_encode(['success' => true, 'data' => $comp]);
        else echo json_encode(['success' => false]);
        exit;
    }
}

// === ФИЛЬТРАЦИЯ ===
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT c.*, u.full_name, u.login FROM chip_complaints c LEFT JOIN users u ON c.user_id = u.id WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND c.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Жалобы и обращения</h1>
            <p>Управление жалобами пользователей на работу бота</p>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Все статусы</option>
                    <option value="new" <?= $statusFilter=='new'?'selected':'' ?>>Новые</option>
                    <option value="in_progress" <?= $statusFilter=='in_progress'?'selected':'' ?>>В работе</option>
                    <option value="resolved" <?= $statusFilter=='resolved'?'selected':'' ?>>Решены</option>
                    <option value="rejected" <?= $statusFilter=='rejected'?'selected':'' ?>>Отклонены</option>
                </select>
                <a href="chip_complaints.php" class="btn btn-outline"><i class="fas fa-undo"></i> Сброс</a>
            </form>
        </div>

        <!-- Таблица -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th><i class="fas fa-hashtag"></i> ID</th><th><i class="fas fa-user"></i> Автор</th><th><i class="fas fa-receipt"></i> Заказ</th><th><i class="fas fa-comment"></i> Жалоба</th><th><i class="fas fa-flag"></i> Статус</th><th><i class="fas fa-cogs"></i> Действия</th></tr></thead>
                    <tbody>
                        <?php foreach($complaints as $c):
                            $statusLabels = ['new'=>'Новая','in_progress'=>'В работе','resolved'=>'Решена','rejected'=>'Отклонена'];
                            $statusClass = $c['status'] == 'resolved' ? 'success' : ($c['status'] == 'in_progress' ? 'warning' : ($c['status'] == 'rejected' ? 'danger' : 'info'));
                        ?>
                        <tr>
                            <td><strong>#<?= $c['id'] ?></strong></td>
                            <td><?= htmlspecialchars($c['full_name'] ?? $c['login'] ?? 'Гость') ?></td>
                            <td><?= $c['order_id'] ? '#'.$c['order_id'] : '—' ?></td>
                            <td class="text-truncate" title="<?= htmlspecialchars($c['message']) ?>"><?= htmlspecialchars($c['message']) ?></td>
                            <td><span class="status-badge status-<?= $statusClass ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span></td>
                            <td>
                                <button class="btn btn-primary btn-sm btn-icon open-modal" data-id="<?= $c['id'] ?>" title="Обработать"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(empty($complaints)): ?>
                <div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Жалоб нет</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- МОДАЛЬНОЕ ОКНО -->
<div id="complaintModal" class="modal" style="display:none">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header"><h3><i class="fas fa-exclamation-circle"></i> Обработка жалобы</h3><button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button></div>
        <div class="modal-body" id="modalContent">Загрузка...</div>
        <div class="modal-footer">
            <select id="statusSelect" class="form-select"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="resolved">Решена</option><option value="rejected">Отклонена</option></select>
            <textarea id="responseText" class="form-input" placeholder="Ответ админа..." rows="2"></textarea>
            <button type="button" class="btn btn-primary" id="saveComplaint"><i class="fas fa-save"></i> Сохранить</button>
        </div>
    </div>
</div>

<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--gray-light:#3a3a3a;--text:#fff;--text-muted:#999;--success:#2ecc71;--danger:#e74c3c;--warning:#f39c12;--info:#3498db}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);line-height:1.6}
.admin-wrapper{display:flex;min-height:100vh}.admin-sidebar{width:240px;background:var(--darker);border-right:1px solid rgba(200,166,86,0.15);position:fixed;height:100vh;overflow-y:auto;z-index:100}
.sidebar-header{padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px}.sidebar-header i{color:var(--gold);font-size:20px}
.sidebar-nav{padding:15px 0}.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--text-muted);font-size:13px;text-decoration:none;transition:0.2s;border-left:3px solid transparent}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(200,166,86,0.1);color:var(--gold);border-left-color:var(--gold)}.sidebar-nav a i{width:20px;text-align:center}
.sidebar-divider{padding:15px 20px 10px;font-size:10px;text-transform:uppercase;letter-spacing:2px;color:#555;font-weight:600}
.admin-main{flex:1;margin-left:240px;padding:30px}.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}.page-header h1 i{color:var(--gold)}.page-header p{color:var(--text-muted);font-size:14px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.filters-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.filters-bar select{padding:10px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px}
.filters-bar select:focus{outline:none;border-color:var(--gold)}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1a1a}.btn-primary:hover{transform:translateY(-2px)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-sm{padding:5px 10px;font-size:11px}.btn-icon{width:32px;height:32px;padding:0;justify-content:center}
.table-responsive{overflow-x:auto}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table th i{margin-right:5px}.data-table tr:hover{background:rgba(200,166,86,0.08)}.text-truncate{max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.status-badge{padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase}.status-success{background:rgba(46,204,113,0.2);color:var(--success)}.status-warning{background:rgba(243,156,18,0.2);color:var(--warning)}.status-danger{background:rgba(231,76,60,0.2);color:var(--danger)}.status-info{background:rgba(52,152,219,0.2);color:var(--info)}
.empty-state{text-align:center;padding:60px;color:#666}.empty-state i{font-size:48px;margin-bottom:16px;opacity:0.3;color:var(--gold)}
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-content{background:var(--gray);border-radius:12px;padding:20px;width:100%;max-width:500px;border:1px solid rgba(255,255,255,0.1)}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,0.1)}
.modal-header h3{font-size:16px;font-weight:600}.modal-close{background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer}.modal-body{margin-bottom:15px;line-height:1.5}
.modal-footer{display:flex;flex-direction:column;gap:10px}.form-select,.form-input{width:100%;padding:10px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit}
.form-input{resize:vertical}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}}
</style>

<script>
let currentId = null;
document.querySelectorAll('.open-modal').forEach(btn => {
    btn.addEventListener('click', function() {
        currentId = this.dataset.id;
        document.getElementById('complaintModal').style.display = 'flex';
        document.getElementById('modalContent').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Загрузка...';

        fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=get_complaint&id='+currentId})
        .then(r=>r.json()).then(data=>{
            if(data.success){
                const c = data.data;
                document.getElementById('modalContent').innerHTML = `
                    <p><strong>Пользователь:</strong> ${c.full_name||c.login||'Гость'}</p>
                    <p><strong>Заказ:</strong> ${c.order_id ? '#'+c.order_id : 'Нет привязки'}</p>
                    <p><strong>Жалоба:</strong><br>${c.message.replace(/\n/g,'<br>')}</p>
                    ${c.photo_path ? `<p><strong>Фото:</strong> <a href="${c.photo_path}" target="_blank" style="color:var(--gold)">Открыть</a></p>` : ''}
                    <p><strong>Ответ:</strong> ${c.admin_response || '<span style="color:#666">Не отвечено</span>'}</p>
                `;
                document.getElementById('statusSelect').value = c.status;
                document.getElementById('responseText').value = c.admin_response || '';
            }
        });
    });
});

document.getElementById('saveComplaint').addEventListener('click', function() {
    if(!currentId) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохранение...';

    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=update_status&id=${currentId}&status=${document.getElementById('statusSelect').value}&response=${encodeURIComponent(document.getElementById('responseText').value)}`})
    .then(r=>r.json()).then(data=>{
        if(data.success){
            alert('Статус обновлён');
            location.reload();
        } else { alert('Ошибка: '+data.error); }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Сохранить';
    });
});

function closeModal() { document.getElementById('complaintModal').style.display = 'none'; }
document.getElementById('complaintModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
