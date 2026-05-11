<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// 2. Создаем объект User
$user = new User();

// 3. Проверяем авторизацию ДО подключения header.php
$isLoggedIn = $user->isLoggedIn();

$pageTitle = 'Вход';
require_once __DIR__ . '/../includes/header.php';

// Если пользователь уже вошел — редирект на главную
if ($isLoggedIn) {
    header('Location: /house_of_taste/');
    exit;
}

$error = '';
$success = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Вызываем метод login() у объекта $user
    $result = $user->login($loginInput, $passwordInput, $remember);

    if ($result['success']) {
        $success = 'Вход выполнен успешно!';
        // Обновляем страницу, чтобы header.php подхватил новые данные сессии
        echo "<script>
            setTimeout(() => {
                window.location.href = '/house_of_taste/';
            }, 1000);
        </script>";
    } else {
        $error = $result['message'];
    }
}
?>

<!-- Подключаем библиотеки -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1 class="auth-title">Вход в <span>Дом Вкуса</span></h1>
        <p class="auth-subtitle">
            Вернитесь к любимым блюдам.<br>
            Введите данные для доступа к аккаунту.
        </p>

        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-grid">
                <div class="input-group pass-wrap">
                    <label class="inp-label"><i class="fas fa-user"></i> Логин или телефон</label>
                    <input type="text" name="login" class="inp-field" required
                           placeholder="chef_alex или +7..." autocomplete="username"
                           value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                </div>
                <div class="input-group pass-wrap">
                    <label class="inp-label"><i class="fas fa-lock"></i> Пароль</label>
                    <input type="password" name="password" id="loginPass" class="inp-field" required
                           placeholder="••••••••" autocomplete="current-password">
                    <div class="field-actions">
                        <i class="fas fa-eye field-icon" id="togglePass" title="Показать пароль"></i>
                    </div>
                </div>
            </div>
            <div class="remember-wrap">
                <label class="remember-label">
                    <input type="checkbox" name="remember">
                    <span>Запомнить меня</span>
                </label>
                <a href="#" class="forgot-link" onclick="showForgotModal(); return false;">
                    <i class="fas fa-key"></i> Забыли пароль?
                </a>
            </div>
            <button type="submit" class="btn-auth" id="loginBtn">
                <i class="fas fa-right-to-bracket"></i> Войти
            </button>
        </form>
        <div class="divider"><span>или</span></div>
        <div class="auth-footer">
            Нет аккаунта? <a href="/house_of_taste/auth/register.php">
                <i class="fas fa-user-plus"></i> Создать аккаунт
            </a>
        </div>
    </div>
</div>

<script>
    document.getElementById('togglePass').addEventListener('click', function() {
        const input = document.getElementById('loginPass');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        this.classList.toggle('fa-eye', isHidden);
        this.classList.toggle('fa-eye-slash', !isHidden);
    });

    function showForgotModal() {
        Swal.fire({
            title: '<i class="fas fa-key" style="color:#c8a656; font-size:32px"></i><br>Восстановление',
            html: `<input type="text" id="recoveryInput" class="swal2-input" placeholder="Email или телефон" style="background:#252525; border:1px solid #444; color:#fff;">`,
            confirmButtonText: 'Отправить',
            confirmButtonColor: '#c8a656',
            cancelButtonText: 'Отмена',
            showCancelButton: true,
            background: '#1e1e1e', color: '#fff',
            preConfirm: () => {
                const value = document.getElementById('recoveryInput').value.trim();
                if (!value) Swal.showValidationMessage('Введите данные');
                return value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ icon: 'success', title: 'Проверьте почту', confirmButtonColor: '#c8a656', background: '#1e1e1e', color: '#fff' });
            }
        });
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Вход...';
    });
</script>

<style>
    :root {
        --gold: #c8a656;
        --gold-dark: #a68a44;
        --gold-glow: rgba(200, 166, 86, 0.4);
        --bg-dark: #111;
        --card-bg: #1e1e1e;
        --input-bg: #252525;
        --text-primary: #fff;
        --text-secondary: #aaa;
        --error: #ff6b6b;
        --success: #51cf66;
    }
    * { font-family: 'Manrope', sans-serif; box-sizing: border-box; }
    .auth-wrapper {
        min-height: calc(100vh - 70px);
        display: flex; align-items: center; justify-content: center;
        padding: 80px 20px 40px;
        background: radial-gradient(ellipse at top, #1a1a2e 0%, var(--bg-dark) 60%);
        position: relative; overflow: hidden;
    }
    .auth-wrapper::before {
        content: ''; position: absolute; width: 600px; height: 600px;
        background: radial-gradient(circle, var(--gold-glow) 0%, transparent 70%);
        top: -200px; right: -100px; border-radius: 50%;
        opacity: 0.15; pointer-events: none;
        animation: pulse 8s ease-in-out infinite;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.15; }
        50% { transform: scale(1.1); opacity: 0.25; }
    }
    .auth-card {
        background: var(--card-bg); width: 100%; max-width: 480px;
        padding: 45px 40px; border-radius: 28px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(200, 166, 86, 0.1);
        position: relative; z-index: 2; backdrop-filter: blur(10px);
        animation: slideUp 0.4s ease-out;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .auth-title {
        text-align: center; font-size: 28px; font-weight: 800;
        color: var(--text-primary); margin-bottom: 8px; letter-spacing: -0.5px;
    }
    .auth-title span {
        color: var(--gold);
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .auth-subtitle {
        text-align: center; font-size: 14px; color: var(--text-secondary);
        margin-bottom: 32px; line-height: 1.5;
    }
    .alert {
        padding: 14px 18px; border-radius: 12px; font-size: 13px;
        margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
    }
    .alert-error {
        background: rgba(255, 107, 107, 0.12); border: 1px solid var(--error); color: var(--error);
    }
    .alert-success {
        background: rgba(81, 207, 102, 0.12); border: 1px solid var(--success); color: var(--success);
    }
    .form-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
    .inp-label {
        display: flex; align-items: center; gap: 8px; font-size: 11px;
        text-transform: uppercase; letter-spacing: 1.2px;
        color: var(--text-secondary); margin-bottom: 8px; font-weight: 700;
    }
    .inp-label i { color: var(--gold); font-size: 10px; width: 14px; text-align: center; }
    .inp-field {
        width: 100%; background: var(--input-bg); border: 1px solid #333;
        padding: 14px 48px 14px 18px; border-radius: 14px;
        color: var(--text-primary); font-size: 15px; transition: all 0.25s ease;
    }
    .inp-field:focus {
        border-color: var(--gold); outline: none; background: #2a2a2a;
        box-shadow: 0 0 0 4px var(--gold-glow);
    }
    .inp-field::placeholder { color: #555; }
    .pass-wrap { position: relative; }
    .field-actions {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        display: flex; align-items: center; gap: 4px;
    }
    .field-icon {
        color: #666; cursor: pointer; transition: all 0.2s; font-size: 14px;
        width: 32px; height: 32px; display: flex; align-items: center;
        justify-content: center; border-radius: 8px;
    }
    .field-icon:hover { color: var(--gold); background: rgba(200,166,86,0.12); }
    .remember-wrap {
        display: flex; justify-content: space-between; align-items: center;
        margin: 8px 0 24px;
    }
    .remember-label {
        display: flex; align-items: center; gap: 10px; font-size: 13px;
        color: var(--text-secondary); cursor: pointer; user-select: none;
    }
    .remember-label input { accent-color: var(--gold); width: 18px; height: 18px; cursor: pointer; }
    .forgot-link {
        font-size: 13px; color: var(--gold); text-decoration: none;
        font-weight: 500; transition: 0.2s;
    }
    .forgot-link:hover { text-decoration: underline; }
    .btn-auth {
        width: 100%; padding: 16px;
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: #111; border: none; border-radius: 14px; font-weight: 800;
        text-transform: uppercase; letter-spacing: 1.2px; cursor: pointer;
        transition: all 0.3s ease; font-size: 14px; position: relative; overflow: hidden;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 12px 30px var(--gold-glow); }
    .btn-auth:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .auth-footer {
        text-align: center; margin-top: 28px; font-size: 14px; color: var(--text-secondary);
    }
    .auth-footer a { color: var(--text-primary); text-decoration: none; font-weight: 700; }
    .auth-footer a:hover { color: var(--gold); }
    .divider {
        display: flex; align-items: center; margin: 24px 0;
        color: var(--text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
    }
    .divider::before, .divider::after {
        content: ''; flex: 1; height: 1px; background: #333;
    }
    .divider span { padding: 0 15px; }
    @media (max-width: 600px) {
        .auth-card { padding: 35px 25px; }
        .auth-title { font-size: 24px; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
