<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

// Метод только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Получаем данные
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int)($input['user_id'] ?? 0);
$sessionId = $input['session_id'] ?? session_id();
$action = $input['action'] ?? 'message';

$response = ['success' => true];

switch ($action) {

    case 'message':
        $message = trim($input['message'] ?? '');
        $photo = $_FILES['photo'] ?? null;

        // Обработка фото для жалобы
        if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../public/uploads/complaints/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $filename = 'complaint_' . ($userId ?: $sessionId) . '_' . time() . '.' . $ext;

            if (move_uploaded_file($photo['tmp_name'], $uploadDir . $filename)) {
                // Сохраняем жалобу в БД
                $stmt = $pdo->prepare("INSERT INTO chip_complaints (user_id, session_id, message, photo_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId ?: null, $sessionId, $message, '/public/uploads/complaints/' . $filename]);

                $response['photo_saved'] = true;
                $response['text'] = '📸 Фото принято! Я передал информацию менеджеру Елене. Она свяжется с вами в ближайшее время.';
                $response['requiresManager'] = true;
                break;
            }
        }

        // Поиск ответа в базе знаний
        $answer = findBotResponse($message, $pdo);
        $response['text'] = $answer['text'] ?? '🤔 Я пока не знаю ответа на этот вопрос. Попробуйте перефразировать или спросите про: меню, доставку, возврат, отзыв, менеджера, карту.';
        $response['redirect'] = $answer['redirect'] ?? null;
        $response['requiresManager'] = $answer['requires_manager'] ?? false;
        $response['knowledge_id'] = $answer['id'] ?? null;

        // Сохраняем в историю
        if ($userId || $sessionId) {
            $stmt = $pdo->prepare("INSERT INTO chip_chat_history (user_id, session_id, user_message, bot_response, knowledge_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId ?: null, $sessionId, $message, $response['text'], $response['knowledge_id']]);
        }
        break;

    case 'rate':
        $rating = (int)($input['rating'] ?? 0);
        $comment = trim($input['comment'] ?? '');

        if ($rating >= 1 && $rating <= 5) {
            $stmt = $pdo->prepare("INSERT INTO chip_ratings (user_id, session_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId ?: null, $sessionId, $rating, $comment]);
            $response['message'] = 'Спасибо за оценку!';
        }
        break;

    case 'clear_history':
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM chip_chat_history WHERE user_id = ?");
            $stmt->execute([$userId]);
            $response['message'] = 'История очищена';
        }
        break;

    default:
        $response['error'] = 'Unknown action';
        http_response_code(400);
}

echo json_encode($response);

// ===== ФУНКЦИЯ ПОИСКА ОТВЕТА =====
function findBotResponse($message, $pdo) {
    $message = mb_strtolower($message);

    // Получаем активные записи из БЗ, сортируем по приоритету
    $stmt = $pdo->prepare("SELECT * FROM chip_knowledge WHERE is_active = 1 ORDER BY priority DESC, id ASC");
    $stmt->execute();
    $knowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($knowledge as $item) {
        $keywords = array_map('trim', explode(',', mb_strtolower($item['keywords'])));
        // Пустые ключевые слова = ответ по умолчанию
        if (empty($item['keywords']) || in_array('', $keywords)) {
            $keywords = [''];
        }

        foreach ($keywords as $kw) {
            if ($kw && mb_strpos($message, $kw) !== false) {
                $responses = array_map('trim', explode('|||', $item['responses']));
                return [
                    'id' => $item['id'],
                    'text' => $responses[array_rand($responses)],
                    'redirect' => $item['redirect_url'],
                    'requires_manager' => (bool)$item['requires_manager']
                ];
            }
        }
    }

    // Ответ по умолчанию
    $default = $pdo->query("SELECT responses FROM chip_knowledge WHERE category = 'other' AND is_active = 1 LIMIT 1")->fetchColumn();
    $responses = $default ? array_map('trim', explode('|||', $default)) : ['🤔 Не понял вопрос.'];

    return [
        'text' => $responses[array_rand($responses)],
        'redirect' => null,
        'requires_manager' => false
    ];
}
