<?php

$pageTitle = 'Главная';
require_once __DIR__ . '/includes/header.php';

// === ФУНКЦИЯ РАСЧЁТА ЦЕНЫ ===
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

// Хиты продаж
$popularProducts = $pdo->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_available = 1 AND p.is_hit = 1
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// Категории — корневые, с подсчётом товаров
$allCategories = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_available = 1) as product_count,
           (SELECT COUNT(*) FROM products p2 WHERE p2.category_id IN (
               SELECT id FROM categories WHERE parent_id = c.id
           ) AND p2.is_available = 1) as subcategory_count
    FROM categories c
    WHERE c.parent_id IS NULL
    ORDER BY c.sort_order
")->fetchAll();

// Отзывы
$reviews = $pdo->query("
    SELECT r.*, u.full_name, u.avatar_url
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 6
")->fetchAll();

// Промокоды
$promoCodes = $pdo->query("
    SELECT * FROM promo_codes
    WHERE is_active = 1 AND valid_to >= CURDATE() AND valid_from <= CURDATE()
    ORDER BY discount_value DESC LIMIT 3
")->fetchAll();

?>

    <main class="main-content" style="flex: 1; padding-top: 70px;">

    <!-- ===== HERO SECTION ===== -->
    <section class="hero-section" style="position: relative; min-height: 85vh; display: flex; align-items: center; overflow: hidden; background: ">
        <div style="position: relative; z-index: 2; max-width: 1300px; margin: 0 auto; padding: 60px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; width: 100%;">
            <div>
                <h1 style="font-size: clamp(28px, 5vw, 42px); font-weight: 300; line-height: 1.3; letter-spacing: 2px; margin-bottom: 25px;">
                    Вкусные блюда и напитки с <strong style="font-weight: 700; color: #c8a656;">доставкой</strong> или <strong style="font-weight: 700; color: #c8a656;">самовывозом</strong>
                </h1>
                <p style="font-size: 14px; color: #999; line-height: 1.8; margin-bottom: 30px; max-width: 480px;">
                    От классических стейков до авторских безалкогольных коктейлей. Готовим из свежих ингредиентов, доставляем горячим.
                </p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="/house_of_taste/pages/catalog.php" class="btn-primary" style="padding: 14px 32px; background: #c8a656; color: #1a1a1a; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; border: none; border-radius: 8px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 10px 30px rgba(200,166,86,0.3);">
                        <i class="fas fa-utensils"></i> Заказать сейчас
                    </a>
                    <a href="#categories" class="btn-outline" style="padding: 14px 32px; border: 2px solid rgba(255,255,255,0.2); color: #fff; font-size: 11px; font-weight: 500; letter-spacing: 2px; text-transform: uppercase; border-radius: 8px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-list"></i> Смотреть меню
                    </a>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 40px; max-width: 400px;">
                    <div style="text-align: center; padding: 15px; background: rgba(34,34,34,0.5); border-radius: 12px; border: 1px solid rgba(200,166,86,0.1);">
                        <i class="fas fa-truck" style="font-size: 24px; color: #c8a656; margin-bottom: 8px; display: block;"></i>
                        <span style="font-size: 11px; color: #ccc;">Бесплатная доставка от 1500₽</span>
                    </div>
                    <div style="text-align: center; padding: 15px; background: rgba(34,34,34,0.5); border-radius: 12px; border: 1px solid rgba(200,166,86,0.1);">
                        <i class="fas fa-clock" style="font-size: 24px; color: #c8a656; margin-bottom: 8px; display: block;"></i>
                        <span style="font-size: 11px; color: #ccc;">Доставка 45-60 мин</span>
                    </div>
                    <div style="text-align: center; padding: 15px; background: rgba(34,34,34,0.5); border-radius: 12px; border: 1px solid rgba(200,166,86,0.1);">
                        <i class="fas fa-shield-alt" style="font-size: 24px; color: #c8a656; margin-bottom: 8px; display: block;"></i>
                        <span style="font-size: 11px; color: #ccc;">Гарантия качества</span>
                    </div>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 25px;">
                <div style="background: rgba(34,34,34,0.9); border: 1px solid rgba(200,166,86,0.2); border-radius: 16px; padding: 25px; min-width: 280px; text-align: right; backdrop-filter: blur(10px);">
                    <div style="font-size: 12px; color: #999; line-height: 2; margin-bottom: 12px;">
                        <i class="fas fa-map-marker-alt"></i> Москва, ул. Тверская, 15
                    </div>
                    <a href="tel:+74951234567" style="font-size: 24px; font-weight: 300; color: #c8a656; display: block; margin-bottom: 8px; transition: color 0.3s;">
                        <i class="fas fa-phone"></i> 8 (495) 123-45-67
                    </a>
                    <a href="mailto:info@houseoftaste.ru" style="font-size: 11px; color: #c8a656; letter-spacing: 1px; transition: color 0.3s;">
                        <i class="fas fa-envelope"></i> INFO@HOUSETASTE.RU
                    </a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                    <div style="font-size: 11px; color: #888; letter-spacing: 1px;">
                        <i class="fas fa-clock"></i> Ежедневно 10:00 — 23:00
                    </div>
                    <a href="/house_of_taste/pages/catalog.php" class="btn-outline" style="padding: 16px 28px; border: 2px solid rgba(255,255,255,0.2); color: #fff; font-size: 11px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; border-radius: 8px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; min-width: 180px; justify-content: center;">
                        <i class="fas fa-store"></i> Самовывоз
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== PROMO BANNERS ===== -->
    <section class="promo-banners">
        <div class="promo-grid">
            <a href="/house_of_taste/auth/register.php" class="promo-card">
                <img src="/house_of_taste/public/img/promo/promo_banner_1.png" alt="Скидка на первый заказ" onerror="this.src='https://placehold.co/600x300/c8a656/1a1a1a?text=Скидка+10%'">
                <div class="promo-overlay">
                    <span class="promo-tag">Новым клиентам</span>
                    <div class="promo-title">Скидка 10% на первый заказ</div>
                    <div class="promo-desc">Зарегистрируйтесь и получите приветственный бонус</div>
                </div>
            </a>
            <a href="/house_of_taste/pages/catalog.php" class="promo-card">
                <img src="/house_of_taste/public/img/promo/promo_banner_2.png" alt="Бесплатная доставка" onerror="this.src='https://placehold.co/600x300/2ecc71/1a1a1a?text=Доставка+0₽'">
                <div class="promo-overlay">
                    <span class="promo-tag">Акция</span>
                    <div class="promo-title">Бесплатная доставка</div>
                    <div class="promo-desc">При заказе от 1500₽ — доставка за наш счёт</div>
                </div>
            </a>
            <a href="/house_of_taste/pages/catalog.php?category=2" class="promo-card">
                <img src="/house_of_taste/public/img/promo/promo_banner_3.png" alt="Бизнес-ланч" onerror="this.src='https://placehold.co/600x300/3498db/1a1a1a?text=Бизнес-ланч'">
                <div class="promo-overlay">
                    <span class="promo-tag">Пн-Пт 12:00-16:00</span>
                    <div class="promo-title">Комбо "Деловой обед"</div>
                    <div class="promo-desc">Суп + салат + горячее + напиток по специальной цене</div>
                </div>
            </a>
        </div>
    </section>

    <!-- ===== КАТЕГОРИИ ===== -->
    <section id="categories" class="categories-section" style="padding: 80px 20px; max-width: 1400px; margin: 0 auto;">
        <div class="section-header">
            <h2 style="font-size: 32px; font-weight: 300; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 15px;">
                Наше <span class="gold" style="font-weight: 700; background: linear-gradient(135deg, #c8a656, #e8c96a); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">меню</span>
            </h2>
            <p style="font-size: 14px; color: #888; max-width: 600px; margin: 0 auto 30px; line-height: 1.6;">
                Выберите категорию и откройте для себя мир изысканных вкусов — от авторских стейков до нежных десертов
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px;" id="categoriesGrid">

        <?php

        $categoryImages = [
            1 => '/public/img/categories/category_drinks.png',
            2 => '/public/img/categories/category_hot.png',
            3 => '/public/img/categories/category_cold.png',
            4 => '/public/img/categories/category_desserts.png',
            14 => '/public/img/categories/category_combo.png',
            15 => '/public/img/categories/category_soups.png',
        ];
        $categoryIcons = [
            1  => 'fas fa-glass-whiskey', 2  => 'fas fa-drumstick-bite', 3  => 'fas fa-bread-slice',
            4  => 'fas fa-ice-cream', 5  => 'fas fa-glass-cheers', 6  => 'fas fa-cocktail',
            7  => 'fas fa-wine-bottle', 8  => 'fas fa-hamburger', 9  => 'fas fa-fish',
            10 => 'fas fa-sandwich', 11 => 'fas fa-carrot', 12 => 'fas fa-cookie-bite',
            13 => 'fas fa-birthday-cake', 14 => 'fas fa-box-open', 15 => 'fas fa-soup',
        ];

        foreach ($allCategories as $cat):
            $totalCount = $cat['product_count'] + $cat['subcategory_count'];
            $categoryClass = '';
            if (in_array($cat['id'], [1, 5, 6, 7])) $categoryClass = 'data-category="drinks"';
            elseif (in_array($cat['id'], [14])) $categoryClass = 'data-category="extra"';
            else $categoryClass = 'data-category="main"';
            $categoryImg = $categoryImages[$cat['id']] ?? null;
            $iconClass = $cat['icon_class'] ?? ($categoryIcons[$cat['id']] ?? 'fas fa-utensils');
        ?>

        <a href="/house_of_taste/pages/catalog.php?category=<?= $cat['id'] ?>"
           class="category-card"
           <?= $categoryClass ?>
           style="background: linear-gradient(145deg, #222, #1a1a1a); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: block; position: relative;">

            <?php if ($categoryImg): ?>
            <div style="position: relative; height: 140px; overflow: hidden;">
                <img src="/house_of_taste<?= htmlspecialchars($categoryImg) ?>"
                     alt="<?= htmlspecialchars($cat['name']) ?>"
                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s;"
                     onerror="this.style.display='none'">
                <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 0%, rgba(26,26,26,0.9) 100%);"></div>
            </div>
            <?php endif; ?>

            <div style="padding: 20px; text-align: center; position: relative;">
                <div style="width: 60px; height: 60px; margin: 0 auto 12px; background: linear-gradient(135deg, rgba(200,166,86,0.15), rgba(200,166,86,0.05)); border: 2px solid rgba(200,166,86,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #c8a656; transition: all 0.4s; box-shadow: 0 8px 32px rgba(200,166,86,0.15);">
                    <i class="<?= htmlspecialchars($iconClass) ?>"></i>
                </div>
                <div style="font-size: 15px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #fff; margin-bottom: 8px;">
                    <?= htmlspecialchars($cat['name']) ?>
                </div>
                <?php if ($totalCount > 0): ?>
                <div style="font-size: 12px; color: #888; font-weight: 500;">
                    <?= $totalCount ?> <?= $totalCount == 1 ? 'позиция' : ($totalCount < 5 ? 'позиции' : 'позиций') ?>
                </div>
                <?php endif; ?>
                <?php if (in_array($cat['id'], [2, 8, 14])): ?>
                <div style="margin-top: 12px; padding: 5px 14px; background: linear-gradient(135deg, #c8a656, #e8c96a); color: #1a1a1a; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border-radius: 12px; display: inline-block;">
                    <i class="fas fa-fire"></i> Популярно
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="/house_of_taste/pages/catalog.php"
               style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: transparent; border: 2px solid rgba(200,166,86,0.3); color: #c8a656; font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; border-radius: 25px; text-decoration: none; transition: all 0.3s;">
                <i class="fas fa-list"></i> Все категории и подкатегории
            </a>
        </div>
    </section>

    <!-- ===== PROMO CODES ===== -->
    <section class="promo-codes-section">
        <div class="section-header">
            <h2>Активные <span class="gold">промокоды</span></h2>
            <p style="font-size: 14px; color: #888; max-width: 600px; margin: 0 auto 40px;">Введите код при оформлении заказа и получите приятный бонус</p>
        </div>
        <div class="promo-codes-grid">
            <?php if (count($promoCodes) > 0): ?>
                <?php foreach ($promoCodes as $promo):
                    $discountText = ($promo['discount_type'] === 'percent') ? '-' . $promo['discount_value'] . '%' : '-' . number_format($promo['discount_value'], 0, '.', ' ') . '₽';
                ?>
                <div class="promo-code-card">
                    <div class="promo-code-header">
                        <span class="promo-code-value"><?= htmlspecialchars($promo['code']) ?></span>
                        <button class="copy-btn" data-code="<?= htmlspecialchars($promo['code']) ?>"><i class="far fa-copy"></i> Копировать</button>
                    </div>
                    <p class="promo-code-desc"><?= htmlspecialchars($promo['description']) ?></p>
                    <div class="promo-code-conditions">
                        <span class="condition-tag highlight"><?= $discountText ?></span>
                        <?php if ($promo['min_order_amount'] > 0): ?><span class="condition-tag">От <?= number_format($promo['min_order_amount'], 0, '.', ' ') ?>₽</span><?php endif; ?>
                        <?php if ($promo['is_first_order_only']): ?><span class="condition-tag">Первый заказ</span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-tag" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>На данный момент активных промокодов нет</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===== ХИТЫ ПРОДАЖ ===== -->
    <section class="hits-section" style="padding: 60px 20px; background: #151515;">
        <div style="max-width: 1300px; margin: 0 auto;">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <h2><span class="gold">Хиты</span> продаж</h2>
                <p style="font-size: 13px; color: #777;">Популярные блюда и напитки от наших гостей</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
                <?php foreach ($popularProducts as $prod):
                    $priceData = calculateFinalPrice((float)$prod['price'], $prod['old_price'] ? (float)$prod['old_price'] : null, (float)($prod['discount_percent'] ?? 0));
                    $imagePath = $prod['image_url'] ? '/house_of_taste' . $prod['image_url'] : '/house_of_taste/public/img/placeholder.png';
                    $isCombo = ($prod['category_id'] == 14);
                ?>
                <div class="product-card" data-product-id="<?= $prod['id'] ?>">
                    <div class="pc-image">
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" onerror="this.src='/house_of_taste/public/img/placeholder.png'; this.onerror=null;">
                        <?php if ($isCombo): ?>
                            <span class="pc-badge combo">Комбо</span>
                        <?php else: ?>
                            <?php if($prod['is_hit']): ?><span class="pc-badge hit">Хит</span><?php endif; ?>
                            <?php if($priceData['hasDiscount']): ?><span class="pc-badge sale">-<?= $priceData['discountPercent'] ?>%</span><?php endif; ?>
                        <?php endif; ?>
                        <div class="pc-actions">
                            <button class="pc-action-btn" data-fav-id="<?= $prod['id'] ?>" title="В избранное"><i class="far fa-heart"></i></button>
                        </div>
                    </div>
                    <div class="pc-info">
                        <div class="pc-category"><?= htmlspecialchars($prod['category_name'] ?? 'Блюдо') ?></div>
                        <h3 class="pc-name"><?= htmlspecialchars($prod['name']) ?></h3>
                        <?php if($prod['weight_volume']): ?><div style="font-size:11px; color:#777; margin-bottom:8px;"><i class="fas fa-weight-hanging"></i> <?= htmlspecialchars($prod['weight_volume']) ?></div><?php endif; ?>
                        <div class="pc-desc"><?= htmlspecialchars(mb_substr($prod['description'] ?? '', 0, 60)) ?>...</div>
                        <div class="pc-bottom">
                            <div class="pc-price-wrap">
                                <span class="pc-price"><?= number_format($priceData['final'], 0, '.', ' ') ?> ₽</span>
                                <?php if($priceData['hasDiscount'] && $priceData['original']): ?><span class="pc-old-price"><?= number_format($priceData['original'], 0, '.', ' ') ?> ₽</span><?php endif; ?>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <a href="/house_of_taste/pages/product.php?id=<?= $prod['id'] ?>" class="details-link">Подробнее</a>
                                <!-- Кнопка корзины: для комбо и обычных товаров — одна функция -->
                                <button class="add-cart-btn" title="Добавить в корзину"
                                         onclick="simpleAddToCart(<?= $prod['id'] ?>, '<?= addslashes($prod['name']) ?>')">
                                        <i class="fas fa-shopping-basket"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-20">
                <a href="/house_of_taste/pages/catalog.php" class="btn-outline" style="padding: 14px 35px; border: 2px solid rgba(255,255,255,0.2); color: #fff; font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-th-large"></i> Смотреть всё меню
                </a>
            </div>
        </div>
    </section>

    <!-- ===== ОТЗЫВЫ ===== -->
    <section class="reviews-section">
        <div class="section-header">
            <h2>Отзывы <span class="gold">гостей</span></h2>
            <p style="font-size: 13px; color: #777;">Что говорят о нас наши постоянные посетители</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($reviews as $rev):
                $avatarPath = $rev['avatar_url'] ? '/house_of_taste' . $rev['avatar_url'] : '';
                $hasAvatar = $rev['avatar_url'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/house_of_taste' . $rev['avatar_url']);
                $initial = mb_strtoupper(mb_substr($rev['full_name'] ?? 'Г', 0, 1, 'UTF-8'), 'UTF-8');
                $productName = '';
                if ($rev['product_id']) {
                    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                    $stmt->execute([$rev['product_id']]);
                    $productName = $stmt->fetchColumn();
                }
            ?>
            <div class="review-card">
                <div class="review-header">
                    <?php if ($hasAvatar): ?>
                        <img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($rev['full_name']) ?>" class="reviewer-avatar" onerror="this.classList.add('placeholder'); this.src=''; this.onerror=null; this.textContent='<?= $initial ?>';">
                        <div class="reviewer-avatar placeholder" style="display:none;"><?= $initial ?></div>
                    <?php else: ?>
                        <div class="reviewer-avatar placeholder"><?= $initial ?></div>
                    <?php endif; ?>
                    <div class="reviewer-info">
                        <div class="reviewer-name"><?= htmlspecialchars($rev['full_name']) ?></div>
                        <div class="review-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
                    </div>
                </div>
                <div class="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?><i class="fas fa-star<?= $i <= ($rev['rating'] ?? 0) ? '' : ' far' ?>"></i><?php endfor; ?>
                </div>
                <?php if ($rev['title']): ?><h4 style="font-size:14px; font-weight:600; color:#fff; margin-bottom:8px;"><?= htmlspecialchars($rev['title']) ?></h4><?php endif; ?>
                <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'] ?? '')) ?></p>
                <?php if ($productName): ?><div class="review-product"><i class="fas fa-utensils"></i> О блюде: <?= htmlspecialchars($productName) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="/house_of_taste/pages/reviews.php" class="write-review-btn">
                <i class="fas fa-pen-to-square"></i> Написать отзыв
            </a>
        </div>
    </section>

</main>

<div id="toast"></div>

<script>
// ===== TOAST УВЕДОМЛЕНИЯ =====
function showToast(message, type) {
    if (type === undefined) type = 'success';
    var oldToast = document.getElementById('toast');
    if (oldToast) oldToast.remove();
    var toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'show ' + type;
    var iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
    toast.innerHTML = '<i class="fas fa-' + iconClass + '"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

// ===== КОПИРОВАНИЕ ПРОМОКОДА =====
document.addEventListener('DOMContentLoaded', function() {
    var copyBtns = document.querySelectorAll('.copy-btn');
    for (var i = 0; i < copyBtns.length; i++) {
        copyBtns[i].addEventListener('click', function(e) {
            e.preventDefault();
            var code = this.dataset.code;
            if (!code) return;
            navigator.clipboard.writeText(code).then(function() {
                var original = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check"></i> Скопировано';
                this.classList.add('copied');
                showToast('Промокод ' + code + ' скопирован!', 'success');
                var self = this;
                setTimeout(function() {
                    self.innerHTML = original;
                    self.classList.remove('copied');
                }, 2000);
            }.bind(this)).catch(function() {
                showToast('Не удалось скопировать', 'error');
            });
        });
    }
    syncFavorites();
});

// ===== ИНИЦИАЛИЗАЦИЯ ИЗБРАННОГО (остальное уже в header.php) =====
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>


<style>
    /* ===== PROMO BANNERS ===== */
    .promo-banners { padding: 40px 20px; background: linear-gradient(135deg, #1a1a1a 0%, #222 100%); }
    .promo-grid { max-width: 1400px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .promo-card { position: relative; border-radius: 16px; overflow: hidden; height: 180px; cursor: pointer; transition: all 0.4s ease; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    .promo-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 20px 60px rgba(200,166,86,0.2); }
    .promo-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
    .promo-card:hover img { transform: scale(1.1); }
    .promo-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(26,26,26,0.95) 0%, transparent 60%); display: flex; flex-direction: column; justify-content: flex-end; padding: 20px; }
    .promo-tag { display: inline-block; padding: 4px 12px; background: #c8a656; color: #1a1a1a; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; border-radius: 20px; margin-bottom: 8px; width: fit-content; }
    .promo-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .promo-desc { font-size: 12px; color: #aaa; }

    /* ===== PROMO CODES ===== */
    .promo-codes-section { padding: 60px 20px; background: #151515; position: relative; overflow: hidden; }
    .promo-codes-section::before { content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px; background: radial-gradient(circle, rgba(200,166,86,0.08) 0%, transparent 70%); pointer-events: none; }
    .section-header { text-align: center; margin-bottom: 40px; position: relative; z-index: 2; }
    .section-header h2 { font-size: 28px; font-weight: 300; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 15px; }
    .section-header h2 .gold { font-weight: 700; }
    .section-header p { font-size: 13px; color: #777; }
    .promo-codes-grid { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; position: relative; z-index: 2; }
    .promo-code-card { background: linear-gradient(145deg, #222, #1a1a1a); border: 1px solid rgba(200,166,86,0.2); border-radius: 16px; padding: 25px; transition: all 0.3s; position: relative; overflow: hidden; }
    .promo-code-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #c8a656, transparent); }
    .promo-code-card:hover { border-color: #c8a656; transform: translateY(-4px); box-shadow: 0 15px 40px rgba(200,166,86,0.15); }
    .promo-code-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .promo-code-value { font-family: 'Courier New', monospace; font-size: 20px; font-weight: 700; color: #c8a656; letter-spacing: 2px; background: rgba(200,166,86,0.1); padding: 8px 16px; border-radius: 8px; }
    .copy-btn { padding: 8px 16px; background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #aaa; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; border-radius: 6px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 6px; }
    .copy-btn:hover { border-color: #c8a656; color: #c8a656; background: rgba(200,166,86,0.1); }
    .copy-btn.copied { background: #2ecc71; border-color: #2ecc71; color: #fff; }
    .promo-code-desc { font-size: 13px; color: #999; margin-bottom: 12px; line-height: 1.6; }
    .promo-code-conditions { display: flex; flex-wrap: wrap; gap: 8px; }
    .condition-tag { font-size: 10px; color: #888; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 4px; }
    .condition-tag.highlight { color: #c8a656; background: rgba(200,166,86,0.1); font-weight: 600; }

    /* ===== REVIEWS ===== */
    .reviews-section { padding: 60px 20px; max-width: 1400px; margin: 0 auto; }
    .review-card { background: linear-gradient(145deg, #222, #1a1a1a); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 25px; transition: all 0.3s; }
    .review-card:hover { border-color: rgba(200,166,86,0.3); transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
    .review-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    .reviewer-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #c8a656; background: #333; }
    .reviewer-avatar.placeholder { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #c8a656, #e8c96a); color: #1a1a1a; font-weight: 700; font-size: 18px; border: none; }
    .reviewer-info { flex: 1; }
    .reviewer-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 3px; }
    .review-date { font-size: 11px; color: #666; }
    .review-stars { color: #c8a656; font-size: 12px; margin-bottom: 10px; }
    .review-text { font-size: 13px; color: #aaa; line-height: 1.7; }
    .review-product { font-size: 11px; color: #c8a656; margin-top: 10px; font-weight: 500; }
    .write-review-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; background: transparent; border: 2px solid rgba(200,166,86,0.3); color: #fff !important; font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; border-radius: 8px; text-decoration: none; transition: all 0.3s; margin-top: 20px; }
    .write-review-btn:hover { background: #c8a656; color: #1a1a1a !important; border-color: #c8a656; transform: translateY(-2px); }

    /* ===== PRODUCT CARD ===== */
    .product-card { background: #222; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; transition: all 0.3s; position: relative; display: flex; flex-direction: column; }
    .product-card:hover { border-color: rgba(200,166,86,0.3); transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
    .pc-image { position: relative; height: 200px; overflow: hidden; }
    .pc-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
    .product-card:hover .pc-image img { transform: scale(1.1); }
    .pc-badge { position: absolute; top: 10px; left: 10px; padding: 4px 10px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; border-radius: 4px; z-index: 2; }
    .pc-badge.sale { background: #e74c3c; color: #fff; }
    .pc-badge.hit { background: #2ecc71; color: #1a1a1a; }
    .pc-badge.new { background: #c8a656; color: #1a1a1a; }
    .pc-badge.combo { background: #9b59b6; color: #fff; }
    .pc-actions { position: absolute; top: 10px; right: 10px; display: flex; flex-direction: column; gap: 5px; opacity: 0; transition: opacity 0.3s; z-index: 2; }
    .product-card:hover .pc-actions { opacity: 1; }
    .pc-action-btn { width: 32px; height: 32px; background: rgba(0,0,0,0.7); border: 1px solid rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; transition: all 0.3s; border-radius: 50%; }
    .pc-action-btn:hover { background: #c8a656; color: #1a1a1a; border-color: #c8a656; }
    .pc-info { padding: 18px; flex: 1; display: flex; flex-direction: column; }
    .pc-category { font-size: 9px; color: #c8a656; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; font-weight: 600; }
    .pc-name { font-size: 15px; font-weight: 600; margin-bottom: 8px; line-height: 1.3; color: #fff; }
    .pc-desc { font-size: 11px; color: #777; line-height: 1.6; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex-grow: 1; }
    .pc-bottom { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); }
    .pc-price-wrap { display: flex; flex-direction: column; }
    .pc-price { font-size: 18px; font-weight: 700; color: #c8a656; }
    .pc-old-price { font-size: 12px; color: #666; text-decoration: line-through; }
    .add-cart-btn { width: 36px; height: 36px; border: 1px solid rgba(200,166,86,0.3); background: transparent; color: #c8a656; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.3s; border-radius: 4px; }
    .add-cart-btn:hover { background: #c8a656; color: #1a1a1a; }
    .details-link { font-size: 11px; color: #999; text-decoration: none; margin-right: 10px; transition: color 0.3s; }
    .details-link:hover { color: #fff; }

    /* ===== TOAST ===== */
    #toast { visibility: hidden; min-width: 250px; background: #333; color: #fff; text-align: center; border-radius: 8px; padding: 12px 20px; position: fixed; z-index: 9999; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
    #toast.show { visibility: visible; animation: fadein 0.3s, fadeout 0.3s 4.7s forwards; }
    #toast.success { border-left: 4px solid #2ecc71; }
    #toast.error { border-left: 4px solid #e74c3c; }

    @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
    @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }

    /* ===== UTILS ===== */
    .text-center { text-align: center; }
    .mt-20 { margin-top: 20px; }
    @media (max-width: 768px) {
        .promo-grid, .promo-codes-grid, .reviews-grid { grid-template-columns: 1fr; }
        .section-header h2 { font-size: 22px; }
    }
</style>
