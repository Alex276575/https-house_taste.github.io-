<?php

$pageTitle = 'Каталог товаров';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';

// === ФИЛЬТРАЦИЯ ===
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$availableFilter = $_GET['available'] ?? '';

$sql = "SELECT p.*, c.name as category_name, s.full_name as chef_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN staff s ON p.chef_id = s.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}
if ($availableFilter !== '') {
    $sql .= " AND p.is_available = ?";
    $params[] = (int)$availableFilter;
}

$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// === КАТЕГОРИИ ДЛЯ ФИЛЬТРА ===
$categories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-utensils"></i> Каталог товаров</h1>
            <p>Управление блюдами и напитками меню</p>
        </div>

        <!-- Фильтры и поиск -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <input type="text" name="search" placeholder="Поиск по названию..." value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">

                <select name="category">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="available">
                    <option value="">Все статусы</option>
                    <option value="1" <?= $availableFilter === '1' ? 'selected' : '' ?>>В наличии</option>
                    <option value="0" <?= $availableFilter === '0' ? 'selected' : '' ?>>Нет в наличии</option>
                </select>

                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Найти</button>
                <a href="products.php" class="btn btn-outline">Сбросить</a>

                <div style="margin-left: auto;">
                    <a href="products_edit.php" class="btn btn-success"><i class="fas fa-plus"></i> Добавить товар</a>
                </div>
            </form>
        </div>

        <!-- Таблица товаров -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Фото</th>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Цена</th>
                            <th>Наличие</th>
                            <th>Хит</th>
                            <th>Шеф</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): ?>
                        <tr>
                            <td>#<?= $prod['id'] ?></td>
                            <td>
                                <?php if ($prod['image_url']): ?>
                                    <img src="/house_of_taste<?= htmlspecialchars($prod['image_url']) ?>"
                                         alt=""
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <span style="color: #666; font-size: 11px;">Нет фото</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                <?php if ($prod['discount_percent'] > 0): ?>
                                    <span class="status-badge" style="background: rgba(231,76,60,0.2); color: #e74c3c; margin-left: 5px;">
                                        -<?= $prod['discount_percent'] ?>%
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($prod['category_name'] ?? '—') ?></td>
                            <td>
                                <?= number_format($prod['price'], 0, '.', ' ') ?> ₽
                                <?php if ($prod['old_price']): ?>
                                    <br><small style="color: #666; text-decoration: line-through;">
                                        <?= number_format($prod['old_price'], 0, '.', ' ') ?> ₽
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $prod['is_available'] ? 'approved' : 'cancelled' ?>">
                                    <?= $prod['is_available'] ? '✓ В наличии' : '✗ Нет' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($prod['is_hit']): ?>
                                    <span class="status-badge" style="background: rgba(46,204,113,0.2); color: #2ecc71;">Хит</span>
                                <?php else: ?>
                                    <span style="color: #666;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($prod['chef_name'] ?? '—') ?></td>
                            <td>
                                <a href="products_edit.php?id=<?= $prod['id'] ?>" class="btn btn-primary btn-sm btn-icon" title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-danger btn-sm btn-icon"
                                        onclick="confirmDelete(<?= $prod['id'] ?>, 'товар')"
                                        title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-utensils" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>Товары не найдены</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
function confirmDelete(id, type) {
    if (confirm('Вы уверены, что хотите удалить ' + type + ' #' + id + '?\nЭто действие нельзя отменить.')) {
        fetch('../api/products_save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete', id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Удалено успешно', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Ошибка: ' + (data.error || 'Неизвестная'), 'error');
            }
        })
        .catch(() => showToast('Ошибка соединения', 'error'));
    }
}

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
</script>
<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--text:#fff;--text-muted:#999;--success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;--info:#3498db}
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
.page-header p{color:var(--text-muted);font-size:14px}

/* CARDS & TABLES */
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.data-table{width:100%;border-collapse:collapse;background:var(--gray);border-radius:12px;overflow:hidden}
.data-table th,.data-table td{padding:15px;text-align:left;border-bottom:1px solid rgba(255,255,255,0.05);font-size:13px}
.data-table th{background:rgba(0,0,0,0.2);font-weight:600;text-transform:uppercase;letter-spacing:1px;font-size:11px;color:var(--text-muted)}
.data-table tr:hover{background:rgba(200,166,86,0.05)}
.data-table img{border-radius:6px;object-fit:cover}

/* STATUS BADGES */
.status-badge{padding:4px 12px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;display:inline-block}
.status-approved{background:rgba(46,204,113,0.2);color:#2ecc71}
.status-cancelled{background:rgba(231,76,60,0.2);color:#e74c3c}
.status-warning{background:rgba(243,156,18,0.2);color:#f39c12}

/* BUTTONS */
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-primary{background:var(--gold);color:#1a1a1a}.btn-primary:hover{background:var(--gold-light)}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.2);color:#fff}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-warning{background:var(--warning);color:#1a1a1a}.btn-warning:hover{background:#e67e22}
.btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#c0392b}
.btn-sm{padding:5px 10px;font-size:11px}.btn-icon{width:32px;height:32px;padding:0;justify-content:center}

/* FILTERS */
.filters-bar{display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.filters-bar input,.filters-bar select{padding:10px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:13px}
.filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--gold)}

/* FORMS */
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;background:#222;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-size:14px;font-family:inherit;transition:0.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.1)}
.form-group textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-group .error{color:var(--danger);font-size:11px;margin-top:4px;display:block}
.form-group input[type="checkbox"]{width:auto;margin-right:8px}

/* MODAL */
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-content{background:var(--gray);border-radius:12px;padding:25px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;border:1px solid rgba(255,255,255,0.1)}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid rgba(255,255,255,0.1)}
.modal-header h3{font-size:18px;font-weight:600}
.modal-close{background:none;border:none;color:#fff;font-size:28px;cursor:pointer;line-height:1}
.modal-close:hover{color:var(--gold)}

/* TOAST */
#admin-toast{position:fixed;bottom:30px;right:30px;padding:15px 25px;background:var(--gray);border-left:4px solid var(--success);color:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:9999;transform:translateX(400px);transition:transform 0.3s;font-size:14px}
#admin-toast.show{transform:translateX(0)}#admin-toast.error{border-left-color:var(--danger)}#admin-toast.warning{border-left-color:var(--warning)}

/* RESPONSIVE */
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.filters-bar{flex-direction:column;align-items:stretch}.admin-main{padding:20px}.data-table{font-size:12px}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
