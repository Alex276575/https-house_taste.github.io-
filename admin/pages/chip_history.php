<?php

$pageTitle = 'История диалогов с Чипом';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php'); exit;
}

// === ФИЛЬТРАЦИЯ ===
$search = $_GET['search'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';

$sql = "SELECT h.*, u.full_name, u.login
        FROM chip_chat_history h
        LEFT JOIN users u ON h.user_id = u.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (h.user_message LIKE ? OR h.bot_response LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($ratingFilter !== '') {
    $sql .= " AND h.rating = ?";
    $params[] = (int)$ratingFilter;
}

$sql .= " ORDER BY h.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$chats = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-comments"></i> История диалогов с Чипом</h1>
            <p>Журнал сообщений пользователей и ответов бота</p>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <div class="filter-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Поиск по сообщению..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="rating">
                    <option value="">Все оценки</option>
                    <option value="5" <?= $ratingFilter=='5'?'selected':'' ?></option>
                    <option value="4" <?= $ratingFilter=='4'?'selected':'' ?>></option>
                    <option value="3" <?= $ratingFilter=='3'?'selected':'' ?>></option>
                    <option value="2" <?= $ratingFilter=='2'?'selected':'' ?>></option>
                    <option value="1" <?= $ratingFilter=='1'?'selected':'' ?>></option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Применить</button>
                <a href="chip_history.php" class="btn btn-outline"><i class="fas fa-undo"></i> Сброс</a>
            </form>
        </div>

        <!-- Таблица -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-clock"></i> Дата</th>
                            <th><i class="fas fa-user"></i> Пользователь</th>
                            <th><i class="fas fa-paper-plane"></i> Сообщение пользователя</th>
                            <th><i class="fas fa-robot"></i> Ответ Чипа</th>
                            <th><i class="fas fa-star"></i> Оценка</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($chats as $chat): ?>
                        <tr>
                            <td><small><?= date('d.m.Y H:i', strtotime($chat['created_at'])) ?></small></td>
                            <td><?= htmlspecialchars($chat['full_name'] ?? $chat['login'] ?? 'Гость') ?></td>
                            <td class="text-truncate" title="<?= htmlspecialchars($chat['user_message']) ?>"><?= htmlspecialchars($chat['user_message']) ?></td>
                            <td class="text-truncate" title="<?= htmlspecialchars($chat['bot_response']) ?>"><?= htmlspecialchars($chat['bot_response']) ?></td>
                            <td>
                                <?php if($chat['rating']): ?>
                                    <span class="rating-stars"><?= str_repeat('', $chat['rating']) ?></span>
                                <?php else: ?>
                                    <span style="color:#666">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(empty($chats)): ?>
                <div class="empty-state"><i class="fas fa-comments"></i><p>Диалоги не найдены</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--gray-light:#3a3a3a;--text:#fff;--text-muted:#999}
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
.filter-group{position:relative}.filter-group i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px}
.filters-bar input,.filters-bar select{padding:10px 15px 10px 32px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px}
.filters-bar select{padding-left:15px}.filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1a1a;box-shadow:0 4px 15px rgba(200,166,86,0.3)}.btn-primary:hover{transform:translateY(-2px)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.table-responsive{overflow-x:auto}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table th i{margin-right:5px}.data-table tr:hover{background:rgba(200,166,86,0.08)}.text-truncate{max-width:350px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rating-stars{font-size:12px;letter-spacing:2px}.empty-state{text-align:center;padding:60px;color:#666}.empty-state i{font-size:48px;margin-bottom:16px;opacity:0.3;color:var(--gold)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
