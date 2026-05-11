<?php
/**
 * API: Добавление в корзину (с поддержкой комбо и upsell)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$root = realpath(__DIR__ . '/..');
require_once $root . '/config/database.php';
require_once $root . '/classes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['action'])) {
        throw new Exception('Missing action');
    }

    $action = $input['action'];
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();

    // ===== ДОБАВЛЕНИЕ UPSILL ТОВАРА =====
    if ($action === 'add_upsell') {
        $upsellId = (int)($input['upsell_id'] ?? 0);
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        // Проверяем существование upsell товара
        $stmt = $pdo->prepare("SELECT id, name, price FROM upsell_items WHERE id = ? AND is_active = 1");
        $stmt->execute([$upsellId]);
        $upsellItem = $stmt->fetch();

        if (!$upsellItem) {
            throw new Exception('Upsell item not found');
        }

        // Создаем уникальный ID для upsell (отрицательный, чтобы не конфликтовал с products)
        $cartProductId = -$upsellId;

        if ($userId) {
            $sql = "INSERT INTO cart (user_id, product_id, quantity, added_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), added_at = NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $cartProductId, $quantity]);
        } else {
            $sql = "INSERT INTO cart (session_id, product_id, quantity, added_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), added_at = NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sessionId, $cartProductId, $quantity]);
        }

    }
    // ===== ДОБАВЛЕНИЕ ОБЫЧНОГО ТОВАРА ИЛИ КОМБО =====
    elseif ($action === 'add' || $action === 'add_combo') {
        if (!isset($input['id'])) {
            throw new Exception('Missing product id');
        }

        $productId = (int)$input['id'];
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        // Проверка товара
        $stmt = $pdo->prepare("SELECT id, name, price, category_id FROM products WHERE id = ? AND is_available = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception('Product not found or unavailable');
        }

        // Обработка комбо
        if ($action === 'add_combo') {
            if ($product['category_id'] != 14) {
                throw new Exception('This product is not a combo set');
            }

            $comboSelections = $input['combo_selections'] ?? [];
            $finalPrice = isset($input['final_price']) ? (float)$input['final_price'] : (float)$product['price'];

            if (!is_array($comboSelections) || empty($comboSelections)) {
                throw new Exception('Combo selections required');
            }

            $comboSelectionJson = json_encode($comboSelections, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);

            if ($userId) {
                $sql = "INSERT INTO cart (user_id, product_id, quantity, combo_selection, added_at)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            quantity = quantity + VALUES(quantity),
                            combo_selection = VALUES(combo_selection),
                            added_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $productId, $quantity, $comboSelectionJson]);
            } else {
                $sql = "INSERT INTO cart (session_id, product_id, quantity, combo_selection, added_at)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            quantity = quantity + VALUES(quantity),
                            combo_selection = VALUES(combo_selection),
                            added_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sessionId, $productId, $quantity, $comboSelectionJson]);
            }
        } else {
            // Обычный товар
            if ($userId) {
                $sql = "INSERT INTO cart (user_id, product_id, quantity, added_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), added_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId, $productId, $quantity]);
            } else {
                $sql = "INSERT INTO cart (session_id, product_id, quantity, added_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), added_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sessionId, $productId, $quantity]);
            }
        }
    }
    // ===== УДАЛЕНИЕ =====
    elseif ($action === 'remove') {
        if (!isset($input['id'])) {
            throw new Exception('Missing product id');
        }

        $productId = (int)$input['id'];

        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND product_id = ?");
            $stmt->execute([$sessionId, $productId]);
        }
    }
    // ===== ОБНОВЛЕНИЕ КОЛИЧЕСТВА =====
    elseif ($action === 'update') {
        if (!isset($input['id'])) {
            throw new Exception('Missing product id');
        }

        $productId = (int)$input['id'];
        $newQty = max(0, (int)($input['quantity'] ?? 0));

        if ($newQty <= 0) {
            if ($userId) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND product_id = ?");
                $stmt->execute([$sessionId, $productId]);
            }
        } else {
            if ($userId) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$newQty, $userId, $productId]);
            } else {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE session_id = ? AND product_id = ?");
                $stmt->execute([$newQty, $sessionId, $productId]);
            }
        }
    }
    else {
        throw new Exception('Unknown action: ' . $action);
    }

    // Подсчёт количества в корзине
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE " . ($userId ? "user_id = ?" : "session_id = ?"));
    $stmt->execute([$userId ?? $sessionId]);
    $cartCount = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'cartCount' => $cartCount,
        'message' => 'Item added'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Cart API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
