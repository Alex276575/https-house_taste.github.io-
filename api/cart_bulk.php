<?php

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'] ?? null;

if (!$userId || !isset($data['ids']) || !is_array($data['ids'])) {
    echo json_encode(['success' => false]); exit;
}

$placeholders = implode(',', array_fill(0, count($data['ids']), '?'));
$stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)");
$params = array_merge([$userId], $data['ids']);
$stmt->execute($params);

echo json_encode(['success' => true]);
