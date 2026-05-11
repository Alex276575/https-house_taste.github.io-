<?php

class Auth {
    private $db;

    public function __construct() {
        // Используем синглтон для подключения к БД
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Вход пользователя
     */
    public function login($login, $password, $remember = false) {
        if (empty($login) || empty($password)) {
            return ['success' => false, 'message' => 'Заполните все поля'];
        }

        // Ищем пользователя по логину или телефону
        $stmt = $this->db->prepare("SELECT * FROM users WHERE login = :login OR phone = :phone LIMIT 1");
        $stmt->execute([':login' => $login, ':phone' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешный вход — сохраняем данные в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar_url'] = $user['avatar_url'];

            // Если нажата галочка "Запомнить меня"
            if ($remember) {
                $this->createRememberToken($user['id']);
            }

            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Неверный логин или пароль'];
    }

    /**
     * Создание токена "Запомнить меня"
     * ИСПРАВЛЕНО: убрана колонка remember_expires, которой нет в БД
     */
    private function createRememberToken($userId) {
        // Генерируем случайный токен
        $token = bin2hex(random_bytes(32));

        // Хэшируем его для безопасности (чтобы даже при утечке БД токен нельзя было использовать)
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);

        // Сохраняем ТОЛЬКО хэш токена в колонку remember_token
        $stmt = $this->db->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
        $stmt->execute([
            ':token' => $hashedToken,
            ':id'    => $userId
        ]);

        // Устанавливаем куки на 30 дней
        setcookie('remember_me', $token, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,  // Запрет доступа через JS
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Проверка авторизации (для header.php и других мест)
     */
    public function isLoggedIn() {
        // 1. Если есть активная сессия — пользователь авторизован
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // 2. Если сессии нет, проверяем куки "Запомнить меня"
        if (isset($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];

            // Ищем всех пользователей, у которых есть токен
            // (В идеале тут нужен индекс, но для небольшого сайта подойдет перебор)
            $stmt = $this->db->prepare("SELECT * FROM users WHERE remember_token IS NOT NULL");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                // Сравниваем токен из куки с хэшем в БД
                if (password_verify($token, $user['remember_token'])) {
                    // Токен совпал! Авторизуем пользователя
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['login'] = $user['login'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['avatar_url'] = $user['avatar_url'];

                    // Можно обновить токен для безопасности (ротация),
                    // но для простоты оставим как есть.
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Выход из системы
     */
    public function logout() {
        // Очищаем сессию
        session_unset();
        session_destroy();

        // Удаляем куки
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE['remember_me']);
        }

        // Обнуляем токен в БД для текущего пользователя (если ID известен)
        if (isset($_SESSION['user_id'])) {
        }

        return true;
    }
}
?>
