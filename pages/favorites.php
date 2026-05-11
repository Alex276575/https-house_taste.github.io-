<?php
$pageTitle = 'Избранное';
require_once __DIR__ . '/../includes/header.php';

?>

<main class="fav-container">

    <div class="page-header">
        <h1 class="page-title">
            Избранное <span class="fav-count-badge" id="fav-count-header">0</span>
        </h1>
        <a href="/house_of_taste/pages/catalog.php" style="color: #999; text-decoration: none; font-size: 14px;">
            <i class="fas fa-arrow-left"></i> В каталог
        </a>
    </div>

    <div id="fav-content">
        <!-- Сюда JS загрузит товары -->
        <div style="text-align:center; padding: 40px; color:#666;">
            <i class="fas fa-spinner fa-spin"></i> Загрузка...
        </div>
    </div>

    <!-- Секция истории заказов (появится если есть заказы) -->
<?php if ($isLoggedIn): ?>
<div class="history-section" id="history-section" style="display:none;">
    <h2 class="history-title">Вы уже заказывали:</h2>
    <div class="history-grid" id="history-grid">
        <!-- JS заполнит -->
    </div>
</div>
<?php endif; ?>

</main>

<script>
// Вспомогательная функция для экранирования кавычек в JS
function addslashes(str) {
    return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

document.addEventListener('DOMContentLoaded', () => {
    loadFavoritesPage();
    loadOrderHistory(); // Загружаем историю если пользователь залогинен
});

function loadFavoritesPage() {
    const favs = getFavorites();
    const container = document.getElementById('fav-content');
    const headerCount = document.getElementById('fav-count-header');

    headerCount.innerText = favs.length;

    if (favs.length === 0) {
        container.innerHTML = `
            <div class="empty-fav">
                <i class="far fa-heart"></i>
                <h2>Список пуст</h2>
                <p style="margin-bottom: 30px; color: #666;">Добавляйте понравившиеся блюда, нажимая на сердечко в каталоге.</p>
                <a href="/house_of_taste/pages/catalog.php" style="padding: 12px 25px; background: #c8a656; color: #1a1a1a; text-decoration: none; border-radius: 8px; font-weight: 600;">Перейти в каталог</a>
            </div>
        `;
        return;
    }

    // Отправляем ID на сервер через AJAX
    fetch('/house_of_taste/api/favorites_get.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ ids: favs })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.products || data.products.length === 0) {
            container.innerHTML = '<div class="empty-fav"><p>Товары не найдены или удалены.</p></div>';
            return;
        }

        let html = '<div class="fav-grid">';

        data.products.forEach(prod => {
            const imgSrc = prod.image_url ? '/house_of_taste' + prod.image_url : '/house_of_taste/public/img/placeholder.png';
            const price = prod.price;
            const oldPrice = prod.old_price;
            const hasDiscount = oldPrice && oldPrice > price;

            html += `
            <div class="product-card">
                <div class="prod-img-box">
                    <img src="${imgSrc}" alt="${prod.name}" class="prod-img">
                    <button class="remove-fav-btn" onclick="removeFromFav(${prod.id}, this)" title="Удалить из избранного">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="prod-info">
                    <div class="prod-cat">${prod.category_name || 'Блюдо'}</div>
                    <h3 class="prod-name">${prod.name}</h3>
                    ${prod.description ? `<div class="prod-desc">${prod.description.substring(0, 50)}...</div>` : ''}

                    <div class="prod-bottom">
                        <div class="prod-price-wrap">
                            <span class="prod-price">${Math.round(price).toLocaleString('ru-RU')} ₽</span>
                            ${hasDiscount ? `<span class="prod-old-price">${Math.round(oldPrice).toLocaleString('ru-RU')} ₽</span>` : ''}
                        </div>

                        <div class="action-buttons">
                            <a href="/house_of_taste/pages/product.php?id=${prod.id}" class="details-link">Подробнее</a>
                            <button class="add-cart-btn"
                                    onclick="simpleAddToCart(${prod.id}, '${addslashes(prod.name)}')">
                                <i class="fas fa-shopping-basket"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        });

        html += '</div>';
        container.innerHTML = html;
    })
    .catch(err => {
        console.error(err);
        container.innerHTML = '<div class="empty-fav"><p>Ошибка загрузки данных.</p></div>';
    });
}

// Удаление со страницы без перезагрузки
function removeFromFav(id, btn) {
    // Вызываем глобальную функцию из header.php для обновления localStorage и иконок
    if (typeof toggleFavorite === 'function') {
        toggleFavorite(id, null);
    } else {
        // Фоллбэк если функции нет
        let favs = JSON.parse(localStorage.getItem('favorites') || '[]');
        favs = favs.filter(fid => fid !== id);
        localStorage.setItem('favorites', JSON.stringify(favs));
    }

    // Удаляем карточку визуально
    const card = btn.closest('.product-card');
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        setTimeout(() => {
            card.remove();
            // Обновляем счетчик
            const remaining = document.querySelectorAll('.product-card').length;
            document.getElementById('fav-count-header').innerText = remaining;

            if (remaining === 0) {
                location.reload();
            }
        }, 300);
    }
}

/// Загрузка истории заказов (для секции "Вы уже заказывали")
function loadOrderHistory() {
    const historySection = document.getElementById('history-section');
    const historyGrid = document.getElementById('history-grid');

    if (!historySection || !historyGrid) return;

    fetch('/house_of_taste/api/orders_history.php')
    .then(r => r.json())
    .then(data => {
        console.log('History response:', data); // Для отладки

        if (data.success && data.items && data.items.length > 0) {
            historySection.style.display = 'block';
            let html = '';

            data.items.forEach(item => {
                const img = item.image_url
                    ? (item.image_url.startsWith('/') ? item.image_url : '/house_of_taste' + item.image_url)
                    : '/house_of_taste/public/img/placeholder.png';

                html += `
                <div class="history-item">
                    <img src="${img}" class="history-img" alt="${item.name}" onerror="this.src='/house_of_taste/public/img/placeholder.png'">
                    <div class="history-info">
                        <h4>${item.name}</h4>
                        <span>${Math.round(item.price).toLocaleString('ru-RU')} ₽</span>
                    </div>
                    <button onclick="simpleAddToCart(${item.product_id}, '${addslashes(item.name)}')"
                            style="margin-left:auto; background:none; border:none; color:#c8a656; cursor:pointer; font-size:16px;"
                            title="Добавить в корзину">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>`;
            });
            historyGrid.innerHTML = html;
        } else {
            // Если товаров нет — скрываем секцию
            historySection.style.display = 'none';
        }
    })
    .catch(e => {
        console.error('History load error:', e);
        const historySection = document.getElementById('history-section');
        if (historySection) historySection.style.display = 'none';
    });
}
</script>

<style>
    /* ===== ОСНОВНЫЕ СТИЛИ ===== */
    .fav-container {
        max-width: 1400px;
        margin: 100px auto 50px;
        padding: 0 20px;
        min-height: 60vh;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title {
        font-size: 32px;
        font-weight: 300;
        margin: 0;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .fav-count-badge {
        background: var(--gold, #c8a656);
        color: #1a1a1a;
        font-size: 14px;
        font-weight: 700;
        padding: 4px 14px;
        border-radius: 20px;
        min-width: 36px;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .back-link {
        color: #999;
        text-decoration: none;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: color 0.3s;
    }
    .back-link:hover { color: #fff; }

    /* ===== СЕТКА 4 КОЛОНКИ ===== */
    .fav-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }

    @media (max-width: 1200px) { .fav-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px)  { .fav-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px)  { .fav-grid { grid-template-columns: 1fr; } }

    /* ===== КАРТОЧКА ТОВАРА ===== */
    .product-card {
        background: #222;
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.3s, border-color 0.3s, box-shadow 0.3s;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-5px);
        border-color: rgba(200,166,86,0.4);
        box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    }

    .prod-img-box {
        position: relative;
        height: 200px;
        overflow: hidden;
        background: #1a1a1a;
    }

    .prod-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .product-card:hover .prod-img { transform: scale(1.05); }

    /* ===== КНОПКА УДАЛЕНИЯ (КРЕСТИК) — всегда в углу, контрастная ===== */
    .remove-fav-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(231, 76, 60, 0.95);
        border: 2px solid #fff;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        padding: 0;
        line-height: 1;
    }

    .remove-fav-btn:hover {
        background: #c0392b;
        transform: scale(1.08);
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }

    .remove-fav-btn:active { transform: scale(0.95); }

    .prod-info {
        padding: 16px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .prod-cat {
        font-size: 10px;
        color: #c8a656;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .prod-name {
        font-size: 15px;
        font-weight: 600;
        color: #fff;
        margin: 0 0 10px 0;
        line-height: 1.35;
        flex-grow: 1;
        min-height: 42px;
    }

    .prod-desc {
        font-size: 12px;
        color: #888;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .prod-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 14px;
        border-top: 1px solid rgba(255,255,255,0.07);
        gap: 10px;
        flex-wrap: wrap;
    }

    .prod-price-wrap {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .prod-price {
        font-size: 18px;
        font-weight: 700;
        color: #c8a656;
        line-height: 1;
    }

    .prod-old-price {
        font-size: 12px;
        color: #666;
        text-decoration: line-through;
        line-height: 1;
    }

    .action-buttons {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .details-link {
        font-size: 12px;
        color: #aaa;
        text-decoration: none;
        transition: color 0.2s;
        white-space: nowrap;
    }
    .details-link:hover { color: #fff; }

    .add-cart-btn {
        width: 38px;
        height: 38px;
        border: 1px solid rgba(200,166,86,0.4);
        background: transparent;
        color: #c8a656;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        transition: all 0.25s;
        border-radius: 6px;
        flex-shrink: 0;
    }

    .add-cart-btn:hover {
        background: #c8a656;
        color: #1a1a1a;
        border-color: #c8a656;
    }

    /* ===== ПУСТОЕ СОСТОЯНИЕ ===== */
    .empty-fav {
        text-align: center;
        padding: 70px 30px;
        color: #999;
        background: #222;
        border-radius: 16px;
        border: 1px dashed rgba(255,255,255,0.12);
        max-width: 500px;
        margin: 0 auto;
    }

    .empty-fav i {
        font-size: 56px;
        margin-bottom: 18px;
        color: #444;
        opacity: 0.7;
    }

    .empty-fav h2 {
        color: #fff;
        margin: 0 0 12px 0;
        font-weight: 400;
        font-size: 22px;
    }

    .empty-fav p {
        margin: 0 0 24px 0;
        color: #777;
        line-height: 1.5;
    }

    .empty-fav .btn-primary {
        padding: 12px 28px;
        background: #c8a656;
        color: #1a1a1a;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
    }
    .empty-fav .btn-primary:hover { background: #b89646; }

    /* ===== ИСТОРИЯ ЗАКАЗОВ ===== */
    .history-section {
        margin-top: 70px;
        padding-top: 35px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .history-title {
        font-size: 22px;
        font-weight: 400;
        color: #fff;
        margin: 0 0 24px 0;
    }

    .history-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }

    .history-item {
        background: #1e1e1e;
        padding: 14px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid rgba(255,255,255,0.06);
        transition: border-color 0.2s;
    }
    .history-item:hover { border-color: rgba(200,166,86,0.3); }

    .history-img {
        width: 54px;
        height: 54px;
        border-radius: 8px;
        object-fit: cover;
        background: #2a2a2a;
        flex-shrink: 0;
    }

    .history-info {
        flex: 1;
        min-width: 0;
    }

    .history-info h4 {
        font-size: 13px;
        color: #fff;
        margin: 0 0 4px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .history-info span {
        font-size: 12px;
        color: #999;
    }

    .history-add-btn {
        background: none;
        border: none;
        color: #c8a656;
        cursor: pointer;
        font-size: 16px;
        padding: 6px;
        border-radius: 6px;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .history-add-btn:hover { background: rgba(200,166,86,0.15); }

    /* Анимация удаления */
    .card-removing {
        animation: fadeOutScale 0.3s ease forwards;
    }
    @keyframes fadeOutScale {
        to {
            opacity: 0;
            transform: scale(0.92);
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
