<?php

class Review {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function add($userId, $productId, $rating, $title, $comment) {
        // Проверка: пользователь должен сделать заказ
        $sql = "SELECT COUNT(*) as cnt FROM orders WHERE user_id = ? AND status IN ('delivered', 'ready_pickup')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        if ($stmt->fetch()['cnt'] == 0) {
            return ['success' => false, 'message' => 'Только клиенты, сделавшие заказ, могут оставлять отзывы'];
        }

        $sql = "INSERT INTO reviews (user_id, product_id, rating, title, comment, status) VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $productId, $rating, $title, $comment]);

        return ['success' => true, 'message' => 'Отзыв отправлен на модерацию'];
    }

    public function getApproved($productId = null, $limit = null) {
        $sql = "SELECT r.*, u.full_name, u.avatar_url
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.status = 'approved'";

        if ($productId) {
            $sql .= " AND r.product_id = " . (int)$productId;
        }

        $sql .= " ORDER BY r.created_at DESC";
        if ($limit) $sql .= " LIMIT " . (int)$limit;

        return $this->db->query($sql)->fetchAll();
    }

    public function approve($id) {
        $sql = "UPDATE reviews SET status = 'approved' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function reject($id) {
        $sql = "UPDATE reviews SET status = 'rejected' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function getPending() {
        return $this->db->query("SELECT r.*, u.full_name, p.name as product_name
                                  FROM reviews r
                                  JOIN users u ON r.user_id = u.id
                                  LEFT JOIN products p ON r.product_id = p.id
                                  WHERE r.status = 'pending'
                                  ORDER BY r.created_at DESC")->fetchAll();
    }
}
