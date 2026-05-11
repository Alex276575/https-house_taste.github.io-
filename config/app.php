<?php

// Режим отладки
define('DEBUG_MODE', true);

// Время сессии (в секундах)
define('SESSION_LIFETIME', 3600);

// Настройки почты
define('MAIL_FROM', 'noreply@domvkusa.ru');
define('MAIL_NAME', 'Дом Вкуса');

// Пагинация
define('ITEMS_PER_PAGE', 12);
define('REVIEWS_PER_PAGE', 10);

// Валюта
define('CURRENCY_SYMBOL', '₽');
define('CURRENCY_CODE', 'RUB');

// Доставка
define('DELIVERY_MIN_ORDER', 1500); // Мин. сумма для бесплатной доставки
define('DELIVERY_FEE', 299); // Стоимость доставки
define('DELIVERY_TIME', '40-60 минут');

// Пути к изображениям
define('IMG_PRODUCTS', '/house_of_taste/public/img/products/');
define('IMG_CHEFS', '/house_of_taste/public/img/staff/');
define('IMG_GALLERY', '/house_of_taste/public/img/gallery/');
define('IMG_PLACEHOLDER', '/house_of_taste/public/img/placeholder.png');
