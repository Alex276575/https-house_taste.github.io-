<?php

class ChipBot {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Обработка сообщения пользователя
     */
    public function processMessage(string $message, ?int $userId, string $sessionId, ?array $photo = null): array {
        $message = trim($message);

        // Обработка фото для жалобы
        if ($photo && isset($photo['error']) && $photo['error'] === UPLOAD_ERR_OK) {
            return $this->handleComplaint($message, $userId, $sessionId, $photo);
        }

        // Поиск ответа в базе знаний
        $answer = $this->findResponse($message);

        // Сохранение в историю
        $this->saveToHistory($userId, $sessionId, $message, $answer['text'], $answer['id'] ?? null);

        return [
            'success' => true,
            'text' => $answer['text'],
            'redirect' => $answer['redirect_url'] ?? null,
            'requiresManager' => (bool)($answer['requires_manager'] ?? false)
        ];
    }

    /**
     * Поиск ответа в chip_knowledge
     */
    private function findResponse(string $message): array {
        $message = mb_strtolower($message);

        try {
            // Получаем активные записи, сортируем по приоритету
            $stmt = $this->pdo->query("SELECT * FROM chip_knowledge WHERE is_active = 1 ORDER BY priority DESC, id ASC");
            $knowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($knowledge as $item) {
                $keywords = array_map('trim', explode(',', mb_strtolower($item['keywords'] ?? '')));
                // Пустые ключевые слова = ответ по умолчанию
                if (empty($item['keywords']) || in_array('', $keywords, true)) {
                    $keywords = [''];
                }

                foreach ($keywords as $kw) {
                    if ($kw && mb_strpos($message, $kw) !== false) {
                        $responses = array_map('trim', explode('|||', $item['responses']));
                        return [
                            'id' => $item['id'],
                            'text' => $responses[array_rand($responses)],
                            'redirect_url' => $item['redirect_url'],
                            'requires_manager' => $item['requires_manager']
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('KB query error: ' . $e->getMessage());
        }

        // Ответ по умолчанию (категория 'other')
        return [
            'text' => 'Я пока не знаю ответа. Спросите про: меню, доставку, возврат, отзыв, менеджера, карту.',
            'redirect_url' => null,
            'requires_manager' => false
        ];
    }

    /**
     * Обработка жалобы с фото
     */
    private function handleComplaint(string $message, ?int $userId, string $sessionId, array $photo): array {
        $uploadDir = __DIR__ . '/../public/uploads/complaints/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $filename = 'complaint_' . ($userId ?: $sessionId) . '_' . time() . '.' . $ext;

        if (move_uploaded_file($photo['tmp_name'], $uploadDir . $filename)) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO chip_complaints (user_id, session_id, message, photo_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId ?: null, $sessionId, $message, '/public/uploads/complaints/' . $filename]);

                return [
                    'success' => true,
                    'text' => 'Фото принято! Передал информацию менеджеру Елене. Она свяжется с вами.',
                    'requiresManager' => true
                ];
            } catch (Exception $e) {
                error_log('Complaint save error: ' . $e->getMessage());
            }
        }

        return [
            'success' => false,
            'text' => 'Ошибка загрузки фото. Попробуйте ещё раз.',
            'requiresManager' => true
        ];
    }

    /**
     * Сохранение в историю чата
     */
    private function saveToHistory(?int $userId, string $sessionId, string $userMsg, string $botResp, ?int $knowledgeId): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO chip_chat_history (user_id, session_id, user_message, bot_response, knowledge_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId ?: null, $sessionId, $userMsg, $botResp, $knowledgeId]);
        } catch (Exception $e) {
            // Игнорируем ошибки истории
        }
    }

    /**
     * Сохранение оценки
     */
    public function saveRating(?int $userId, string $sessionId, int $rating, string $comment): array {
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Invalid rating'];
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO chip_ratings (user_id, session_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId ?: null, $sessionId, $rating, $comment]);
            return ['success' => true, 'message' => 'Спасибо за оценку!'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'DB error'];
        }
    }

    /**
     * Очистка истории
     */
    public function clearHistory(?int $userId, string $sessionId): array {
        if ($userId) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM chip_chat_history WHERE user_id = ?");
                $stmt->execute([$userId]);
                return ['success' => true, 'message' => 'История очищена'];
            } catch (Exception $e) {
                return ['success' => false, 'error' => 'DB error'];
            }
        }
        return ['success' => true];
    }
}
