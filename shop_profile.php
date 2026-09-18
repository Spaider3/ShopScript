<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/rate.php';
require_once __DIR__ . '/modules/shop_functions.php';

// ---- AJAX-поиск товаров (тот же виджет, что и в profile.php) ----
if (($_GET['ajax'] ?? '') === '1' && ($_GET['type'] ?? '') === 'products') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') > 100) $q = mb_substr($q, 0, 100, 'UTF-8');

    $productRows = $q === '' ? [] : search_products($pdo, $q, 20);
    $products = [];
    foreach ($productRows as $row) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'price' => number_format((float)$row['price'], 2, ',', ' '),
            'photo' => resolve_avatar((string)$row['photo']),
            'seller_id' => (int)$row['seller_id'],
            'seller_name' => (string)$row['seller_name'],
            'purchases' => (int)$row['purchases'],
            'review_rating' => (float)$row['review_rating'],
            'review_count' => (int)$row['review_count'],
            'url' => 'shop_profile.php?id=' . (int)$row['seller_id'] . '#shop',
        ];
    }

    json_response(['status' => 'ok', 'products' => $products]);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$number = $id && $id > 0 ? $id : 1;

$stmt = $pdo->prepare('SELECT * FROM profiles WHERE `Number` = :id LIMIT 1');
$stmt->execute([':id' => $number]);
$a = $stmt->fetch();

if (!$a) {
    $a = $pdo->query('SELECT * FROM profiles ORDER BY `Number` ASC LIMIT 1')->fetch();
    if (!$a) {
        exit('Профили отсутствуют.');
    }
    $number = (int)$a['Number'];
}

$csrf = csrf_token();
$viewerId = !empty($_SESSION['logged_in']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
$panelAccess = $online === 1 || ($viewerId > 0 && in_array(get_user_role($pdo, $viewerId), ['admin','moderator'], true));
$profileId = (int)$a['Number'];
$isOwnProfile = $viewerId > 0 && $viewerId === $profileId;
$unreadCount = count_unread_messages($pdo, $viewerId);

// Права магазина для текущего пользователя
$canBuy = can_use_shop($pdo, $viewerId, 'buy_products');
$canSell = $viewerId > 0 && $isOwnProfile && can_use_shop($pdo, $viewerId, 'sell_products');

// Обработка POST-действий магазина выполняется во включаемом виджете.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shop_action'])) {
    $p = $a; // виджет ожидает $p — массив профиля
    include __DIR__ . '/modules/shop_widget.php';
    if (!headers_sent()) {
        header('Location: shop_profile.php?id=' . (int)$a['Number'] . '#shop');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Магазин | <?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
<link rel="stylesheet" type="text/css" href="css/style_shop.css"/>
<script type="text/javascript" src="js/function.js" defer></script>
</head>
<body>
<div id="container">
<div id="header">
<?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?>
<div id="menu">
<?php if ($panelAccess): ?> / <a href="admin.php">В панель управления</a><?php endif; ?>
 / <a href="index.php">На главную</a>
<?php if ($online === 1): ?> / <a href="?act=logout">Выход</a> /<?php endif; ?>
<?php if ($viewerId > 0): ?>
 / Вы вошли как <?= htmlspecialchars((string)($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
 / <form method="post" action="logout.php" class="inline_logout_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
<button type="submit" class="link_button">Выход</button>
</form>
<?php endif; ?>
</div>
</div>

<div id="photo">
<div id="avatar">
<img src="<?= htmlspecialchars($a['Photo'], ENT_QUOTES, 'UTF-8') ?>" width="200" alt="" />
</div>

<div id="user_info">
<?php
$isOnline = !empty($a['is_online']);
$regDate = !empty($a['created_at']) ? date('d.m.Y', strtotime((string)$a['created_at'])) : null;
$lastSeen = !empty($a['last_seen']) ? date('d.m.Y H:i', strtotime((string)$a['last_seen'])) : null;
?>
<div id="user_status">
<span class="status_dot <?= $isOnline ? 'online' : 'offline' ?>"></span>
<?= $isOnline ? 'В сети' : 'Не в сети' ?>
</div>
<?php if ($regDate !== null): ?>
<div id="user_reg_date">На сайте с <?= htmlspecialchars($regDate, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (!$isOnline && $lastSeen !== null): ?>
<div id="user_last_seen">Последний визит: <?= htmlspecialchars($lastSeen, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<?php
$rateObj = new percentage();
$rateObj->calculate((int)$a['Rating']);
?>
<div align="center" id="rate">
<div id="rate_text"><?= (int)$a['Rating'] ?>%</div>
<div>
<div id="rate_left" style="width:<?= (int)$rateObj->rate_left ?>%;"></div>
<div id="rate_right" style="width:<?= (int)$rateObj->rate_right ?>%;"></div>
</div>
</div>

<div id="profile_menu">
<a class="button" href="profile.php?id=<?= $viewerId ?>">Профиль</a>
<a class="button" href="messages_profile.php?id=<?= (int)$a['Number'] ?>">Личные сообщения<?php if ($unreadCount > 0): ?> <span class="badge_count"><?= $unreadCount ?></span><?php endif; ?></a>
<a class="button" href="messages.php">Все диалоги</a>
<a class="button" href="shop_profile.php?id=<?= (int)$a['Number'] ?>">Магазин</a>
<?php if ($isOwnProfile): ?>
<a class="button" href="edit_profile.php">Редактировать профиль</a>
<?php endif; ?>
</div>
</div>

<div id="content">
<div id="error_div"><?= $error ?? '' ?></div>

<section class="product_search" id="product-search" aria-label="Поиск товаров">
    <div class="product_search_head">
        <div>
            <span class="product_search_kicker">ПОИСК ТОВАРОВ</span>
            <h3>Найти товар</h3>
        </div>
        <span class="product_search_hint">Поиск по названию и описанию</span>
    </div>
    <form class="product_search_form" id="product-search-form" autocomplete="off">
        <input id="product-search-input" type="search" maxlength="100" placeholder="Название товара…" aria-label="Поиск товаров">
        <button type="submit" class="button">Найти</button>
    </form>
    <div id="product-search-status" class="product_search_status">Начните вводить запрос.</div>
    <div id="product-search-results" class="shop_products_grid" aria-live="polite"></div>
</section>

<?php
// Виджет магазина — виден всегда, не только когда нет поискового запроса
$p = $a;
include __DIR__ . '/modules/shop_widget.php';
?>
</div>

<div id="footer">&copy; Mr.Green</div>
</div>

<script>
(function () {
    'use strict';

    // ---- Поиск товаров (AJAX), тот же виджет, что и на profile.php ----
    var productInput = document.getElementById('product-search-input');
    var productForm = document.getElementById('product-search-form');
    var productResults = document.getElementById('product-search-results');
    var productStatus = document.getElementById('product-search-status');
    var productTimer = null;
    var productController = null;

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    function renderProducts(items) {
        productResults.innerHTML = '';
        items.forEach(function (item) {
            var card = document.createElement('a');
            card.className = 'shop_product_card search_product_card';
            card.href = item.url;
            card.innerHTML =
                '<div class="shop_product_media">' +
                '<img class="shop_product_photo" src="' + escapeHtml(item.photo) + '" alt="' + escapeHtml(item.name) + '" loading="lazy">' +
                '</div>' +
                '<div class="shop_product_info">' +
                '<h4 class="shop_product_name">' + escapeHtml(item.name) + '</h4>' +
                '<p class="shop_product_desc">Продавец: ' + escapeHtml(item.seller_name) + '</p>' +
                '<div class="shop_product_bottom">' +
                '<div class="shop_product_price"><span>' + escapeHtml(item.price) + '</span> €</div>' +
                '</div>' +
                '</div>';
            productResults.appendChild(card);
        });
    }

    function searchProducts() {
        var q = productInput.value.trim();
        if (!q) {
            if (productController) productController.abort();
            productResults.innerHTML = '';
            productStatus.textContent = 'Начните вводить запрос.';
            return;
        }
        if (productController) productController.abort();
        productController = new AbortController();
        productStatus.textContent = 'Поиск…';
        fetch('shop_profile.php?ajax=1&type=products&q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: productController.signal
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (data.status !== 'ok') throw new Error('Ошибка поиска');
            var products = data.products || [];
            renderProducts(products);
            if (!products.length) {
                productStatus.textContent = 'Ничего не найдено.';
                return;
            }
            productStatus.textContent = 'Найдено товаров: ' + products.length;
        })
        .catch(function (error) {
            if (error.name === 'AbortError') return;
            productStatus.textContent = 'Не удалось выполнить поиск. Попробуйте ещё раз.';
        });
    }

    if (productForm) {
        productForm.addEventListener('submit', function (event) {
            event.preventDefault();
            searchProducts();
        });
    }
    if (productInput) {
        productInput.addEventListener('input', function () {
            clearTimeout(productTimer);
            productTimer = setTimeout(searchProducts, 220);
        });
    }
})();
</script>
</body>
</html>
