<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

// Подключаем конфигурацию и классы
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

// === 1. Проверка метода запроса ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// === 2. Получение и очистка данных ===
$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$fullName = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// === 3. Серверная валидация ===

// 3.1 Логин: только латиница, цифры, подчёркивание, 3-30 символов
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $login)) {
    echo json_encode(['success' => false, 'message' => 'Логин: только латиница, цифры и _ (3-30 симв.)']);
    exit;
}

// 3.2 Имя: только буквы (рус/англ), пробелы, дефис, 2-50 символов
if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{2,50}$/u', $fullName)) {
    echo json_encode(['success' => false, 'message' => 'Имя: только буквы (без цифр и спецсимволов)']);
    exit;
}

// 3.3 Телефон: очистка и проверка
$phoneClean = preg_replace('/[^0-9+]/', '', $phone);
if (strlen(preg_replace('/[^0-9]/', '', $phoneClean)) < 10) {
    echo json_encode(['success' => false, 'message' => 'Некорректный номер телефона']);
    exit;
}

// 3.4 Пароль: мин. 8 символов, есть буквы И цифры, НЕТ кириллицы
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Пароль: минимум 8 символов']);
    exit;
}
if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Пароль: должны быть буквы и цифры']);
    exit;
}
if (preg_match('/[а-яА-ЯёЁ]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Пароль: кириллица запрещена']);
    exit;
}

// 3.5 Совпадение паролей
if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Пароли не совпадают']);
    exit;
}

// === 4. Обработка аватара ===
$avatarUrl = null; // По умолчанию — без аватара (будет дефолтный в шапке)

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/house_of_taste/public/img/avas/user_uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmp = $_FILES['avatar']['tmp_name'];
    $fileName = $_FILES['avatar']['name'];
    $fileSize = $_FILES['avatar']['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Проверка MIME-типа
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $maxSize = 2 * 1024 * 1024; // 2 МБ

    if (!in_array($mimeType, $allowedMimes) || !in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Аватар: недопустимый формат (JPG, PNG, WEBP)']);
        exit;
    }

    if ($fileSize > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Аватар: файл слишком большой (макс. 2 МБ)']);
        exit;
    }

    // Генерируем безопасное уникальное имя
    $safeLogin = preg_replace('/[^a-zA-Z0-9]/', '', substr($login, 0, 10));
    $newFileName = 'ava_' . $safeLogin . '_' . uniqid() . '.' . $fileExt;
    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $destination)) {
        // Сохраняем ПУТЬ относительно public/img/avas/
        $avatarUrl = '/public/img/avas/user_uploads/' . $newFileName;
    }
}

// === 5. Работа с базой данных ===
try {
    $db = Database::getInstance()->getConnection();

    // 5.1 Проверка уникальности логина и телефона
    $stmt = $db->prepare("SELECT id, login, phone FROM users WHERE login = :login OR phone = :phone LIMIT 1");
    $stmt->execute([
        ':login' => $login,
        ':phone' => $phoneClean
    ]);

    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing['login'] === $login) {
            echo json_encode(['success' => false, 'message' => 'Этот логин уже занят']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Этот телефон уже зарегистрирован']);
        }
        exit;
    }

    // 5.2 Хеширование пароля (bcrypt)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // 5.3 ВСТАВКА — ИСПОЛЬЗУЕМ ПРАВИЛЬНЫЕ ИМЕНА КОЛОНОК!
    $sql = "INSERT INTO users (
                login,
                password_hash,
                role,
                full_name,
                phone,
                avatar_url,
                created_at,
                updated_at
            ) VALUES (
                :login,
                :password_hash,
                0,
                :full_name,
                :phone,
                :avatar_url,
                NOW(),
                NOW()
            )";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':login'        => $login,
        ':password_hash'=> $passwordHash,
        ':full_name'    => $fullName,
        ':phone'        => $phoneClean,
        ':avatar_url'   => $avatarUrl
    ]);

    // 5.4 Успех!
    $userId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Регистрация успешна',
        'user_id' => $userId
    ]);

} catch (PDOException $e) {
    // Логируем реальную ошибку
    error_log("Registration DB Error [" . $e->getCode() . "]: " . $e->getMessage());

    // Дружелюбное сообщение пользователю
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'message' => 'Пользователь уже существует']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера. Попробуйте позже.']);
    }
} catch (Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Произошла непредвиденная ошибка']);
}

?>
