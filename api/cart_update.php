<?php
/**
 * API: Обновление количества
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Cart.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'], $input['delta'])) {
        throw new Exception('Missing params');
    }

    $userId = $_SESSION['user_id'] ?? null;
    $productId = (int)$input['id'];
    $delta = (int)$input['delta'];
    $where = $userId ? "user_id = ?" : "session_id = ?";

    // Текущее количество
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE $where AND product_id = ?");
    $stmt->execute([$userId ?? session_id(), $productId]);
    $currentQty = (int)($stmt->fetchColumn() ?? 0);
    $newQty = $currentQty + $delta;

    if ($newQty <= 0) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE $where AND product_id = ?");
        $stmt->execute([$userId ?? session_id(), $productId]);
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE $where AND product_id = ?");
        $stmt->execute([$newQty, $userId ?? session_id(), $productId]);
    }

    // Подсчёт
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE $where");
    $stmt->execute([$userId ?? session_id()]);
    $cartCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT SUM(p.price * c.quantity) FROM cart c INNER JOIN products p ON c.product_id = p.id WHERE $where AND p.is_available = 1");
    $stmt->execute([$userId ?? session_id()]);
    $cartTotal = round($stmt->fetchColumn() ?? 0);

    echo json_encode([
        'success' => true,
        'cart_count' => $cartCount,
        'cart_total' => $cartTotal
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Cart update: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error'], JSON_UNESCAPED_UNICODE);
}
exit;
