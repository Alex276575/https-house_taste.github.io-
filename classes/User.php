<?php

class User {
    private $db;
    private $pdo;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
    }

    public function login($login, $password, $remember = false) {
        if (empty($login) || empty($password)) {
            return ['success' => false, 'message' => 'Заполните все поля'];
        }
        $stmt = $this->pdo->prepare("SELECT id, login, password_hash, role, full_name, phone, avatar_url FROM users WHERE login = :login OR phone = :phone LIMIT 1");
        $stmt->execute([':login' => $login, ':phone' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = (int)$user['role'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            $_SESSION['phone'] = $user['phone'];
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Неверный логин или пароль'];
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        $stmt = $this->pdo->prepare("SELECT id, login, full_name, phone, avatar_url, role, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function logout() {
        unset($_SESSION['user_id'], $_SESSION['login'], $_SESSION['full_name'], $_SESSION['role'], $_SESSION['avatar_url'], $_SESSION['phone']);
        return true;
    }

    public function register($data) {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) return ['success' => false, 'message' => reset($errors)];
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE login = :login OR phone = :phone LIMIT 1");
        $stmt->execute([':login' => $data['login'], ':phone' => $data['phone']]);
        if ($stmt->fetch()) return ['success' => false, 'message' => 'Пользователь с таким логином или телефоном уже существует'];
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (login, password_hash, role, full_name, phone, avatar_url, created_at, updated_at) VALUES (:login, :password_hash, 0, :full_name, :phone, :avatar_url, NOW(), NOW())");
            $stmt->execute([':login' => $data['login'], ':password_hash' => $passwordHash, ':full_name' => trim($data['full_name']), ':phone' => $data['phone'], ':avatar_url' => $data['avatar_url'] ?? null]);
            return ['success' => true, 'message' => 'Регистрация успешна', 'user_id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            error_log("User::register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка базы данных'];
        }
    }

    private function validateRegistration($data) {
        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $data['login'] ?? '')) $errors['login'] = 'Логин: только латиница, цифры и _ (3-30 симв.)';
        if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,50}$/u', $data['full_name'] ?? '')) $errors['full_name'] = 'Имя: только буквы (без цифр и спецсимволов)';
        $phoneClean = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
        if (strlen($phoneClean) < 10) $errors['phone'] = 'Некорректный номер телефона';
        $pass = $data['password'] ?? '';
        if (strlen($pass) < 8) $errors['password'] = 'Пароль: минимум 8 символов';
        elseif (!preg_match('/[a-zA-Z]/', $pass) || !preg_match('/[0-9]/', $pass)) $errors['password'] = 'Пароль: должны быть буквы и цифры';
        elseif (preg_match('/[а-яА-ЯёЁ]/', $pass)) $errors['password'] = 'Пароль: кириллица запрещена';
        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) $errors['password_confirm'] = 'Пароли не совпадают';
        return $errors;
    }

    public function updateProfile($userId, $data) {
        $allowedFields = ['full_name', 'phone', 'avatar_url'];
        $updates = []; $params = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) { $updates[] = "$field = :$field"; $params[":$field"] = $data[$field]; }
        }
        if (empty($updates)) return ['success' => false, 'message' => 'Нет данных для обновления'];
        $params[':id'] = $userId; $updates[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute($params);
            if (isset($data['full_name'])) $_SESSION['full_name'] = $data['full_name'];
            if (isset($data['avatar_url'])) $_SESSION['avatar_url'] = $data['avatar_url'];
            return ['success' => true, 'message' => 'Профиль обновлён'];
        } catch (PDOException $e) {
            error_log("User::updateProfile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка при обновлении'];
        }
    }

    public function isAdmin() {
        return $this->isLoggedIn() && ($_SESSION['role'] ?? 0) === 1;
    }

    // 🔧 ===== НОВЫЕ МЕТОДЫ ДЛЯ ОТЗЫВОВ =====

    /**
     * Проверка: есть ли у пользователя хоть один завершённый заказ
     */
    public function hasAnyOrder($userId) {
        if (!$this->pdo || !$userId) return false;
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('delivered', 'ready_pickup')");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('User::hasAnyOrder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверка: заказывал ли пользователь конкретный продукт
     */
    public function hasOrderedProduct($userId, $productId) {
        if (!$this->pdo || !$userId || !$productId) return false;
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('delivered', 'ready_pickup')
            ");
            $stmt->execute([$userId, $productId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('User::hasOrderedProduct error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить список продуктов, которые заказывал пользователь
     */
    public function getOrderedProducts($userId) {
        if (!$this->pdo || !$userId) return [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT oi.product_id, p.name
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                WHERE o.user_id = ? AND o.status IN ('delivered', 'ready_pickup')
                ORDER BY p.name
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('User::getOrderedProducts error: ' . $e->getMessage());
            return [];
        }
    }
}
?>
