<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];

// Фильтруем, оставляем только числа
$ids = array_filter($ids, 'is_numeric');

if (empty($ids)) {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.is_available = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
$products = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'products' => $products
]);
