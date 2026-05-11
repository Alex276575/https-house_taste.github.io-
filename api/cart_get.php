<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
ini_set('display_errors', 0);

$root = realpath(__DIR__ . '/..');
require_once $root . '/config/database.php';
require_once $root . '/classes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();

    $where = $userId ? "c.user_id = ?" : "c.session_id = ?";

    // Получаем все items из корзины
    $stmt = $pdo->prepare("
        SELECT c.quantity, c.product_id
        FROM cart c
        WHERE $where
        ORDER BY c.added_at DESC
    ");
    $stmt->execute([$userId ?? $sessionId]);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $total = 0;
    $count = 0;

    foreach ($rawItems as $raw) {
        $pid = (int)$raw['product_id'];
        $qty = (int)$raw['quantity'];

        $itemData = null;

        if ($pid > 0) {
            // Это обычный товар из таблицы products
            $stmtP = $pdo->prepare("SELECT id, name, price, old_price, discount_percent, image_url FROM products WHERE id = ?");
            $stmtP->execute([$pid]);
            $product = $stmtP->fetch();

            if ($product) {
                $price = (float)$product['price'];
                $discount = (float)($product['discount_percent'] ?? 0);
                $finalPrice = ($discount > 0 && $discount < 100) ? $price * (1 - $discount/100) : $price;

                $itemData = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $price,
                    'final_price' => $finalPrice,
                    'image_url' => $product['image_url'],
                    'quantity' => $qty,
                    'is_upsell' => false
                ];
            }
        } else {
            // Это Upsell товар (ID отрицательный)
            $upsellId = abs($pid);
            $stmtU = $pdo->prepare("SELECT id, name, price, image_url, icon_class FROM upsell_items WHERE id = ?");
            $stmtU->execute([$upsellId]);
            $upsell = $stmtU->fetch();

            if ($upsell) {
                $itemData = [
                    'product_id' => -$upsell['id'], // Возвращаем отрицательный ID для фронта
                    'name' => $upsell['name'],
                    'price' => (float)$upsell['price'],
                    'final_price' => (float)$upsell['price'],
                    'image_url' => $upsell['image_url'],
                    'icon_class' => $upsell['icon_class'] ?? 'fas fa-box',
                    'quantity' => $qty,
                    'is_upsell' => true
                ];
            }
        }

        if ($itemData) {
            // 🔥 ИСПРАВЛЕНИЕ ПУТИ К КАРТИНКЕ
            if (!empty($itemData['image_url'])) {
                if (strpos($itemData['image_url'], '/house_of_taste') !== 0) {
                     $itemData['image_url'] = '/house_of_taste' . $itemData['image_url'];
                }
            } else {
                // Если нет картинки, используем заглушку или иконку (для фронта)
                $itemData['image_url'] = '';
            }

            $items[] = $itemData;
            $total += $itemData['final_price'] * $qty;
            $count += $qty;
        }
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => $count,
        'total' => round($total)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Cart get error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
exit;
