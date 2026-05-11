<?php

$pageTitle = 'Карточка товара';

require_once __DIR__ . '/../includes/header.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: /house_of_taste/pages/catalog.php');
    exit;
}

// 1. Информация о товаре
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.id as category_id,
           s.full_name as chef_name, s.position as chef_position
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN staff s ON p.chef_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /house_of_taste/pages/catalog.php');
    exit;
}

// 2. Отзывы
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 10
");
$stmt->execute([$productId]);
$reviews = $stmt->fetchAll();

// Рейтинг
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ? AND status = 'approved'");
$stmt->execute([$productId]);
$ratingData = $stmt->fetch();

// 3. Похожие товары
$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND is_available = 1 ORDER BY is_hit DESC LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

// 4. Проверка прав на отзыв
$user = new User();
$isLoggedIn = $user->isLoggedIn();
$userHasOrdered = false;
$alreadyReviewed = false;
$canReview = false;
$reviewMessage = '';

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];

    // Проверяем, есть ли завершенный заказ с этим товаром
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('delivered', 'ready_pickup')
    ");
    $stmtCheck->execute([$userId, $productId]);
    $userHasOrdered = $stmtCheck->fetchColumn() > 0;

    // Проверяем, не оставлял ли он уже отзыв
    $stmtRevCheck = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ? AND product_id = ?");
    $stmtRevCheck->execute([$userId, $productId]);
    $alreadyReviewed = $stmtRevCheck->fetchColumn() > 0;

    if ($alreadyReviewed) {
        $canReview = false;
        $reviewMessage = 'Вы уже оставили отзыв к этому товару.';
    } elseif ($userHasOrdered) {
        $canReview = true;
    } else {
        $canReview = false;
        $reviewMessage = 'Чтобы оставить отзыв, вам необходимо заказать это блюдо.';
    }
} else {
    $canReview = false;
    $reviewMessage = 'Для написания отзыва необходимо <a href="/house_of_taste/auth/login.php" style="color:#c8a656; text-decoration:underline;">войти</a> или <a href="/house_of_taste/auth/register.php" style="color:#c8a656; text-decoration:underline;">зарегистрироваться</a>.';
}

// Цены и картинки
$finalPrice = $product['price'];
$hasDiscount = $product['old_price'] && $product['old_price'] > $product['price'];
$discountPercent = $hasDiscount ? round((($product['old_price'] - $product['price']) / $product['old_price']) * 100) : 0;
$imagePath = $product['image_url'] ? '/house_of_taste' . $product['image_url'] : '/house_of_taste/public/img/placeholder.png';
$pageTitle = htmlspecialchars($product['name']);
?>

<div class="product-container">

    <!-- Основной блок -->
    <div class="product-main">
        <div class="product-gallery">
            <div class="main-image">
                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <div class="image-badges">
                    <?php if($product['is_hit']): ?><span class="badge badge-hit">Хит</span><?php endif; ?>
                    <?php if($hasDiscount): ?><span class="badge badge-sale">-<?= $discountPercent ?>%</span><?php endif; ?>
                </div>
                <button class="favorite-btn" data-fav-id="<?= $product['id'] ?>" id="favBtn">
                    <i class="far fa-heart"></i>
                </button>
            </div>
        </div>

        <div class="product-info">
            <div>
                <span class="prod-cat"><?= htmlspecialchars($product['category_name']) ?></span>
                <h1><?= htmlspecialchars($product['name']) ?></h1>
            </div>

            <div class="rating-box">
                <div class="stars">
                    <?php for($i=1;$i<=5;$i++) echo '<i class="fas fa-star'.($i<=round($ratingData['avg_rating']??0)?'':' far').'"></i>'; ?>
                </div>
                <span><?= number_format($ratingData['avg_rating']??0, 1) ?> (<?= $ratingData['review_count'] ?> отзывов)</span>
            </div>

            <p class="description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <div class="meta-box">
                <div class="meta-row">
                    <span class="meta-label">Вес/Объем</span>
                    <span class="meta-val"><?= htmlspecialchars($product['weight_volume']) ?></span>
                </div>
                <?php if($product['chef_name']): ?>
                <div class="meta-row">
                    <span class="meta-label">Шеф-повар</span>
                    <span class="meta-val"><?= htmlspecialchars($product['chef_name']) ?></span>
                </div>
                <?php endif; ?>

                <?php if($product['ingredients']): ?>
                <div class="ingredients-block">
                    <div class="ingredients-title">Состав:</div>
                    <div class="ingredients-text"><?= nl2br(htmlspecialchars($product['ingredients'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="price-area">
                <span class="curr-price"><?= number_format($finalPrice, 0, '.', ' ') ?> ₽</span>
                <?php if($hasDiscount): ?>
                    <span class="old-price"><?= number_format($product['old_price'], 0, '.', ' ') ?> ₽</span>
                    <span class="discount-badge">-<?= $discountPercent ?>%</span>
                <?php endif; ?>
            </div>

            <?php if($product['is_available']): ?>
            <div class="cart-controls">
                <div class="qty-wrap">
                    <button class="qty-btn" onclick="changeQty(-1)">-</button>
                    <input type="text" class="qty-inp" value="1" id="qtyInput" readonly>
                    <button class="qty-btn" onclick="changeQty(1)">+</button>
                </div>
                <button class="add-cart-btn" onclick="addToCartProduct()">
                    <i class="fas fa-shopping-basket"></i> В корзину
                </button>
            </div>
            <?php else: ?>
            <div style="padding: 15px; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 8px; color: #e74c3c; text-align: center;">
                <i class="fas fa-times-circle"></i> Товар временно отсутствует
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Отзывы -->
    <div id="reviews">
        <h2 class="section-title">Отзывы покупателей</h2>

        <?php if(count($reviews) > 0): ?>
            <?php foreach($reviews as $rev): ?>
            <div class="review-card">
                <div class="rev-head">
                    <span class="rev-name"><?= htmlspecialchars($rev['full_name']) ?></span>
                    <span class="rev-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></span>
                </div>
                <div class="stars" style="font-size:12px; margin-bottom:8px;">
                    <?php for($i=1;$i<=5;$i++) echo '<i class="fas fa-star'.($i<=$rev['rating']?'':' far').'"></i>'; ?>
                </div>
                <p class="rev-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#666; margin-bottom:30px;">Отзывов пока нет.</p>
        <?php endif; ?>

        <!-- Блок добавления отзыва -->
        <div style="margin-top: 40px;">
            <h3 style="color:#fff; margin-bottom:15px;">Оставить отзыв</h3>

            <?php if($canReview): ?>
                <form class="review-form-box" action="/house_of_taste/api/review_add.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">

                    <div class="form-group">
                        <label class="form-label">Ваша оценка</label>
                        <select name="rating" class="form-input" style="width:100px;">
                            <option value="5">5 - Отлично</option>
                            <option value="4">4 - Хорошо</option>
                            <option value="3">3 - Нормально</option>
                            <option value="2">2 - Плохо</option>
                            <option value="1">1 - Ужасно</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Комментарий</label>
                        <textarea name="comment" class="form-textarea" required placeholder="Напишите ваши впечатления..."></textarea>
                    </div>

                    <button type="submit" class="submit-rev-btn">Отправить отзыв</button>
                </form>
            <?php else: ?>
                <div class="alert-box <?= $isLoggedIn ? 'alert-warning' : 'alert-info' ?>">
                    <i class="fas fa-info-circle"></i> <?= $reviewMessage ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Похожие товары -->
    <?php if(count($relatedProducts) > 0): ?>
    <div style="margin-top: 80px;">
        <h2 class="section-title">Похожие товары</h2>
        <div class="related-grid">
            <?php foreach($relatedProducts as $rel):
                $rImg = $rel['image_url'] ? '/house_of_taste'.$rel['image_url'] : '/house_of_taste/public/img/placeholder.png';
                $rPrice = $rel['old_price'] ?? $rel['price'];
            ?>
            <a href="?id=<?= $rel['id'] ?>" class="rel-card">
                <img src="<?= htmlspecialchars($rImg) ?>" class="rel-img">
                <div class="rel-info">
                    <div class="rel-title"><?= htmlspecialchars($rel['name']) ?></div>
                    <div class="rel-price"><?= number_format($rPrice, 0, '.', ' ') ?> ₽</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Управление количеством
let qty = 1;
function changeQty(d) {
    qty += d;
    if(qty < 1) qty = 1;
    document.getElementById('qtyInput').value = qty;
}

// Добавление в корзину
function addToCartProduct() {
    fetch('/house_of_taste/api/cart_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: <?= $product['id'] ?>,
            action: 'add',
            quantity: qty
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            showToast('Товар добавлен в корзину');
            updateCartBadge(data.cart_count);
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        showToast('Ошибка сети', 'error');
    });
}

// Инициализация избранного
document.addEventListener('DOMContentLoaded', () => {
    const favs = getFavorites();
    const btn = document.getElementById('favBtn');
    const id = <?= $product['id'] ?>;

    if(favs.includes(id)) {
        btn.classList.add('active');
        btn.querySelector('i').classList.replace('far', 'fas');
    }

    btn.onclick = () => toggleFavorite(id, btn);
});
</script>

<style>
    /* Основные стили */
    .product-container { max-width: 1400px; margin: 0 auto; padding: 100px 20px 60px; }

    /* Сетка товара - исправлено */
    .product-main {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 50px;
        margin-bottom: 60px;
        align-items: start;
    }

    /* Галерея */
    .main-image {
        position: relative;
        background: #222;
        border-radius: 16px;
        overflow: hidden;
        aspect-ratio: 1;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    .main-image:hover img { transform: scale(1.05); }

    .image-badges {
        position: absolute;
        top: 15px;
        left: 15px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        z-index: 2;
    }
    .badge {
        padding: 6px 12px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        border-radius: 4px;
        color: #fff;
    }
    .badge-hit { background: #c8a656; color: #1a1a1a; }
    .badge-sale { background: #e74c3c; }

    .favorite-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(0,0,0,0.6);
        border: 1px solid rgba(255,255,255,0.2);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: 0.3s;
        z-index: 2;
    }
    .favorite-btn:hover { background: #fff; color: #1a1a1a; }
    .favorite-btn.active { background: #e74c3c; border-color: #e74c3c; color: #fff; }

    /* Инфо о товаре */
    .product-info {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .product-info h1 {
        font-size: 36px;
        font-weight: 300;
        color: #fff;
        margin: 0;
    }
    .prod-cat {
        color: #c8a656;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 2px;
        font-weight: 600;
    }

    .rating-box {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #888;
        font-size: 14px;
    }
    .stars { color: #c8a656; }

    .description {
        color: #bbb;
        line-height: 1.8;
        font-size: 15px;
        margin: 0;
    }

    /* Мета-блок с составом */
    .meta-box {
        background: #222;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .meta-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .meta-row:last-child {
        border: none;
        margin: 0;
        padding: 0;
    }
    .meta-label { color: #888; }
    .meta-val { color: #fff; font-weight: 500; }

    .ingredients-block {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.05);
    }
    .ingredients-title {
        color: #888;
        font-size: 13px;
        margin-bottom: 8px;
    }
    .ingredients-text {
        color: #aaa;
        font-size: 14px;
        line-height: 1.6;
    }

    /* Цена */
    .price-area {
        display: flex;
        align-items: baseline;
        gap: 15px;
    }
    .curr-price {
        font-size: 32px;
        font-weight: 700;
        color: #c8a656;
    }
    .old-price {
        font-size: 20px;
        text-decoration: line-through;
        color: #666;
    }
    .discount-badge {
        background: #e74c3c;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
    }

    /* Корзина */
    .cart-controls {
        display: flex;
        gap: 15px;
    }
    .qty-wrap {
        display: flex;
        border: 1px solid #444;
        border-radius: 8px;
        overflow: hidden;
        height: 50px;
    }
    .qty-btn {
        width: 40px;
        background: #222;
        border: none;
        color: #fff;
        cursor: pointer;
        font-size: 18px;
        transition: 0.2s;
    }
    .qty-btn:hover { background: #333; color: #c8a656; }
    .qty-inp {
        width: 50px;
        background: #1a1a1a;
        border: none;
        color: #fff;
        text-align: center;
        font-weight: bold;
    }

    .add-cart-btn {
        flex: 1;
        background: #c8a656;
        color: #1a1a1a;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
        transition: 0.3s;
        height: 50px;
    }
    .add-cart-btn:hover { background: #e8c96a; }

    /* Отзывы */
    .section-title {
        font-size: 24px;
        margin-bottom: 30px;
        color: #fff;
        border-bottom: 1px solid #333;
        padding-bottom: 15px;
    }

    .review-card {
        background: #222;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .rev-head {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .rev-name { font-weight: 600; color: #fff; }
    .rev-date { font-size: 12px; color: #666; }
    .rev-text { color: #aaa; font-size: 14px; line-height: 1.6; }

    /* Форма отзыва */
    .review-form-box {
        background: #222;
        padding: 25px;
        border-radius: 12px;
        margin-top: 30px;
        border: 1px solid #c8a656;
    }
    .form-group { margin-bottom: 15px; }
    .form-label {
        display: block;
        margin-bottom: 5px;
        color: #ccc;
        font-size: 13px;
    }
    .form-input, .form-textarea {
        width: 100%;
        background: #1a1a1a;
        border: 1px solid #444;
        padding: 10px;
        color: #fff;
        border-radius: 6px;
        font-family: inherit;
    }
    .form-textarea { height: 100px; resize: vertical; }
    .submit-rev-btn {
        background: #c8a656;
        color: #1a1a1a;
        border: none;
        padding: 10px 25px;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
    }

    .alert-box {
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
        font-size: 14px;
    }
    .alert-info {
        background: rgba(52, 152, 219, 0.1);
        border: 1px solid #3498db;
        color: #3498db;
    }
    .alert-warning {
        background: rgba(231, 76, 60, 0.1);
        border: 1px solid #e74c3c;
        color: #e74c3c;
    }

    /* Похожие */
    .related-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    .rel-card {
        background: #222;
        border-radius: 12px;
        overflow: hidden;
        transition: 0.3s;
        text-decoration: none;
    }
    .rel-card:hover { transform: translateY(-5px); }
    .rel-img { height: 180px; width: 100%; object-fit: cover; }
    .rel-info { padding: 15px; }
    .rel-title {
        font-size: 14px;
        color: #fff;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rel-price { color: #c8a656; font-weight: 700; }

    @media (max-width: 992px) {
        .product-main { grid-template-columns: 1fr; }
        .related-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
