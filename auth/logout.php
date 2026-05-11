<?php

session_start();

// Очищаем все данные сессии
$_SESSION = array();

// Если используются куки для запоминания, удаляем их
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную
header('Location: /house_of_taste/');
exit;
