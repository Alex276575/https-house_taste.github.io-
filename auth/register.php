<?php

$pageTitle = 'Регистрация';
require_once __DIR__ . '/../includes/header.php';

if ($isLoggedIn) {
    header('Location: /house_of_taste/');
    exit;
}
?>

<!-- Подключаем библиотеки -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="auth-wrapper">
    <div class="reg-card">
        <h1 class="reg-title">Добро пожаловать в <span>Дом Вкуса</span></h1>
        <p class="reg-subtitle">
            <strong>Ресторанная еда премиум-класса</strong> с доставкой на дом или самовывозом.<br>
            Создайте аккаунт за 30 секунд и начните наслаждаться вкусом.
        </p>

        <form id="regForm" method="POST" enctype="multipart/form-data" novalidate>
            <div class="form-grid">
                <!-- Логин -->
                <div class="input-group">
                    <label class="inp-label"><i class="fas fa-at"></i> Логин</label>
                    <input type="text" name="login" id="regLogin" class="inp-field" required
                           placeholder="chef_alex" autocomplete="username"
                           maxlength="30" pattern="[a-zA-Z0-9_]+">
                    <div class="error-msg" id="err-login">Только латиница, цифры и _</div>
                </div>

                <!-- Пароль -->
                <div class="input-group pass-wrap">
                    <label class="inp-label"><i class="fas fa-lock"></i> Пароль</label>
                    <input type="password" name="password" id="regPass" class="inp-field" required
                           placeholder="Придумайте надёжный пароль" autocomplete="new-password"
                           maxlength="50">
                    <div class="pass-actions">
                        <i class="fas fa-eye eye-icon" id="togglePass" title="Показать пароль"></i>
                        <i class="fas fa-magic gen-icon" id="genPass" title="Сгенерировать пароль"></i>
                    </div>
                    <div class="error-msg" id="err-pass">Мин. 8 символов, буквы + цифры</div>

                    <!-- Индикатор надёжности -->
                    <div class="strength-meter" id="strengthMeter">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Надёжность пароля</div>
                    </div>

                    <button type="button" class="gen-pass-btn" id="genPassBtn">
                        <i class="fas fa-dice"></i> Сгенерировать надёжный пароль
                    </button>
                </div>

                <!-- Повтор пароля -->
                <div class="input-group pass-wrap">
                    <label class="inp-label"><i class="fas fa-lock-open"></i> Повторите пароль</label>
                    <input type="password" name="password_confirm" id="regPassConfirm" class="inp-field" required
                           placeholder="Подтвердите пароль" autocomplete="new-password"
                           maxlength="50">
                    <i class="fas fa-eye eye-icon" id="togglePassConfirm" style="position:absolute; right:15px; top:38px;"></i>
                    <div class="error-msg" id="err-confirm">Пароли не совпадают</div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #333; margin: 24px 0;">

            <div class="form-grid">
                <!-- Имя -->
                <div class="input-group">
                    <label class="inp-label"><i class="fas fa-user"></i> Ваше имя</label>
                    <input type="text" name="full_name" id="regName" class="inp-field" required
                           placeholder="Александр" autocomplete="name" maxlength="50">
                    <div class="error-msg" id="err-name">Только буквы (рус/англ)</div>
                </div>

                <!-- Телефон -->
                <div class="input-group">
                    <label class="inp-label"><i class="fas fa-phone"></i> Телефон</label>
                    <input type="tel" name="phone" id="regPhone" class="inp-field" required
                           placeholder="+7 (999) 000-00-00" autocomplete="tel" maxlength="20">
                </div>
            </div>

            <!-- Аватар -->
            <div class="ava-upload-box">
                <div class="ava-preview" id="avaPreviewBox">
                    <i class="fas fa-user-chef"></i>
                    <img id="avaImg" src="" alt="Avatar">
                </div>
                <label for="avatarFile" class="upload-btn-sm">
                    <i class="fas fa-cloud-upload-alt"></i> Загрузить фото профиля
                </label>
                <input type="file" id="avatarFile" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                    <i class="fas fa-info-circle"></i> JPG, PNG, WEBP до 2 МБ (необязательно)
                </div>
            </div>

            <!-- Согласие -->
            <div class="policy-check">
                <input type="checkbox" id="policy" required>
                <span>Я принимаю <a href="/house_of_taste/legal/privacy.php" target="_blank">Политику конфиденциальности</a> и даю согласие на обработку персональных данных.</span>
            </div>

            <button type="submit" class="btn-reg" id="regBtn">
                <i class="fas fa-user-plus"></i> Создать аккаунт
            </button>
        </form>

        <div class="login-link">
            Уже с нами? <a href="/house_of_taste/auth/login.php"><i class="fas fa-sign-in-alt"></i> Войти в аккаунт</a>
        </div>
    </div>
</div>

<!-- Скрипты -->
<script src="/house_of_taste/public/js/auth.js"></script>

<script>
    // === Глобальные настройки ===
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: { popup: 'swal2-toast' }
    });

    // === 1. Предпросмотр аватара ===
    function previewAvatar(input) {
        const previewBox = document.getElementById('avaPreviewBox');
        const img = document.getElementById('avaImg');
        const icon = previewBox.querySelector('i');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                Toast.fire({ icon: 'error', title: 'Файл слишком большой (макс. 2 МБ)' });
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
                icon.style.display = 'none';
                previewBox.style.borderColor = 'var(--gold)';
                Toast.fire({ icon: 'success', title: 'Фото загружено' });
            }
            reader.readAsDataURL(file);
        }
    }

    // === 2. Мгновенная фильтрация ввода ===
    document.getElementById('regLogin').addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '').replace(/[а-яА-ЯёЁ]/g, '');
    });

    ['regPass', 'regPassConfirm'].forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            this.value = this.value.replace(/[а-яА-ЯёЁ]/g, '');
        });
    });

    document.getElementById('regName').addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Zа-яА-ЯёЁ\s-]/g, '');
    });

    document.getElementById('regPhone').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9+\s\-\(\)]/g, '');
    });

    // === 3. Проверка пароля с zxcvbn ===
    const passInput = document.getElementById('regPass');
    const strengthMeter = document.getElementById('strengthMeter');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');

    passInput.addEventListener('input', function() {
        const val = this.value;

        if (val.length === 0) {
            strengthMeter.classList.remove('active');
            return;
        }

        strengthMeter.classList.add('active');
        const result = zxcvbn(val);
        const score = result.score; // 0-4

        // Очистка классов
        strengthFill.className = 'strength-fill';
        strengthText.className = 'strength-text';

        // Применение стилей
        const levels = ['weak', 'fair', 'good', 'strong'];
        const labels = ['Очень слабый', 'Слабый', 'Хороший', 'Отличный'];
        const colors = ['#ff6b6b', '#fcc419', '#4dabf7', '#51cf66'];

        if (score < 2) {
            strengthFill.classList.add(levels[0]);
            strengthText.classList.add(levels[0]);
            strengthText.style.color = colors[0];
        } else if (score === 2) {
            strengthFill.classList.add(levels[1]);
            strengthText.classList.add(levels[1]);
            strengthText.style.color = colors[1];
        } else if (score === 3) {
            strengthFill.classList.add(levels[2]);
            strengthText.classList.add(levels[2]);
            strengthText.style.color = colors[2];
        } else {
            strengthFill.classList.add(levels[3]);
            strengthText.classList.add(levels[3]);
            strengthText.style.color = colors[3];
        }

        strengthText.innerText = `Надёжность: ${labels[score]}`;

        // Подсказка, если пароль слабый
        if (score < 3 && val.length >= 8) {
            strengthText.innerHTML += ` <i class="fas fa-lightbulb" style="margin-left:5px" title="Добавьте цифры и спецсимволы"></i>`;
        }
    });

    // === 4. Генератор надёжного пароля ===
    function generatePassword(length = 14, options = {}) {
        const defaults = {
            lowercase: true,
            uppercase: true,
            numbers: true,
            symbols: true
        };
        const settings = { ...defaults, ...options };

        let charset = '';
        if (settings.lowercase) charset += 'abcdefghijklmnopqrstuvwxyz';
        if (settings.uppercase) charset += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (settings.numbers) charset += '0123456789';
        if (settings.symbols) charset += '!@#$%^&*()_+-=';

        if (!charset) charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        let password = '';
        const array = new Uint32Array(length);
        crypto.getRandomValues(array);

        for (let i = 0; i < length; i++) {
            password += charset[array[i] % charset.length];
        }

        return password;
    }

    function applyGeneratedPassword() {
        const pass = generatePassword(16);
        const passInput = document.getElementById('regPass');
        const confirmInput = document.getElementById('regPassConfirm');

        passInput.value = pass;
        confirmInput.value = pass;

        // Показать пароль на 3 секунды, затем скрыть
        passInput.type = 'text';
        confirmInput.type = 'text';

        Toast.fire({
            icon: 'success',
            title: 'Пароль сгенерирован!',
            text: 'Скопируйте его и сохраните в надёжном месте'
        });

        setTimeout(() => {
            passInput.type = 'password';
            confirmInput.type = 'password';
        }, 3000);

        // Запустить проверку надёжности
        passInput.dispatchEvent(new Event('input'));
    }

    document.getElementById('genPass').addEventListener('click', applyGeneratedPassword);
    document.getElementById('genPassBtn').addEventListener('click', applyGeneratedPassword);

    // === 5. Показать/скрыть пароль ===
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.getElementById('togglePass').addEventListener('click', () => {
        togglePasswordVisibility('regPass', 'togglePass');
    });
    document.getElementById('togglePassConfirm').addEventListener('click', () => {
        togglePasswordVisibility('regPassConfirm', 'togglePassConfirm');
    });

    // === 6. Валидация формы ===
    const loginInput = document.getElementById('regLogin');
    const nameInput = document.getElementById('regName');
    const confirmInput = document.getElementById('regPassConfirm');

    const regexLogin = /^[a-zA-Z0-9_]{3,30}$/;
    const regexName = /^[a-zA-Zа-яА-ЯёЁ\s-]{2,50}$/;

    function showError(input, errId, show, msg = '') {
        const errEl = document.getElementById(errId);
        if (show) {
            input.classList.add('error');
            if (errEl) {
                if (msg) errEl.innerText = msg;
                errEl.style.display = 'block';
            }
        } else {
            input.classList.remove('error');
            if (errEl) errEl.style.display = 'none';
        }
    }

    function validatePassword() {
        const p1 = passInput.value;
        const p2 = confirmInput.value;
        const hasCyrillic = /[а-яА-ЯёЁ]/.test(p1);
        const isValid = p1.length >= 8 && /[A-Za-z]/.test(p1) && /\d/.test(p1) && !hasCyrillic;

        if (p1.length > 0 && !isValid) {
            showError(passInput, 'err-pass', true, hasCyrillic ? 'Кириллица запрещена' : 'Мин. 8 симв., буквы + цифры');
        } else {
            showError(passInput, 'err-pass', false);
        }

        if (p2.length > 0 && p1 !== p2) {
            showError(confirmInput, 'err-confirm', true);
        } else {
            showError(confirmInput, 'err-confirm', false);
        }
    }

    passInput.addEventListener('input', validatePassword);
    confirmInput.addEventListener('input', validatePassword);

    // === 7. Отправка формы с красивыми уведомлениями ===
    document.getElementById('regForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        let hasError = false;

        if (!regexLogin.test(loginInput.value)) {
            showError(loginInput, 'err-login', true);
            hasError = true;
        }
        if (!regexName.test(nameInput.value)) {
            showError(nameInput, 'err-name', true);
            hasError = true;
        }
        if (passInput.value.length < 8 || !/[A-Za-z]/.test(passInput.value) || !/\d/.test(passInput.value)) {
            showError(passInput, 'err-pass', true);
            hasError = true;
        }
        if (passInput.value !== confirmInput.value) {
            showError(confirmInput, 'err-confirm', true);
            hasError = true;
        }
        if (!document.getElementById('policy').checked) {
            Swal.fire({
                icon: 'warning',
                title: 'Требуется согласие',
                text: 'Подтвердите принятие Политики конфиденциальности',
                confirmButtonColor: '#c8a656'
            });
            return;
        }

        if (hasError) {
            Toast.fire({ icon: 'error', title: 'Проверьте заполнение полей' });
            return;
        }

        // Показываем загрузку
        const btn = document.getElementById('regBtn');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Регистрация...';

        try {
            const formData = new FormData(this);
            const response = await fetch('/house_of_taste/auth/register_process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                // Успешная регистрация
                await Swal.fire({
                    icon: 'success',
                    title: 'Добро пожаловать!',
                    html: `<strong>${loginInput.value}</strong>, ваш аккаунт создан!<br><small>Теперь вы можете оформить первый заказ</small>`,
                    confirmButtonColor: '#c8a656',
                    confirmButtonText: 'Перейти в каталог'
                });
                window.location.href = '/house_of_taste/auth/login.php?registered=1';
            } else {
                // Ошибка от сервера
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка регистрации',
                    text: data.message || 'Попробуйте ещё раз',
                    confirmButtonColor: '#c8a656'
                });
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Ошибка сети',
                text: 'Проверьте подключение к интернету',
                confirmButtonColor: '#c8a656'
            });
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
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
        --warning: #fcc419;
    }

    * { font-family: 'Manrope', sans-serif; }

    .auth-wrapper {
        min-height: calc(100vh - 70px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 80px 20px 40px;
        background: radial-gradient(ellipse at top, #1a1a2e 0%, var(--bg-dark) 60%);
        position: relative;
        overflow: hidden;
    }

    /* Декоративные элементы фона */
    .auth-wrapper::before {
        content: '';
        position: absolute;
        width: 600px; height: 600px;
        background: radial-gradient(circle, var(--gold-glow) 0%, transparent 70%);
        top: -200px; right: -100px;
        border-radius: 50%;
        opacity: 0.15;
        pointer-events: none;
        animation: pulse 8s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.15; }
        50% { transform: scale(1.1); opacity: 0.25; }
    }

    .reg-card {
        background: var(--card-bg);
        width: 100%;
        max-width: 520px;
        padding: 45px 40px;
        border-radius: 28px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.7),
            0 0 0 1px rgba(200, 166, 86, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.05);
        position: relative;
        z-index: 2;
        backdrop-filter: blur(10px);
    }

    .reg-title {
        text-align: center;
        font-size: 28px;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }
    .reg-title span {
        color: var(--gold);
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .reg-subtitle {
        text-align: center;
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 32px;
        line-height: 1.5;
        font-weight: 500;
    }
    .reg-subtitle strong { color: var(--gold); }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }

    .inp-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: var(--text-secondary);
        margin-bottom: 8px;
        font-weight: 700;
    }
    .inp-label i { color: var(--gold); font-size: 10px; }

    .inp-field {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid #333;
        padding: 14px 18px;
        border-radius: 14px;
        color: var(--text-primary);
        font-size: 15px;
        transition: all 0.25s ease;
        box-sizing: border-box;
    }
    .inp-field:focus {
        border-color: var(--gold);
        outline: none;
        background: #2a2a2a;
        box-shadow: 0 0 0 4px var(--gold-glow);
    }
    .inp-field::placeholder { color: #555; }

    .inp-field.error {
        border-color: var(--error);
        animation: shake 0.3s ease;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-4px); }
        75% { transform: translateX(4px); }
    }

    .error-msg {
        color: var(--error);
        font-size: 12px;
        margin-top: 6px;
        display: none;
        font-weight: 500;
        padding-left: 4px;
    }

    /* Поле пароля с индикатором */
    .pass-wrap { position: relative; }

    .pass-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        position: absolute;
        right: 12px;
        top: 14px;
    }

    .eye-icon, .gen-icon {
        color: #666;
        cursor: pointer;
        transition: 0.2s;
        font-size: 14px;
        padding: 4px;
        border-radius: 6px;
    }
    .eye-icon:hover, .gen-icon:hover {
        color: var(--gold);
        background: rgba(200,166,86,0.1);
    }

    /* Индикатор надёжности пароля */
    .strength-meter {
        margin-top: 10px;
        display: none;
    }
    .strength-meter.active { display: block; }

    .strength-bar {
        height: 4px;
        background: #333;
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    .strength-fill {
        height: 100%;
        width: 0%;
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    .strength-fill.weak { width: 25%; background: var(--error); }
    .strength-fill.fair { width: 50%; background: var(--warning); }
    .strength-fill.good { width: 75%; background: #4dabf7; }
    .strength-fill.strong { width: 100%; background: var(--success); }

    .strength-text {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .strength-text.weak { color: var(--error); }
    .strength-text.fair { color: var(--warning); }
    .strength-text.good { color: #4dabf7; }
    .strength-text.strong { color: var(--success); }

    /* Кнопка генерации пароля */
    .gen-pass-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: transparent;
        border: 1px solid #444;
        color: var(--text-secondary);
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 8px;
    }
    .gen-pass-btn:hover {
        border-color: var(--gold);
        color: var(--gold);
        background: rgba(200,166,86,0.08);
    }
    .gen-pass-btn i { font-size: 11px; }

    /* Блок аватара */
    .ava-upload-box {
        background: var(--input-bg);
        border: 1px dashed #444;
        border-radius: 16px;
        padding: 20px;
        margin: 24px 0;
        text-align: center;
        transition: all 0.3s ease;
    }
    .ava-upload-box:hover {
        border-color: var(--gold);
        background: rgba(200,166,86,0.03);
    }

    .ava-preview {
        width: 90px; height: 90px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2a2a2a, #1a1a1a);
        margin: 0 auto 12px;
        overflow: hidden;
        border: 3px solid #333;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: all 0.3s ease;
    }
    .ava-preview:hover {
        border-color: var(--gold);
        transform: scale(1.03);
        box-shadow: 0 0 20px var(--gold-glow);
    }
    .ava-preview img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: none;
    }
    .ava-preview i {
        font-size: 32px;
        color: #555;
        transition: 0.3s;
    }
    .ava-preview:hover i { color: var(--gold); }

    .upload-btn-sm {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--gold);
        cursor: pointer;
        font-weight: 600;
    }
    .upload-btn-sm:hover { text-decoration: none; opacity: 0.9; }

    /* Чекбокс политики */
    .policy-check {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin: 24px 0;
        font-size: 13px;
        color: var(--text-secondary);
        line-height: 1.5;
    }
    .policy-check input {
        accent-color: var(--gold);
        margin-top: 3px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .policy-check a {
        color: var(--gold);
        text-decoration: none;
        font-weight: 600;
        transition: 0.2s;
    }
    .policy-check a:hover { text-decoration: underline; }

    /* Кнопка регистрации */
    .btn-reg {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: #111;
        border: none;
        border-radius: 14px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        position: relative;
        overflow: hidden;
    }
    .btn-reg::before {
        content: '';
        position: absolute;
        top: 0; left: -100%;
        width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }
    .btn-reg:hover::before { left: 100%; }
    .btn-reg:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px var(--gold-glow);
    }
    .btn-reg:active { transform: translateY(0); }
    .btn-reg:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .login-link {
        text-align: center;
        margin-top: 28px;
        font-size: 14px;
        color: var(--text-secondary);
    }
    .login-link a {
        color: var(--text-primary);
        text-decoration: none;
        font-weight: 700;
        transition: 0.2s;
    }
    .login-link a:hover { color: var(--gold); }

    /* Адаптив */
    @media (max-width: 600px) {
        .reg-card { padding: 35px 25px; }
        .reg-title { font-size: 24px; }
        .form-grid { gap: 16px; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
