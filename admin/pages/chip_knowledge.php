<?php

$pageTitle = 'База знаний Чипа';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === ОБРАБОТКА СОХРАНЕНИЯ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $category = $_POST['category'] ?? 'other';
        $keywords = trim($_POST['keywords'] ?? '');
        $responses = trim($_POST['responses'] ?? '');
        $redirect_url = trim($_POST['redirect_url'] ?? '');
        $requires_photo = isset($_POST['requires_photo']) ? 1 : 0;
        $requires_manager = isset($_POST['requires_manager']) ? 1 : 0;
        $priority = (int)($_POST['priority'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE chip_knowledge SET
                    category=?, keywords=?, responses=?, redirect_url=?,
                    requires_photo=?, requires_manager=?, priority=?, is_active=?,
                    updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$category, $keywords, $responses, $redirect_url,
                          $requires_photo, $requires_manager, $priority, $is_active, $id]);
        } else {
            // Создание
            $stmt = $pdo->prepare("
                INSERT INTO chip_knowledge
                (category, keywords, responses, redirect_url, requires_photo,
                 requires_manager, priority, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$category, $keywords, $responses, $redirect_url,
                          $requires_photo, $requires_manager, $priority, $is_active]);
        }

        $_SESSION['admin_message'] = 'Запись сохранена';
        $_SESSION['admin_message_type'] = 'success';
        header('Location: chip_knowledge.php');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM chip_knowledge WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['admin_message'] = 'Запись удалена';
        $_SESSION['admin_message_type'] = 'success';
        header('Location: chip_knowledge.php');
        exit;
    }
}

// === ЗАГРУЗКА ДАННЫХ ===
$categories = ['menu','delivery','refund','review','manager','hours','address','payment','promo','greeting','thanks','bye','map','other'];
$editRecord = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM chip_knowledge WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRecord = $stmt->fetch();
}

$sql = "SELECT * FROM chip_knowledge ORDER BY priority DESC, created_at DESC";
$knowledge = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-brain"></i> База знаний Чипа</h1>
            <p>Управление ответами чат-бота для клиентов</p>
        </div>

        <!-- Форма редактирования/добавления -->
        <div class="card">
            <div class="card-header">
                <h3><?= $editRecord ? '✏️ Редактировать' : '+ Добавить запись' ?></h3>
                <?php if ($editRecord): ?>
                    <a href="chip_knowledge.php" class="btn btn-outline">Отмена</a>
                <?php endif; ?>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="save">
                <?php if ($editRecord): ?>
                    <input type="hidden" name="id" value="<?= $editRecord['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Категория</label>
                        <select name="category" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"
                                    <?= ($editRecord['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Приоритет (чем больше — тем выше)</label>
                        <input type="number" name="priority" value="<?= $editRecord['priority'] ?? 0 ?>" min="0" max="100">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ключевые слова (через запятую)</label>
                    <input type="text" name="keywords"
                           value="<?= htmlspecialchars($editRecord['keywords'] ?? '') ?>"
                           placeholder="меню,блюда,еда,заказать" required>
                    <small style="color: #666;">По этим словам бот будет подбирать ответ</small>
                </div>

                <div class="form-group">
                    <label>Варианты ответов (разделяйте через |||)</label>
                    <textarea name="responses" rows="4" required placeholder="Ответ 1|||Ответ 2|||Ответ 3"><?= htmlspecialchars($editRecord['responses'] ?? '') ?></textarea>
                    <small style="color: #666;">Бот случайно выберет один из вариантов</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>URL для перехода (опционально)</label>
                        <input type="url" name="redirect_url"
                               value="<?= htmlspecialchars($editRecord['redirect_url'] ?? '') ?>"
                               placeholder="/house_of_taste/pages/catalog.php">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="requires_photo"
                                       <?= ($editRecord['requires_photo'] ?? 0) ? 'checked' : '' ?>>
                                Требуется фото
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="requires_manager"
                                       <?= ($editRecord['requires_manager'] ?? 0) ? 'checked' : '' ?>>
                                Передавать менеджеру
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active"
                               <?= ($editRecord['is_active'] ?? 1) ? 'checked' : '' ?>>
                        Активна
                    </label>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Сохранить запись
                </button>
            </form>
        </div>

        <!-- Список записей -->
        <div class="card">
            <div class="card-header">
                <h3>Все записи базы знаний</h3>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Категория</th>
                            <th>Ключевые слова</th>
                            <th>Приоритет</th>
                            <th>Фото/Менеджер</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($knowledge as $item): ?>
                        <tr>
                            <td>#<?= $item['id'] ?></td>
                            <td><span class="status-badge"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars(mb_substr($item['keywords'], 0, 40)) ?><?= strlen($item['keywords']) > 40 ? '...' : '' ?>
                            </td>
                            <td><?= $item['priority'] ?></td>
                            <td>
                                <?php if ($item['requires_photo']): ?><i class="fas fa-camera" title="Требует фото" style="color: #3498db;"></i> <?php endif; ?>
                                <?php if ($item['requires_manager']): ?><i class="fas fa-user-tie" title="Передаёт менеджеру" style="color: #f39c12;"></i><?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $item['is_active'] ? 'approved' : 'cancelled' ?>">
                                    <?= $item['is_active'] ? 'Активна' : 'Неактивна' ?>
                                </span>
                            </td>
                            <td>
                                <a href="chip_knowledge.php?edit=<?= $item['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить запись?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php
// Показать сообщение если есть
if (isset($_SESSION['admin_message'])):
?>
<script>
function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.id = 'admin-toast';
    toast.className = type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
showToast('<?= $_SESSION['admin_message'] ?>', '<?= $_SESSION['admin_message_type'] ?? 'success' ?>');
</script>
<?php
    unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);
endif;
?>

<style>

:root {
    --gold: #c8a656;
    --gold-light: #e8c96a;
    --gold-dark: #a68a44;
    --dark: #1a1a1a;
    --darker: #0f0f0f;
    --gray: #2a2a2a;
    --gray-light: #3a3a3a;
    --text: #ffffff;
    --text-muted: #999999;
    --success: #2ecc71;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
    --shadow: 0 4px 20px rgba(0,0,0,0.3);
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

.admin-sidebar {
    width: 240px;
    background: var(--darker);
    border-right: 1px solid rgba(200,166,86,0.15);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
}

.sidebar-header {
    padding: 15px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 16px;
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
    text-decoration: none;
    transition: 0.2s;
    border-left: 3px solid transparent;
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

.admin-main {
    flex: 1;
    margin-left: 240px;
    padding: 30px;
}

/* ===== PAGE HEADER ===== */
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
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header h1 i { color: var(--gold); }
.page-header p { color: var(--text-muted); font-size: 14px; }

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

/* ===== FORMS ===== */
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 11px;
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
    background: var(--gray-light);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: var(--text);
    font-size: 13px;
    font-family: inherit;
    transition: 0.2s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,166,86,0.2);
}
.form-group textarea { resize: vertical; min-height: 80px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-group small { display: block; margin-top: 5px; color: var(--text-muted); font-size: 11px; }

/* ===== BUTTONS ===== */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}
.btn i { font-size: 13px; }
.btn-primary {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #1a1a1a;
    box-shadow: 0 4px 15px rgba(200,166,86,0.3);
}
.btn-primary:hover {
    background: linear-gradient(135deg, var(--gold-light), var(--gold));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(200,166,86,0.4);
}
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #27ae60; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-outline {
    background: transparent;
    border: 2px solid rgba(255,255,255,0.2);
    color: var(--text);
}
.btn-outline:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(200,166,86,0.1);
}
.btn-sm { padding: 5px 10px; font-size: 11px; }
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    justify-content: center;
    border-radius: 8px;
}
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }

/* ===== TABLES ===== */
.table-responsive { overflow-x: auto; }
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--gray);
    border-radius: 12px;
    overflow: hidden;
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
.data-table th i { margin-right: 5px; }
.data-table tr:hover { background: rgba(200,166,86,0.08); }
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
.status-approved { background: rgba(46,204,113,0.2); color: var(--success); }
.status-cancelled { background: rgba(231,76,60,0.2); color: var(--danger); }
.status-pending { background: rgba(243,156,18,0.2); color: var(--warning); }

/* ===== TOAST NOTIFICATIONS ===== */
#admin-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 14px 22px;
    background: var(--gray);
    border-left: 4px solid var(--success);
    color: var(--text);
    border-radius: 8px;
    box-shadow: var(--shadow);
    z-index: 9999;
    transform: translateX(400px);
    transition: transform 0.25s;
    font-size: 13px;
    font-weight: 500;
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
    .admin-main { margin-left: 70px; }
}

@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .admin-main { padding: 20px; }
    .data-table { font-size: 12px; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
