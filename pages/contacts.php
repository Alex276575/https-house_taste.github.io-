<?php

$pageTitle = 'Поддержка';

// 1. AJAX ОБРАБОТКА — ДО ЛЮБОГО ВЫВОДА (КРИТИЧНО!)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Отключаем вывод ошибок, чтобы они не ломали JSON-ответ
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    // Подключение к БД
    $pdo = null;
    try {
        $host = 'localhost'; $db = 'house_of_taste'; $user = 'root'; $pass = '';
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        error_log('DB Connection Error: ' . $e->getMessage());
    }

    // Получаем входные данные
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : $_POST;
    if (!is_array($input)) $input = [];

    $action = $input['action'] ?? '';
    $message = trim($input['message'] ?? '');
    $userId = $input['user_id'] ?? null;
    $sessionId = $input['session_id'] ?? session_id();

    // ===== КЛАСС ЧИП-БОТА =====
    class ChipBot {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        /**
         * Получает ответ бота на сообщение пользователя
         */
        public function getResponse($msg) {
            $msgLower = mb_strtolower(trim($msg));

            // Поиск в базе знаний
            if ($this->pdo) {
                try {
                    $stmt = $this->pdo->query("SELECT * FROM chip_knowledge WHERE is_active = 1 ORDER BY priority DESC, id ASC");
                    $knowledge = $stmt->fetchAll();

                    foreach ($knowledge as $row) {
                        $keywordsStr = mb_strtolower(trim($row['keywords'] ?? ''));

                        // Если ключевые слова пустые — пропускаем (это заглушка 'other')
                        if (empty($keywordsStr)) continue;

                        $keywords = array_filter(array_map('trim', explode(',', $keywordsStr)));

                        foreach ($keywords as $keyword) {
                            $keyword = trim($keyword);
                            if (!empty($keyword) && mb_strpos($msgLower, $keyword) !== false) {
                                // Нашли совпадение — возвращаем случайный ответ из вариантов
                                $responses = array_filter(array_map('trim', explode('|||', $row['responses'])));
                                $responseText = !empty($responses) ? $responses[array_rand($responses)] : '';

                                return [
                                    'text' => $responseText,
                                    'redirect' => !empty($row['redirect_url']) ? $row['redirect_url'] : null,
                                    'needsManager' => (bool)($row['requires_manager'] ?? false),
                                    'requiresPhoto' => (bool)($row['requires_photo'] ?? false)
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('ChipBot DB Error: ' . $e->getMessage());
                }
            }

            // ===== FALLBACK: ответы если ничего не найдено в БД =====
            $fallbacks = [
                [
                    'keywords' => ['меню', 'блюда', 'еда', 'каталог', 'что есть', 'заказать', 'выбрать', 'ассортимент'],
                    'text' => 'Полное меню доступно в <a href="/house_of_taste/pages/catalog.php" class="chat-link">каталоге</a>. Стейки, салаты, десерты и авторские напитки! ',
                    'redirect' => '/house_of_taste/pages/catalog.php'
                ],
                [
                    'keywords' => ['достав', 'привез', 'курьер', 'время доставки', 'когда приедет', 'бесплатная доставка'],
                    'text' => 'Доставка: ежедневно 10:00–23:00. Среднее время — 45-60 минут.<br><strong>Бесплатно</strong> при заказе от 1500₽!',
                    'redirect' => null
                ],
                [
                    'keywords' => ['возвра', 'вернуть', 'жалоб', 'не понравилось', 'проблем', 'брак', 'некачествен'],
                    'text' => 'Жаль, что что-то пошло не так.<br><br>Чтобы оформить возврат:<br><strong>Прикрепите фото</strong> блюда (нажмите)<br> Кратко опишите проблему<br>Укажите номер заказа (если есть)<br><br>Я передам информацию менеджеру Елене.',
                    'redirect' => null,
                    'needsManager' => true,
                    'requiresPhoto' => true
                ],
                [
                    'keywords' => ['отзыв', 'оценить', 'комментарий', 'мнение', 'рекоменд'],
                    'text' => 'Ценим ваше мнение! Оставить отзыв можно <a href="/house_of_taste/pages/reviews.php" class="chat-link">на странице отзывов</a>.',
                    'redirect' => '/house_of_taste/pages/reviews.php'
                ],
                [
                    'keywords' => ['менедж', 'елена', 'позвон', 'связаться', 'человек', 'оператор', 'поддержка'],
                    'text' => 'Менеджер <strong>Елена</strong>: <a href="tel:+74951234567" class="chat-link"><strong>+7 (495) 123-45-67</strong></a><br>Нужна помощь человека? Позвоните — Елена на связи!',
                    'redirect' => null,
                    'needsManager' => true
                ],
                [
                    'keywords' => ['карт', 'схема', 'адрес', 'где находитесь', 'как добраться', 'локация', 'метро'],
                    'text' => 'Адрес: Москва, ул. Тверская, 15.<br><br>Метро: Тверская, Пушкинская, Чеховская (2 мин пешком)<br>Есть бесплатная парковка во дворе.<br><br><a href="#" class="chat-link" data-action="show-map"> Показать карту</a>',
                    'redirect' => null
                ],
                [
                    'keywords' => ['привет',"прив", 'здрав', 'чип', 'хай', 'добрый день', 'доброе утро', 'добрый вечер'],
                    'text' => 'Привет! Рад видеть вас. Я <strong>Чип</strong> — ваш помощник в «Доме Вкуса».<br><br>Чем могу помочь?<br>• Показать меню <br>• Рассказать про доставку<br>• Помочь с возвратом<br>• Соединить с менеджером‍',
                    'redirect' => null
                ],
                [
                    'keywords' => ['спас', 'благодар', 'класс', 'супер', 'молодец', 'круто', 'отлично', 'здорово'],
                    'text' => 'Всегда рад помочь! Если будут ещё вопросы — просто напишите, я на связи 24/7.<br><br>Приятного аппетита!',
                    'redirect' => null
                ],
                [
                    'keywords' => ['пока', 'до свид', 'заверш', 'конец', 'всего доброго', 'до встречи'],
                    'text' => 'До встречи! Приятного аппетита и ждём вас снова в «Доме Вкуса»!<br><br>Ваш Чип',
                    'redirect' => null
                ],
                [
                    'keywords' => ['оплат', 'карта', 'наличн', 'деньги', 'способы оплаты', 'терминал', 'безнал'],
                    'text' => '<strong>Способы оплаты:</strong><br>• Наличные курьеру при получении<br>• Банковская карта курьеру (терминал)<br><br><em>Онлайн-оплата на сайте пока не доступна, но мы работаем над этим!</em>',
                    'redirect' => null
                ],
                [
                    'keywords' => ['промокод', 'промо', 'скидка', 'акция', 'бонус', 'купон', 'выгода', 'код'],
                    'text' => '<strong>Актуальные промокоды:</strong><br>• <strong>WELCOME10</strong> — 10% на первый заказ<br>• <strong>TASTE15</strong> — 15% при заказе от 1000₽<br><br>Все акции смотрите в разделе <a href="/house_of_taste/pages/index.php#promos" class="chat-link">Акции</a>.',
                    'redirect' => '/house_of_taste/pages/index.php'
                ],
                [
                    'keywords' => ['график', 'время работы', 'когда открыты', 'режим', 'часы', 'расписание', 'круглосуточно', '24/7'],
                    'text' => '<strong>Режим работы:</strong><br><strong>Круглосуточно</strong> — 24/7<br>Без перерывов и выходных!<br><br> Кухня принимает заказы непрерывно.<br>Доставка работает круглосуточно по Москве.',
                    'redirect' => null
                ]
            ];

            // Поиск во fallback-массиве
            foreach ($fallbacks as $fb) {
                foreach ($fb['keywords'] as $kw) {
                    if (mb_strpos($msgLower, $kw) !== false) {
                        return [
                            'text' => $fb['text'],
                            'redirect' => $fb['redirect'] ?? null,
                            'needsManager' => $fb['needsManager'] ?? false,
                            'requiresPhoto' => $fb['requiresPhoto'] ?? false
                        ];
                    }
                }
            }

            // Дефолтный ответ если ничего не найдено
            return [
                'text' => 'Я пока не знаю ответа на этот вопрос.<br><br>Попробуйте спросить про:<br><strong>меню</strong>, <strong>доставку</strong>, <strong>возврат</strong>, <strong>отзыв</strong>, <strong>менеджера</strong>, <strong>адрес</strong>, <strong>карту</strong>.<br><br>Или напишите иначе — я постараюсь помочь!',
                'redirect' => null,
                'needsManager' => false
            ];
        }

        /**
         * Сохраняет историю диалога
         */
        public function saveHistory($userId, $sessionId, $userMsg, $botResp) {
            if (!$this->pdo || empty($userMsg)) return;
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO chip_chat_history
                    (user_id, session_id, user_message, bot_response, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId ?: null, $sessionId, $userMsg, $botResp]);
            } catch (Exception $e) {
                error_log('ChipBot History Error: ' . $e->getMessage());
            }
        }

        /**
         * Сохраняет оценку разговора
         */
        public function saveRating($userId, $sessionId, $rating, $comment = null) {
            if (!$this->pdo || $rating < 1 || $rating > 5) return false;
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO chip_ratings
                    (user_id, session_id, rating, comment, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId ?: null, $sessionId, $rating, $comment]);
                return true;
            } catch (Exception $e) {
                error_log('ChipBot Rating Error: ' . $e->getMessage());
                return false;
            }
        }

        /**
         * Очищает историю чата для пользователя
         */
        public function clearHistory($userId) {
            if (!$this->pdo || !$userId) return;
            try {
                $stmt = $this->pdo->prepare("DELETE FROM chip_chat_history WHERE user_id = ?");
                $stmt->execute([$userId]);
            } catch (Exception $e) {
                error_log('ChipBot Clear Error: ' . $e->getMessage());
            }
        }
    }

    // ===== ЦЕНЗУРА: простой поиск без регексов =====
    $badWords = ['блин', 'черт', 'ёбан', 'пизд', 'хер', 'сука', 'бля', 'долб', 'мудак'];
    $isCensored = false;
    $msgLower = mb_strtolower($message);
    foreach ($badWords as $bw) {
        if (mb_strpos($msgLower, $bw) !== false) {
            $isCensored = true;
            break;
        }
    }

    if ($isCensored) {
        echo json_encode(['success' => true, 'text' => 'В чате приветствуется вежливое общение. Пожалуйста, переформулируйте вопрос.', 'censored' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== ОБРАБОТКА ДЕЙСТВИЙ =====
    $bot = new ChipBot($pdo);

    switch ($action) {
        case 'message':
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Пустое сообщение'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $answer = $bot->getResponse($message);
            $bot->saveHistory($userId, $sessionId, $message, $answer['text']);

            echo json_encode([
                'success' => true,
                'text' => $answer['text'],
                'redirect' => $answer['redirect'],
                'needsManager' => $answer['needsManager'] ?? false,
                'requiresPhoto' => $answer['requiresPhoto'] ?? false
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'rate':
            $rating = (int)($input['rating'] ?? 0);
            $comment = trim($input['comment'] ?? '');
            $success = $bot->saveRating($userId, $sessionId, $rating, $comment);
            echo json_encode(['success' => $success], JSON_UNESCAPED_UNICODE);
            break;

        case 'clear':
            $bot->clearHistory($userId);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . htmlspecialchars($action)], JSON_UNESCAPED_UNICODE);
    }

    exit; // КРИТИЧНО: завершаем AJAX-запрос, чтобы не выводить HTML
}

// ===== 2. ПОДКЛЮЧЕНИЕ ШАПКИ (для обычного просмотра страницы) =====
require_once __DIR__ . '/../includes/header.php';

// ===== 3. ДАННЫЕ СТРАНИЦЫ =====
$isAuthorized = isset($_SESSION['user_id']);
$userName = $isAuthorized ? htmlspecialchars($_SESSION['user_name'] ?? 'Гость') : '';
$userId = $isAuthorized ? (int)$_SESSION['user_id'] : 0;
$sessionId = session_id();
$manager = ['name' => 'Елена', 'phone' => '+7 (495) 123-45-67', 'tel' => '+74951234567'];
$address = 'Москва, ул. Тверская, 15';
$mapUrl = 'https://yandex.ru/map-widget/v1/?um=constructor%3A0&source=constructor';
?>

<!-- ===== БИБЛИОТЕКИ (CDN) ===== -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<br>

<div class="chip-chat-container">
    <!-- Оверлей авторизации -->
    <div class="auth-overlay <?= $isAuthorized ? 'hidden' : '' ?>" id="authOverlay">
        <div class="auth-icon"><i class="fas fa-user-lock"></i></div>
        <h4>Авторизация</h4>
        <p>Войдите или зарегистрируйтесь, чтобы общаться с Чипом и сохранять историю диалогов</p>
        <div class="auth-buttons">
            <button class="auth-btn primary" onclick="location.href='/house_of_taste/auth/login.php'">
                <i class="fas fa-sign-in-alt"></i> Войти
            </button>
            <button class="auth-btn secondary" onclick="location.href='/house_of_taste/auth/register.php'">
                <i class="fas fa-user-plus"></i> Регистрация
            </button>
        </div>
    </div>

    <!-- Шапка чата -->
    <div class="chip-chat-header">
        <div class="chip-avatar">Ч</div>
        <div class="chip-info">
            <h3>Чип <span style="font-weight:400;color:var(--text-secondary);font-size:12px;">| помощник</span></h3>
            <span class="chip-status"><i class="fas fa-circle" style="font-size:6px;"></i> Онлайн</span>
        </div>
        <div class="chip-actions">
            <button class="chip-action-btn" id="clearChatBtn" title="Очистить чат"><i class="fas fa-trash-can"></i></button>
            <button class="chip-action-btn" id="rateChatBtn" title="Оценить разговор"><i class="fas fa-star"></i></button>
        </div>
    </div>

    <!-- Область сообщений -->
    <div class="chip-messages" id="chatMessages">
        <div class="message bot">
            <div class="message-content">
                Привет, <?= $isAuthorized ? $userName : 'друг' ?>!<br><br>
                Я <strong>Чип</strong> - ваш персональный помощник в ресторане «Дом Вкуса».<br><br>
                Чем могу помочь?<br>
                • Показать меню<br>
                • Рассказать про доставку<br>
                • Помочь с возвратом<br>
                • Соединить с менеджером‍<br><br>
                Выберите вопрос ниже или напишите свой!
            </div>
            <span class="message-time"><?= date('H:i') ?></span>
        </div>
    </div>

    <!-- Быстрые ответы -->
    <div class="quick-replies" id="quickReplies">
        <button class="quick-reply-btn" data-action="menu"><i class="fas fa-utensils"></i> Меню</button>
        <button class="quick-reply-btn" data-action="delivery"><i class="fas fa-truck-fast"></i> Доставка</button>
        <button class="quick-reply-btn" data-action="refund"><i class="fas fa-rotate-left"></i> Возврат</button>
        <button class="quick-reply-btn" data-action="review"><i class="fas fa-comment"></i> Отзыв</button>
        <button class="quick-reply-btn" data-action="manager"><i class="fas fa-user-tie"></i> Менеджер</button>
    </div>

    <!-- Область ввода -->
    <div class="chip-input-area">
        <div class="upload-preview" id="uploadPreview">
            <img src="" class="preview-image" id="previewImg" alt="Предпросмотр">
            <button class="preview-remove" id="removeUpload" title="Удалить"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="input-wrapper">
            <textarea class="message-input" id="messageInput" placeholder="Напишите сообщение..." rows="1" maxlength="500"></textarea>
            <div class="input-actions">
                <button class="input-btn" id="attachBtn" title="Прикрепить фото"><i class="fas fa-paperclip"></i></button>
                <input type="file" id="fileInput" accept="image/*" class="sr-only">
                <button class="input-btn send-btn" id="sendBtn" title="Отправить"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно оценки -->
<div class="rating-modal" id="ratingModal">
    <div class="rating-card">
        <h4>Как прошёл разговор с Чипом?</h4>
        <div class="stars-input">
            <input type="radio" name="rating" id="star5" value="5"><label for="star5"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star4" value="4"><label for="star4"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star3" value="3"><label for="star3"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star2" value="2"><label for="star2"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star1" value="1"><label for="star1"><i class="fas fa-star"></i></label>
        </div>
        <textarea class="rating-comment" id="ratingComment" placeholder="Комментарий (необязательно)"></textarea>
        <div class="rating-actions">
            <button class="rating-btn cancel" id="cancelRating">Отмена</button>
            <button class="rating-btn submit" id="submitRating">Отправить</button>
        </div>
    </div>
</div>

<script>
(function($){ 'use strict';

const CONFIG = {
    userId: <?= $userId ?>,
    sessionId: '<?= addslashes($sessionId) ?>',
    isAuthorized: <?= $isAuthorized ? 'true' : 'false' ?>,
    manager: <?= json_encode($manager, JSON_UNESCAPED_UNICODE) ?>,
    address: <?= json_encode($address, JSON_UNESCAPED_UNICODE) ?>,
    mapUrl: <?= json_encode($mapUrl, JSON_UNESCAPED_UNICODE) ?>
};

class ChipBot {
    constructor(cfg) {
        this.cfg = cfg;
        this.currentPhoto = null;
        this.init();
    }

    init() {
        // Отправка по кнопке
        $('#sendBtn').on('click', () => this.send());

        // Отправка по Enter (без Shift)
        $('#messageInput').on('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });

        // Быстрые ответы
        $('.quick-reply-btn').on('click', e => {
            const action = $(e.currentTarget).data('action');
            this.quickAction(action);
        });

        // Загрузка фото
        $('#attachBtn').on('click', () => $('#fileInput').click());
        $('#fileInput').on('change', e => this.handleFile(e));
        $('#removeUpload').on('click', () => this.clearUpload());

        // Кнопки управления
        $('#clearChatBtn').on('click', () => this.clearChat());
        $('#rateChatBtn').on('click', () => $('#ratingModal').addClass('active'));
        $('#cancelRating').on('click', () => $('#ratingModal').removeClass('active'));
        $('#submitRating').on('click', () => this.submitRating());

        // Обработка ссылок в ответах бота
        $(document).on('click', '.message-content a.chat-link', e => {
            const action = $(e.currentTarget).data('action');
            if (action === 'show-map') {
                e.preventDefault();
                const mapHtml = `${CONFIG.address}<br><div class="map-embed"><iframe src="${CONFIG.mapUrl}" width="100%" height="250" frameborder="0"></iframe></div>`;
                this.addBot(mapHtml);
            }
        });

        // Блокировка для неавторизованных
        if (!this.cfg.isAuthorized) {
            const blockAuth = (e) => {
                if (!$(e.target).closest('.auth-overlay, .auth-btn').length) {
                    e.preventDefault();
                    this.showAuth();
                    return false;
                }
            };
            $('#sendBtn, .quick-reply-btn, .message-input').on('click keydown', blockAuth);
        }

        // Авто-высота textarea
        $('#messageInput').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }

    async send() {
        const $inp = $('#messageInput');
        const text = $inp.val().trim();
        const photo = this.currentPhoto;

        if (!text && !photo) return;
        if (!this.cfg.isAuthorized) { this.showAuth(); return; }

        // Добавляем сообщение пользователя
        this.addUser(text, photo);
        $inp.val('').css('height', 'auto');
        this.clearUpload();
        this.showTyping();

        try {
            const fd = new FormData();
            fd.append('action', 'message');
            fd.append('user_id', this.cfg.userId);
            fd.append('session_id', this.cfg.sessionId);
            if (text) fd.append('message', text);
            if (photo?.file) fd.append('photo', photo.file);

            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                credentials: 'same-origin'
            });

            this.hideTyping();
            const responseText = await response.text();

            // Парсим JSON с обработкой ошибок
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON parse error:', responseText);
                throw new Error('Некорректный ответ сервера');
            }

            // Обработка цензуры
            if (data?.censored) {
                this.addBot(data.text);
                return;
            }

            // Обработка ошибок
            if (data?.error) {
                this.addBot('' + data.error);
                return;
            }

            if (!data?.success) {
                this.addBot('Ошибка: ' + JSON.stringify(data));
                return;
            }

            // Редирект если указан
            if (data.redirect) {
                setTimeout(() => { window.location.href = data.redirect; }, 800);
                return;
            }

            // Ответ от бота
            if (data.needsManager) {
                this.addBot(`Позвоните менеджеру <strong>${CONFIG.manager.name}</strong>:<br><a href="tel:${CONFIG.manager.tel}" class="chat-link"><strong>${CONFIG.manager.phone}</strong></a>`);
            } else {
                this.addBot(data.text);
            }

            // Если бот просит фото — подсвечиваем кнопку
            if (data.requiresPhoto) {
                setTimeout(() => {
                    $('#attachBtn').effect('highlight', {color: '#c8a656'}, 1000);
                    this.toast(' Нажмите на иконку чтобы прикрепить фото', 'info');
                }, 500);
            }

        } catch (err) {
            this.hideTyping();
            this.addBot('⚠️ Ошибка соединения. Проверьте интернет и попробуйте снова.');
            console.error('ChipBot send error:', err);
        }
    }

    addUser(text, photo = null) {
        const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        const photoHtml = photo?.preview ? `<br><img src="${photo.preview}" alt="Фото">` : '';
        const escapedText = this.escapeHtml(text);
        const html = `<div class="message user">
            <div class="message-content">${escapedText}${photoHtml}</div>
            <span class="message-time">${time}</span>
        </div>`;
        $('#chatMessages').append(html);
        this.scrollBottom();
    }

    addBot(text) {
        const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        // Парсим Markdown-подобный текст
        const parsed = marked.parse(text, { breaks: true, sanitize: false });
        const html = `<div class="message bot">
            <div class="message-content">${parsed}</div>
            <span class="message-time">${time}</span>
        </div>`;
        $('#chatMessages').append(html);
        this.scrollBottom();
    }

    showTyping() {
        $('#chatMessages').append(`
            <div class="message bot typing" id="typingIndicator">
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `);
        this.scrollBottom();
    }

    hideTyping() {
        $('#typingIndicator').remove();
    }

    scrollBottom() {
        const $c = $('#chatMessages');
        $c.scrollTop($c[0].scrollHeight);
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    quickAction(action) {
        const actions = {
            menu: () => this.sendText('Покажите меню'),
            delivery: () => this.sendText('Расскажите про доставку'),
            refund: () => {
                this.sendText('Хочу оформить возврат');
                setTimeout(() => $('#attachBtn').click(), 400);
            },
            review: () => { window.location.href = '/house_of_taste/pages/reviews.php'; },
            manager: () => this.sendText('Соедините с менеджером'),
            map: () => this.sendText('Покажите карту')
        };
        if (actions[action]) actions[action]();
    }

    sendText(text) {
        $('#messageInput').val(text);
        this.send();
    }

    handleFile(e) {
        const file = e.target.files[0];
        if (!file || !file.type.startsWith('image/')) {
            this.toast('Выберите изображение (JPG, PNG, GIF)', 'error');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            this.toast('Фото должно быть меньше 5 МБ', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (ev) => {
            this.currentPhoto = { file, preview: ev.target.result };
            $('#previewImg').attr('src', ev.target.result);
            $('#uploadPreview').addClass('active');
        };
        reader.onerror = () => this.toast('Ошибка чтения файла', 'error');
        reader.readAsDataURL(file);
    }

    clearUpload() {
        this.currentPhoto = null;
        $('#fileInput').val('');
        $('#uploadPreview').removeClass('active');
    }

    clearChat() {
        if (!confirm('Очистить историю чата? Это действие нельзя отменить.')) return;

        const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        $('#chatMessages').html(`
            <div class="message bot">
                <div class="message-content">Чат очищен. Чем могу помочь?</div>
                <span class="message-time">${time}</span>
            </div>
        `);
        this.toast('Чат очищен', 'success');
        this.apiCall('clear');
    }

    async submitRating() {
        const rating = $('input[name="rating"]:checked').val();
        if (!rating) {
            this.toast('Выберите оценку', 'error');
            return;
        }

        const comment = $('#ratingComment').val().trim();

        try {
            const result = await this.apiCall('rate', '', { rating: +rating, comment });
            const data = await result.json();

            if (data.success) {
                this.toast('Спасибо за оценку!', 'success');
                $('#ratingModal').removeClass('active');
                $('input[name="rating"]').prop('checked', false);
                $('#ratingComment').val('');
            } else {
                throw new Error('Ошибка сохранения');
            }
        } catch (e) {
            this.toast('Не удалось отправить оценку', 'error');
            console.error('Rating error:', e);
        }
    }

    async apiCall(action, text = '', extra = {}) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('user_id', this.cfg.userId);
        fd.append('session_id', this.cfg.sessionId);
        if (text) fd.append('message', text);
        Object.entries(extra).forEach(([k, v]) => fd.append(k, v));

        return fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        });
    }

    showAuth() {
        $('#authOverlay').removeClass('hidden').hide().fadeIn(180);
        this.toast('Требуется авторизация', 'info');
    }

    toast(msg, type = 'info') {
        Toastify({
            text: msg,
            duration: type === 'error' ? 4000 : 3000,
            gravity: "bottom",
            position: "right",
            backgroundColor: type === 'error' ? '#e74c3c' : type === 'success' ? '#2ecc71' : '#c8a656',
            stopOnFocus: true,
            className: 'chip-toast'
        }).showToast();
    }
}

// Инициализация после загрузки DOM
$(document).ready(function() {
    window.chipBot = new ChipBot(CONFIG);

    // Фокус на поле ввода при загрузке (если авторизован)
    if (CONFIG.isAuthorized) {
        setTimeout(() => $('#messageInput').focus(), 500);
    }
});

})(jQuery);
</script>

<style>

:root {
    --gold: #c8a656;
    --gold-hover: #e8c96a;
    --dark-bg: #1a1a1a;
    --card-bg: #222;
    --text-primary: #fff;
    --text-secondary: #aaa;
    --border-color: rgba(200,166,86,0.2);
    --success: #2ecc71;
    --error: #e74c3c;
    --info: #3498db;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--dark-bg);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.5;
}

.chip-chat-container {
    max-width: 500px;
    margin: 120px auto 80px;
    background: linear-gradient(145deg, var(--card-bg), #1a1a1a);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 140px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.4);
}

.chip-chat-header {
    padding: 15px 18px;
    background: linear-gradient(135deg, #2a2a2a, #222);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.chip-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--dark-bg);
    font-weight: 700;
    font-size: 18px;
    position: relative;
    flex-shrink: 0;
}
.chip-avatar::after {
    content: '';
    position: absolute;
    width: 11px;
    height: 11px;
    background: var(--success);
    border: 2px solid var(--card-bg);
    border-radius: 50%;
    bottom: 1px;
    right: 1px;
}

.chip-info h3 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}
.chip-status {
    font-size: 10px;
    color: var(--success);
    display: flex;
    align-items: center;
    gap: 4px;
}

.chip-actions {
    margin-left: auto;
    display: flex;
    gap: 6px;
}
.chip-action-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    font-size: 12px;
}
.chip-action-btn:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(200,166,86,0.1);
}

.chip-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px 18px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #181818;
    scroll-behavior: smooth;
}
.chip-messages::-webkit-scrollbar { width: 5px; }
.chip-messages::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 3px;
}

.message {
    max-width: 88%;
    animation: slideIn 0.25s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.message.bot { align-self: flex-start; }
.message.user { align-self: flex-end; }

.message-content {
    padding: 11px 14px;
    border-radius: 15px;
    font-size: 13px;
    line-height: 1.45;
    word-wrap: break-word;
}
.message.bot .message-content {
    background: #2a2a2a;
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
    border: 1px solid var(--border-color);
}
.message.user .message-content {
    background: linear-gradient(135deg, var(--gold), var(--gold-hover));
    color: var(--dark-bg);
    border-bottom-right-radius: 4px;
    font-weight: 500;
}

.message-content img {
    max-width: 100%;
    max-height: 180px;
    border-radius: 10px;
    margin-top: 8px;
    display: block;
    object-fit: cover;
}
.message-content .map-embed {
    margin-top: 10px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.message-content .map-embed iframe {
    display: block;
    filter: grayscale(100%) invert(92%) contrast(85%);
}

.message-time {
    font-size: 9px;
    color: #555;
    margin-top: 4px;
    display: block;
    text-align: right;
}
.message.user .message-time {
    color: rgba(26,26,26,0.6);
}

.quick-replies {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    padding: 0 18px 12px;
    border-top: 1px solid var(--border-color);
    background: #1e1e1e;
}
.quick-reply-btn {
    padding: 7px 13px;
    background: rgba(200,166,86,0.12);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    color: var(--gold);
    font-size: 11px;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}
.quick-reply-btn:hover {
    background: var(--gold);
    color: var(--dark-bg);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(200,166,86,0.3);
}

.chip-input-area {
    padding: 12px 18px 15px;
    border-top: 1px solid var(--border-color);
    background: #1e1e1e;
}

.upload-preview {
    display: none;
    padding: 8px;
    background: #2a2a2a;
    border-radius: 10px;
    margin-bottom: 10px;
    position: relative;
}
.upload-preview.active { display: block; }
.preview-image {
    max-width: 100%;
    max-height: 140px;
    border-radius: 8px;
    object-fit: cover;
}
.preview-remove {
    position: absolute;
    top: -7px;
    right: -7px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--error);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    transition: 0.2s;
}
.preview-remove:hover { background: #c0392b; transform: scale(1.1); }

.input-wrapper {
    display: flex;
    gap: 9px;
    align-items: flex-end;
}
.message-input {
    flex: 1;
    background: #2a2a2a;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 11px 14px;
    color: var(--text-primary);
    font-size: 13px;
    resize: none;
    min-height: 42px;
    max-height: 110px;
    transition: border-color 0.2s;
    line-height: 1.4;
}
.message-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(200,166,86,0.15);
}

.input-actions {
    display: flex;
    gap: 5px;
}
.input-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    font-size: 14px;
}
.input-btn:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(200,166,86,0.1);
}
.input-btn.send-btn {
    background: var(--gold);
    border-color: var(--gold);
    color: var(--dark-bg);
}
.input-btn.send-btn:hover {
    background: var(--gold-hover);
    transform: scale(1.04);
    box-shadow: 0 4px 12px rgba(200,166,86,0.4);
}

.auth-overlay {
    position: absolute;
    inset: 0;
    background: rgba(26,26,26,0.96);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10;
    padding: 25px;
    text-align: center;
    backdrop-filter: blur(4px);
}
.auth-overlay.hidden { display: none !important; }

.auth-icon {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    color: var(--dark-bg);
    font-size: 26px;
}
.auth-overlay h4 {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px;
}
.auth-overlay p {
    font-size: 12px;
    color: var(--text-secondary);
    margin: 0 0 22px;
    max-width: 260px;
    line-height: 1.4;
}
.auth-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}
.auth-btn {
    padding: 9px 22px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: 0.25s;
    border: none;
    display: flex;
    align-items: center;
    gap: 6px;
}
.auth-btn.primary {
    background: var(--gold);
    color: var(--dark-bg);
}
.auth-btn.primary:hover {
    background: var(--gold-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(200,166,86,0.4);
}
.auth-btn.secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}
.auth-btn.secondary:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(200,166,86,0.1);
}

.rating-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.rating-modal.active { display: flex; animation: fadeIn 0.2s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.rating-card {
    background: linear-gradient(145deg, var(--card-bg), #1a1a1a);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 28px 25px;
    max-width: 380px;
    width: 100%;
    text-align: center;
    animation: slideUp 0.25s ease;
}
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.rating-card h4 {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 18px;
}

.stars-input {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-bottom: 22px;
    flex-direction: row-reverse;
}
.stars-input input { display: none; }
.stars-input label {
    font-size: 26px;
    color: #3a3a3a;
    cursor: pointer;
    transition: 0.15s;
}
.stars-input label:hover,
.stars-input input:checked ~ label {
    color: var(--gold);
    transform: scale(1.1);
    text-shadow: 0 0 10px rgba(200,166,86,0.5);
}

.rating-comment {
    width: 100%;
    background: #2a2a2a;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 11px 13px;
    color: var(--text-primary);
    font-size: 12px;
    margin-bottom: 18px;
    resize: none;
    min-height: 70px;
    line-height: 1.4;
}
.rating-comment:focus {
    outline: none;
    border-color: var(--gold);
}

.rating-actions {
    display: flex;
    gap: 9px;
    justify-content: center;
}
.rating-btn {
    padding: 9px 26px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    border: none;
}
.rating-btn.submit {
    background: var(--gold);
    color: var(--dark-bg);
}
.rating-btn.submit:hover {
    background: var(--gold-hover);
    transform: translateY(-2px);
}
.rating-btn.cancel {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}
.rating-btn.cancel:hover {
    border-color: var(--error);
    color: var(--error);
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 11px 14px;
    background: #2a2a2a;
    border-radius: 15px;
    border-bottom-left-radius: 4px;
    width: fit-content;
    border: 1px solid var(--border-color);
}
.typing-indicator span {
    width: 7px;
    height: 7px;
    background: var(--gold);
    border-radius: 50%;
    animation: bounce 1.3s infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.15s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.3s; }
@keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-7px); }
}

.message-content a.chat-link {
    color: var(--gold);
    text-decoration: none;
    font-weight: 600;
    border-bottom: 1px dashed rgba(200,166,86,0.5);
    transition: 0.2s;
}
.message-content a.chat-link:hover {
    border-bottom-style: solid;
    color: var(--gold-hover);
}

.hidden { display: none !important; }
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    border: 0;
}

/* Адаптив для мобильных */
@media (max-width: 480px) {
    .chip-chat-container {
        margin: 10px auto 30px;
        height: calc(100vh - 100px);
        border-radius: 16px;
    }
    .message { max-width: 92%; }
    .quick-reply-btn { font-size: 10px; padding: 6px 10px; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
