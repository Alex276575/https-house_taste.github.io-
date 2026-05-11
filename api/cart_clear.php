<?php

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { echo json_encode(['success' => false]); exit; }

$stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
$stmt->execute([$userId]);

echo json_encode(['success' => true]);
