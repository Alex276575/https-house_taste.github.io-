<?php

$pageTitle = 'Оценки работы Чипа';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /house_of_taste/auth/login.php'); exit;
}

// === СТАТИСТИКА ===
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM chip_ratings")->fetchColumn(),
    'avg' => $pdo->query("SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM chip_ratings")->fetchColumn(),
    '5' => $pdo->query("SELECT COUNT(*) FROM chip_ratings WHERE rating=5")->fetchColumn(),
    '1' => $pdo->query("SELECT COUNT(*) FROM chip_ratings WHERE rating=1")->fetchColumn()
];

// === СПИСОК ОТЗЫВОВ ===
$sql = "SELECT r.*, u.full_name, u.login FROM chip_ratings r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC";
$reviews = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="admin-main">
        <div class="page-header">
            <h1><i class="fas fa-star"></i> Оценки работы Чипа</h1>
            <p>Отзывы и рейтинги от пользователей</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-chart-bar"></i><div><span class="stat-num"><?= $stats['total'] ?></span><span class="stat-label">Всего отзывов</span></div></div>
            <div class="stat-card success"><i class="fas fa-star"></i><div><span class="stat-num"><?= $stats['avg'] ?>/5</span><span class="stat-label">Средний рейтинг</span></div></div>
            <div class="stat-card warning"><i class="fas fa-thumbs-up"></i><div><span class="stat-num"><?= $stats['5'] ?></span><span class="stat-label">Пятёрки</span></div></div>
            <div class="stat-card danger"><i class="fas fa-thumbs-down"></i><div><span class="stat-num"><?= $stats['1'] ?></span><span class="stat-label">Единицы</span></div></div>
        </div>

        <!-- Список -->
        <div class="card">
            <?php foreach($reviews as $rev): ?>
            <div class="review-item">
                <div class="review-header">
                    <div class="review-author">
                        <div class="author-avatar"><?= mb_substr($rev['full_name'] ?? $rev['login'] ?? 'Г', 0, 1) ?></div>
                        <div>
                            <strong><?= htmlspecialchars($rev['full_name'] ?? $rev['login'] ?? 'Аноним') ?></strong>
                            <small style="color:#666"><?= date('d.m.Y H:i', strtotime($rev['created_at'])) ?></small>
                        </div>
                    </div>
                    <div class="review-rating"><?= str_repeat('', $rev['rating']) ?></div>
                </div>
                <?php if($rev['comment']): ?>
                    <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if(empty($reviews)): ?>
                <div class="empty-state"><i class="fas fa-star"></i><p>Оценок пока нет</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
:root{--gold:#c8a656;--gold-light:#e8c96a;--dark:#1a1a1a;--darker:#0f0f0f;--gray:#2a2a2a;--gray-light:#3a3a3a;--text:#fff;--text-muted:#999;--success:#2ecc71;--danger:#e74c3c;--warning:#f39c12}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Montserrat',sans-serif;background:var(--dark);color:var(--text);line-height:1.6}
.admin-wrapper{display:flex;min-height:100vh}.admin-sidebar{width:240px;background:var(--darker);border-right:1px solid rgba(200,166,86,0.15);position:fixed;height:100vh;overflow-y:auto;z-index:100}
.sidebar-header{padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px}.sidebar-header i{color:var(--gold);font-size:20px}
.sidebar-nav{padding:15px 0}.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--text-muted);font-size:13px;text-decoration:none;transition:0.2s;border-left:3px solid transparent}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(200,166,86,0.1);color:var(--gold);border-left-color:var(--gold)}.sidebar-nav a i{width:20px;text-align:center}
.sidebar-divider{padding:15px 20px 10px;font-size:10px;text-transform:uppercase;letter-spacing:2px;color:#555;font-weight:600}
.admin-main{flex:1;margin-left:240px;padding:30px}.page-header{margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.page-header h1{font-size:28px;font-weight:300;letter-spacing:2px;margin-bottom:5px;display:flex;align-items:center;gap:10px}.page-header h1 i{color:var(--gold)}.page-header p{color:var(--text-muted);font-size:14px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:30px}
.stat-card{background:var(--gray);padding:20px;border-radius:12px;display:flex;align-items:center;gap:15px;border:1px solid rgba(255,255,255,0.05);transition:0.3s}
.stat-card:hover{border-color:var(--gold);transform:translateY(-2px)}.stat-card i{font-size:28px;color:var(--gold);width:40px;text-align:center}
.stat-card.success i{color:var(--success)}.stat-card.warning i{color:var(--warning)}.stat-card.danger i{color:var(--danger)}
.stat-num{font-size:24px;font-weight:700;display:block}.stat-label{font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.card{background:var(--gray);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:25px}
.review-item{padding:20px 0;border-bottom:1px solid rgba(255,255,255,0.05)}.review-item:last-child{border-bottom:none}
.review-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.review-author{display:flex;align-items:center;gap:12px}
.author-avatar{width:40px;height:40px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#1a1a1a;font-size:16px}
.review-rating{font-size:14px;letter-spacing:2px}
.review-text{color:var(--text-muted);line-height:1.6;background:var(--gray-light);padding:15px;border-radius:8px;font-size:13px}
.empty-state{text-align:center;padding:60px;color:#666}.empty-state i{font-size:48px;margin-bottom:16px;opacity:0.3;color:var(--gold)}
@media(max-width:1024px){.admin-sidebar{width:70px}.sidebar-header span,.sidebar-nav a span,.sidebar-divider{display:none}.sidebar-nav a{justify-content:center;padding:15px}.admin-main{margin-left:70px}}
@media(max-width:768px){.admin-main{padding:20px}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
