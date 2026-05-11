<?php

$pageTitle = 'О ресторане';
require_once __DIR__ . '/../includes/header.php';

// Получаем данные о сотрудниках из БД
$chefs = $pdo->query("SELECT * FROM staff WHERE position = 'chef' AND is_active = 1 ORDER BY id ASC")->fetchAll();
$barkeepers = $pdo->query("SELECT * FROM staff WHERE position = 'barkeeper' AND is_active = 1 ORDER BY id ASC")->fetchAll();
$managers = $pdo->query("SELECT * FROM staff WHERE position = 'manager' AND is_active = 1 ORDER BY id ASC")->fetchAll();

?>

<main class="main-content" style="flex: 1;">

    <!-- Hero Section -->
    <section class="about-hero">
        <div class="about-hero-content">
            <h1>Дом<span>Вкуса</span></h1>
            <p>Гастрономическое пространство, где рождаются кулинарные шедевры. Создаем эмоции через вкус с 2020 года.</p>
            <a href="/house_of_taste/pages/catalog.php"
               style="padding: 18px 45px; background: linear-gradient(135deg, #c8a656, #e8c96a); color: #1a1a1a; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border-radius: 10px; text-decoration: none; display: inline-block; box-shadow: 0 10px 30px rgba(200,166,86,0.3);">
                Меню
            </a>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section dark-section">
        <div class="about-grid">
            <div class="about-image">
                <div class="ai-accent"></div>
                <img src="/house_of_taste/public/img/gallery/gallery_0.jpg" alt="О ресторане">
                <div class="ai-accent2"></div>
            </div>
            <div class="about-text">
                <div class="hero-badge">О нашем ресторане</div>
                <h2>Традиции и <span>инновации</span></h2>
                <p>Ресторан «Дом Вкуса» был основан в 2020 году с одной простой целью - создавать блюда, которые дарят настоящие эмоции. Мы сочетаем классические кулинарные традиции с современными техниками и авторскими рецептами.</p>
                <p>Наши шеф-повара используют только свежие, тщательно отобранные ингредиенты. Мы гордимся нашей картой безалкогольных напитков - авторские коктейли, молочные коктейли, свежевыжатые соки и уникальные лимонады.</p>
                <p>Каждое блюдо в нашем меню - это результат творческого поиска и стремления к совершенству вкуса. Мы приглашаем вас открыть для себя мир «Дома Вкуса».</p>
                <div class="about-stats">
                    <div class="about-stat">
                        <div class="as-num">6+</div>
                        <div class="as-label">Лет в бизнесе</div>
                    </div>
                    <div class="about-stat">
                        <div class="as-num">1000+</div>
                        <div class="as-label">Довольных клиентов</div>
                    </div>
                    <div class="about-stat">
                        <div class="as-num">50+</div>
                        <div class="as-label">Авторских блюд</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Promo Banner Section -->
    <section class="promo-banner">
        <div class="promo-content">
            <div class="promo-badge">Специальное предложение</div>
            <h2>-15% <span>скидка</span> на первый заказ</h2>
            <p>Каждую неделю в вашем личном кабинете появляются новые промокоды и специальные предложения. Не упустите возможность сэкономить на любимых блюдах!</p>
            <a href="/house_of_taste/pages/catalog.php" class="promo-btn">Заказать сейчас</a>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section dark-section">
        <div class="section-header">
            <h2>Наша <span>Команда</span></h2>
            <p>Вдохновляем через вкус, объединяем через эмоции</p>
        </div>

        <div class="team-grid">
            <?php foreach ($chefs as $chef): ?>
            <div class="team-card">
                <div class="team-photo-wrapper">
                    <div class="team-photo">
                        <img src="/house_of_taste<?= htmlspecialchars($chef['photo_url']) ?>"
                             alt="<?= htmlspecialchars($chef['full_name']) ?>"
                             onerror="this.src='https://placehold.co/400x500/222/c8a656?text=Шеф'">
                    </div>
                </div>
                <div class="team-info">
                    <h3 class="team-name"><?= htmlspecialchars($chef['full_name']) ?></h3>
                    <span class="team-role">
                        <?= strpos(strtolower($chef['bio']), 'кондитер') !== false ? 'Шеф-кондитер' : 'Шеф-повар' ?>
                    </span>
                    <p class="team-bio"><?= htmlspecialchars($chef['bio']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($barkeepers as $bar): ?>
            <div class="team-card">
                <div class="team-photo-wrapper">
                    <div class="team-photo">
                        <img src="/house_of_taste<?= htmlspecialchars($bar['photo_url']) ?>"
                             alt="<?= htmlspecialchars($bar['full_name']) ?>"
                             onerror="this.src='https://placehold.co/400x500/222/c8a656?text=Бармен'">
                    </div>
                </div>
                <div class="team-info">
                    <h3 class="team-name"><?= htmlspecialchars($bar['full_name']) ?></h3>
                    <span class="team-role">Бар-менеджер</span>
                    <p class="team-bio"><?= htmlspecialchars($bar['bio']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($managers as $mgr): ?>
            <div class="team-card">
                <div class="team-photo-wrapper">
                    <div class="team-photo">
                        <img src="/house_of_taste<?= htmlspecialchars($mgr['photo_url']) ?>"
                             alt="<?= htmlspecialchars($mgr['full_name']) ?>"
                             onerror="this.src='https://placehold.co/400x500/222/c8a656?text=Менеджер'">
                    </div>
                </div>
                <div class="team-info">
                    <h3 class="team-name"><?= htmlspecialchars($mgr['full_name']) ?></h3>
                    <span class="team-role">Управляющая</span>
                    <p class="team-bio"><?= htmlspecialchars($mgr['bio']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<style>
    :root {
        --gold: #c8a656;
        --gold-light: #e8c96a;
        --dark-bg: #1a1a1a;
    }

    /* Hero Section */
    .about-hero {
        position: relative;
        height: 75vh;
        min-height: 650px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)),
                    url('/house_of_taste/public/img/gallery/gallery_05.jpg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        text-align: center;
        color: #fff;
    }

    .about-hero-content h1 {
        font-size: clamp(42px, 7vw, 72px);
        font-weight: 300;
        letter-spacing: 6px;
        text-transform: uppercase;
        margin-bottom: 20px;
    }

    .about-hero-content h1 span {
        font-weight: 700;
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: block;
    }

    .about-hero-content p {
        font-size: 20px;
        color: #e0e0e0;
        max-width: 650px;
        margin: 0 auto 35px;
        line-height: 1.6;
    }

    /* Common Dark Background */
    .dark-section {
        background: #1a1a1a;
    }

    /* About Section */
    .about-section {
        padding: 100px 40px;
        max-width: 1300px;
        margin: 0 auto;
    }

    .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        align-items: center;
    }

    .about-image {
        position: relative;
    }

    .about-image img {
        width: 100%;
        height: 500px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    }

    .about-image .ai-accent {
        position: absolute;
        top: -20px;
        left: -20px;
        width: 120px;
        height: 120px;
        border-top: 3px solid var(--gold);
        border-left: 3px solid var(--gold);
        z-index: 2;
    }

    .about-image .ai-accent2 {
        position: absolute;
        bottom: -20px;
        right: -20px;
        width: 120px;
        height: 120px;
        border-bottom: 3px solid var(--gold);
        border-right: 3px solid var(--gold);
        z-index: 2;
    }

    .about-text .hero-badge {
        display: inline-block;
        padding: 8px 20px;
        border: 1px solid rgba(200,166,86,0.4);
        font-size: 11px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 25px;
    }

    .about-text h2 {
        font-size: 42px;
        font-weight: 300;
        letter-spacing: 4px;
        margin-bottom: 15px;
        color: #fff;
    }

    .about-text h2 span {
        color: var(--gold);
        font-weight: 700;
    }

    .about-text p {
        font-size: 14px;
        color: #aaa;
        line-height: 2.2;
        margin-bottom: 20px;
        font-weight: 300;
    }

    .about-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
        margin-top: 40px;
    }

    .about-stat {
        text-align: center;
        padding: 30px 20px;
        border: 1px solid rgba(200,166,86,0.2);
        background: rgba(200,166,86,0.03);
        transition: all 0.3s;
    }

    .about-stat:hover {
        border-color: var(--gold);
        background: rgba(200,166,86,0.06);
        transform: translateY(-5px);
    }

    .about-stat .as-num {
        font-size: 36px;
        font-weight: 700;
        color: var(--gold);
        display: block;
        margin-bottom: 8px;
    }

    .about-stat .as-label {
        font-size: 11px;
        color: #777;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    /* Promo Banner Section */
    .promo-banner {
        padding: 120px 40px;
        background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.8)),
                    url('/house_of_taste/public/img/promo/promo_banner4.png');
        background-size: cover;
        background-position: center;
        position: relative;
    }

    .promo-content {
        max-width: 900px;
        margin: 0 auto;
        text-align: center;
        position: relative;
        z-index: 2;
    }

    .promo-badge {
        display: inline-block;
        padding: 10px 30px;
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: #1a1a1a;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        border-radius: 30px;
        margin-bottom: 30px;
    }

    .promo-content h2 {
        font-size: 48px;
        font-weight: 300;
        letter-spacing: 4px;
        color: #fff;
        margin-bottom: 20px;
    }

    .promo-content h2 span {
        color: var(--gold);
        font-weight: 700;
    }

    .promo-content p {
        font-size: 16px;
        color: #ddd;
        line-height: 1.8;
        margin-bottom: 40px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .promo-btn {
        padding: 18px 50px;
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: #1a1a1a;
        font-weight: 700;
        font-size: 13px;
        letter-spacing: 2px;
        text-transform: uppercase;
        border-radius: 8px;
        text-decoration: none;
        display: inline-block;
        box-shadow: 0 10px 30px rgba(200,166,86,0.3);
        transition: all 0.3s;
    }

    .promo-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(200,166,86,0.4);
    }

    /* Team Section */
    .team-section {
        padding: 100px 40px;
    }

    .section-header {
        text-align: center;
        margin-bottom: 60px;
    }

    .section-header h2 {
        font-size: 42px;
        font-weight: 300;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: #fff;
        margin-bottom: 15px;
    }

    .section-header h2 span {
        color: var(--gold);
        font-weight: 700;
    }

    .section-header p {
        font-size: 14px;
        color: #888;
        letter-spacing: 1px;
    }

    .team-grid {
        max-width: 1300px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 30px;
    }

    .team-card {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(200,166,86,0.15);
        transition: all 0.4s;
        overflow: hidden;
    }

    .team-card:hover {
        border-color: var(--gold);
        transform: translateY(-8px);
        box-shadow: 0 20px 50px rgba(200,166,86,0.1);
        background: rgba(200,166,86,0.03);
    }

    .team-photo-wrapper {
        position: relative;
        height: 250px;
        overflow: hidden;
    }

    .team-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top center;
        transition: transform 0.6s;
    }

    .team-card:hover .team-photo img {
        transform: scale(1.08);
    }

    .team-info {
        padding: 25px 20px;
        text-align: center;
    }

    .team-name {
        font-size: 18px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 8px;
        letter-spacing: 1px;
    }

    .team-role {
        font-size: 11px;
        color: var(--gold);
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 15px;
        display: block;
        font-weight: 600;
    }

    .team-bio {
        font-size: 12px;
        color: #999;
        line-height: 1.7;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .about-grid {
            grid-template-columns: 1fr;
            gap: 50px;
        }
        .team-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .about-section,
        .promo-banner,
        .team-section {
            padding: 80px 20px;
        }
        .team-grid {
            grid-template-columns: 1fr;
        }
        .about-text h2,
        .section-header h2,
        .promo-content h2 {
            font-size: 32px;
        }
        .about-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
