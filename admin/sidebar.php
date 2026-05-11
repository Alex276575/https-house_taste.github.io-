<aside class="admin-sidebar">
    <div class="sidebar-header">
        <i class="fas fa-utensils"></i>
        <span>Админ-панель</span>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Дашборд</a>
        <a href="pages/orders.php" class="<?= strpos($_SERVER['PHP_SELF'], 'orders') !== false ? 'active' : '' ?>"><i class="fas fa-receipt"></i> Заказы</a>
        <a href="pages/users.php" class="<?= strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : '' ?>"><i class="fas fa-users"></i> Пользователи</a>
        <a href="pages/products.php" class="<?= strpos($_SERVER['PHP_SELF'], 'products') !== false ? 'active' : '' ?>"><i class="fas fa-utensils"></i> Каталог</a>
        <a href="pages/categories.php" class="<?= strpos($_SERVER['PHP_SELF'], 'categories') !== false ? 'active' : '' ?>"><i class="fas fa-list"></i> Категории</a>
        <a href="pages/reviews.php" class="<?= strpos($_SERVER['PHP_SELF'], 'reviews') !== false ? 'active' : '' ?>"><i class="fas fa-comment-alt"></i> Отзывы</a>
        <a href="pages/promo.php" class="<?= strpos($_SERVER['PHP_SELF'], 'promo') !== false ? 'active' : '' ?>"><i class="fas fa-percent"></i> Промокоды</a>
        <a href="pages/staff.php" class="<?= strpos($_SERVER['PHP_SELF'], 'staff') !== false ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> Сотрудники</a>
        <a href="pages/upsell.php" class="<?= strpos($_SERVER['PHP_SELF'], 'upsell') !== false ? 'active' : '' ?>"><i class="fas fa-gift"></i> Доп. товары</a>
        <div class="sidebar-divider">Чип (бот)</div>
        <a href="pages/chip_knowledge.php" class="<?= strpos($_SERVER['PHP_SELF'], 'chip_knowledge') !== false ? 'active' : '' ?>"><i class="fas fa-brain"></i> База знаний</a>
        <a href="pages/chip_history.php" class="<?= strpos($_SERVER['PHP_SELF'], 'chip_history') !== false ? 'active' : '' ?>"><i class="fas fa-comments"></i> Диалоги</a>
        <a href="pages/chip_complaints.php" class="<?= strpos($_SERVER['PHP_SELF'], 'chip_complaints') !== false ? 'active' : '' ?>"><i class="fas fa-exclamation-circle"></i> Жалобы</a>
        <a href="/house_of_taste/" class="sidebar-logout"><i class="fas fa-external-link-alt"></i> На сайт</a>
    </nav>
</aside>
