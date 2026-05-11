<?php
// === БАЗОВЫЕ ПУТИ ===
define('BASE_URL', '/house_of_taste');
define('ADMIN_URL', BASE_URL . '/admin');
define('ADMIN_PAGES_URL', ADMIN_URL . '/pages');

// === ФУНКЦИЯ ДЛЯ ГЕНЕРАЦИИ ССЫЛОК ===
function adminUrl($path = '') {
    return ADMIN_URL . '/' . ltrim($path, '/');
}

function pageUrl($path = '') {
    return ADMIN_PAGES_URL . '/' . ltrim($path, '/');
}

// === ПРОВЕРКА АКТИВНОЙ СТРАНИЦЫ (для подсветки в меню) ===
function isActivePage($pageName) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $pageName ? 'active' : '';
}

function isActiveSection($section) {
    return strpos($_SERVER['REQUEST_URI'], $section) !== false ? 'active' : '';
}
?>
