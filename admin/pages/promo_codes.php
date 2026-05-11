<?php

$pageTitle = 'Управление промокодами';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === ПРОВЕРКА ПРАВ ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php');
    exit;
}

$message = '';
$messageType = '';
$errors = [];

// === ОБРАБОТКА ДЕЙСТВИЙ (AJAX) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // УДАЛЕНИЕ
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        try {
            $id = (int)$_POST['id'];
            $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // СОХРАНЕНИЕ (добавление/редактирование)
    if ($_POST['action'] === 'save') {
        $errors = [];
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discount_type = $_POST['discount_type'] ?? 'percent';
        $discount_value = (float)str_replace(',', '.', $_POST['discount_value'] ?? 0);
        $min_order_amount = (float)str_replace(',', '.', $_POST['min_order_amount'] ?? 0);
        $is_first_order_only = isset($_POST['is_first_order_only']) ? 1 : 0;
        $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
        $valid_to = $_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days'));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');

        // === ВАЛИДАЦИЯ ===
        // Код: 3-20 символов, только буквы, цифры, дефис
        if (!preg_match('/^[A-Z0-9\-]{3,20}$/', $code)) {
            $errors['code'] = '3-20 символов: A-Z, 0-9, -';
        }

        // Проверка уникальности кода (если не редактируем тот же)
        $checkCode = $pdo->prepare("SELECT id FROM promo_codes WHERE code = ? AND id != ?");
        $checkCode->execute([$code, $_POST['promo_id'] ?? 0]);
        if ($checkCode->fetch()) {
            $errors['code'] = 'Такой промокод уже существует';
        }

        // Тип скидки
        if (!in_array($discount_type, ['percent', 'fixed'])) {
            $errors['discount_type'] = 'Неверный тип';
        }

        // Значение скидки
        if ($discount_type === 'percent') {
            if ($discount_value < 1 || $discount_value > 100) {
                $errors['discount_value'] = '1-100%';
            }
        } else {
            if ($discount_value < 1 || $discount_value > 999999.99) {
                $errors['discount_value'] = '1 - 999 999.99 ₽';
            }
        }

        // Минимальная сумма заказа
        if ($min_order_amount < 0) {
            $errors['min_order_amount'] = 'Не может быть отрицательной';
        }

        // Максимальное использование
        if ($max_uses !== null && $max_uses < 1) {
            $errors['max_uses'] = 'Минимум 1 или оставьте пустым';
        }

        // Даты действия
        if (empty($valid_from) || empty($valid_to)) {
            $errors['valid_from'] = 'Обе даты обязательны';
        } elseif ($valid_from > $valid_to) {
            $errors['valid_to'] = 'Дата окончания должна быть позже';
        }

        // Описание: максимум 255 символов
        if ($description && mb_strlen($description) > 255) {
            $errors['description'] = 'Максимум 255 символов';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        try {
            if (!empty($_POST['promo_id']) && $_POST['promo_id'] > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE promo_codes SET
                    code = ?, discount_type = ?, discount_value = ?,
                    min_order_amount = ?, is_first_order_only = ?,
                    max_uses = ?, valid_from = ?, valid_to = ?,
                    is_active = ?, description = ?
                    WHERE id = ?");
                $stmt->execute([
                    $code, $discount_type, $discount_value,
                    $min_order_amount, $is_first_order_only,
                    $max_uses, $valid_from, $valid_to,
                    $is_active, $description,
                    $_POST['promo_id']
                ]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO promo_codes
                    (code, discount_type, discount_value, min_order_amount,
                     is_first_order_only, max_uses, current_uses,
                     valid_from, valid_to, is_active, description)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
                $stmt->execute([
                    $code, $discount_type, $discount_value,
                    $min_order_amount, $is_first_order_only,
                    $max_uses, $valid_from, $valid_to,
                    $is_active, $description
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 📥 ПОЛУЧЕНИЕ ДАННЫХ ПРОМОКОДА
    if ($_POST['action'] === 'get_promo' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $promo = $stmt->fetch();
        if ($promo) {
            echo json_encode(['success' => true, 'data' => $promo]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Промокод не найден']);
        }
        exit;
    }
}

// === ФИЛЬТРАЦИЯ И ПОИСК ===
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

$sql = "SELECT * FROM promo_codes WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (code LIKE ? OR description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($statusFilter === 'active') {
    $sql .= " AND is_active = 1 AND valid_to >= CURDATE()";
} elseif ($statusFilter === 'expired') {
    $sql .= " AND (is_active = 0 OR valid_to < CURDATE())";
}

$sql .= " ORDER BY valid_to DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$promos = $stmt->fetchAll();

// Статистика
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM promo_codes")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM promo_codes WHERE is_active=1 AND valid_to>=CURDATE()")->fetchColumn(),
    'expired' => $pdo->query("SELECT COUNT(*) FROM promo_codes WHERE is_active=0 OR valid_to<CURDATE()")->fetchColumn(),
    'used' => $pdo->query("SELECT COALESCE(SUM(current_uses),0) FROM promo_codes")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i> Управление промокодами</h1>
            <p>Создание и управление скидочными кодами</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-tags"></i>
                <div>
                    <span class="stat-num"><?= $stats['total'] ?></span>
                    <span class="stat-label">Всего промокодов</span>
                </div>
            </div>
            <div class="stat-card success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <span class="stat-num"><?= $stats['active'] ?></span>
                    <span class="stat-label">Активных</span>
                </div>
            </div>
            <div class="stat-card danger">
                <i class="fas fa-clock"></i>
                <div>
                    <span class="stat-num"><?= $stats['expired'] ?></span>
                    <span class="stat-label">Истекло</span>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-percentage"></i>
                <div>
                    <span class="stat-num"><?= $stats['used'] ?></span>
                    <span class="stat-label">Использовано</span>
                </div>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div class="action-bar">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Создать промокод
            </button>
            <a href="orders.php" class="btn btn-outline">
                <i class="fas fa-receipt"></i> К заказам
            </a>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <div class="filter-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Поиск по коду или описанию..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Все статусы</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Активные</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Истекшие</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Найти</button>
                <a href="promo_codes.php" class="btn btn-outline"><i class="fas fa-undo"></i> Сброс</a>
            </form>
        </div>

        <!-- Таблица промокодов -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> Код</th>
                            <th><i class="fas fa-gift"></i> Скидка</th>
                            <th><i class="fas fa-ruble-sign"></i> Мин. заказ</th>
                            <th><i class="fas fa-calendar-range"></i> Срок действия</th>
                            <th><i class="fas fa-chart-line"></i> Использовано</th>
                            <th><i class="fas fa-toggle-on"></i> Статус</th>
                            <th><i class="fas fa-cogs"></i> Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promos as $promo):
                            $isActive = $promo['is_active'] && $promo['valid_to'] >= date('Y-m-d');
                            $discountLabel = $promo['discount_type'] === 'percent'
                                ? $promo['discount_value'] . '%'
                                : number_format($promo['discount_value'], 0, '.', ' ') . ' ₽';
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--gold)"><?= htmlspecialchars($promo['code']) ?></strong>
                                <?php if($promo['is_first_order_only']): ?>
                                    <br><small class="badge badge-info">Только 1-й заказ</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $promo['discount_type'] === 'percent' ? 'warning' : 'success' ?>">
                                    <?= $discountLabel ?>
                                </span>
                            </td>
                            <td>
                                <?= $promo['min_order_amount'] > 0 ? number_format($promo['min_order_amount'], 0, '.', ' ') . ' ₽' : '—' ?>
                            </td>
                            <td>
                                <small>
                                    <?= date('d.m.Y', strtotime($promo['valid_from'])) ?> —
                                    <?= date('d.m.Y', strtotime($promo['valid_to'])) ?>
                                </small>
                                <?php if(!$isActive): ?>
                                    <br><span class="badge badge-danger" style="font-size:9px">Истёк</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $promo['current_uses'] ?> /
                                <?= $promo['max_uses'] ?? '∞' ?>
                                <?php if($promo['max_uses'] && $promo['current_uses'] >= $promo['max_uses']): ?>
                                    <br><span class="badge badge-danger" style="font-size:9px">Лимит исчерпан</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $isActive ? 'success' : 'danger' ?>">
                                    <?= $isActive ? 'Активен' : 'Неактивен' ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <button class="btn btn-primary btn-sm btn-icon" onclick="openModal(<?= $promo['id'] ?>)" title="Редактировать">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-icon" onclick="confirmDelete(<?= $promo['id'] ?>, '<?= addslashes($promo['code']) ?>')" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($promos)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>Промокоды не найдены</p>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Создать первый промокод
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- МОДАЛЬНОЕ ОКНО -->
<div id="promoModal" class="modal" style="display:none">
    <div class="modal-content" style="max-width:650px">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="modalTitle">Создать промокод</span></h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="promoForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="promo_id" id="promo_id">

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Код промокода *</label>
                    <input type="text" name="code" id="code" maxlength="20" required
                           placeholder="WELCOME2026" style="text-transform:uppercase"
                           oninput="this.value = this.value.toUpperCase()">
                    <span class="error" id="error_code"></span>
                    <small class="hint">Только A-Z, 0-9, дефис • 3-20 символов</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-gift"></i> Тип скидки *</label>
                    <select name="discount_type" id="discount_type" required>
                        <option value="percent">Процент (%)</option>
                        <option value="fixed">Фиксированная сумма (₽)</option>
                    </select>
                    <span class="error" id="error_discount_type"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-percentage"></i> Значение скидки *</label>
                    <div class="input-with-suffix">
                        <input type="number" step="0.01" name="discount_value" id="discount_value" min="0.01" max="999999.99" required placeholder="10">
                        <span class="suffix" id="discountSuffix">%</span>
                    </div>
                    <span class="error" id="error_discount_value"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-ruble-sign"></i> Мин. сумма заказа</label>
                    <input type="number" step="0.01" name="min_order_amount" id="min_order_amount" min="0" placeholder="0">
                    <span class="error" id="error_min_order_amount"></span>
                    <small class="hint">0 = без ограничений</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar-start"></i> Дата начала *</label>
                    <input type="date" name="valid_from" id="valid_from" required>
                    <span class="error" id="error_valid_from"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-end"></i> Дата окончания *</label>
                    <input type="date" name="valid_to" id="valid_to" required>
                    <span class="error" id="error_valid_to"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-infinity"></i> Макс. использований</label>
                    <input type="number" name="max_uses" id="max_uses" min="1" placeholder="Оставьте пустым для безлимита">
                    <span class="error" id="error_max_uses"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-check"></i> Ограничения</label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_first_order_only" id="is_first_order_only" value="1">
                        <i class="fas fa-lock"></i> Только для первого заказа
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> Описание</label>
                <textarea name="description" id="description" maxlength="255" rows="2" placeholder="Описание акции для админки/пользователя"></textarea>
                <small class="hint"><span id="descCount">0</span>/255 символов</small>
                <span class="error" id="error_description"></span>
            </div>

            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                    <i class="fas fa-toggle-on"></i> Промокод активен
                </label>
                <small class="hint">Снимите галочку, чтобы временно отключить</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">
                    <i class="fas fa-times"></i> Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> <span>Создать</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- СТИЛИ -->
<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--gold-dark:#a68a44;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--gray-light:#3a3a3a;--text:#fff;--text-muted:#999999;--success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;--info:#3498db;--shadow:0 4px 20px rgba(0,0,0,0.3)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);line-height:1.6}

/* LAYOUT */
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

/* PAGE HEADER */
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:var(--gold)}
.page-header p{color:var(--text-muted);font-size:14px}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--gray);padding:20px;border-radius:12px;display:flex;align-items:center;gap:15px;border:1px solid rgba(255,255,255,0.05);transition:0.3s}
.stat-card:hover{border-color:var(--gold);transform:translateY(-2px)}
.stat-card i{font-size:28px;color:var(--gold);width:40px;text-align:center}
.stat-card.warning i{color:var(--warning)}.stat-card.danger i{color:var(--danger)}.stat-card.success i{color:var(--success)}
.stat-num{font-size:24px;font-weight:700;display:block}.stat-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}

/* ACTION BAR */
.action-bar{display:flex;gap:10px;margin-bottom:20px}

/* CARDS */
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}

/* FILTERS */
.filters-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.filter-group{position:relative;display:flex;align-items:center}
.filter-group i{position:absolute;left:12px;color:var(--text-muted);font-size:12px}
.filter-group input{padding-left:32px}
.filters-bar input,.filters-bar select{padding:10px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;min-width:180px;transition:0.2s}
.filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}

/* TABLE */
.table-responsive{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table th i{margin-right:5px}
.data-table tr:hover{background:rgba(200,166,86,0.08)}
.actions-cell{display:flex;gap:6px}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
.badge-success{background:rgba(46,204,113,0.15);color:var(--success)}.badge-danger{background:rgba(231,76,60,0.15);color:var(--danger)}.badge-warning{background:rgba(243,156,18,0.15);color:var(--warning)}.badge-info{background:rgba(52,152,219,0.15);color:var(--info)}

/* BUTTONS */
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn i{font-size:13px}
.btn-primary{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1a1a;box-shadow:0 4px 15px rgba(200,166,86,0.3)}
.btn-primary:hover{background:linear-gradient(135deg,var(--gold-light),var(--gold));transform:translateY(-2px);box-shadow:0 6px 20px rgba(200,166,86,0.4)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold);background:rgba(200,166,86,0.1)}
.btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#c0392b;transform:translateY(-2px)}
.btn-sm{padding:6px 12px;font-size:11px;border-radius:6px}
.btn-icon{width:36px;height:36px;padding:0;justify-content:center;border-radius:8px}
.btn:disabled{opacity:0.6;cursor:not-allowed;transform:none !important}

/* FORMS */
.form-group{margin-bottom:18px}
.form-group label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group label i{color:var(--gold);font-size:12px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit;transition:0.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}
.form-group textarea{resize:vertical;min-height:75px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group .error{color:var(--danger);font-size:11px;margin-top:5px;display:flex;align-items:center;gap:4px}
.form-group .error i{font-size:10px}
.form-group .hint{display:block;font-size:11px;color:var(--text-muted);margin-top:5px}
.checkbox-group label{display:flex;align-items:center;gap:8px;color:var(--text);text-transform:none;letter-spacing:0;font-size:13px;cursor:pointer}
.checkbox-group input[type="checkbox"]{width:18px;height:18px;accent-color:var(--gold);cursor:pointer;margin:0}
.checkbox-label{display:flex;align-items:center;gap:8px;color:var(--text);font-size:13px;cursor:pointer}
.checkbox-label input[type="checkbox"]{width:18px;height:18px;accent-color:var(--gold);cursor:pointer;margin:0}

/* INPUT WITH SUFFIX */
.input-with-suffix{position:relative}
.input-with-suffix .suffix{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;font-weight:600}
.input-with-suffix input{padding-right:35px}

/* MODAL */
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px;animation:fadeIn 0.2s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-content{background:var(--gray);border-radius:16px;padding:24px;width:100%;max-width:650px;max-height:92vh;overflow-y:auto;border:1px solid rgba(255,255,255,0.1);animation:slideIn 0.25s;box-shadow:var(--shadow)}
@keyframes slideIn{from{transform:translateY(-30px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,0.1)}
.modal-header h3{font-size:17px;font-weight:600;display:flex;align-items:center;gap:8px}
.modal-header h3 i{color:var(--gold)}
.modal-close{background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;transition:0.2s;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%}
.modal-close:hover{background:rgba(255,255,255,0.1);color:var(--danger)}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1)}

/* EMPTY STATE */
.empty-state{text-align:center;padding:60px 20px;color:var(--text-muted)}
.empty-state i{font-size:48px;margin-bottom:16px;opacity:0.3;color:var(--gold)}
.empty-state p{margin-bottom:20px;font-size:14px}

/* TOAST */
#admin-toast{position:fixed;bottom:30px;right:30px;padding:14px 22px;background:var(--gray);border-left:4px solid var(--success);color:var(--text);border-radius:8px;box-shadow:var(--shadow);z-index:9999;transform:translateX(400px);transition:transform 0.25s;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px}
#admin-toast.show{transform:translateX(0)}
#admin-toast.error{border-left-color:var(--danger)}
#admin-toast.warning{border-left-color:var(--warning)}
#admin-toast i{font-size:16px}

/* RESPONSIVE */
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.action-bar{flex-direction:column}.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}.data-table{font-size:12px}.modal-content{max-width:100%;margin:10px}}
</style>

<!-- СКРИПТЫ -->
<script>
// Открытие модального окна
function openModal(promoId = null) {
    const modal = document.getElementById('promoModal');
    const form = document.getElementById('promoForm');
    const title = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('saveBtn');
    const discountType = document.getElementById('discount_type');
    const discountSuffix = document.getElementById('discountSuffix');

    form.reset();
    document.querySelectorAll('.error').forEach(el => el.textContent = '');
    document.getElementById('descCount').textContent = '0';

    // Устанавливаем даты по умолчанию
    document.getElementById('valid_from').value = new Date().toISOString().split('T')[0];
    document.getElementById('valid_to').value = new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0];

    // Обработчик смены типа скидки
    discountType.onchange = function() {
        discountSuffix.textContent = this.value === 'percent' ? '%' : '₽';
        const input = document.getElementById('discount_value');
        if(this.value === 'percent') {
            input.max = '100';
            input.placeholder = '10';
        } else {
            input.max = '999999.99';
            input.placeholder = '100';
        }
    };

    if (promoId) {
        // Режим редактирования
        title.innerHTML = '<i class="fas fa-pen"></i> Редактировать промокод';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Обновить</span>';
        document.getElementById('promo_id').value = promoId;
        saveBtn.disabled = true;

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_promo&id=' + promoId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const p = data.data;
                document.getElementById('code').value = p.code || '';
                document.getElementById('discount_type').value = p.discount_type || 'percent';
                document.getElementById('discount_value').value = p.discount_value || '';
                document.getElementById('min_order_amount').value = p.min_order_amount || '';
                document.getElementById('is_first_order_only').checked = p.is_first_order_only == 1;
                document.getElementById('max_uses').value = p.max_uses || '';
                document.getElementById('valid_from').value = p.valid_from || '';
                document.getElementById('valid_to').value = p.valid_to || '';
                document.getElementById('is_active').checked = p.is_active == 1;
                document.getElementById('description').value = p.description || '';

                // Обновляем суффикс
                discountSuffix.textContent = p.discount_type === 'percent' ? '%' : '₽';
                document.getElementById('descCount').textContent = (p.description || '').length;
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка загрузки', 'error'))
        .finally(() => saveBtn.disabled = false);
    } else {
        // Режим добавления
        title.innerHTML = '<i class="fas fa-plus-circle"></i> Создать промокод';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Создать</span>';
        document.getElementById('promo_id').value = '';
        document.getElementById('is_active').checked = true;
        discountSuffix.textContent = '%';
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Закрытие модального окна
function closeModal() {
    document.getElementById('promoModal').style.display = 'none';
    document.body.style.overflow = '';
}

//  Подтверждение удаления
function confirmDelete(id, code) {
    if (confirm(`❗ Удалить промокод "${code}"?\n\n⚠️ Это действие нельзя отменить!`)) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('<i class="fas fa-check-circle"></i> Промокод удалён', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Ошибка'), 'error');
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка соединения', 'error'));
    }
}

//  Отправка формы
document.getElementById('promoForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveBtn');

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Сохранение...</span>';
    document.querySelectorAll('.error').forEach(el => el.textContent = '');

    const formData = new FormData(this);

    fetch('', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('<i class="fas fa-check-circle"></i> ' +
                (document.getElementById('promo_id').value ? 'Промокод обновлён' : 'Промокод создан'), 'success');
            setTimeout(() => location.reload(), 1000);
        } else if (data.errors) {
            Object.keys(data.errors).forEach(field => {
                const errorEl = document.getElementById('error_' + field);
                if (errorEl) errorEl.textContent = data.errors[field];
            });
            showToast('<i class="fas fa-exclamation-triangle"></i> Исправьте ошибки', 'warning');
        } else {
            showToast('<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Ошибка'), 'error');
        }
    })
    .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка соединения', 'error'))
    .finally(() => {
        saveBtn.disabled = false;
        const isEdit = document.getElementById('promo_id').value;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>' + (isEdit ? 'Обновить' : 'Создать') + '</span>';
    });
});

// Счётчик символов описания
document.getElementById('description')?.addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});

// Закрытие по клику вне
document.getElementById('promoModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ⌨️ Закрытие по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Toast уведомления
function showToast(html, type) {
    const existing = document.getElementById('admin-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'admin-toast';
    toast.className = type;
    toast.innerHTML = html;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

<?php if(isset($_SESSION['admin_message'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('<?= addslashes($_SESSION['admin_message']) ?>', '<?= $_SESSION['admin_message_type'] ?? 'success' ?>');
});
<?php unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
