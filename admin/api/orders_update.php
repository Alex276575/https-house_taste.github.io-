<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'update_status') {
        $orderId = (int)($data['order_id'] ?? 0);
        $newStatus = $data['status'] ?? '';
        $allowed = ['new','confirmed','cooking','ready_pickup','delivering','delivered','cancelled'];

        if (!$orderId || !in_array($newStatus, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Неверные параметры']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        // Если заказ доставлен — можно отправить уведомление (опционально)
        // if ($newStatus === 'delivered') { /* send notification */ }

        echo json_encode(['success' => true, 'order_id' => $orderId, 'new_status' => $newStatus]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Неверное действие']);
    }
} catch (PDOException $e) {
    error_log("Orders API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
}
?>
