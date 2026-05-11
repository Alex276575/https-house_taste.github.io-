<?php

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// 2. Получение и валидация данных
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID товара']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Неверная оценка']);
    exit;
}

if (empty($comment) || strlen($comment) < 5) {
    echo json_encode(['success' => false, 'message' => 'Комментарий слишком короткий']);
    exit;
}

try {
    // 3. ПРОВЕРКА: Покупал ли пользователь этот товар?
    // Ищем завершенный заказ с этим товаром у этого пользователя
    $stmtCheckOrder = $db->prepare("
        SELECT COUNT(*)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        AND oi.product_id = ?
        AND o.status = 'completed'
    ");
    $stmtCheckOrder->execute([$userId, $productId]);
    $hasPurchased = $stmtCheckOrder->fetchColumn() > 0;

    if (!$hasPurchased) {
        echo json_encode(['success' => false, 'message' => 'Вы можете оставить отзыв только после покупки этого товара']);
        exit;
    }

    // 4. ПРОВЕРКА: Не оставлял ли он уже отзыв?
    $stmtCheckReview = $db->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ? AND product_id = ?");
    $stmtCheckReview->execute([$userId, $productId]);
    $alreadyReviewed = $stmtCheckReview->fetchColumn() > 0;

    if ($alreadyReviewed) {
        echo json_encode(['success' => false, 'message' => 'Вы уже оставили отзыв к этому товару']);
        exit;
    }

    // 5. Добавление отзыва
    // Статус 'approved' означает, что отзыв появится сразу. Можно заменить на 'pending' для модерации.
    $stmtInsert = $db->prepare("
        INSERT INTO reviews (user_id, product_id, rating, comment, status, created_at)
        VALUES (?, ?, ?, ?, 'approved', NOW())
    ");

    $stmtInsert->execute([$userId, $productId, $rating, $comment]);

    echo json_encode([
        'success' => true,
        'message' => 'Спасибо за ваш отзыв!'
    ]);

} catch (Exception $e) {

    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Произошла ошибка при сохранении отзыва']);
}
