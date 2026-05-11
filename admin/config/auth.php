<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

$user = new User();

// Только для администраторов
if (!$user->isLoggedIn() || !$user->isAdmin()) {
    // Сохраняем текущий URL для возврата после входа
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: /house_of_taste/auth/login.php');
    exit;
}

// Глобальная переменная $pdo для удобства
$db = Database::getInstance();
$pdo = $db->getConnection();
?>
