<?php

$pageTitle = 'Каталог';
require_once __DIR__ . '/../includes/header.php';

// ===== ПАРАМЕТРЫ ФИЛЬТРАЦИИ =====
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = $_GET['sort'] ?? 'popular';
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 50000;

// ===== КАТЕГОРИИ =====
$categories = $pdo->query("
    SELECT id, name, parent_id
    FROM categories
    WHERE parent_id IS NULL
    ORDER BY sort_order ASC
")->fetchAll();

// ===== SQL ЗАПРОС =====
$sql = "SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_available = 1";
$params = [];

if ($categoryId > 0) {
    $stmtSub = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
    $stmtSub->execute([$categoryId]);
    $subCats = $stmtSub->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($subCats)) {
        $placeholders = implode(',', array_fill(0, count($subCats), '?'));
        $sql .= " AND (p.category_id = ? OR p.category_id IN ($placeholders))";
        $params[] = $categoryId;
        foreach ($subCats as $subId) {
            $params[] = $subId;
        }
    } else {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $likeTerm = "%{$searchQuery}%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
}

$sql .= " AND p.price >= ? AND p.price <= ?";
$params[] = $minPrice;
$params[] = $maxPrice;

switch ($sortBy) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY p.is_hit DESC, p.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$currentCategoryName = null;
if ($categoryId > 0) {
    $stmtCat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmtCat->execute([$categoryId]);
    $currentCategoryName = $stmtCat->fetchColumn();
}

// ===== ФУНКЦИЯ РАСЧЁТА ЦЕНЫ =====
function calculateFinalPrice($price, $oldPrice, $discountPercent) {
    if (!empty($oldPrice) && $oldPrice > $price) {
        return [
            'final' => $price,
            'original' => $oldPrice,
            'hasDiscount' => true,
            'discountPercent' => round((($oldPrice - $price) / $oldPrice) * 100)
        ];
    }
    if ($discountPercent > 0) {
        $final = $price * (1 - $discountPercent / 100);
        return [
            'final' => $final,
            'original' => $price,
            'hasDiscount' => true,
            'discountPercent' => $discountPercent
        ];
    }
    return [
        'final' => $price,
        'original' => null,
        'hasDiscount' => false,
        'discountPercent' => 0
    ];
}
?>

<div class="catalog-container">
    <!-- ЗАГОЛОВОК И ПОИСК -->
    <div class="page-header">
        <div class="page-title">
            <h1><?= $currentCategoryName ? htmlspecialchars($currentCategoryName) : 'Наше <span>Меню</span>' ?></h1>
        </div>
        <form method="GET" style="position: relative;">
            <input type="hidden" name="category" value="<?= $categoryId ?>">
            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666; font-size: 12px;"></i>
            <input type="text" name="search" placeholder="Поиск блюд..." value="<?= htmlspecialchars($searchQuery) ?>"
                   style="padding: 8px 12px 8px 35px; background: #222; border: 1px solid #333; border-radius: 4px; color: #fff; width: 220px; font-size: 12px;">
        </form>
    </div>

    <!-- ФИЛЬТРЫ ПО КАТЕГОРИЯМ -->
    <div class="catalog-filters">
        <a href="?" class="filter-btn <?= $categoryId == 0 ? 'active' : '' ?>">Все меню</a>
        <?php foreach ($categories as $cat): ?>
            <a href="?category=<?= $cat['id'] ?>" class="filter-btn <?= $categoryId == $cat['id'] ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ПАНЕЛЬ УПРАВЛЕНИЯ -->
    <div class="controls-bar">
        <form method="GET" class="price-filter-form">
            <input type="hidden" name="category" value="<?= $categoryId ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
            <span style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600;">Цена:</span>
            <input type="number" name="min_price" placeholder="От" class="price-input-mini"
                   value="<?= $minPrice > 0 ? $minPrice : '' ?>">
            <input type="number" name="max_price" placeholder="До" class="price-input-mini"
                   value="<?= $maxPrice < 50000 ? $maxPrice : '' ?>">
            <button type="submit" style="background: none; border: none; color: #c8a656; cursor: pointer;">
                <i class="fas fa-check"></i>
            </button>
            <?php if ($minPrice > 0 || $maxPrice < 50000): ?>
                <a href="?category=<?= $categoryId ?>" style="color: #666; font-size: 12px; margin-left: 5px;">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </form>
        <select onchange="location.href = this.value" class="sort-select">
            <?php
            function buildSortUrl($val) {
                $p = $_GET;
                $p['sort'] = $val;
                return '?' . http_build_query($p);
            }
            ?>
            <option value="<?= buildSortUrl('popular') ?>" <?= $sortBy == 'popular' ? 'selected' : '' ?>>Популярные</option>
            <option value="<?= buildSortUrl('newest') ?>" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Новинки</option>
            <option value="<?= buildSortUrl('price_asc') ?>" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Сначала дешевые</option>
            <option value="<?= buildSortUrl('price_desc') ?>" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Сначала дорогие</option>
        </select>
    </div>

    <!-- СЕТКА ТОВАРОВ -->
    <div class="products-grid">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $prod):
                $priceData = calculateFinalPrice(
                    (float)$prod['price'],
                    $prod['old_price'] ? (float)$prod['old_price'] : null,
                    (float)($prod['discount_percent'] ?? 0)
                );
                $imgSrc = $prod['image_url']
                    ? '/house_of_taste' . $prod['image_url']
                    : '/house_of_taste/public/img/placeholder.png';
                $isCombo = ($prod['category_id'] == 14);
            ?>
                <div class="product-card" data-product-id="<?= $prod['id'] ?>">
                    <div class="pc-image">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">

                        <?php if ($isCombo): ?>
                            <span class="pc-badge combo">Комбо</span>
                        <?php else: ?>
                            <?php if ($prod['is_hit']): ?>
                                <span class="pc-badge hit">Хит</span>
                            <?php endif; ?>
                            <?php if ($priceData['hasDiscount']): ?>
                                <span class="pc-badge sale">-<?= $priceData['discountPercent'] ?>%</span>
                            <?php endif; ?>
                            <?php if (!$prod['is_hit'] && !$priceData['hasDiscount'] && (strtotime($prod['created_at']) > strtotime('-7 days'))): ?>
                                <span class="pc-badge new">Новинка</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="pc-actions">
                            <button class="pc-action-btn" title="В избранное" data-fav-id="<?= $prod['id'] ?>">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>

                    <div class="pc-info">
                        <div class="pc-category"><?= htmlspecialchars($prod['category_name'] ?? 'Блюдо') ?></div>
                        <h3 class="pc-name"><?= htmlspecialchars($prod['name']) ?></h3>
                        <div class="pc-desc"><?= htmlspecialchars(mb_substr($prod['description'] ?? '', 0, 60)) ?>...</div>

                        <div class="pc-bottom">
                            <div class="pc-price-wrap">
                                <span class="pc-price"><?= number_format($priceData['final'], 0, '.', ' ') ?> ₽</span>
                                <?php if ($priceData['hasDiscount'] && $priceData['original']): ?>
                                    <span class="pc-old-price"><?= number_format($priceData['original'], 0, '.', ' ') ?> ₽</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <a href="/house_of_taste/pages/product.php?id=<?= $prod['id'] ?>" class="details-link">Подробнее</a>
                                <!-- Единая кнопка корзины для всех товаров (включая комбо) -->
                                <button class="add-cart-btn" title="Добавить в корзину"
                                        onclick="simpleAddToCart(<?= $prod['id'] ?>, '<?= addslashes($prod['name']) ?>')">
                                    <i class="fas fa-shopping-basket"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search" style="font-size: 40px; margin-bottom: 20px; color: #333;"></i>
                <h3>Ничего не найдено</h3>
                <p>Попробуйте изменить параметры фильтрации</p>
                <a href="?" class="filter-btn" style="margin-top: 15px; display: inline-block;">Сбросить все</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ТОЛЬКО ИНИЦИАЛИЗАЦИЯ ИЗБРАННОГО (остальное уже в header.php) ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, что функции из header.php загружены
    if (typeof getFavorites === 'function' && typeof toggleFavorite === 'function') {
        var favs = getFavorites();
        var favBtns = document.querySelectorAll('.pc-action-btn[data-fav-id]');

        for (var i = 0; i < favBtns.length; i++) {
            var btn = favBtns[i];
            var id = parseInt(btn.dataset.favId);
            var icon = btn.querySelector('i');

            // Синхронизация иконки при загрузке
            if (favs.indexOf(id) !== -1 && icon) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#e74c3c';
            }

            // Привязываем обработчик только один раз
            if (!btn.dataset.favBound) {
                btn.dataset.favBound = '1';
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var id = parseInt(this.dataset.favId);
                    toggleFavorite(id, this);
                });
            }
        }
    }
});
</script>

<style>
    /* ===== БАЗОВЫЕ СТИЛИ КАТАЛОГА ===== */
    .catalog-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 100px 20px 60px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-title h1 {
        font-size: 32px;
        font-weight: 300;
        margin: 0;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .page-title span {
        color: #c8a656;
        font-weight: 700;
    }

    .catalog-filters {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-bottom: 40px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 10px 22px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: transparent;
        color: #999;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        border-radius: 2px;
    }

    .filter-btn:hover,
    .filter-btn.active {
        border-color: #c8a656;
        color: #c8a656;
    }

    .filter-btn.active {
        background: rgba(200, 166, 86, 0.05);
        box-shadow: 0 0 15px rgba(200, 166, 86, 0.1);
    }

    .controls-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding: 15px 20px;
        background: #222;
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .price-filter-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .price-input-mini {
        width: 80px;
        background: #1a1a1a;
        border: 1px solid #333;
        padding: 8px;
        border-radius: 4px;
        color: #fff;
        font-size: 12px;
        text-align: center;
    }

    .price-input-mini:focus {
        border-color: #c8a656;
        outline: none;
    }

    .sort-select {
        padding: 8px 12px;
        background: #1a1a1a;
        border: 1px solid #333;
        color: #fff;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
  }

  /* Адаптивность для мобильных */
  @media (max-width: 1200px) {
      .products-grid {
          grid-template-columns: repeat(3, 1fr);
      }
  }

  @media (max-width: 768px) {
      .products-grid {
          grid-template-columns: repeat(2, 1fr);
      }
  }

  @media (max-width: 480px) {
      .products-grid {
          grid-template-columns: 1fr;
      }
  }

    /* ===== КАРТОЧКА ТОВАРА ===== */
    .product-card {
        background: #222;
        border: 1px solid rgba(255, 255, 255, 0.05);
        overflow: hidden;
        transition: all 0.3s;
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        border-color: rgba(200, 166, 86, 0.3);
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    .pc-image {
        position: relative;
        height: 200px;
        overflow: hidden;
    }

    .pc-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .product-card:hover .pc-image img {
        transform: scale(1.1);
    }

    .pc-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 4px 10px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        z-index: 2;
        border-radius: 3px;
        color: #fff;
    }

    .pc-badge.sale {
        background: #e74c3c;
    }

    .pc-badge.new {
        background: #c8a656;
        color: #1a1a1a;
    }

    .pc-badge.hit {
        background: #2ecc71;
        color: #1a1a1a;
    }

    .pc-badge.combo {
        background: #9b59b6;
    }

    .pc-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        opacity: 0;
        transition: opacity 0.3s;
        z-index: 2;
    }

    .product-card:hover .pc-actions {
        opacity: 1;
    }

    .pc-action-btn {
        width: 32px;
        height: 32px;
        background: rgba(0, 0, 0, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: all 0.3s;
        border-radius: 50%;
    }

    .pc-action-btn:hover {
        background: #c8a656;
        color: #1a1a1a;
        border-color: #c8a656;
    }

    .pc-info {
        padding: 18px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .pc-category {
        font-size: 9px;
        color: #c8a656;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .pc-name {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 8px;
        line-height: 1.3;
        color: #fff;
    }

    .pc-desc {
        font-size: 11px;
        color: #777;
        line-height: 1.6;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex-grow: 1;
    }

    .pc-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .pc-price-wrap {
        display: flex;
        flex-direction: column;
    }

    .pc-price {
        font-size: 18px;
        font-weight: 700;
        color: #c8a656;
    }

    .pc-old-price {
        font-size: 12px;
        color: #666;
        text-decoration: line-through;
    }

    .add-cart-btn {
        width: 36px;
        height: 36px;
        border: 1px solid rgba(200, 166, 86, 0.3);
        background: transparent;
        color: #c8a656;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.3s;
        border-radius: 4px;
    }

    .add-cart-btn:hover {
        background: #c8a656;
        color: #1a1a1a;
    }

    .details-link {
        font-size: 11px;
        color: #999;
        text-decoration: none;
        margin-right: 10px;
        transition: color 0.3s;
    }

    .details-link:hover {
        color: #fff;
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px;
        color: #666;
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .controls-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .price-filter-form {
            justify-content: center;
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
