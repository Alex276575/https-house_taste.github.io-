<?php
$pageTitle = 'Условия использования | Дом Вкуса';
$currentPage = 'terms';
require_once $_SERVER['DOCUMENT_ROOT'] . '/house_of_taste/includes/header.php';
?>

<main class="legal-page" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">

    <div style="background: black; border-radius: 12px; padding: 40px; box-shadow: 0 4px 30px rgba(0,0,0,0.4); border: 1px solid #3a3a5a;">

        <h1 style="font-size: 28px; font-weight: 700; color: #f0f0f0; margin: 0 0 10px; border-bottom: 3px solid #c8a656; padding-bottom: 20px;">
            Условия использования
        </h1>
        <p style="color: #aaa; font-size: 14px; margin: 0 0 30px;">
            Последнее обновление: <?= date('d.m.Y') ?>
        </p>

        <!-- Раздел 1 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">1</span>
                Общие положения
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <p style="margin: 0 0 12px;">
                    Настоящие Условия использования (далее — «Условия») регулируют порядок использования веб-сайта
                    <strong>«Дом Вкуса»</strong> (далее — «Сайт»), расположенного по адресу:
                    <a href="https://houseoftaste.ru" style="color: #c8a656; text-decoration: none;">houseoftaste.ru</a>,
                    а также заказа товаров и услуг через Сайт.
                </p>
                <p style="margin: 0 0 12px;">
                    Используя Сайт, вы подтверждаете, что ознакомились с настоящими Условиями, понимаете их и принимаете в полном объёме.
                    Если вы не согласны с Условиями, пожалуйста, прекратите использование Сайта.
                </p>
                <p style="margin: 0;">
                    Администрация Сайта оставляет за собой право вносить изменения в настоящие Условия в любое время.
                    Актуальная версия всегда доступна по адресу: <code style="background: #2a2a4a; color: #c8a656; padding: 2px 6px; border-radius: 4px; font-size: 13px; border: 1px solid #3a3a5a;">/legal/terms.php</code>
                </p>
            </div>
        </section>

        <!-- Раздел 2 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">2</span>
                Регистрация и аккаунт
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;">Для оформления заказа регистрация не обязательна, но рекомендуется для отслеживания истории заказов и участия в программе лояльности.</li>
                    <li style="margin-bottom: 10px;">При регистрации вы обязуетесь предоставлять достоверную и актуальную информацию.</li>
                    <li style="margin-bottom: 10px;">Вы несёте ответственность за сохранность данных вашего аккаунта и за все действия, совершённые под вашей учётной записью.</li>
                    <li style="margin-bottom: 0;">Мы оставляем за собой право заблокировать аккаунт при подозрении на мошенничество или нарушение настоящих Условий.</li>
                </ul>
            </div>
        </section>

        <!-- Раздел 3 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">3</span>
                Заказ и доставка
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;"><strong style="color: #f0f0f0;">Оформление:</strong> Заказ считается принятым после подтверждения оператором или автоматической системой.</li>
                    <li style="margin-bottom: 10px;"><strong style="color: #f0f0f0;">Цены:</strong> Все цены указаны в рублях РФ и включают НДС. Администрация вправе изменять цены без предварительного уведомления.</li>
                    <li style="margin-bottom: 10px;"><strong style="color: #f0f0f0;">Доставка:</strong> Осуществляется по Москве в пределах МКАД. Время доставки: 40–60 минут. При заказе от 1500₽ — бесплатно, иначе — 199₽.</li>
                    <li style="margin-bottom: 10px;"><strong style="color: #f0f0f0;">Самовывоз:</strong> Доступен по адресу: Москва, ул. Тверская, 15. Готовность заказа: 20–30 минут.</li>
                    <li style="margin-bottom: 0;"><strong style="color: #f0f0f0;">Отмена:</strong> Заказ можно отменить до начала приготовления. После подтверждения готовности — отмена невозможна.</li>
                </ul>
            </div>
        </section>

        <!-- Раздел 4 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">4</span>
                Оплата
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;">Оплата производится при получении заказа: наличными или банковской картой курьеру/в ресторане.</li>
                    <li style="margin-bottom: 10px;">Онлайн-оплата на сайте в настоящее время не поддерживается.</li>
                    <li style="margin-bottom: 0;">При использовании промокодов скидка применяется автоматически при вводе корректного кода в корзине.</li>
                </ul>
            </div>
        </section>

        <!-- Раздел 5 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">5</span>
                Возврат и качество
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;">Продовольственные товары надлежащего качества возврату не подлежат (ст. 25 ЗоЗПП РФ).</li>
                    <li style="margin-bottom: 10px;">При обнаружении брака или несоответствия заказа — немедленно свяжитесь с нами:
                        <a href="tel:+74951234567" style="color: #c8a656; text-decoration: none;">+7 (495) 123-45-67</a>
                        или через чат-бота «Чип».
                    </li>
                    <li style="margin-bottom: 0;">Для рассмотрения претензии необходимо предоставить фото блюда и номер заказа. Решение принимается в течение 24 часов.</li>
                </ul>
            </div>
        </section>

        <!-- Раздел 6 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">6</span>
                Интеллектуальная собственность
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <p style="margin: 0 0 12px;">
                    Все материалы Сайта (тексты, изображения, логотипы, дизайн) являются собственностью ООО «Дом Вкуса»
                    или используются на законных основаниях. Копирование, распространение или использование контента
                    без письменного разрешения запрещено.
                </p>
                <p style="margin: 0;">
                    Допускается цитирование в информационных целях с обязательной ссылкой на источник.
                </p>
            </div>
        </section>

        <!-- Раздел 7 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">7</span>
                Ответственность и ограничения
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;">Сайт предоставляется «как есть». Мы не гарантируем бесперебойную работу и отсутствие технических сбоев.</li>
                    <li style="margin-bottom: 10px;">Администрация не несёт ответственности за убытки, возникшие в результате использования или невозможности использования Сайта.</li>
                    <li style="margin-bottom: 0;">Вы обязуетесь не использовать Сайт для незаконных действий, спама, взлома или распространения вредоносного ПО.</li>
                </ul>
            </div>
        </section>

        <!-- Раздел 8 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">8</span>
                Персональные данные
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <p style="margin: 0;">
                    Обработка персональных данных осуществляется в соответствии с
                    <a href="/house_of_taste/legal/privacy.php" style="color: #c8a656; text-decoration: none;">Политикой конфиденциальности</a>
                    и Федеральным законом № 152-ФЗ «О персональных данных».
                </p>
            </div>
        </section>

        <!-- Раздел 9 -->
        <section style="margin-bottom: 35px;">
            <h2 style="font-size: 20px; font-weight: 600; color: #f0f0f0; margin: 0 0 15px; display: flex; align-items: center; gap: 10px;">
                <span style="background: #c8a656; color: #1a1a1a; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700;">9</span>
                Заключительные положения
            </h2>
            <div style="color: #d0d0d0; line-height: 1.8; font-size: 15px;">
                <ul style="margin: 0; padding-left: 24px;">
                    <li style="margin-bottom: 10px;">Настоящие Условия регулируются законодательством Российской Федерации.</li>
                    <li style="margin-bottom: 10px;">Все споры разрешаются путём переговоров. При недостижении согласия — в судебном порядке по месту нахождения Администрации.</li>
                    <li style="margin-bottom: 0;">Реквизиты: ООО «Дом Вкуса», ИНН 7701234567, Москва, ул. Тверская, 15.</li>
                </ul>
            </div>
        </section>

        <!-- Контакты для вопросов -->
        <div style="background: #2a2a4a; border-left: 4px solid #c8a656; padding: 20px; border-radius: 0 8px 8px 0; margin-top: 40px; border: 1px solid #3a3a5a;">
            <p style="margin: 0 0 10px; font-weight: 600; color: #f0f0f0;">
                <i class="fas fa-question-circle" style="color: #c8a656; margin-right: 8px;"></i>
                Вопросы по Условиям?
            </p>
            <p style="margin: 0; color: #ccc; font-size: 14px;">
                Свяжитесь с нами:
                <a href="mailto:info@housetaste.ru" style="color: #c8a656; text-decoration: none;">info@housetaste.ru</a>
                или позвоните:
                <a href="tel:+74951234567" style="color: #c8a656; text-decoration: none;">+7 (495) 123-45-67</a>
            </p>
        </div>

    </div>
</main>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/house_of_taste/includes/footer.php'; ?>
