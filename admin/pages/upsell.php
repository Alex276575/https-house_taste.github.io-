<?php

$pageTitle = 'Дополнительные товары (Upsell)';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === ПРОВЕРКА ПРАВ ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php');
    exit;
}

$message = '';
$messageType = '';

// === ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ДЛЯ ПУТЕЙ ===
function assetUrl($path) {
    if (empty($path)) return '/house_of_taste/public/img/placeholder.png';
    return strpos($path, '/house_of_taste') === 0 ? $path : '/house_of_taste' . ltrim($path, '/');
}

// === ОБРАБОТКА ДЕЙСТВИЙ (AJAX) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // УДАЛЕНИЕ
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        try {
            $id = (int)$_POST['id'];

            // Получаем путь к фото для удаления файла
            $stmt = $pdo->prepare("SELECT image_url FROM upsell_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if ($item && $item['image_url']) {
                $filePath = __DIR__ . '/../' . ltrim($item['image_url'], '/');
                if (file_exists($filePath)) @unlink($filePath);
            }

            $pdo->prepare("DELETE FROM upsell_items WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    //  СОХРАНЕНИЕ (добавление/редактирование)
    if ($_POST['action'] === 'save') {
        $errors = [];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)str_replace(',', '.', $_POST['price'] ?? 0);
        $category = $_POST['category'] ?? 'accessories';
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $icon_class = trim($_POST['icon_class'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_url = '';

        // Если редактируем, получаем текущее фото
        if (!empty($_POST['item_id']) && $_POST['item_id'] > 0) {
            $stmt = $pdo->prepare("SELECT image_url FROM upsell_items WHERE id = ?");
            $stmt->execute([(int)$_POST['item_id']]);
            $currentItem = $stmt->fetch();
            $image_url = $currentItem ? $currentItem['image_url'] : '';
        }

        // === ВАЛИДАЦИЯ ===
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = '2-100 символов';
        }

        if ($price < 0 || $price > 999999.99) {
            $errors['price'] = '0 - 999 999.99 ₽';
        }

        $validCategories = ['accessories', 'food', 'souvenir', 'recommended'];
        if (!in_array($category, $validCategories)) {
            $errors['category'] = 'Неверная категория';
        }

        if ($sort_order < 0 || $sort_order > 999) {
            $errors['sort_order'] = '0 - 999';
        }

        if ($description && mb_strlen($description) > 255) {
            $errors['description'] = 'Максимум 255 символов';
        }

        // Иконка: только классы Font Awesome
        if ($icon_class && !preg_match('/^fas\s+fa-[\w\-]+$/', $icon_class)) {
            $errors['icon_class'] = 'Формат: "fas fa-gift"';
        }

        // Загрузка изображения
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $errors['image'] = 'JPG/PNG/GIF/WEBP';
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $errors['image'] = 'Максимум 2 МБ';
            } else {
                $filename = 'upsell_' . uniqid() . '.' . $ext;
                $uploadDir = __DIR__ . '/../public/img/upsell/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $uploadPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    // Удаляем старое фото если было
                    if ($currentItem && $currentItem['image_url']) {
                        $oldPath = __DIR__ . '/../' . ltrim($currentItem['image_url'], '/');
                        if (file_exists($oldPath) && $oldPath !== $uploadPath) {
                            @unlink($oldPath);
                        }
                    }
                    $image_url = '/public/img/upsell/' . $filename;
                } else {
                    $errors['image'] = 'Ошибка сохранения файла';
                }
            }
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        try {
            if (!empty($_POST['item_id']) && $_POST['item_id'] > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE upsell_items SET
                    name = ?, description = ?, price = ?, category = ?,
                    sort_order = ?, icon_class = ?, is_active = ?, image_url = ?
                    WHERE id = ?");
                $stmt->execute([
                    $name, $description, $price, $category,
                    $sort_order, $icon_class, $is_active, $image_url,
                    $_POST['item_id']
                ]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO upsell_items
                    (name, description, price, category, sort_order, icon_class, is_active, image_url, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $name, $description, $price, $category,
                    $sort_order, $icon_class, $is_active, $image_url
                ]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ПОЛУЧЕНИЕ ДАННЫХ ТОВАРА
    if ($_POST['action'] === 'get_item' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM upsell_items WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $item = $stmt->fetch();
        if ($item) {
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Товар не найден']);
        }
        exit;
    }
}

// === ФИЛЬТРАЦИЯ ===
$categoryFilter = $_GET['category'] ?? '';
$activeFilter = $_GET['active'] ?? '';

$sql = "SELECT * FROM upsell_items WHERE 1=1";
$params = [];

if ($categoryFilter) {
    $sql .= " AND category = ?";
    $params[] = $categoryFilter;
}
if ($activeFilter !== '') {
    $sql .= " AND is_active = ?";
    $params[] = (int)$activeFilter;
}

$sql .= " ORDER BY sort_order ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Статистика
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM upsell_items")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM upsell_items WHERE is_active=1")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM upsell_items WHERE is_active=0")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-gift"></i> Дополнительные товары</h1>
            <p>Настройка Upsell-предложений в корзине</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-boxes"></i>
                <div>
                    <span class="stat-num"><?= $stats['total'] ?></span>
                    <span class="stat-label">Всего товаров</span>
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
                <i class="fas fa-times-circle"></i>
                <div>
                    <span class="stat-num"><?= $stats['inactive'] ?></span>
                    <span class="stat-label">Скрытых</span>
                </div>
            </div>
        </div>

        <!--  Кнопки действий -->
        <div class="action-bar">
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Добавить товар
            </button>
            <a href="orders.php" class="btn btn-outline">
                <i class="fas fa-shopping-cart"></i> К заказам
            </a>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <select name="category" onchange="this.form.submit()">
                    <option value="">Все категории</option>
                    <option value="accessories" <?= $categoryFilter === 'accessories' ? 'selected' : '' ?>>Аксессуары</option>
                    <option value="food" <?= $categoryFilter === 'food' ? 'selected' : '' ?>> Еда/Напитки</option>
                    <option value="souvenir" <?= $categoryFilter === 'souvenir' ? 'selected' : '' ?>>Сувениры</option>
                    <option value="recommended" <?= $categoryFilter === 'recommended' ? 'selected' : '' ?>> Рекомендации</option>
                </select>
                <select name="active" onchange="this.form.submit()">
                    <option value="">Все статусы</option>
                    <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>> Активные</option>
                    <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Скрытые</option>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Применить</button>
                <a href="upsell.php" class="btn btn-outline"><i class="fas fa-undo"></i> Сброс</a>
            </form>
        </div>

        <!-- Таблица товаров -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-image"></i> Фото</th>
                            <th><i class="fas fa-tag"></i> Название</th>
                            <th><i class="fas fa-folder"></i> Категория</th>
                            <th><i class="fas fa-ruble-sign"></i> Цена</th>
                            <th><i class="fas fa-sort-numeric-down"></i> Порядок</th>
                            <th><i class="fas fa-icons"></i> Иконка</th>
                            <th><i class="fas fa-toggle-on"></i> Статус</th>
                            <th><i class="fas fa-cogs"></i> Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $catLabel = '';
                            $catClass = '';
                            switch($item['category']) {
                                case 'accessories': $catLabel = 'Аксессуар'; $catClass = 'info'; break;
                                case 'food': $catLabel = 'Еда'; $catClass = 'warning'; break;
                                case 'souvenir': $catLabel = 'Сувенир'; $catClass = 'success'; break;
                                case 'recommended': $catLabel = 'Рекомендация'; $catClass = 'primary'; break;
                            }
                        ?>
                        <tr>
                            <td><strong>#<?= $item['id'] ?></strong></td>
                            <td>
                                <?php if ($item['image_url']): ?>
                                    <img src="<?= htmlspecialchars(assetUrl($item['image_url'])) ?>"
                                         class="item-thumb" onerror="this.src='/house_of_taste/public/img/placeholder.png'">
                                <?php else: ?>
                                    <span class="item-thumb placeholder"><i class="fas fa-image"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <?php if($item['description']): ?>
                                    <br><small style="color:#666"><?= htmlspecialchars(mb_substr($item['description'], 0, 40)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $catClass ?>"><?= $catLabel ?></span></td>
                            <td>
                                <strong style="color:var(--gold)"><?= number_format($item['price'], 0, '.', ' ') ?> ₽</strong>
                            </td>
                            <td><?= $item['sort_order'] ?></td>
                            <td>
                                <?php if($item['icon_class']): ?>
                                    <i class="<?= htmlspecialchars($item['icon_class']) ?>" style="color:var(--gold);font-size:16px"></i>
                                <?php else: ?>
                                    <span style="color:#666">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $item['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $item['is_active'] ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <button class="btn btn-primary btn-sm btn-icon" onclick="openModal(<?= $item['id'] ?>)" title="Редактировать">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-icon" onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <p>Дополнительные товары не найдены</p>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Добавить первый товар
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- МОДАЛЬНОЕ ОКНО -->
<div id="upsellModal" class="modal" style="display:none">
    <div class="modal-content" style="max-width:650px">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="modalTitle">Добавить товар</span></h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="upsellForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="item_id" id="item_id">

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Название *</label>
                    <input type="text" name="name" id="name" maxlength="100" required placeholder="Например: Фирменный пакет">
                    <span class="error" id="error_name"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-ruble-sign"></i> Цена (₽) *</label>
                    <input type="number" step="0.01" name="price" id="price" min="0" max="999999.99" required placeholder="0.00">
                    <span class="error" id="error_price"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-folder"></i> Категория *</label>
                    <select name="category" id="category" required>
                        <option value="accessories">Аксессуары</option>
                        <option value="food">Еда/Напитки</option>
                        <option value="souvenir">Сувениры</option>
                        <option value="recommended">Рекомендации</option>
                    </select>
                    <span class="error" id="error_category"></span>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sort-numeric-down"></i> Порядок сортировки</label>
                    <input type="number" name="sort_order" id="sort_order" min="0" max="999" value="0">
                    <span class="error" id="error_sort_order"></span>
                    <small class="hint">Меньшее число = выше в списке</small>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Описание</label>
                <textarea name="description" id="description" maxlength="255" rows="2" placeholder="Краткое описание для клиента"></textarea>
                <small class="hint"><span id="descCount">0</span>/255 символов</small>
                <span class="error" id="error_description"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-icons"></i> Иконка (Font Awesome)</label>
                    <input type="text" name="icon_class" id="icon_class" maxlength="50" placeholder="fas fa-gift">
                    <span class="error" id="error_icon_class"></span>
                    <small class="hint"><a href="https://fontawesome.com/icons" target="_blank">Найти иконку ↗</a></small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-toggle-on"></i> Статус</label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                        <span>Активен (показывать в корзине)</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-image"></i> Изображение товара</label>
                <div class="image-upload-area">
                    <input type="file" name="image" id="image" accept="image/*" hidden>
                    <div class="upload-box" onclick="document.getElementById('image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Нажмите для загрузки фото</span>
                    </div>
                    <span class="file-name" id="fileName">Файл не выбран</span>
                </div>
                <span class="error" id="error_image"></span>
                <div id="imagePreview" class="image-preview">
                    <img id="previewImg" src="" alt="">
                    <button type="button" class="remove-image" onclick="removeImage()"><i class="fas fa-times"></i></button>
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
.filters-bar select{padding:10px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;min-width:150px;transition:0.2s}
.filters-bar select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}

/* TABLE */
.table-responsive{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table th i{margin-right:5px}
.data-table tr:hover{background:rgba(200,166,86,0.08)}
.actions-cell{display:flex;gap:6px}

/* ITEM THUMBNAIL */
.item-thumb{width:50px;height:50px;object-fit:cover;border-radius:8px;border:2px solid rgba(255,255,255,0.1);transition:0.2s}
.item-thumb:hover{border-color:var(--gold)}
.item-thumb.placeholder{display:flex;align-items:center;justify-content:center;background:var(--gray-light);color:var(--text-muted);font-size:20px}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
.badge-success{background:rgba(46,204,113,0.15);color:var(--success)}
.badge-danger{background:rgba(231,76,60,0.15);color:var(--danger)}
.badge-warning{background:rgba(243,156,18,0.15);color:var(--warning)}
.badge-info{background:rgba(52,152,219,0.15);color:var(--info)}
.badge-primary{background:rgba(200,166,86,0.15);color:var(--gold)}

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
.form-group{margin-bottom:20px}
.form-group label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group label i{color:var(--gold);font-size:12px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit;transition:0.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}
.form-group textarea{resize:vertical;min-height:75px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group .error{color:var(--danger);font-size:11px;margin-top:5px;display:flex;align-items:center;gap:4px}
.form-group .error i{font-size:10px}
.form-group .hint{display:block;font-size:11px;color:var(--text-muted);margin-top:5px}
.form-group .hint a{color:var(--gold);text-decoration:none}
.form-group .hint a:hover{text-decoration:underline}
.checkbox-label{display:flex;align-items:center;gap:8px;color:var(--text);font-size:13px;cursor:pointer}
.checkbox-label input[type="checkbox"]{width:18px;height:18px;accent-color:var(--gold);cursor:pointer;margin:0}

/* IMAGE UPLOAD */
.image-upload-area{display:flex;flex-direction:column;gap:8px}
.upload-box{padding:15px;background:var(--gray-light);border:2px dashed rgba(255,255,255,0.2);border-radius:8px;color:var(--text);font-size:12px;cursor:pointer;transition:0.2s;display:flex;flex-direction:column;align-items:center;gap:6px}
.upload-box:hover{border-color:var(--gold);color:var(--gold)}
.file-name{font-size:11px;color:var(--text-muted)}
.image-preview{position:relative;display:none;margin-top:12px;width:fit-content}
.image-preview.show{display:block}
.image-preview img{max-height:120px;border-radius:8px;border:2px solid rgba(255,255,255,0.1)}
.remove-image{position:absolute;top:-8px;right:-8px;width:24px;height:24px;background:var(--danger);color:#fff;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:0.2s}
.remove-image:hover{background:#c0392b;transform:scale(1.1)}

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
//Открытие модального окна
function openModal(itemId = null) {
    const modal = document.getElementById('upsellModal');
    const form = document.getElementById('upsellForm');
    const title = document.getElementById('modalTitle');
    const saveBtn = document.getElementById('saveBtn');

    form.reset();
    document.querySelectorAll('.error').forEach(el => el.textContent = '');
    document.getElementById('descCount').textContent = '0';
    document.getElementById('fileName').textContent = 'Файл не выбран';
    document.getElementById('sort_order').value = '0';

    // Сброс превью
    document.getElementById('previewImg').src = '';
    document.getElementById('imagePreview').classList.remove('show');

    if (itemId) {
        title.innerHTML = '<i class="fas fa-pen"></i> Редактировать товар';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Обновить</span>';
        document.getElementById('item_id').value = itemId;
        saveBtn.disabled = true;

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_item&id=' + itemId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                const item = data.data;
                document.getElementById('name').value = item.name || '';
                document.getElementById('price').value = item.price || '';
                document.getElementById('category').value = item.category || 'accessories';
                document.getElementById('sort_order').value = item.sort_order || 0;
                document.getElementById('description').value = item.description || '';
                document.getElementById('icon_class').value = item.icon_class || '';
                document.getElementById('is_active').checked = item.is_active == 1;

                if (item.image_url) {
                    document.getElementById('previewImg').src = item.image_url.startsWith('/') ? '/house_of_taste' + item.image_url : item.image_url;
                    document.getElementById('imagePreview').classList.add('show');
                }

                document.getElementById('descCount').textContent = (item.description || '').length;
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка загрузки', 'error'))
        .finally(() => saveBtn.disabled = false);
    } else {
        title.innerHTML = '<i class="fas fa-plus-circle"></i> Добавить товар';
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>Сохранить</span>';
        document.getElementById('item_id').value = '';
        document.getElementById('is_active').checked = true;
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

//  Закрытие модального окна
function closeModal() {
    document.getElementById('upsellModal').style.display = 'none';
    document.body.style.overflow = '';
}

//  Подтверждение удаления
function confirmDelete(id, name) {
    if (confirm(` Удалить товар "${name}"?\n\n⚠️ Это действие нельзя отменить!`)) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('<i class="fas fa-check-circle"></i> Товар удалён', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Ошибка'), 'error');
            }
        })
        .catch(() => showToast('<i class="fas fa-exclamation-triangle"></i> Ошибка соединения', 'error'));
    }
}

//  Отправка формы
document.getElementById('upsellForm').addEventListener('submit', function(e) {
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
                (document.getElementById('item_id').value ? 'Товар обновлён' : 'Товар добавлен'), 'success');
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
        const isEdit = document.getElementById('item_id').value;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span>' + (isEdit ? 'Обновить' : 'Сохранить') + '</span>';
    });
});

// Предпросмотр изображения
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fileName = document.getElementById('fileName');

    if (file) {
        fileName.textContent = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('imagePreview').classList.add('show');
            };
            reader.readAsDataURL(file);
        }
    }
});

//  Удаление превью
function removeImage() {
    document.getElementById('image').value = '';
    document.getElementById('fileName').textContent = 'Файл не выбран';
    document.getElementById('imagePreview').classList.remove('show');
    document.getElementById('previewImg').src = '';
}

//  Счётчик символов
document.getElementById('description').addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});

//  Закрытие по клику вне
document.getElementById('upsellModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ⌨️ Закрытие по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

//  Toast уведомления
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
showToast('<i class="fas fa-info-circle"></i> <?= addslashes($_SESSION['admin_message']) ?>', '<?= $_SESSION['admin_message_type'] ?? 'info' ?>');
<?php unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
