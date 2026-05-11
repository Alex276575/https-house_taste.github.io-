<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'create') {
        $stmt = $pdo->prepare("
            INSERT INTO products
            (category_id, chef_id, name, description, ingredients, price, old_price,
             discount_percent, weight_volume, image_url, is_available, is_hit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['category_id'],
            $data['chef_id'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['ingredients'] ?? null,
            $data['price'],
            $data['old_price'] ?? null,
            $data['discount_percent'] ?? 0,
            $data['weight_volume'] ?? null,
            $data['image_url'] ?? null,
            $data['is_available'] ?? 1,
            $data['is_hit'] ?? 0
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("
            UPDATE products SET
                category_id=?, chef_id=?, name=?, description=?, ingredients=?,
                price=?, old_price=?, discount_percent=?, weight_volume=?,
                image_url=?, is_available=?, is_hit=?
            WHERE id=?
        ");
        $stmt->execute([
            $data['category_id'],
            $data['chef_id'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['ingredients'] ?? null,
            $data['price'],
            $data['old_price'] ?? null,
            $data['discount_percent'] ?? 0,
            $data['weight_volume'] ?? null,
            $data['image_url'] ?? null,
            $data['is_available'] ?? 1,
            $data['is_hit'] ?? 0,
            $data['id']
        ]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'delete') {
        // Удаляем связанные записи в favorites и order_items (ON DELETE SET NULL/CASCADE)
        $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Неверное действие']);
    }
} catch (PDOException $e) {
    error_log("Products API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
}
?>
