<?php

$pageTitle = 'Модерация отзывов';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';
// === ПРОВЕРКА ПРАВ ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php');
    exit;
}

// === ОБРАБОТКА ДЕЙСТВИЙ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reviewId = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reviewId]);
        $message = 'Отзыв одобрен';
        $msgType = 'success';
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reviewId]);
        $message = 'Отзыв отклонён';
        $msgType = 'warning';
    }

    $_SESSION['admin_message'] = $message;
    $_SESSION['admin_message_type'] = $msgType;
    header('Location: reviews.php');
    exit;
}

// === ФИЛЬТРАЦИЯ ===
$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT r.*, u.full_name, u.login, p.name as product_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN products p ON r.product_id = p.id
        WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Статистика
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='approved'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='rejected'")->fetchColumn(),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-comment-alt"></i> Модерация отзывов</h1>
            <p>Проверка и публикация отзывов клиентов</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-comments"></i>
                <div>
                    <span class="stat-num"><?= $stats['total'] ?></span>
                    <span class="stat-label">Всего отзывов</span>
                </div>
            </div>
            <div class="stat-card warning">
                <i class="fas fa-clock"></i>
                <div>
                    <span class="stat-num"><?= $stats['pending'] ?></span>
                    <span class="stat-label">На модерации</span>
                </div>
            </div>
            <div class="stat-card success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <span class="stat-num"><?= $stats['approved'] ?></span>
                    <span class="stat-label">Опубликовано</span>
                </div>
            </div>
            <div class="stat-card danger">
                <i class="fas fa-times-circle"></i>
                <div>
                    <span class="stat-num"><?= $stats['rejected'] ?></span>
                    <span class="stat-label">Отклонено</span>
                </div>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="card">
            <form method="GET" class="filters-bar">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Все статусы</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                        На модерации (<?= $stats['pending'] ?>)
                    </option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>
                        Одобрено (<?= $stats['approved'] ?>)
                    </option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>
                        Отклонено (<?= $stats['rejected'] ?>)
                    </option>
                </select>
                <a href="reviews.php" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Сбросить
                </a>
            </form>
        </div>

        <!-- Список отзывов -->
        <div class="card">
            <div class="reviews-list">
                <?php foreach ($reviews as $rev): ?>
                <div class="review-card">
                    <!-- Шапка отзыва -->
                    <div class="review-header">
                        <div class="review-author">
                            <div class="author-avatar">
                                <?= mb_substr($rev['full_name'] ?? $rev['login'] ?? 'A', 0, 1) ?>
                            </div>
                            <div class="author-info">
                                <strong><?= htmlspecialchars($rev['full_name'] ?? $rev['login'] ?? 'Аноним') ?></strong>
                                <span class="review-date">
                                    <i class="far fa-clock"></i>
                                    <?= date('d.m.Y в H:i', strtotime($rev['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <span class="status-badge status-<?= $rev['status'] ?>">
                            <?= $rev['status'] === 'pending' ? 'На модерации' :
                                ($rev['status'] === 'approved' ? ' Опубликовано' : 'Отклонено') ?>
                        </span>
                    </div>

                    <!-- Товар (если есть) -->
                    <?php if ($rev['product_name']): ?>
                    <div class="review-product">
                        <i class="fas fa-utensils"></i>
                        <span><?= htmlspecialchars($rev['product_name']) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Рейтинг -->
                    <div class="review-rating">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fas fa-star" style="color: <?= $i <= $rev['rating'] ? 'var(--gold)' : '#444' ?>; font-size: 14px;"></i>
                        <?php endfor; ?>
                        <span class="rating-value"><?= $rev['rating'] ?>/5</span>
                    </div>

                    <!-- Заголовок и текст -->
                    <?php if ($rev['title']): ?>
                    <h4 class="review-title"><?= htmlspecialchars($rev['title']) ?></h4>
                    <?php endif; ?>

                    <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>

                    <!-- Кнопки действий -->
                    <?php if ($rev['status'] === 'pending'): ?>
                    <div class="review-actions">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Одобрить
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $rev['id'] ?>">
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Отклонить
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-alt"></i>
                    <p>Отзывы не найдены</p>
                    <?php if ($statusFilter !== 'all'): ?>
                        <a href="reviews.php" class="btn btn-outline">Показать все</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
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

/* CARDS */
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px;margin-bottom:20px}

/* FILTERS */
.filters-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.filters-bar select{padding:10px 15px;background:var(--gray-light);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text);font-size:13px;min-width:200px;transition:0.2s}
.filters-bar select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(200,166,86,0.2)}

/* REVIEWS LIST */
.reviews-list{display:flex;flex-direction:column;gap:20px}

/* REVIEW CARD */
.review-card{background:var(--gray-light);border-radius:12px;padding:20px;border:1px solid rgba(255,255,255,0.05);transition:0.2s}
.review-card:hover{border-color:rgba(200,166,86,0.3)}

/* REVIEW HEADER */
.review-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;padding-bottom:15px;border-bottom:1px solid rgba(255,255,255,0.08)}
.review-author{display:flex;align-items:center;gap:12px}
.author-avatar{width:40px;height:40px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#1a1a1a;font-size:16px}
.author-info{display:flex;flex-direction:column;gap:3px}
.author-info strong{font-size:14px}
.review-date{font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px}
.review-date i{font-size:10px}

/* STATUS BADGES */
.status-badge{padding:5px 14px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:inline-flex;align-items:center;gap:4px}
.status-pending{background:rgba(243,156,18,0.15);color:var(--warning)}.status-approved{background:rgba(46,204,113,0.15);color:var(--success)}.status-rejected{background:rgba(231,76,60,0.15);color:var(--danger)}

/* REVIEW PRODUCT */
.review-product{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gold);margin-bottom:12px;padding:8px 12px;background:rgba(200,166,86,0.1);border-radius:6px;width:fit-content}
.review-product i{font-size:11px}

/* REVIEW RATING */
.review-rating{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.rating-value{font-size:12px;color:var(--text-muted);margin-left:5px}

/* REVIEW TITLE & TEXT */
.review-title{font-size:15px;font-weight:600;margin-bottom:10px;color:var(--text)}
.review-text{color:var(--text-muted);line-height:1.7;font-size:13px;margin-bottom:15px;white-space:pre-wrap}

/* REVIEW ACTIONS */
.review-actions{display:flex;gap:10px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.08)}

/* BUTTONS */
.btn{padding:8px 16px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn i{font-size:12px}
.btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#27ae60;transform:translateY(-2px)}
.btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#c0392b;transform:translateY(-2px)}
.btn-outline{background:transparent;border:2px solid rgba(255,255,255,0.2);color:var(--text)}.btn-outline:hover{border-color:var(--gold);color:var(--gold);background:rgba(200,166,86,0.1)}
.btn-sm{padding:6px 12px;font-size:11px;border-radius:6px}

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
@media(max-width:768px){.review-header{flex-direction:column;gap:12px;align-items:flex-start}.stats-grid{grid-template-columns:1fr 1fr}.admin-main{padding:20px}.filters-bar{flex-direction:column;align-items:stretch}}
</style>

<!--  СКРИПТЫ -->
<script>
// Toast уведомления
function showToast(msg, type) {
    const existing = document.getElementById('admin-toast');
    if (existing) existing.remove();

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
