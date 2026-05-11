<?php
/**
 * API: Подсчёт товаров в корзине
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'] ?? null;

    $where = $userId ? "user_id = ?" : "session_id = ?";
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE $where");
    $stmt->execute([$userId ?? session_id()]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0], JSON_UNESCAPED_UNICODE);
}
exit;
