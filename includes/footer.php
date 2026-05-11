<?php

$footerCategories = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_available = 1) as product_count
    FROM categories c
    WHERE c.parent_id IS NULL
    ORDER BY c.sort_order
")->fetchAll();

$contact = [
    'address' => 'Москва, ул. Тверская, 15',
    'phone' => '+7 (495) 123-45-67',
    'phone_link' => 'tel:+74951234567',
    'hours' => 'Ежедневно 10:00 - 23:00',
    'email' => 'info@housetaste.ru'
];

?>

<!-- ===== FOOTER ===== -->
<footer class="site-footer" style="margin-top: auto; background: #111; border-top: 1px solid rgba(255,255,255,0.05);">

    <!-- Верхняя часть с колонками -->
    <div class="footer-main" style="max-width: 1300px; margin: 0 auto; padding: 40px 20px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px;">

        <!-- Бренд -->
        <div class="footer-brand">
            <a href="/house_of_taste/" style="text-decoration: none; display: inline-flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #c8a656, #e8c96a); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; color: #1a1a1a; flex-shrink: 0;">Д</div>
                <div>
                    <span style="color: #fff; font-weight: 800; font-size: 17px; display: block; line-height: 1.1;">ДОМ</span>
                    <span style="color: #c8a656; font-weight: 800; font-size: 17px; display: block; line-height: 1.1;">ВКУСА</span>
                </div>
            </a>
            <p style="font-size: 13px; color: #777; line-height: 1.7; margin: 0 0 15px;">
                Ресторан авторской кухни и безалкогольных коктейлей. Свежие ингредиенты, доставка по Москве.
            </p>

            <!-- Соцсети -->
            <div class="footer-social" style="display: flex; gap: 12px; margin-top: 10px;">

            </div>
        </div>

        <!-- Меню сайта -->
        <div class="footer-col">
            <h4 style="font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #c8a656; margin: 0 0 18px; padding-bottom: 8px; border-bottom: 1px solid rgba(200,166,86,0.2);">Навигация</h4>
            <ul style="list-style: none; padding: 0; margin: 0; line-height: 2.2;">
                <li><a href="/house_of_taste/" style="font-size: 13px; color: #888; text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 6px;"><i class="fas fa-chevron-right" style="font-size: 9px; color: #c8a656;"></i>Главная</a></li>
                <li><a href="/house_of_taste/pages/catalog.php" style="font-size: 13px; color: #888; text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 6px;"><i class="fas fa-chevron-right" style="font-size: 9px; color: #c8a656;"></i>Каталог</a></li>
                <li><a href="/house_of_taste/pages/about.php" style="font-size: 13px; color: #888; text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 6px;"><i class="fas fa-chevron-right" style="font-size: 9px; color: #c8a656;"></i>О ресторане</a></li>
                <li><a href="/house_of_taste/pages/contacts.php" style="font-size: 13px; color: #888; text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 6px;"><i class="fas fa-chevron-right" style="font-size: 9px; color: #c8a656;"></i>Поддержка</a></li>
            </ul>
        </div>

        <!-- Категории меню -->
        <div class="footer-col">
            <h4 style="font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #c8a656; margin: 0 0 18px; padding-bottom: 8px; border-bottom: 1px solid rgba(200,166,86,0.2);">Категории</h4>
            <ul style="list-style: none; padding: 0; margin: 0; line-height: 2.2;">
                <?php foreach ($footerCategories as $cat): ?>
                    <li>
                        <a href="/house_of_taste/pages/catalog.php?category=<?= $cat['id'] ?>"
                           style="font-size: 13px; color: #888; text-decoration: none; transition: color 0.2s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-chevron-right" style="font-size: 9px; color: #c8a656;"></i>
                            <?= htmlspecialchars($cat['name']) ?>
                            <?php if ($cat['product_count'] > 0): ?>
                                <span style="color: #555; font-size: 11px; margin-left: auto;">(<?= $cat['product_count'] ?>)</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Контакты -->
        <div class="footer-col">
            <h4 style="font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #c8a656; margin: 0 0 18px; padding-bottom: 8px; border-bottom: 1px solid rgba(200,166,86,0.2);">Контакты</h4>
            <ul style="list-style: none; padding: 0; margin: 0; line-height: 2.3; font-size: 13px; color: #888;">
                <li style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-map-marker-alt" style="color: #c8a656; font-size: 12px; margin-top: 3px; flex-shrink: 0;"></i>
                    <span><?= htmlspecialchars($contact['address']) ?></span>
                </li>
                <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-clock" style="color: #c8a656; font-size: 12px; flex-shrink: 0;"></i>
                    <span><?= htmlspecialchars($contact['hours']) ?></span>
                </li>
                <li style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-phone" style="color: #c8a656; font-size: 12px; flex-shrink: 0;"></i>
                    <a href="<?= htmlspecialchars($contact['phone_link']) ?>" style="color: #888; text-decoration: none; transition: color 0.2s;"><?= htmlspecialchars($contact['phone']) ?></a>
                </li>
                <li style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-envelope" style="color: #c8a656; font-size: 12px; flex-shrink: 0;"></i>
                    <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" style="color: #888; text-decoration: none; transition: color 0.2s;"><?= htmlspecialchars($contact['email']) ?></a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Нижняя часть -->
    <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.05); padding: 18px 20px; text-align: center; max-width: 1300px; margin: 0 auto;">
        <p style="font-size: 12px; color: #666; margin: 0; line-height: 1.8;">
            &copy; <?= date('Y') ?> «Дом Вкуса». Все права защищены.
            <span style="margin: 0 8px; color: #444;">|</span>
            <a href="/house_of_taste/legal/privacy.php" style="color: #777; text-decoration: none; transition: color 0.2s;">Политика конфиденциальности</a>
            <span style="margin: 0 8px; color: #444;">|</span>
            <a href="/house_of_taste/legal/terms.php" style="color: #777; text-decoration: none; transition: color 0.2s;">Условия использования</a>
        </p>
    </div>
</footer>

<style>
    .site-footer a:hover { color: #c8a656 !important; }
    .site-footer .footer-brand a:hover div:first-child { transform: scale(1.05); transition: transform 0.2s; }
    .site-footer [class*="fa-"] { transition: color 0.2s; }
    .site-footer a:hover [class*="fa-"] { color: #c8a656 !important; }
    .footer-social a:hover { background: #c8a656 !important; transform: translateY(-2px); }

    @media (max-width: 768px) {
        .footer-main { grid-template-columns: 1fr; text-align: center; padding: 30px 15px 15px; }
        .footer-col ul li a { justify-content: center !important; }
        .footer-col ul li a i { display: none; }
        .footer-brand { display: flex; flex-direction: column; align-items: center; }
        .footer-social { justify-content: center; }
        .footer-bottom p { font-size: 11px; }
        .footer-bottom span { display: block; margin: 4px 0 !important; }
    }
</style>
