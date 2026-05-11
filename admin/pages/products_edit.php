<?php

$pageTitle = 'Редактирование товара';
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

// === ИД ТОВАРА (если редактирование) ===
$productId = (int)($_GET['id'] ?? 0);
$isEdit = $productId > 0;

// === ЗАГРУЗКА ДАННЫХ ПРИ РЕДАКТИРОВАНИИ ===
$product = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        $_SESSION['admin_message'] = 'Товар не найден';
        $_SESSION['admin_message_type'] = 'error';
        header('Location: products.php');
        exit;
    }
}

// === ОБРАБОТКА ФОРМЫ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $chef_id = !empty($_POST['chef_id']) ? (int)$_POST['chef_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $price = (float)str_replace(',', '.', $_POST['price'] ?? 0);
    $old_price = !empty($_POST['old_price']) ? (float)str_replace(',', '.', $_POST['old_price']) : null;
    $weight_volume = trim($_POST['weight_volume'] ?? '');
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $is_hit = isset($_POST['is_hit']) ? 1 : 0;
    $image_url = $product ? $product['image_url'] : '';

    // === ВАЛИДАЦИЯ ===
    // Название: 2-100 символов, обязательно
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Минимум 2 символа';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Максимум 100 символов';
    }

    // Категория: обязательна и должна существовать
    $catCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $catCheck->execute([$category_id]);
    if ($category_id <= 0 || !$catCheck->fetch()) {
        $errors['category_id'] = 'Выберите категорию';
    }

    // Шеф-повар: если указан, должен быть активным поваром
    if ($chef_id) {
        $chefCheck = $pdo->prepare("SELECT id FROM staff WHERE id = ? AND position = 'chef' AND is_active = 1");
        $chefCheck->execute([$chef_id]);
        if (!$chefCheck->fetch()) {
            $errors['chef_id'] = 'Неверный выбор шефа';
        }
    }

    // Цена: от 0.01 до 999 999.99 ₽, обязательно
    if ($price <= 0) {
        $errors['price'] = 'Цена должна быть больше 0';
    } elseif ($price > 999999.99) {
        $errors['price'] = 'Слишком высокая цена';
    }

    // Старая цена: если указана, должна быть больше текущей
    if ($old_price !== null) {
        if ($old_price <= 0) {
            $errors['old_price'] = 'Старая цена должна быть > 0';
        } elseif ($old_price <= $price) {
            $errors['old_price'] = 'Должна быть больше текущей';
        }
    }

    // Вес/объём: формат "350 г", "400 мл", "1.5 кг"
    if ($weight_volume && !preg_match('/^[\d\s\/,]+(?:г|мл|кг|л)$/u', $weight_volume)) {
        $errors['weight_volume'] = 'Пример: "350 г", "400 мл"';
    }

    // Описание: максимум 1000 символов
    if ($description && mb_strlen($description) > 1000) {
        $errors['description'] = 'Максимум 1000 символов';
    }

    // Ингридиенты: максимум 500 символов
    if ($ingredients && mb_strlen($ingredients) > 500) {
        $errors['ingredients'] = 'Максимум 500 символов';
    }

    // === ЗАГРУЗКА ИЗОБРАЖЕНИЯ ===
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $errors['image'] = 'Допустимы: JPG, PNG, GIF, WEBP';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors['image'] = 'Максимум 5 МБ';
        } else {
            $filename = 'product_' . uniqid() . '.' . $ext;
            // Путь для сохранения файла (относительно корня сайта)
            $uploadDir = __DIR__ . '/../public/img/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploadPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                // Удаляем старое изображение если есть
                if ($product && $product['image_url']) {
                    $oldPath = __DIR__ . '/../' . ltrim($product['image_url'], '/');
                    if (file_exists($oldPath) && $oldPath !== $uploadPath) {
                        @unlink($oldPath);
                    }
                }
                // Сохраняем путь относительно корня сайта
                $image_url = '/public/img/products/' . $filename;
            } else {
                $errors['image'] = 'Ошибка сохранения файла';
            }
        }
    }

    // Если есть ошибки — не сохраняем
    if (empty($errors)) {
        // Авто-расчёт скидки
        $discount = ($old_price && $old_price > $price)
            ? round((($old_price - $price) / $old_price) * 100, 2)
            : 0;

        try {
            if ($isEdit) {
                // UPDATE — БЕЗ updated_at, так как колонки нет в БД!
                $stmt = $pdo->prepare("UPDATE products SET
                    category_id = ?, chef_id = ?, name = ?, description = ?, ingredients = ?,
                    price = ?, old_price = ?, discount_percent = ?, weight_volume = ?,
                    image_url = ?, is_available = ?, is_hit = ?
                    WHERE id = ?");
                $stmt->execute([
                    $category_id, $chef_id, $name, $description, $ingredients,
                    $price, $old_price, $discount, $weight_volume, $image_url,
                    $is_available, $is_hit, $productId
                ]);
                $message = 'Товар обновлён';
                $messageType = 'success';
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO products
                    (category_id, chef_id, name, description, ingredients, price, old_price,
                     discount_percent, weight_volume, image_url, is_available, is_hit, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $category_id, $chef_id, $name, $description, $ingredients,
                    $price, $old_price, $discount, $weight_volume, $image_url,
                    $is_available, $is_hit
                ]);
                $message = 'Товар добавлен';
                $messageType = 'success';
            }

            // Редирект с сообщением
            $_SESSION['admin_message'] = $message;
            $_SESSION['admin_message_type'] = $messageType;
            header('Location: products.php');
            exit;

        } catch (Exception $e) {
            $errors['database'] = 'Ошибка БД: ' . $e->getMessage();
        }
    }
}

// === ДАННЫЕ ДЛЯ ФОРМ ===
$categories = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY sort_order, name")->fetchAll();
$chefs = $pdo->query("SELECT id, full_name FROM staff WHERE position = 'chef' AND is_active = 1")->fetchAll();

// Значения по умолчанию или из БД
$name = $product ? $product['name'] : ($_POST['name'] ?? '');
$category_id = $product ? $product['category_id'] : ($_POST['category_id'] ?? '');
$chef_id = $product ? $product['chef_id'] : ($_POST['chef_id'] ?? '');
$description = $product ? $product['description'] : ($_POST['description'] ?? '');
$ingredients = $product ? $product['ingredients'] : ($_POST['ingredients'] ?? '');
$price = $product ? $product['price'] : ($_POST['price'] ?? '');
$old_price = $product ? $product['old_price'] : ($_POST['old_price'] ?? '');
$weight_volume = $product ? $product['weight_volume'] : ($_POST['weight_volume'] ?? '');
$is_available = $product ? $product['is_available'] : (isset($_POST['is_available']) ? 1 : 0);
$is_hit = $product ? $product['is_hit'] : (isset($_POST['is_hit']) ? 1 : 0);
$image_url = $product ? $product['image_url'] : '';

// Функция для получения полного URL изображения
function getProductImage($url) {
    if (empty($url)) return '/house_of_taste/public/img/placeholder.png';
    // Если путь уже содержит /house_of_taste — возвращаем как есть
    if (strpos($url, '/house_of_taste') === 0) return $url;
    // Иначе добавляем префикс
    return '/house_of_taste' . ltrim($url, '/');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1>
                <i class="fas <?= $isEdit ? 'fa-pen' : 'fa-plus-circle' ?>"></i>
                <?= $isEdit ? 'Редактировать товар' : 'Добавить товар' ?>
            </h1>
            <p><?= $isEdit ? 'Изменение данных блюда или напитка' : 'Создание новой карточки товара' ?></p>
        </div>

        <!-- ФОРМА -->
        <div class="card">
            <form method="POST" enctype="multipart/form-data" id="productForm">

                <!-- Основные данные -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Основная информация</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Название *</label>
                            <input type="text" name="name" id="name" maxlength="100" required
                                   value="<?= htmlspecialchars($name) ?>" placeholder="Введите название">
                            <?php if(isset($errors['name'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['name'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-folder-open"></i> Категория *</label>
                            <select name="category_id" id="category_id" required>
                                <option value="">Выберите категорию...</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                        <?= str_repeat('—', $cat['parent_id'] ? 1 : 0) ?>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(isset($errors['category_id'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['category_id'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-ruble-sign"></i> Цена (₽) *</label>
                            <input type="number" step="0.01" name="price" id="price" min="0.01" max="999999.99" required
                                   value="<?= htmlspecialchars($price) ?>" placeholder="0.00">
                            <?php if(isset($errors['price'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['price'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Старая цена</label>
                            <input type="number" step="0.01" name="old_price" id="old_price" min="0.01"
                                   value="<?= htmlspecialchars($old_price) ?>" placeholder="Для скидки">
                            <?php if(isset($errors['old_price'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['old_price'] ?></span>
                            <?php endif; ?>
                            <small class="hint">Оставьте пустым, если скидки нет</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-weight-hanging"></i> Вес / Объём</label>
                            <input type="text" name="weight_volume" id="weight_volume" maxlength="20"
                                   value="<?= htmlspecialchars($weight_volume) ?>" placeholder="350 г, 400 мл, 1.5 кг">
                            <?php if(isset($errors['weight_volume'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['weight_volume'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Шеф-повар</label>
                            <select name="chef_id" id="chef_id">
                                <option value="">Не указан</option>
                                <?php foreach($chefs as $chef): ?>
                                    <option value="<?= $chef['id'] ?>"
                                        <?= $chef_id == $chef['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($chef['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(isset($errors['chef_id'])): ?>
                                <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['chef_id'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Описание и состав -->
                <div class="form-section">
                    <h3><i class="fas fa-align-left"></i> Описание и состав</h3>

                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Описание</label>
                        <textarea name="description" id="description" maxlength="1000" rows="3"
                                  placeholder="Краткое описание блюда для каталога..."><?= htmlspecialchars($description) ?></textarea>
                        <small class="hint"><span id="descCount">0</span>/1000 символов</small>
                        <?php if(isset($errors['description'])): ?>
                            <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['description'] ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-list-ul"></i> Состав / Ингридиенты</label>
                        <textarea name="ingredients" id="ingredients" maxlength="500" rows="2"
                                  placeholder="Основные ингридиенты, аллергены..."><?= htmlspecialchars($ingredients) ?></textarea>
                        <small class="hint"><span id="ingCount">0</span>/500 символов</small>
                        <?php if(isset($errors['ingredients'])): ?>
                            <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['ingredients'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Изображение -->
                <div class="form-section">
                    <h3><i class="fas fa-image"></i> Изображение</h3>

                    <div class="form-group">
                        <label><i class="fas fa-cloud-upload-alt"></i> Загрузить фото</label>
                        <div class="image-upload">
                            <input type="file" name="image" id="image" accept="image/*" hidden>
                            <label for="image" class="upload-btn">
                                <i class="fas fa-plus"></i> Выбрать файл
                            </label>
                            <span class="file-name" id="fileName">Файл не выбран</span>
                        </div>
                        <small class="hint">JPG, PNG, GIF, WEBP • Макс. 5 МБ</small>
                        <?php if(isset($errors['image'])): ?>
                            <span class="error"><i class="fas fa-exclamation-circle"></i> <?= $errors['image'] ?></span>
                        <?php endif; ?>

                        <!-- Предпросмотр -->
                        <div id="imagePreview" class="image-preview <?= $image_url ? 'show' : '' ?>">
                            <img id="previewImg" src="<?= $image_url ? getProductImage($image_url) : '' ?>" alt="" onerror="this.src='/house_of_taste/public/img/placeholder.png'">
                            <button type="button" class="remove-image" onclick="removeImage()">
                                <i class="fas fa-times"></i>
                            </button>
                            <input type="hidden" name="current_image" value="<?= htmlspecialchars($image_url) ?>">
                        </div>
                    </div>
                </div>

                <!-- Настройки -->
                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> Настройки</h3>

                    <div class="form-row">
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="is_available" id="is_available" value="1" <?= $is_available ? 'checked' : '' ?>>
                                <i class="fas fa-check-circle"></i> В наличии
                            </label>
                            <small class="hint">Скрыть товар из каталога, сняв галочку</small>
                        </div>
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="is_hit" id="is_hit" value="1" <?= $is_hit ? 'checked' : '' ?>>
                                <i class="fas fa-fire"></i> Хит продаж
                            </label>
                            <small class="hint">Показать метку «Хит» в каталоге</small>
                        </div>
                    </div>
                </div>

                <!-- Кнопки -->
                <div class="form-actions">
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Отмена
                    </a>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> <span><?= $isEdit ? 'Обновить' : 'Добавить' ?></span>
                    </button>
                </div>

                <?php if(isset($errors['database'])): ?>
                    <div class="error global-error">
                        <i class="fas fa-database"></i> <?= $errors['database'] ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>
</div>

<!-- СТИЛИ (те же, что были) -->
<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--gold-dark:#a68a44;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--gray-light:#3a3a3a;--text:#fff;--text-muted:#999999;--success:#2ecc71;--warning:#f39c12;--danger:#e74c3c;--info:#3498db;--shadow:0 4px 20px rgba(0,0,0,0.3)}
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
.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:var(--gold)}
.page-header p{color:var(--text-muted);font-size:14px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}
.form-section{margin-bottom:30px;padding-bottom:25px;border-bottom:1px solid rgba(255,255,255,0.08)}
.form-section:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
.form-section h3{font-size:16px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;color:var(--gold)}
.form-section h3 i{font-size:14px}
.form-group{margin-bottom:20px}
.form-group label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
.form-group label i{color:var(--gold);font-size:12px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit;transition:0.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}
.form-group textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-group .error{color:var(--danger);font-size:11px;margin-top:5px;display:flex;align-items:center;gap:4px}
.form-group .error i{font-size:10px}
.form-group .hint{display:block;font-size:11px;color:var(--text-muted);margin-top:5px}
.checkbox-group label{display:flex;align-items:center;gap:8px;color:var(--text);text-transform:none;letter-spacing:0;font-size:13px;cursor:pointer}
.checkbox-group input[type="checkbox"]{width:18px;height:18px;accent-color:var(--gold);cursor:pointer;margin:0}
.checkbox-group .hint{margin-left:26px}
.image-upload{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.upload-btn{padding:10px 18px;background:var(--gray-light);border:2px dashed rgba(255,255,255,0.2);border-radius:8px;color:var(--text);font-size:12px;cursor:pointer;transition:0.2s;display:flex;align-items:center;gap:6px}
.upload-btn:hover{border-color:var(--gold);color:var(--gold)}
.file-name{font-size:12px;color:var(--text-muted)}
.image-preview{position:relative;display:none;margin-top:15px}
.image-preview.show{display:block}
.image-preview img{max-height:150px;border-radius:8px;border:2px solid rgba(255,255,255,0.1)}
.remove-image{position:absolute;top:-10px;right:-10px;width:28px;height:28px;background:var(--danger);color:#fff;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:0.2s}
.remove-image:hover{background:#c0392b;transform:scale(1.1)}
.form-actions{display:flex;gap:12px;justify-content:flex-end;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);margin-top:10px}
.global-error{background:rgba(231,76,60,0.15);border:1px solid var(--danger);color:var(--danger);padding:12px 15px;border-radius:8px;margin-top:15px;font-size:13px;display:flex;align-items:center;gap:8px}
.global-error i{font-size:14px}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn i{font-size:13px}
.btn-primary{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:#1a1a1a;box-shadow:0 4px 15px rgba(200,166,86,0.3)}
.btn-primary:hover{background:linear-gradient(135deg,var(--gold-light),var(--gold));transform:translateY(-2px);box-shadow:0 6px 20px rgba(200,166,86,0.4)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}
.btn-outline:hover{border-color:var(--gold);color:var(--gold);background:rgba(200,166,86,0.1)}
.btn:disabled{opacity:0.6;cursor:not-allowed;transform:none !important}
#admin-toast{position:fixed;bottom:30px;right:30px;padding:14px 22px;background:var(--gray);border-left:4px solid var(--success);color:var(--text);border-radius:8px;box-shadow:var(--shadow);z-index:9999;transform:translateX(400px);transition:transform 0.25s;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px}
#admin-toast.show{transform:translateX(0)}
#admin-toast.error{border-left-color:var(--danger)}
#admin-toast.warning{border-left-color:var(--warning)}
#admin-toast i{font-size:16px}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.admin-main{padding:20px}.form-actions{flex-direction:column-reverse}.btn{width:100%;justify-content:center}}
</style>

<!-- СКРИПТЫ -->
<script>
// Счётчик символов
document.getElementById('description')?.addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});
document.getElementById('ingredients')?.addEventListener('input', function() {
    document.getElementById('ingCount').textContent = this.value.length;
});
document.addEventListener('DOMContentLoaded', function() {
    const desc = document.getElementById('description');
    const ing = document.getElementById('ingredients');
    if(desc) document.getElementById('descCount').textContent = desc.value.length;
    if(ing) document.getElementById('ingCount').textContent = ing.value.length;
});

// Предпросмотр изображения
document.getElementById('image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const fileName = document.getElementById('fileName');

    if(file) {
        fileName.textContent = file.name.length > 25 ? file.name.substring(0,22) + '...' : file.name;
        if(file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('imagePreview').classList.add('show');
            };
            reader.readAsDataURL(file);
        }
    } else {
        fileName.textContent = 'Файл не выбран';
    }
});

// Удаление превью
function removeImage() {
    document.getElementById('image').value = '';
    document.getElementById('fileName').textContent = 'Файл не выбран';
    document.getElementById('imagePreview').classList.remove('show');
    document.getElementById('previewImg').src = '';
}

// Авто-расчёт скидки
document.getElementById('old_price')?.addEventListener('input', function() {
    const price = parseFloat(document.getElementById('price')?.value) || 0;
    const oldPrice = parseFloat(this.value) || 0;
    if(oldPrice > price && price > 0) {
        const discount = ((oldPrice - price) / oldPrice * 100).toFixed(1);
        console.log(`Скидка: ${discount}%`);
    }
});

// Валидация формы перед отправкой
document.getElementById('productForm')?.addEventListener('submit', function(e) {
    const saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Сохранение...</span>';
    document.querySelectorAll('.error').forEach(el => el.textContent = '');
});

// Toast уведомления
function showToast(msg, type) {
    const existing = document.getElementById('admin-toast');
    if(existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'admin-toast';
    toast.className = type;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${msg}`;
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
