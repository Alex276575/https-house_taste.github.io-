<?php

$pageTitle = 'Управление категориями';
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

            // Проверяем, есть ли подкатегории
            $checkSub = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $checkSub->execute([$id]);
            if ($checkSub->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'Нельзя удалить: есть подкатегории']);
                exit;
            }

            // Проверяем, есть ли товары в категории
            $checkProd = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $checkProd->execute([$id]);
            if ($checkProd->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'Нельзя удалить: есть товары']);
                exit;
            }

            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // СОХРАНЕНИЕ (добавление/редактирование)
    if ($_POST['action'] === 'save') {
        $errors = [];
        $name = trim($_POST['name'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $icon_class = trim($_POST['icon_class'] ?? '');

        // === ВАЛИДАЦИЯ ===
        // Название: 2-50 символов, обязательно
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Минимум 2 символа';
        } elseif (mb_strlen($name) > 50) {
            $errors['name'] = 'Максимум 50 символов';
        }

        // Родительская категория: если указана, должна существовать и не быть самой собой
        if ($parent_id) {
            if ($parent_id == ($_POST['category_id'] ?? 0)) {
                $errors['parent_id'] = 'Категория не может быть своей подкатегорией';
            } else {
                $checkParent = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
                $checkParent->execute([$parent_id]);
                if (!$checkParent->fetch()) {
                    $errors['parent_id'] = 'Неверная родительская категория';
                }
            }
        }

        // Порядок сортировки: 0-999
        if ($sort_order < 0 || $sort_order > 999) {
            $errors['sort_order'] = 'От 0 до 999';
        }

        // Иконка: только классы Font Awesome
        if ($icon_class && !preg_match('/^fas\s+fa-[\w\-]+$/', $icon_class)) {
            $errors['icon_class'] = 'Формат: "fas fa-utensils"';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        try {
            if (!empty($_POST['category_id']) && $_POST['category_id'] > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE categories SET
                    name = ?, parent_id = ?, sort_order = ?, icon_class = ?
                    WHERE id = ?");
                $stmt->execute([$name, $parent_id, $sort_order, $icon_class, $_POST['category_id']]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO categories
                    (name, parent_id, sort_order, icon_class)
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $parent_id, $sort_order, $icon_class]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ПОЛУЧЕНИЕ ДАННЫХ КАТЕГОРИИ
    if ($_POST['action'] === 'get_category' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $category = $stmt->fetch();
        if ($category) {
            echo json_encode(['success' => true, 'data' => $category]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Категория не найдена']);
        }
        exit;
    }
}

// === ПОЛУЧЕНИЕ ВСЕХ КАТЕГОРИЙ С ИЕРАРХИЕЙ ===
$allCategories = $pdo->query("SELECT * FROM categories ORDER BY sort_order, name")->fetchAll();

// Функция для построения дерева
function buildCategoryTree($categories, $parentId = null, $level = 0) {
    $result = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $cat['level'] = $level;
            $cat['children'] = buildCategoryTree($categories, $cat['id'], $level + 1);
            $result[] = $cat;
        }
    }
    return $result;
}

$categoryTree = buildCategoryTree($allCategories);

// === ТОП-УРОВЕНЬ ДЛЯ ВЫБОРА РОДИТЕЛЯ ===
$topCategories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-folder-tree"></i> Управление категориями</h1>
            <p>Создание и редактирование структуры меню</p>
        </div>

        <!-- Кнопки действий -->
        <div class="action-bar">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Добавить категорию
            </button>
            <a href="products.php" class="btn btn-outline">
                <i class="fas fa-utensils"></i> К товарам
            </a>
        </div>

        <!-- Таблица категорий -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-tag"></i> Название</th>
                            <th><i class="fas fa-sitemap"></i> Родитель</th>
                            <th><i class="fas fa-icons"></i> Иконка</th>
                            <th><i class="fas fa-sort-numeric-down"></i> Порядок</th>
                            <th><i class="fas fa-cubes"></i> Товаров</th>
                            <th><i class="fas fa-cogs"></i> Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryTree as $cat): ?>
                            <?= renderCategoryRow($cat, $pdo) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($allCategories)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>Категории не созданы</p>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Создать первую категорию
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- МОДАЛЬНОЕ ОКНО -->
<div id="categoryModal" class="modal" style="display:none">
    <div class="modal-content" style="max-width:550px">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="modalTitle">Добавить категорию</span></h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="categoryForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" id="category_id">

            <div class="form-group">
                <label><i class="fas fa-tag"></i> Название *</label>
                <input type="text" name="name" id="name" maxlength="50" required placeholder="Введите название">
                <span class="error" id="error_name"></span>
            </div>

            <div class="form-group">
                <label><i class="fas fa-folder-plus"></i> Родительская категория</label>
                <select name="parent_id" id="parent_id">
                    <option value="">Без родителя (верхний уровень)</option>
                    <?php foreach ($topCategories as $top): ?>
                        <option value="<?= $top['id'] ?>"><?= htmlspecialchars($top['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="error" id="error_parent_id"></span>
                <small class="hint">Оставьте пустым для категории верхнего уровня</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down"></i> Порядок</label>
                    <input type="number" name="sort_order" id="sort_order" min="0" max="999" value="0">
                    <span class="error" id="error_sort_order"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-icons"></i> Иконка (Font Awesome)</label>
                    <input type="text" name="icon_class" id="icon_class" maxlength="50" placeholder="fas fa-utensils">
                    <span class="error" id="error_icon_class"></span>
                    <small class="hint"><a href="https://fontawesome.com/icons" target="_blank">Найти иконку ↗</a></small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">
                    <i class="fas fa-times"></i> Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> <span>Сохранить</span>
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
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:var(--gold)}
.page-header p{color:var(--text-muted);font-size:14px}

/* ACTION BAR */
.action-bar{display:flex;gap:10px;margin-bottom:20px}

/* CARDS */
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}

/* TABLE */
.table-responsive{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table th i{margin-right:5px}
.data-table tr:hover{background:rgba(200,166,86,0.08)}
.data-table .sub-row{background:rgba(0,0,0,0.1)}
.data-table .sub-row td{padding-left:45px}
.data-table .sub-row .sub-row td{padding-left:75px}
.data-table .category-name{display:flex;align-items:center;gap:8px;font-weight:500}
.data-table .category-name i{color:var(--gold);font-size:14px}
.data-table .category-name .level-indicator{color:var(--text-muted);font-size:12px}

/* BUTTONS */
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn i{font-size:13px}
.btn-primary{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1a1a;box-shadow:0 4px 15px rgba(200,166,86,0.3)}
.btn-primary:hover{background:linear-gradient(135deg,var(--gold-light),var(--gold));transform:translateY(-2px);box-shadow:0 6px 20px rgba(200,166,86,0.4)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold);background:rgba(200,166,86,0.1)}
.btn-warning{background:var(--warning);color:#1a1a1a}
.btn-warning:hover{background:#e67e22;transform:translateY(-2px)}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{background:#c0392b;transform:translateY(-2px)}
.btn-sm{padding:6px 12px;font-size:11px;border-radius:6px}
.btn-icon{width:36px;height:36px;padding:0;justify-content:center;border-radius:8px}
.btn:disabled{opacity:0.6;cursor:not-allowed;transform:none !important}

/* FORMS */
.form-group{margin-bottom:20px}
.form-group label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group label i{color:var(--gold);font-size:12px}
.form-group input,.form-group select{width:100%;padding:12px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit;transition:0.2s}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group .error{color:var(--danger);font-size:11px;margin-top:5px;display:flex;align-items:center;gap:4px}
.form-group .error i{font-size:10px}
.form-group .hint{display:block;font-size:11px;color:var(--text-muted);margin-top:5px}
.form-group .hint a{color:var(--gold);text-decoration:none}
.form-group .hint a:hover{text-decoration:underline}

/* MODAL */
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px;animation:fadeIn 0.2s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-content{background:var(--gray);border-radius:16px;padding:24px;width:100%;max-width:550px;max-height:92vh;overflow-y:auto;border:1px solid rgba(255,255,255,0.1);animation:slideIn 0.25s;box-shadow:var(--shadow)}
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
@media(max-width:768px){.form-row{grid-template-columns:1fr}.action-bar{flex-direction:column}.admin-main{padding:20px}.data-table{font-size:12px}.modal-content{max-width:100%;margin:10px}}
</style>

<!-- СКРИПТЫ -->
<script>
// Открытие модального окна
function openModal(categoryId = null) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const title = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('saveBtn');

    form.reset();
    document.querySelectorAll('.error').forEach(el => el.textContent = '');
    document.getElementById('sort_order').value = '0';

    if (categoryId) {
        // Режим редактирования
        title.innerHTML = '<i class="fas fa-pen"></i> Редактировать категорию';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Обновить</span>';
        document.getElementById('category_id').value = categoryId;
        saveBtn.disabled = true;

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_category&id=' + categoryId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const c = data.data;
                document.getElementById('name').value = c.name || '';
                document.getElementById('parent_id').value = c.parent_id || '';
                document.getElementById('sort_order').value = c.sort_order || 0;
                document.getElementById('icon_class').value = c.icon_class || '';
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка загрузки', 'error'))
        .finally(() => saveBtn.disabled = false);
    } else {
        // Режим добавления
        title.innerHTML = '<i class="fas fa-plus-circle"></i> Добавить категорию';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Добавить</span>';
        document.getElementById('category_id').value = '';
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

//  Закрытие модального окна
function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Подтверждение удаления
function confirmDelete(id, name) {
    if (confirm(`Удалить категорию "${name}"?\n\n⚠️ Это действие нельзя отменить!`)) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('<i class="fas fa-check-circle"></i> Категория удалена', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Ошибка'), 'error');
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка соединения', 'error'));
    }
}

// Отправка формы
document.getElementById('categoryForm').addEventListener('submit', function(e) {
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
                (document.getElementById('category_id').value ? 'Категория обновлена' : 'Категория добавлена'), 'success');
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
        const isEdit = document.getElementById('category_id').value;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>' + (isEdit ? 'Обновить' : 'Добавить') + '</span>';
    });
});

// Закрытие по клику вне
document.getElementById('categoryModal').addEventListener('click', function(e) {
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

<?php
// === ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ДЛЯ ОТРИСОВКИ СТРОК ===
function renderCategoryRow($cat, $pdo, $level = 0) {
    $indent = str_repeat('— ', $level);
    $icon = $cat['icon_class'] ? '<i class="'.htmlspecialchars($cat['icon_class']).'"></i>' : '<i class="fas fa-folder" style="color:var(--gold)"></i>';

    // Считаем товары в категории
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$cat['id']]);
    $productCount = $stmt->fetchColumn();

    $html = '<tr'.($level > 0 ? ' class="sub-row"' : '').'>';
    $html .= '<td><strong>#'.$cat['id'].'</strong></td>';
    $html .= '<td><div class="category-name">'.$icon.' '.str_repeat('<span class="level-indicator">—</span>', $level).htmlspecialchars($cat['name']).'</div></td>';
    $html .= '<td>'.($cat['parent_id'] ? 'Подкатегория' : '<span style="color:var(--gold)">Верхний уровень</span>').'</td>';
    $html .= '<td>'.($cat['icon_class'] ? '<code style="font-size:10px">'.htmlspecialchars($cat['icon_class']).'</code>' : '<span style="color:var(--text-muted)">—</span>').'</td>';
    $html .= '<td>'.$cat['sort_order'].'</td>';
    $html .= '<td>'.($productCount > 0 ? '<span style="color:var(--gold)">'.$productCount.' шт.</span>' : '<span style="color:var(--text-muted)">0</span>').'</td>';
    $html .= '<td class="actions-cell">';
    $html .= '<button class="btn btn-primary btn-sm btn-icon" onclick="openModal('.$cat['id'].')" title="Редактировать"><i class="fas fa-pen"></i></button>';
    $html .= '<button class="btn btn-danger btn-sm btn-icon" onclick="confirmDelete('.$cat['id'].', \''.addslashes($cat['name']).'\')" title="Удалить"><i class="fas fa-trash"></i></button>';
    $html .= '</td></tr>';

    // Рекурсивно добавляем подкатегории
    if (!empty($cat['children'])) {
        foreach ($cat['children'] as $child) {
            $html .= renderCategoryRow($child, $pdo, $level + 1);
        }
    }

    return $html;
}
require_once __DIR__ . '/../includes/footer.php';
?>
