<?php
declare(strict_types=1);

/**
 * Виджет магазина для страницы профиля.
 * Ожидает определённые переменные:
 *   $pdo        — подключение к БД
 *   $p          — массив профиля (Number, User, UserLogin)
 *   $viewerId   — id текущего пользователя (0 если не авторизован)
 *   $csrf       — CSRF-токен
 *   $canSell    — может ли текущий пользователь продавать
 *   $canBuy     — может ли текущий пользователь покупать
 */

require_once __DIR__ . '/shop_image_handler.php';

$shopProfileId = (int)$p['Number'];
$shopOwnerName = htmlspecialchars((string)$p['User'], ENT_QUOTES, 'UTF-8');
$shopError = '';
$walletBalance = $viewerId > 0 ? get_wallet_balance($pdo, $viewerId) : 0.0;

// Этот виджет подключается и из profile.php, и из shop_profile.php — формы
// внутри него должны отправлять данные на ТУ страницу, с которой их
// открыли, а не на жёстко прописанную. Раньше «Добавить товар» всегда вёл
// на profile.php, а «Удалить товар» — всегда на shop_profile.php: если
// открыть виджет со «своей» страницы, кнопка перекидывала на другую
// страницу и там же показывала результат (прыжок к #shop выглядел как
// «магазин выскакивает наверх»). basename($_SERVER['SCRIPT_NAME']) — это
// имя реально выполняемого входного скрипта, не пользовательский ввод.
$shopWidgetSelf = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'profile.php'));
if (!in_array($shopWidgetSelf, ['profile.php', 'shop_profile.php'], true)) {
    $shopWidgetSelf = 'profile.php';
}

// -------- Обработка POST-действий владельца магазина --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewerId === $shopProfileId && $canSell) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $shopError = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        $shopAction = (string)($_POST['shop_action'] ?? '');

        if ($shopAction === 'add_product') {
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $price = $price === false ? 0.0 : max(0.0, $price);
            $hasPhoto = isset($_FILES['product_photo']) && $_FILES['product_photo']['error'] !== UPLOAD_ERR_NO_FILE;

            if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
                $shopError = 'Введите название товара (до 255 символов).';
            } elseif ($price <= 0) {
                $shopError = 'Цена должна быть больше нуля.';
            } else {
                $photo = 'images/nophoto.jpg';
            if (isset($_FILES['product_photo']) && $_FILES['product_photo']['error'] === UPLOAD_ERR_OK) {
                $handler = new ShopImageHandler();
                $result = $handler->process($_FILES['product_photo']);
                if ($result['ok']) {
                    $photo = $result['path'];
                } else {
                    $shopError = $result['error'];
                }
            }

                $stmt = $pdo->prepare(
                    'INSERT INTO shop_products
                        (seller_id, name, description, price, photo, moderation_status)
                     VALUES (:seller, :name, :desc, :price, :photo, \'pending\')'
                );
                $stmt->execute([
                    ':seller' => $shopProfileId,
                    ':name' => $name,
                    ':desc' => $desc !== '' ? $desc : null,
                    ':price' => $price,
                    ':photo' => $photo,
                ]);

                if (!headers_sent()) {
                    header('Location: ' . $shopWidgetSelf . '?id=' . $shopProfileId . '#shop');
                    exit;
                }
            }
        }

        if ($shopAction === 'delete_product') {
            $deleteId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($deleteId) {
                $stmt = $pdo->prepare('SELECT photo FROM shop_products WHERE id = :id AND seller_id = :seller LIMIT 1');
                $stmt->execute([':id' => $deleteId, ':seller' => $shopProfileId]);
                $oldPhoto = $stmt->fetchColumn();

                if ($oldPhoto !== false) {
                    // Товар, по которому уже есть заказы, нельзя удалить физически —
                    // это защищено внешним ключом fk_shop_order_items_product
                    // (ON DELETE RESTRICT), чтобы не повредить историю заказов.
                    // В этом случае просто скрываем его из магазина.
                    $stmt = $pdo->prepare(
                        'SELECT 1 FROM shop_order_items WHERE product_id = :id LIMIT 1'
                    );
                    $stmt->execute([':id' => $deleteId]);
                    $hasOrders = (bool)$stmt->fetchColumn();

                    if ($hasOrders) {
                        $stmt = $pdo->prepare(
                            'UPDATE shop_products SET is_deleted = 1 WHERE id = :id AND seller_id = :seller'
                        );
                        $stmt->execute([':id' => $deleteId, ':seller' => $shopProfileId]);
                    } else {
                        try {
                            $stmt = $pdo->prepare('DELETE FROM shop_products WHERE id = :id AND seller_id = :seller');
                            $stmt->execute([':id' => $deleteId, ':seller' => $shopProfileId]);

                            if ($oldPhoto && $oldPhoto !== 'images/nophoto.jpg') {
                                $oldFile = __DIR__ . '/../' . ltrim((string)$oldPhoto, '/');
                                if (is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                        } catch (PDOException $e) {
                            // На случай гонки: заказ мог появиться между проверкой выше и
                            // самим DELETE. Молча переводим товар в «мягкое» удаление
                            // вместо фатальной ошибки.
                            $stmt = $pdo->prepare(
                                'UPDATE shop_products SET is_deleted = 1 WHERE id = :id AND seller_id = :seller'
                            );
                            $stmt->execute([':id' => $deleteId, ':seller' => $shopProfileId]);
                        }
                    }
                }
            }
        }
    }
}

$shopProducts = get_seller_products($pdo, $shopProfileId, $viewerId);
$productIds = array_map(static fn(array $row): int => (int)$row['id'], $shopProducts);
$reviewMap = get_product_review_map($pdo, $productIds, $viewerId);
$reviewPreviewMap = get_product_review_preview_map($pdo, $productIds);
$reviewableIds = array_flip(get_reviewable_product_ids($pdo, $viewerId, $productIds));
$cartItems = get_cart();
$cartCount = array_sum($cartItems);
?>

<div class="shop_widget" id="shop">
    <div class="shop_widget_head">
        <div>
            <div class="shop_widget_kicker">МАГАЗИН ПОЛЬЗОВАТЕЛЯ</div>
            <h3 class="shop_widget_title">Товары <span><?= $shopOwnerName ?></span></h3>
            <p class="shop_widget_subtitle">Покупайте товары напрямую у пользователя</p>
        </div>
        <div class="shop_widget_icon" aria-hidden="true">🛍</div>
    </div>

    <div class="shop_toolbar">
        <div class="shop_toolbar_left">
            <?php if ($viewerId > 0): ?>
            <div class="shop_wallet_badge">Кошелёк: <strong><?= number_format($walletBalance, 2, ',', ' ') ?> €</strong></div>
            <a class="shop_action shop_action_primary" href="shop_cart.php">
                <span>Корзина</span>
                <?php if ($cartCount > 0): ?><b class="shop_cart_badge"><?= $cartCount ?></b><?php endif; ?>
            </a>
            <?php endif; ?>
            <?php if ($viewerId === $shopProfileId): ?>
            <a class="shop_action" href="shop_cart.php?view=orders">Мои заказы</a>
            <a class="shop_action" href="shop_cart.php?view=sales">Продажи</a>
            <?php endif; ?>
        </div>
        <?php if ($shopProducts): ?>
        <div class="shop_product_count"><?= count($shopProducts) ?> <?= count($shopProducts) === 1 ? 'товар' : (count($shopProducts) < 5 ? 'товара' : 'товаров') ?></div>
        <?php endif; ?>
    </div>

    <?php if ($shopError !== ''): ?>
    <div class="shop_notice shop_notice_error"><?= htmlspecialchars($shopError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($shopProducts): ?>
    <div class="shop_products_grid">
    <?php foreach ($shopProducts as $product): ?>
    <?php
    $productPhoto = htmlspecialchars((string)$product['photo'], ENT_QUOTES, 'UTF-8');
    $productName = htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8');
    $productDesc = htmlspecialchars((string)($product['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $productPrice = number_format((float)$product['price'], 2, ',', ' ');
    ?>
    <article class="shop_product_card">
        <div class="shop_product_media">
            <img class="shop_product_photo" src="<?= $productPhoto ?>" alt="<?= $productName ?>" loading="lazy" />
            <?php if (($product['moderation_status'] ?? 'approved') === 'approved'): ?>
            <span class="shop_product_tag">Одобрен · В наличии</span>
            <?php elseif (($product['moderation_status'] ?? '') === 'pending'): ?>
            <span class="shop_product_tag">На модерации</span>
            <?php else: ?>
            <span class="shop_product_tag">Отклонён</span>
            <?php endif; ?>
        </div>
        <div class="shop_product_info">
            <h4 class="shop_product_name"><?= $productName ?></h4>
            <?php if ($productDesc !== ''): ?>
            <p class="shop_product_desc"><?= $productDesc ?></p>
            <?php else: ?>
            <p class="shop_product_desc shop_product_desc_empty">Описание товара не добавлено</p>
            <?php endif; ?>
            <div class="shop_product_bottom">
                <div class="shop_product_price"><span><?= $productPrice ?></span> €</div>
                <span class="shop_product_hint">Цена</span>
            </div>
            <?php
            $review = $reviewMap[(int)$product['id']] ?? ['avg_rating' => 0, 'review_count' => 0, 'my_rating' => null];
            $reviewCount = (int)$review['review_count'];
            $avgPct = (int)round(((float)$review['avg_rating'] / 5) * 100);
            $reviewNoun = $reviewCount === 1 ? 'отзыв' : ($reviewCount < 5 ? 'отзыва' : 'отзывов');
            ?>
            <button type="button" class="product_review_summary" data-reviews-for="<?= (int)$product['id'] ?>" data-product-name="<?= $productName ?>" title="Показать все отзывы">
                <span class="stars_static" aria-hidden="true"><span class="stars_base">★★★★★</span><span class="stars_top" style="width:<?= $avgPct ?>%">★★★★★</span></span>
                <b><?= number_format((float)$review['avg_rating'], 1, ',', ' ') ?></b>
                <small><?= $reviewCount ?> <?= $reviewNoun ?></small>
                <span class="review_open_hint">Все отзывы</span>
            </button>
            <?php if (!empty($reviewPreviewMap[(int)$product['id']])): ?>
            <details class="product_review_list">
                <summary>Последние отзывы</summary>
                <?php foreach ($reviewPreviewMap[(int)$product['id']] as $item): ?>
                <div class="product_review_item">
                    <strong><?= htmlspecialchars($item['buyer_name'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$item['rating'] ?>★</strong>
                    <?php if ($item['review'] !== ''): ?><span><?= nl2br(htmlspecialchars($item['review'], ENT_QUOTES, 'UTF-8')) ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <button type="button" class="product_review_all_btn" data-reviews-for="<?= (int)$product['id'] ?>" data-product-name="<?= $productName ?>">Все отзывы (<?= $reviewCount ?>)</button>
            </details>
            <?php endif; ?>
        </div>
        <?php if ($viewerId > 0 && isset($reviewableIds[(int)$product['id']]) && $review['my_rating'] === null): ?>
        <form class="product_review_form" data-review-form>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div class="review_form_row">
                <span class="review_form_label">Ваша оценка</span>
                <span class="star_rating" role="radiogroup" aria-label="Оценка от 1 до 5 звёзд">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                    <input type="radio" id="rating-<?= (int)$product['id'] ?>-<?= $star ?>" name="rating" value="<?= $star ?>" <?= $star === 5 ? 'checked' : '' ?> />
                    <label for="rating-<?= (int)$product['id'] ?>-<?= $star ?>" title="<?= $star ?> <?= $star === 1 ? 'звезда' : ($star < 5 ? 'звезды' : 'звёзд') ?>">★</label>
                <?php endfor; ?>
                </span>
            </div>
            <textarea name="review" maxlength="1000" rows="3" placeholder="Расскажите о товаре: качество, продавец, скорость (необязательно)"></textarea>
            <div class="review_form_footer">
                <span class="review_char_counter" data-max="1000">Осталось символов: 1000</span>
                <button type="submit" class="small_review_button">Оставить отзыв</button>
            </div>
            <span class="review_form_message" aria-live="polite"></span>
        </form>
        <?php elseif ($review['my_rating'] !== null): ?>
        <div class="product_review_mine">Ваша оценка: <?= (int)$review['my_rating'] ?> ★</div>
        <?php endif; ?>
        <div class="shop_product_actions">
        <?php if ($viewerId === $shopProfileId && ($product['moderation_status'] ?? 'approved') !== 'approved'): ?>
            <span class="shop_product_moderation_note">Товар нельзя купить, пока его не одобрит модератор или администратор.</span>
        <?php endif; ?>
        <?php if ($viewerId > 0 && $viewerId !== $shopProfileId && $canBuy && ($product['moderation_status'] ?? 'approved') === 'approved'): ?>
            <button type="button" class="button shop-buy-btn shop_buy_button" data-product="<?= (int)$product['id'] ?>" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">Добавить в корзину</button>
        <?php elseif ($viewerId === $shopProfileId): ?>
            <form method="post" action="<?= htmlspecialchars($shopWidgetSelf, ENT_QUOTES, 'UTF-8') ?>?id=<?= $shopProfileId ?>#shop" class="shop_delete_form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                <input type="hidden" name="shop_action" value="delete_product" />
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>" />
                <button type="submit" class="shop_delete_btn" onclick="return confirm('Удалить товар?')">Удалить товар</button>
            </form>
        <?php elseif ($viewerId <= 0): ?>
            <a class="button shop_buy_button" href="login.php">Войти для покупки</a>
        <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="shop_empty">
        <div class="shop_empty_icon">🛒</div>
        <strong>Пока нет товаров</strong>
        <span>Здесь появятся товары, которые пользователь выставит на продажу.</span>
    </div>
    <?php endif; ?>

    <?php if ($viewerId === $shopProfileId && $canSell): ?>
    <details class="shop_add_product">
        <summary><span class="shop_add_icon">＋</span><span>Добавить новый товар</span><small>Название, цена, описание и фото</small></summary>
        <form method="post" action="<?= htmlspecialchars($shopWidgetSelf, ENT_QUOTES, 'UTF-8') ?>?id=<?= $shopProfileId ?>#shop" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="shop_action" value="add_product" />
            <div class="shop_form_grid">
                <div class="shop_form_field shop_form_field_wide">
                    <label>Название товара *</label>
                    <input type="text" name="name" maxlength="255" placeholder="Например, фотоаппарат" required />
                </div>
                <div class="shop_form_field">
                    <label>Цена (€) *</label>
                    <input type="number" name="price" min="0.01" step="0.01" placeholder="0,00" required />
                </div>
                <div class="shop_form_field">
                    <label>Фото товара</label>
                    <input type="file" name="product_photo" accept="image/jpeg,image/png,image/webp,image/gif" />
                </div>
                <div class="shop_form_field shop_form_field_full">
                    <label>Описание</label>
                    <textarea name="description" rows="4" maxlength="2000" placeholder="Расскажите о товаре…"></textarea>
                </div>
            </div>
            <div class="shop_form_footer">
                <span>Фото будет обрезано до квадрата 400×400 px по центру и сжато без потери качества.</span>
                <button type="submit" class="button shop_save_button">Опубликовать товар</button>
            </div>
        </form>
    </details>
    <?php elseif ($viewerId === $shopProfileId && !$canSell): ?>
    <div class="shop_no_perm">У вас нет прав на продажу товаров.</div>
    <?php endif; ?>

    <script>
    // CSRF-токен для AJAX-запросов отзывов (модальное окно «Все отзывы»).
    window.shopCsrfToken = <?= json_encode(htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')) ?>;
    </script>

    <!-- Модальное окно со всеми отзывами на товар -->
    <div class="reviews_modal" id="reviews_modal" hidden>
        <div class="reviews_modal_backdrop" data-reviews-close></div>
        <div class="reviews_modal_window" role="dialog" aria-modal="true" aria-labelledby="reviews_modal_title">
            <header class="reviews_modal_head">
                <div class="reviews_modal_head_text">
                    <h4 id="reviews_modal_title">Отзывы</h4>
                    <p class="reviews_modal_sub"></p>
                </div>
                <button type="button" class="reviews_modal_close" data-reviews-close aria-label="Закрыть">&times;</button>
            </header>
            <div class="reviews_modal_body">
                <aside class="reviews_modal_summary">
                    <div class="reviews_avg">
                        <b>—</b>
                        <span class="stars_static stars_static_big" aria-hidden="true"><span class="stars_base">★★★★★</span><span class="stars_top" style="width:0%">★★★★★</span></span>
                        <small></small>
                    </div>
                    <div class="reviews_distribution"></div>
                </aside>
                <div class="reviews_modal_list" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>
