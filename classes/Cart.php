<?php

class Cart {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            $this->pdo = null;
        }
    }

    private function getIdentifier($userId = null) {
        if ($userId && $userId > 0) {
            return ['type' => 'user', 'id' => (int)$userId];
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return ['type' => 'session', 'id' => session_id()];
    }

    /**
     * Добавление товара в корзину
     */
    public function add($productId, $quantity = 1, $userId = null) {
        if (!$this->pdo) return false;
        try {
            $identifier = $this->getIdentifier($userId);

            // Проверка товара в БД (может быть как products, так и upsell_items)
            if ($productId > 0) {
                // Обычный товар
                $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND is_available = 1");
                $stmt->execute([(int)$productId]);
                if (!$stmt->fetch()) return false;
            } else {
                // Upsell товар (отрицательный ID)
                $upsellId = abs($productId);
                $stmt = $this->pdo->prepare("SELECT id FROM upsell_items WHERE id = ? AND is_active = 1");
                $stmt->execute([$upsellId]);
                if (!$stmt->fetch()) return false;
            }

            $field = $identifier['type'] === 'user' ? 'user_id' : 'session_id';
            $sql = "INSERT INTO cart ($field, product_id, quantity) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$identifier['id'], (int)$productId, (int)$quantity]);
        } catch (Exception $e) {
            error_log('Cart::add: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение всех товаров корзины (включая upsell)
     */
    public function getItems($userId = null) {
        if (!$this->pdo) return [];
        try {
            $identifier = $this->getIdentifier($userId);
            $where = $identifier['type'] === 'user' ? "c.user_id = ?" : "c.session_id = ?";

            // Получаем все товары из корзины
            $stmt = $this->pdo->prepare("
                SELECT c.quantity, c.product_id
                FROM cart c
                WHERE $where
                ORDER BY c.added_at DESC
            ");
            $stmt->execute([$identifier['id']]);
            $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];

            foreach ($rawItems as $raw) {
                $pid = (int)$raw['product_id'];
                $qty = (int)$raw['quantity'];
                $itemData = null;

                if ($pid > 0) {
                    // Обычный товар из products
                    $stmtP = $this->pdo->prepare("
                        SELECT p.id, p.name, p.price, p.old_price, p.discount_percent, p.image_url
                        FROM products p
                        WHERE p.id = ? AND p.is_available = 1
                    ");
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
                            'old_price' => $product['old_price'],
                            'discount_percent' => $discount,
                            'final_price' => $finalPrice,
                            'image_url' => $product['image_url'],
                            'quantity' => $qty,
                            'is_upsell' => false
                        ];
                    }
                } else {
                    // Upsell товар (отрицательный ID)
                    $upsellId = abs($pid);
                    $stmtU = $this->pdo->prepare("
                        SELECT id, name, price, image_url, icon_class
                        FROM upsell_items
                        WHERE id = ? AND is_active = 1
                    ");
                    $stmtU->execute([$upsellId]);
                    $upsell = $stmtU->fetch();

                    if ($upsell) {
                        $itemData = [
                            'product_id' => -$upsell['id'],
                            'name' => $upsell['name'],
                            'price' => (float)$upsell['price'],
                            'old_price' => null,
                            'discount_percent' => 0,
                            'final_price' => (float)$upsell['price'],
                            'image_url' => $upsell['image_url'],
                            'icon_class' => $upsell['icon_class'] ?? 'fas fa-box',
                            'quantity' => $qty,
                            'is_upsell' => true
                        ];
                    }
                }

                if ($itemData) {
                    // Исправление пути к картинке
                    if (!empty($itemData['image_url'])) {
                        if (strpos($itemData['image_url'], '/house_of_taste') !== 0) {
                            $itemData['image_url'] = '/house_of_taste' . $itemData['image_url'];
                        }
                    } else {
                        $itemData['image_url'] = '/house_of_taste/public/img/placeholder.png';
                    }

                    $items[] = $itemData;
                }
            }

            return $items;
        } catch (Exception $e) {
            error_log('Cart::getItems: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получение количества товаров в корзине
     */
    public function getCount($userId = null) {
        if (!$this->pdo) return 0;
        try {
            $identifier = $this->getIdentifier($userId);
            $where = $identifier['type'] === 'user' ? "user_id = ?" : "session_id = ?";
            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE $where");
            $stmt->execute([$identifier['id']]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Получение общей суммы корзины
     */
    public function getTotal($userId = null) {
        $items = $this->getItems($userId);
        $total = 0;
        foreach ($items as $item) {
            $total += $item['final_price'] * $item['quantity'];
        }
        return $total;
    }

    /**
     * Обновление количества товара
     */
    public function update($productId, $quantity, $userId = null) {
        if (!$this->pdo) return false;
        try {
            $identifier = $this->getIdentifier($userId);
            $field = $identifier['type'] === 'user' ? 'user_id' : 'session_id';

            if ($quantity <= 0) {
                return $this->remove($productId, $userId);
            }

            $stmt = $this->pdo->prepare("UPDATE cart SET quantity = ? WHERE $field = ? AND product_id = ?");
            return $stmt->execute([(int)$quantity, $identifier['id'], (int)$productId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Удаление товара из корзины
     */
    public function remove($productId, $userId = null) {
        if (!$this->pdo) return false;
        try {
            $identifier = $this->getIdentifier($userId);
            $field = $identifier['type'] === 'user' ? 'user_id' : 'session_id';
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE $field = ? AND product_id = ?");
            return $stmt->execute([$identifier['id'], (int)$productId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Очистка корзины
     */
    public function clear($userId = null) {
        if (!$this->pdo) return false;
        try {
            $identifier = $this->getIdentifier($userId);
            $field = $identifier['type'] === 'user' ? 'user_id' : 'session_id';
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE $field = ?");
            return $stmt->execute([$identifier['id']]);
        } catch (Exception $e) {
            return false;
        }
    }
}
