<?php

class Order {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($userId, $data) {
        try {
            $this->pdo->beginTransaction();

            // === 1. Получаем товары корзины ===
            $cartItems = $this->getCartItemsWithUpsell($userId);
            if (empty($cartItems)) {
                throw new Exception('Корзина пуста');
            }

            // === 2. Расчёт сумм ===
            $subtotal = $this->calculateCartTotal($cartItems);
            $deliveryMethod = $data['delivery_method'] ?? 'delivery';
            $deliveryPrice = $this->calculateDelivery($deliveryMethod, $userId, $subtotal);
            $discountAmount = $this->applyPromoCode($data['promo_code'] ?? null, $subtotal, $userId, $deliveryMethod);
            $tipAmount = max(0, (float)($data['tip_amount'] ?? 0));
            $finalAmount = $subtotal - $discountAmount + $deliveryPrice + $tipAmount;

            // === 3. Валидация payment_method ===
            $paymentMethod = $data['payment_method'] ?? 'cash';
            if (!in_array($paymentMethod, ['cash', 'card'])) {
                $paymentMethod = 'cash';
            }

            // === 4. Создаём заказ ===
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    user_id, address_id, promo_code_id, delivery_method,
                    payment_method, status, total_amount, discount_amount,
                    delivery_amount, tip_amount, final_amount, customer_comment,
                    recipient_name, recipient_phone, created_at, updated_at
                ) VALUES (
                    :uid, :addr_id, :promo_id, :delivery, :payment, 'new',
                    :total, :discount, :delivery_price, :tip, :final, :comment,
                    :rec_name, :rec_phone, NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':uid' => $userId,
                ':addr_id' => $deliveryMethod === 'delivery' ? ($data['address_id'] ?? null) : null,
                ':promo_id' => $discountAmount > 0 ? $this->getPromoId($data['promo_code'] ?? null) : null,
                ':delivery' => $deliveryMethod,
                ':payment' => $paymentMethod,
                ':total' => $subtotal,
                ':discount' => $discountAmount,
                ':delivery_price' => $deliveryPrice,
                ':tip' => $tipAmount,
                ':final' => $finalAmount,
                ':comment' => $data['customer_comment'] ?? '',
                ':rec_name' => !empty($data['recipient_name']) ? $data['recipient_name'] : null,
                ':rec_phone' => !empty($data['recipient_phone']) ? $data['recipient_phone'] : null
            ]);

            $orderId = $this->pdo->lastInsertId();

            // === 5. Добавляем позиции ===
            $this->addOrderItems($orderId, $cartItems);

            // === 6. Обновляем промокод ===
            if ($discountAmount > 0 && !empty($data['promo_code'])) {
                $this->updatePromoUsage($data['promo_code']);
            }

            // === 7. Очищаем корзину ===
            $this->pdo->prepare("DELETE FROM cart WHERE user_id = :uid")
                ->execute([':uid' => $userId]);

            $this->pdo->commit();
            return ['success' => true, 'order_id' => $orderId, 'final_amount' => $finalAmount];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Order PDO Error [" . $e->getCode() . "]: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Order Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    private function getCartItemsWithUpsell($userId) {
        $items = [];

        // Обычные товары
        $stmt = $this->pdo->prepare("
            SELECT c.quantity, c.product_id, p.name, p.price, p.old_price,
                   p.discount_percent, p.image_url, p.category_id, 'product' as type
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = :uid AND p.is_available = 1
        ");
        $stmt->execute([':uid' => $userId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($products) {
            foreach ($products as $p) {
                if (!$p) continue;
                // Используем price, если old_price null
                $price = isset($p['old_price']) && $p['old_price'] !== null ? (float)$p['old_price'] : (float)$p['price'];
                $items[] = [
                    'product_id' => (int)$p['product_id'],
                    'name' => $p['name'],
                    'price' => $price,
                    'quantity' => (int)($p['quantity'] ?? 1),
                    'image_url' => $p['image_url'],
                    'type' => 'product'
                ];
            }
        }

        // Upsell товары
        // ИСПРАВЛЕНИЕ: явно выбираем u.id AS upsell_id, чтобы не было конфликта с c.product_id
        $stmtU = $this->pdo->prepare("
            SELECT c.quantity, c.product_id, u.id AS upsell_id, u.name, u.price, u.image_url, u.category, 'upsell' as type
            FROM cart c
            JOIN upsell_items u ON ABS(c.product_id) = u.id
            WHERE c.user_id = :uid AND c.product_id < 0 AND u.is_active = 1
        ");
        $stmtU->execute([':uid' => $userId]);
        $upsells = $stmtU->fetchAll(PDO::FETCH_ASSOC);

        if ($upsells) {
            foreach ($upsells as $u) {
                if (!$u) continue;

                // ИСПРАВЛЕНИЕ: используем upsell_id вместо id
                $upsellId = isset($u['upsell_id']) ? (int)$u['upsell_id'] : 0;

                $items[] = [
                    // Для order_items сохраняем ID upsell-товара.
                    // Если внешний ключ на products удален, это сработает.
                    // Если нет, можно использовать 0, но тогда потеряется связь.
                    'product_id' => $upsellId,
                    'name' => $u['name'],
                    'price' => (float)($u['price'] ?? 0),
                    'quantity' => (int)($u['quantity'] ?? 1),
                    'image_url' => $u['image_url'],
                    'type' => 'upsell',
                    'category' => $u['category'] ?? 'accessories'
                ];
            }
        }

        return $items;
    }

    private function calculateCartTotal($items) {
        $total = 0;
        foreach ($items as $item) {
            if (!$item) continue;
            $total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1);
        }
        return $total;
    }

    private function calculateDelivery($method, $userId, $subtotal) {
        if ($method === 'pickup') return 0;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status != 'cancelled'");
        $stmt->execute([':uid' => $userId]);
        $orderCount = $stmt->fetchColumn();
        return ($orderCount == 0 && $subtotal >= 1500) ? 0 : 150;
    }

    private function applyPromoCode($code, $subtotal, $userId, $deliveryMethod) {
        if (empty($code) || $deliveryMethod === 'pickup') return 0;
        $stmt = $this->pdo->prepare("
            SELECT * FROM promo_codes
            WHERE code = :code AND is_active = 1
            AND valid_from <= CURDATE() AND valid_to >= CURDATE()
        ");
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$promo) return 0;

        if (!empty($promo['is_first_order_only'])) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status != 'cancelled'");
            $stmt->execute([':uid' => $userId]);
            if ($stmt->fetchColumn() > 0) return 0;
        }
        if (!empty($promo['min_order_amount']) && $subtotal < $promo['min_order_amount']) return 0;
        if ($promo['max_uses'] !== null && ($promo['current_uses'] ?? 0) >= $promo['max_uses']) return 0;

        return $promo['discount_type'] === 'percent'
            ? $subtotal * ($promo['discount_value'] / 100)
            : min($promo['discount_value'] ?? 0, $subtotal);
    }

    private function getPromoId($code) {
        if (empty($code)) return null;
        $stmt = $this->pdo->prepare("SELECT id FROM promo_codes WHERE code = :code");
        $stmt->execute([':code' => strtoupper(trim($code))]);
        return $stmt->fetchColumn() ?: null;
    }

    private function updatePromoUsage($code) {
        $stmt = $this->pdo->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE code = :code");
        $stmt->execute([':code' => strtoupper(trim($code))]);
    }

    private function addOrderItems($orderId, $items) {
        $insert = $this->pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name_snapshot, quantity, price_at_moment, total_price)
            VALUES (:oid, :pid, :name, :qty, :price, :total)
        ");
        foreach ($items as $item) {
            if (!$item) continue;

            // ИСПРАВЛЕНИЕ: гарантируем, что product_id не NULL
            $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;

            // Если product_id равен 0 (например, если Upsell не нашелся),
            // убедимся, что имя товара сохранено в snapshot
            $itemName = $item['name'] ?? 'Товар';

            $insert->execute([
                ':oid' => $orderId,
                ':pid' => $productId,
                ':name' => $itemName,
                ':qty' => (int)($item['quantity'] ?? 1),
                ':price' => (float)($item['price'] ?? 0),
                ':total' => (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1)
            ]);
        }
    }

    public function getUserOrders($userId, $limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, CASE WHEN o.address_id IS NOT NULL
                THEN CONCAT(ua.city, ', ', ua.street, ', ', ua.house) ELSE 'Самовывоз' END as delivery_address
            FROM orders o LEFT JOIN user_addresses ua ON o.address_id = ua.id
            WHERE o.user_id = :uid ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderDetails($orderId, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, CASE WHEN o.address_id IS NOT NULL
                THEN CONCAT(ua.city, ', ', ua.street, ', ', ua.house,
                IF(ua.apartment IS NOT NULL, CONCAT(' кв.', ua.apartment), ''))
                ELSE 'Самовывоз: Москва, ул. Тверская, 15' END as delivery_address
            FROM orders o LEFT JOIN user_addresses ua ON o.address_id = ua.id
            WHERE o.id = :oid AND o.user_id = :uid
        ");
        $stmt->execute([':oid' => $orderId, ':uid' => $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;

        $stmt = $this->pdo->prepare("
            SELECT oi.*, CASE WHEN oi.product_id < 0 OR oi.product_id NOT IN (SELECT id FROM products) THEN 'upsell' ELSE 'product' END as item_type
            FROM order_items oi WHERE oi.order_id = :oid
        ");
        $stmt->execute([':oid' => $orderId]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($order['promo_code_id']) {
            $stmt = $this->pdo->prepare("SELECT code FROM promo_codes WHERE id = :id");
            $stmt->execute([':id' => $order['promo_code_id']]);
            $order['promo_code'] = $stmt->fetchColumn();
        }
        return $order;
    }

    public function cancelOrder($orderId, $userId) {
        $allowedStatuses = ['new', 'confirmed'];
        $stmt = $this->pdo->prepare("SELECT status FROM orders WHERE id = :oid AND user_id = :uid");
        $stmt->execute([':oid' => $orderId, ':uid' => $userId]);
        $order = $stmt->fetch();
        if (!$order || !in_array($order['status'], $allowedStatuses)) {
            return ['success' => false, 'error' => 'Заказ нельзя отменить'];
        }
        $stmt = $this->pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :oid");
        $stmt->execute([':oid' => $orderId]);
        return ['success' => true];
    }

    public static function getStatusLabels() {
        return [
            'new' => ['label' => 'Новый', 'class' => 'status-new', 'icon' => 'fas fa-clock'],
            'confirmed' => ['label' => 'Подтверждён', 'class' => 'status-confirmed', 'icon' => 'fas fa-check'],
            'cooking' => ['label' => 'Готовится', 'class' => 'status-cooking', 'icon' => 'fas fa-utensils'],
            'ready_pickup' => ['label' => 'Готов к выдаче', 'class' => 'status-ready', 'icon' => 'fas fa-store'],
            'delivering' => ['label' => 'В доставке', 'class' => 'status-delivering', 'icon' => 'fas fa-motorcycle'],
            'delivered' => ['label' => 'Доставлен', 'class' => 'status-delivered', 'icon' => 'fas fa-check-circle'],
            'cancelled' => ['label' => 'Отменён', 'class' => 'status-cancelled', 'icon' => 'fas fa-times']
        ];
    }
}
