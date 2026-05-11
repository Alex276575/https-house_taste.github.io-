<aside class="admin-sidebar">
    <div class="sidebar-header">
        <i class="fas fa-utensils"></i>
        <span>Админ-панель</span>
    </div>

    <nav class="sidebar-nav">
        <!-- Дашборд -->
        <a href="<?= adminUrl('index.php') ?>" class="<?= isActivePage('index.php') ?>">
            <i class="fas fa-tachometer-alt"></i> Дашборд
        </a>

        <div class="sidebar-divider">Заказы</div>
        <a href="<?= pageUrl('orders.php') ?>" class="<?= isActiveSection('orders') ?>">
            <i class="fas fa-receipt"></i> Все заказы
        </a>

        <div class="sidebar-divider">Контент</div>
        <a href="<?= pageUrl('products.php') ?>" class="<?= isActiveSection('products') ?>">
            <i class="fas fa-utensils"></i> Каталог
        </a>
        <a href="<?= pageUrl('categories.php') ?>" class="<?= isActiveSection('categories') ?>">
            <i class="fas fa-list"></i> Категории
        </a>
        <a href="<?= pageUrl('reviews.php') ?>" class="<?= isActiveSection('reviews') ?>">
            <i class="fas fa-comment-alt"></i> Отзывы
        </a>
        <a href="<?= pageUrl('promo_codes.php') ?>" class="<?= isActiveSection('promo') ?>">
            <i class="fas fa-percent"></i> Промокоды
        </a>

        <div class="sidebar-divider">Команда</div>
        <a href="<?= pageUrl('staff.php') ?>" class="<?= isActiveSection('staff') ?>">
            <i class="fas fa-user-tie"></i> Сотрудники
        </a>
        <a href="<?= pageUrl('users.php') ?>" class="<?= isActiveSection('users') ?>">
            <i class="fas fa-users"></i> Пользователи
        </a>

        <div class="sidebar-divider">Дополнительно</div>
        <a href="<?= pageUrl('upsell.php') ?>" class="<?= isActiveSection('upsell') ?>">
            <i class="fas fa-gift"></i> Доп. товары
        </a>

        <div class="sidebar-divider">Чип (бот)</div>
        <a href="<?= pageUrl('chip_knowledge.php') ?>" class="<?= isActiveSection('chip_knowledge') ?>">
            <i class="fas fa-brain"></i> База знаний
        </a>
        <a href="<?= pageUrl('chip_history.php') ?>" class="<?= isActiveSection('chip_history') ?>">
            <i class="fas fa-comments"></i> Диалоги
        </a>
        <a href="<?= pageUrl('chip_reviews.php') ?>" class="<?= isActiveSection('chip_reviews') ?>">
            <i class="fas fa-star"></i> Оценки бота
        </a>
        <a href="<?= pageUrl('chip_complaints.php') ?>" class="<?= isActiveSection('chip_complaints') ?>">
            <i class="fas fa-exclamation-circle"></i> Жалобы
        </a>

        <div class="sidebar-divider"></div>
        <a href="<?= BASE_URL ?>/" class="sidebar-logout" target="_blank">
            <i class="fas fa-external-link-alt"></i> На сайт
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-logout">
            <i class="fas fa-sign-out-alt"></i> Выйти
        </a>
    </nav>
</aside>
