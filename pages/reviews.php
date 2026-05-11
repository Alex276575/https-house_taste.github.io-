<?php

$pageTitle = 'Отзывы';
require_once __DIR__ . '/../includes/header.php';

if (!class_exists('Review')) require_once __DIR__ . '/../classes/Review.php';
if (!class_exists('Product')) require_once __DIR__ . '/../classes/Product.php';

$review = new Review();
$user = new User();
$product = new Product();

// Получаем все продукты для формы (потом отфильтруем)
$allProducts = $product->getAll();

// Обработка добавления отзыва
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user->isLoggedIn()) {
    $productId = $_POST['product_id'] ?? null;
    $rating = $_POST['rating'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    if ($productId && $rating >= 1 && $rating <= 5 && $comment) {
        if ($user->hasOrderedProduct($_SESSION['user_id'], $productId)) {
            $result = $review->add($_SESSION['user_id'], $productId, $rating, $title, $comment);
            $message = $result['message'] ?? ($result ? 'Отзыв отправлен на модерацию!' : 'Ошибка при сохранении');
        } else {
            $message = 'Вы не заказывали это блюдо. Отзыв можно оставить только о заказанных товарах.';
        }
    }
}

$reviews = $review->getApproved(null, 20);
$orderedProducts = $user->isLoggedIn() ? $user->getOrderedProducts($_SESSION['user_id']) : [];
?>

<section class="reviews-page">
    <div class="reviews-header-actions">
        <a href="#reviews-list" class="btn-view-reviews">
            <i class="fas fa-comments"></i>
            Посмотреть отзывы других
        </a>
    </div>

    <h1 class="page-title">Отзывы <span class="gold">гостей</span></h1>
    <p class="section-subtitle">Что говорят о нас наши постоянные посетители</p>

    <?php if ($message): ?>
    <div class="alert alert-<?= strpos($message, 'Ошибка') !== false || strpos($message, '') !== false ? 'danger' : 'success' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- ЛОГИКА ОТОБРАЖЕНИЯ ФОРМЫ -->
    <?php if (!$user->isLoggedIn()): ?>
        <div class="alert alert-info">
            <i class="fas fa-lock me-2"></i>
            <a href="/house_of_taste/auth/login.php">Войдите</a>, чтобы оставить отзыв и делиться впечатлениями о наших блюдах.
        </div>

    <?php elseif (!$user->hasAnyOrder($_SESSION['user_id'])): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Чтобы оставить отзыв, необходимо сначала сделать заказ в нашем ресторане.
            <br><br>
            <a href="/house_of_taste/pages/catalog.php" class="btn-primary" style="width:auto; display:inline-flex; margin-top:10px;">
                <i class="fas fa-utensils"></i>Перейти в меню
            </a>
        </div>

    <?php elseif (empty($orderedProducts)): ?>
        <div class="alert alert-info">
            <i class="fas fa-check-circle me-2"></i>
            Спасибо за ваши заказы! Как только они будут доставлены, вы сможете оставить отзыв о выбранных блюдах.
            <br><br>
            <a href="/house_of_taste/pages/catalog.php" class="btn-primary" style="width:auto; display:inline-flex; margin-top:10px;">
                <i class="fas fa-shopping-basket"></i>Сделать новый заказ
            </a>
        </div>

    <?php else: ?>
        <div class="review-form-wrapper">
            <h2><i class="fas fa-pen-to-square me-2"></i>Оставить отзыв</h2>
            <form method="POST" class="review-form">
                <div class="form-group">
                    <label>Выберите блюдо</label>
                    <select name="product_id" required>
                        <option value="">-- Выберите из заказанных --</option>
                        <?php foreach ($orderedProducts as $op):
                            $prod = array_filter($allProducts, fn($p) => $p['id'] == $op['product_id']);
                            $prod = reset($prod);
                        ?>
                        <option value="<?= $op['product_id'] ?>"><?= htmlspecialchars($prod['name'] ?? 'Товар #' . $op['product_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ваша оценка</label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                        <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Заголовок</label>
                    <input type="text" name="title" required placeholder="Кратко о вашем впечатлении" maxlength="200">
                </div>

                <div class="form-group">
                    <label>Комментарий</label>
                    <textarea name="comment" rows="4" required placeholder="Расскажите подробнее: вкус, подача, впечатления..." maxlength="1000"></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Отправить отзыв
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ===== СПИСОК ОТЗЫВОВ ===== -->
    <div id="reviews-list" class="reviews-list">
        <?php if (empty($reviews)): ?>
        <div class="empty-reviews">
            <i class="fas fa-comment-alt"></i>
            <p>Пока нет отзывов. Будьте первым!</p>
            <?php if ($user->isLoggedIn() && !empty($orderedProducts)): ?>
            <a href="#" class="btn-outline" onclick="document.querySelector('.review-form-wrapper')?.scrollIntoView({behavior:'smooth'}); return false;">
                <i class="fas fa-pen"></i>Написать отзыв
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
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
                        <img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($rev['full_name']) ?>" class="reviewer-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="reviewer-avatar" style="display:none;"><?= $initial ?></div>
                    <?php else: ?>
                        <div class="reviewer-avatar"><?= $initial ?></div>
                    <?php endif; ?>
                    <div class="reviewer-info">
                        <div class="reviewer-name"><?= htmlspecialchars($rev['full_name'] ?? 'Гость') ?></div>
                        <div class="review-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
                    </div>
                </div>
                <div class="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?= $i <= ($rev['rating'] ?? 0) ? '' : ' far' ?>"></i>
                    <?php endfor; ?>
                </div>
                <?php if ($rev['title']): ?>
                <h4 style="font-size:14px; font-weight:600; color:#fff; margin-bottom:8px;"><?= htmlspecialchars($rev['title']) ?></h4>
                <?php endif; ?>
                <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'] ?? '')) ?></p>
                <?php if ($productName): ?>
                    <div class="review-product">
                        <i class="fas fa-utensils"></i>О блюде: <?= htmlspecialchars($productName) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<style>

:root { --gold: #c8a656; --gold-hover: #e8c96a; --dark-bg: #1a1a1a; --card-bg: #222; --text-primary: #fff; --text-secondary: #aaa; --border-color: rgba(200,166,86,0.2); }

.reviews-page {
    padding: 100px 20px 60px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-title {
    font-size: 32px;
    font-weight: 300;
    letter-spacing: 4px;
    text-transform: uppercase;
    margin-bottom: 15px;
    text-align: center;
}
.page-title .gold {
    font-weight: 700;
    background: linear-gradient(135deg, var(--gold), var(--gold-hover));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.section-subtitle {
    font-size: 14px;
    color: #888;
    max-width: 600px;
    margin: 0 auto 40px;
    text-align: center;
    line-height: 1.6;
}

.reviews-header-actions {
    text-align: center;
    margin-bottom: 40px;
}
.btn-view-reviews {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: transparent;
    border: 2px solid var(--gold);
    color: #fff !important;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s;
}
.btn-view-reviews:hover {
    background: var(--gold);
    color: #1a1a1a !important;
    transform: translateY(-2px);
}

/* ===== ALERTS ===== */
.alert {
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
    font-size: 14px;
}
.alert-info { background: rgba(52,152,219,0.1); border: 1px solid rgba(52,152,219,0.3); color: #3498db; }
.alert-success { background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.3); color: #2ecc71; }
.alert-danger { background: rgba(231,76,60,0.1); border: 1px solid rgba(231,76,60,0.3); color: #e74c3c; }
.alert a { color: inherit; font-weight: 600; text-decoration: none; border-bottom: 1px dashed currentColor; }
.alert a:hover { border-bottom-style: solid; }

/* ===== REVIEW FORM ===== */
.review-form-wrapper {
    background: linear-gradient(145deg, var(--card-bg), #1a1a1a);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 50px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.review-form-wrapper h2 {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 25px;
    text-align: center;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.form-group select, .form-group input, .form-group textarea {
    width: 100%;
    background: #2a2a2a;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 12px 14px;
    color: var(--text-primary);
    font-size: 13px;
    transition: border-color 0.2s;
}
.form-group select:focus, .form-group input:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--gold);
}
.form-group textarea { resize: vertical; min-height: 100px; }
.form-group small { display: block; margin-top: 5px; font-size: 11px; color: #666; }

/* Rating Input */
.rating-input { display: flex; justify-content: center; gap: 5px; flex-direction: row-reverse; }
.rating-input input { display: none; }
.rating-input label {
    font-size: 24px;
    color: #3a3a3a;
    cursor: pointer;
    transition: color 0.15s, transform 0.15s;
    line-height: 1;
}
.rating-input label:hover, .rating-input label:hover ~ label, .rating-input input:checked ~ label {
    color: var(--gold);
    transform: scale(1.1);
}

/* Submit Button */
.btn-primary {
    width: 100%;
    padding: 14px;
    background: var(--gold);
    color: #1a1a1a !important;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-primary:hover {
    background: var(--gold-hover);
    transform: translateY(-2px);
}
.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

/* ===== REVIEW CARDS ===== */
.reviews-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}
.review-card {
    background: linear-gradient(145deg, var(--card-bg), #1a1a1a);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s;
}
.review-card:hover {
    border-color: rgba(200,166,86,0.3);
    transform: translateY(-4px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
}
.review-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
.reviewer-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--gold);
    background: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: var(--dark-bg);
}
.reviewer-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.reviewer-info { flex: 1; }
.reviewer-name { font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 3px; }
.review-date { font-size: 11px; color: #666; }
.review-stars { color: var(--gold); font-size: 12px; margin-bottom: 10px; }
.review-text { font-size: 13px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 10px; }
.review-product {
    font-size: 11px;
    color: var(--gold);
    margin-top: 10px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}
.review-product i { font-size: 10px; }

/* Empty state */
.empty-reviews { text-align: center; padding: 60px 20px; color: #666; }
.empty-reviews i { font-size: 40px; margin-bottom: 15px; color: #333; }
.empty-reviews p { font-size: 16px; margin-bottom: 20px; }
.empty-reviews .btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border: 2px solid rgba(255,255,255,0.2);
    color: #fff !important;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s;
}
.empty-reviews .btn-outline:hover { border-color: var(--gold); color: var(--gold) !important; }

/* Responsive */
@media (max-width: 768px) {
    .reviews-page { padding: 90px 15px 40px; }
    .reviews-list { grid-template-columns: 1fr; }
    .review-form-wrapper { padding: 25px 20px; }
    .page-title { font-size: 24px; }
}

</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
