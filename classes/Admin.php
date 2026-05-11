<?php

class Admin {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Статистика
    public function getStats() {
        return [
            'total_orders' => $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'total_users' => $this->db->query("SELECT COUNT(*) FROM users WHERE role = 0")->fetchColumn(),
            'total_products' => $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
            'pending_reviews' => $this->db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
            'today_revenue' => $this->db->query("SELECT SUM(final_amount) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0
        ];
    }

    // Управление товарами
    public function addProduct($data) {
        $sql = "INSERT INTO products (category_id, chef_id, name, description, ingredients, price, old_price,
                discount_percent, weight_volume, image_url, is_available, is_hit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['category_id'], $data['chef_id'] ?? null, $data['name'], $data['description'],
            $data['ingredients'], $data['price'], $data['old_price'] ?? null, $data['discount_percent'] ?? 0,
            $data['weight_volume'], $data['image_url'] ?? null, $data['is_available'] ?? 1, $data['is_hit'] ?? 0
        ]);
    }

    public function updateProduct($id, $data) {
        $sql = "UPDATE products SET category_id=?, chef_id=?, name=?, description=?, ingredients=?,
                price=?, old_price=?, discount_percent=?, weight_volume=?, image_url=?,
                is_available=?, is_hit=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['category_id'], $data['chef_id'] ?? null, $data['name'], $data['description'],
            $data['ingredients'], $data['price'], $data['old_price'] ?? null, $data['discount_percent'] ?? 0,
            $data['weight_volume'], $data['image_url'] ?? null, $data['is_available'] ?? 1,
            $data['is_hit'] ?? 0, $id
        ]);
    }

    public function deleteProduct($id) {
        return $this->db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    }

    // Управление заказами
    public function updateOrderStatus($orderId, $status) {
        $sql = "UPDATE orders SET status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $orderId]);
    }

    public function getAllOrders() {
        return $this->db->query("SELECT o.*, u.full_name, u.login
                                  FROM orders o
                                  JOIN users u ON o.user_id = u.id
                                  ORDER BY o.created_at DESC")->fetchAll();
    }

    // Управление промокодами
    public function addPromoCode($data) {
        $sql = "INSERT INTO promo_codes (code, discount_type, discount_value, min_order_amount,
                is_first_order_only, max_uses, valid_from, valid_to, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['code'], $data['discount_type'], $data['discount_value'],
            $data['min_order_amount'] ?? 0, $data['is_first_order_only'] ?? 0,
            $data['max_uses'] ?? null, $data['valid_from'], $data['valid_to'], $data['description']
        ]);
    }

    public function getAllPromoCodes() {
        return $this->db->query("SELECT * FROM promo_codes ORDER BY created_at DESC")->fetchAll();
    }
}
