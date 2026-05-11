<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Если пользователь не авторизован — возвращаем пустой массив
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $pdo = Database::getInstance()->getConnection();

    // Получаем товары из завершённых заказов (делает акцент на delivered, но также учитывает другие успешные статусы)
    $sql = "SELECT
                p.id as product_id,
                p.name,
                p.price,
                p.image_url,
                p.category_id,
                COUNT(oi.id) as order_count
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN products p ON oi.product_id = p.id
            WHERE o.user_id = :uid
                AND o.status IN ('delivered', 'ready_pickup', 'confirmed', 'cooking')
                AND p.is_available = 1
            GROUP BY p.id
            ORDER BY order_count DESC, oi.id DESC
            LIMIT 4";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Если товаров из заказов нет — пробуем взять просто последние просмотренные/добавленные в корзину
    if (empty($items)) {
        $sqlFallback = "SELECT
                            p.id as product_id,
                            p.name,
                            p.price,
                            p.image_url,
                            p.category_id,
                            1 as order_count
                        FROM cart c
                        INNER JOIN products p ON c.product_id = p.id
                        WHERE (c.user_id = :uid OR c.session_id = :sid)
                            AND p.is_available = 1
                        GROUP BY p.id
                        ORDER BY c.added_at DESC
                        LIMIT 4";
        $stmtFallback = $pdo->prepare($sqlFallback);
        $stmtFallback->execute([
            ':uid' => $userId,
            ':sid' => session_id()
        ]);
        $items = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
    }

    // Форматируем ответ
    $response = [];
    foreach ($items as $item) {
        $response[] = [
            'product_id' => (int)$item['product_id'],
            'name' => $item['name'],
            'price' => (float)$item['price'],
            'image_url' => $item['image_url'],
            'category_id' => (int)($item['category_id'] ?? 0),
            'order_count' => (int)($item['order_count'] ?? 1)
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($response),
        'items' => $response
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Orders history API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'items' => []
    ], JSON_UNESCAPED_UNICODE);
}
