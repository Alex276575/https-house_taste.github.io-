<?php

class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($categoryId = null, $limit = null) {
        $sql = "SELECT p.*, c.name as category_name, s.full_name as chef_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN staff s ON p.chef_id = s.id
                WHERE p.is_available = 1";

        if ($categoryId) {
            $sql .= " AND p.category_id = " . (int)$categoryId;
        }

        $sql .= " ORDER BY p.created_at DESC";
        if ($limit) $sql .= " LIMIT " . (int)$limit;

        return $this->db->query($sql)->fetchAll();
    }

    public function getById($id) {
        $sql = "SELECT p.*, c.name as category_name, s.full_name as chef_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN staff s ON p.chef_id = s.id
                WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getCategories() {
        return $this->db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order")->fetchAll();
    }

    public function getHits() {
        return $this->db->query("SELECT * FROM products WHERE is_hit = 1 AND is_available = 1 LIMIT 6")->fetchAll();
    }

    public function search($query) {
        $sql = "SELECT * FROM products WHERE name LIKE ? OR description LIKE ? AND is_available = 1";
        $stmt = $this->db->prepare($sql);
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
}
