<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DOM VKUSA — Иконки</title>
    <!-- Подключение Font Awesome (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #fff; padding: 40px; }
        .icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 20px; max-width: 1000px; margin: 0 auto; }
        .icon-card { background: #222; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(200,166,86,0.2); }
        .icon-card i { font-size: 32px; color: #c8a656; margin-bottom: 10px; display: block; }
        .icon-card code { font-size: 11px; color: #888; display: block; margin-top: 8px; word-break: break-all; }
        h1 { text-align: center; margin-bottom: 40px; }
        h1 span { color: #c8a656; }
    </style>
</head>
<body>
    <h1>DOM VKUSA <span>Иконки</span></h1>
    <p style="text-align:center; color:#888; margin-bottom:30px;">
        Все иконки из Font Awesome 6.4.0. Используйте классы <code>fas fa-***</code> в вашем HTML.
    </p>

    <div class="icon-grid">
        <!-- Популярные иконки для ресторана -->
        <div class="icon-card"><i class="fas fa-utensils"></i><code>fas fa-utensils</code><small>Посуда</small></div>
        <div class="icon-card"><i class="fas fa-truck"></i><code>fas fa-truck</code><small>Доставка</small></div>
        <div class="icon-card"><i class="fas fa-clock"></i><code>fas fa-clock</code><small>Время</small></div>
        <div class="icon-card"><i class="fas fa-shopping-basket"></i><code>fas fa-shopping-basket</code><small>Корзина</small></div>
        <div class="icon-card"><i class="fas fa-heart"></i><code>fas fa-heart</code><small>Избранное</small></div>
        <div class="icon-card"><i class="fas fa-star"></i><code>fas fa-star</code><small>Рейтинг</small></div>
        <div class="icon-card"><i class="fas fa-phone"></i><code>fas fa-phone</code><small>Телефон</small></div>
        <div class="icon-card"><i class="fas fa-envelope"></i><code>fas fa-envelope</code><small>Email</small></div>
        <div class="icon-card"><i class="fas fa-map-marker-alt"></i><code>fas fa-map-marker-alt</code><small>Адрес</small></div>
        <div class="icon-card"><i class="fas fa-store"></i><code>fas fa-store</code><small>Самовывоз</small></div>
        <div class="icon-card"><i class="fas fa-pen-to-square"></i><code>fas fa-pen-to-square</code><small>Отзыв</small></div>
        <div class="icon-card"><i class="fas fa-fire"></i><code>fas fa-fire</code><small>Популярно</small></div>
        <div class="icon-card"><i class="fas fa-tag"></i><code>fas fa-tag</code><small>Промокод</small></div>
        <div class="icon-card"><i class="fas fa-check-circle"></i><code>fas fa-check-circle</code><small>Успех</small></div>
        <div class="icon-card"><i class="fas fa-exclamation-circle"></i><code>fas fa-exclamation-circle</code><small>Ошибка</small></div>
        <div class="icon-card"><i class="fas fa-spinner fa-spin"></i><code>fas fa-spinner fa-spin</code><small>Загрузка</small></div>
    </div>

    <p style="text-align:center; margin-top:50px; color:#666; font-size:12px;">
        Полный список иконок: <a href="https://fontawesome.com/icons" target="_blank" style="color:#c8a656;">fontawesome.com/icons</a>
    </p>
</body>
</html>
