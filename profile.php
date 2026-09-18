<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
$siteName = get_site_name($pdo);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/rate.php';
require_once __DIR__ . '/modules/shop_functions.php';
require_once __DIR__ . '/modules/messages_functions.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
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
refresh_user_rating($pdo, (int)$a['Number']);
$panelAccess = $online === 1 || ($viewerId > 0 && in_array(get_user_role($pdo, $viewerId), ['admin','moderator'], true));
$moderationCounts = $panelAccess
    ? get_moderation_notification_counts($pdo)
    : ['orders' => 0, 'products' => 0, 'topups' => 0, 'total' => 0];

if (($_GET['act'] ?? '') === 'delete' && $viewerId > 0 && (user_has_permission($pdo, $viewerId, 'moderate_shop') || user_has_permission($pdo, $viewerId, 'manage_users'))) {
    $deleteId = filter_input(INPUT_GET, 'del', FILTER_VALIDATE_INT);
    if ($deleteId) {
        $stmt = $pdo->prepare('DELETE FROM comments WHERE id_comment = :id');
        $stmt->execute([':id' => $deleteId]);
        header('Location: profile.php?id=' . (int)$a['Number']);
        exit;
    }
}
$profileId = (int)$a['Number'];
$unreadCount = count_unread_messages($pdo, $viewerId);
$isOwnProfile = $viewerId > 0 && $viewerId === $profileId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } elseif ($viewerId <= 0) {
        $error = 'Чтобы отправить комментарий, необходимо войти в аккаунт.';
    } elseif (isset($_POST['shop_action'])) {
        // Действия магазина (добавление/удаление товара и т.п.) обрабатываются
        // отдельно, в modules/shop_widget.php ниже по странице — здесь их
        // достаточно просто пропустить, чтобы они не попадали в ветку
        // «это комментарий» и не требовали несуществующее поле Text.
    } elseif (($_POST['wallet_topup'] ?? '') === '1') {
        if ($viewerId !== (int)$a['Number'] || user_is_banned($pdo, $viewerId)) {
            $error = 'Только активный владелец профиля может создавать заявку на пополнение.';
        } else {
            $amount = filter_var($_POST['topup_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
            $amount = $amount === false ? 0.0 : (float)$amount;
            $currency = strtoupper(trim((string)($_POST['topup_currency'] ?? '')));
            try {
                $requestId = create_topup_request($pdo, $viewerId, $amount, $currency);
                $senderName = trim((string)($_SESSION['user_name'] ?? ''));
                $stmt = $pdo->prepare(
                    'SELECT u.id, p.User
                       FROM users u
                       JOIN profiles p ON p.Number = u.id
                       JOIN roles r ON r.id = u.role_id
                      WHERE r.name IN ("admin", "moderator")
                        AND u.id <> :me
                        AND COALESCE(p.banned, 0) = 0
                      ORDER BY FIELD(r.name, "admin", "moderator"), u.id ASC'
                );
                $stmt->execute([':me' => $viewerId]);
                $recipients = $stmt->fetchAll();

                if ($recipients) {
                    $content = 'Заявка на пополнение #' . $requestId . ': пользователь ' . $senderName .
                        ' (ID: ' . $viewerId . ') запросил ' . number_format($amount, 2, ',', ' ') .
                        ' € через ' . $currency . '. Заявка доступна в панели управления.';
                    foreach ($recipients as $recipient) {
                        send_private_message(
                            $pdo,
                            $viewerId,
                            $senderName,
                            (int)$recipient['id'],
                            (string)$recipient['User'],
                            $content
                        );
                    }
                }

                header('Location: profile.php?id=' . (int)$a['Number'] . '&wallet_requested=1');
                exit;
            } catch (Throwable $e) {
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось создать заявку на пополнение.';
            }
        }
    } else {
        // Имя комментария берём только из авторизованной сессии.
        // Поле Name в HTML остаётся для совместимости со старым JS/разметкой,
        // но его значение с клиента больше не используется.
        $name = trim((string)($_SESSION['user_name'] ?? ''));
        $text = trim((string)($_POST['Text'] ?? ''));

        if (user_is_banned($pdo, $viewerId)) {
            $error = 'Заблокированный пользователь не может оставлять комментарии.';
        } elseif ($name === '' || $text === '') {
            $error = $errors[5];
        } else {
            $name = mb_substr($name, 0, 15, 'UTF-8');
            $text = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));

            $stmt = $pdo->prepare(
                'INSERT INTO comments (name, comment, id_profile, commenter_id) VALUES (:name, :comment, :profile, :commenter_id)'
            );
            $stmt->execute([
                ':name' => $name,
                ':comment' => $text,
                ':profile' => (string)$a['Number'],
                ':commenter_id' => $viewerId,
            ]);
            refresh_user_rating($pdo, $viewerId);

            header('Location: profile.php?id=' . (int)$a['Number']);
            exit;
        }
    }
}


// ---- AJAX-поиск товаров (отдельно от поиска пользователей в user_search.php) ----
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

$commentsPerPage = 20;
$commentsPage = max(1, (int)($_GET['comments_page'] ?? 1));
$stmtCountComments = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE id_profile = :profile');
$stmtCountComments->execute([':profile' => (string)$a['Number']]);
$commentsCount = (int)$stmtCountComments->fetchColumn();
$commentsTotalPages = max(1, (int)ceil($commentsCount / $commentsPerPage));
$commentsPage = min($commentsPage, $commentsTotalPages);
$commentsOffset = ($commentsPage - 1) * $commentsPerPage;
$stmt = $pdo->prepare(
    'SELECT c.*,
            COALESCE(
                (SELECT Photo FROM profiles WHERE Number = c.commenter_id LIMIT 1),
                (SELECT Photo FROM profiles WHERE User = c.name LIMIT 1)
            ) AS commenter_photo
       FROM comments c
      WHERE c.id_profile = :profile
      ORDER BY c.id_comment DESC
      LIMIT :offset, :limit'
);
$stmt->bindValue(':profile', (string)$a['Number']);
$stmt->bindValue(':offset', $commentsOffset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $commentsPerPage, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
<link rel="stylesheet" type="text/css" href="css/style_shop.css"/>
<script type="text/javascript" src="js/function.js" defer></script>
</head>
<body>
<div id="container">
<div id="header">
<?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?>
<div id="menu">
<?php if ($panelAccess): ?> / <a href="admin.php">В панель управления</a> / <details class="notif_bell">
<summary class="notif_bell_toggle" aria-label="Уведомления модерации" title="Уведомления модерации">
🔔<?php if ($moderationCounts['total'] > 0): ?><span class="badge_count"><?= $moderationCounts['total'] ?></span><?php endif; ?>
</summary>
<div class="notif_bell_menu">
<a href="admin.php?section=orders">Покупки, ожидающие одобрения<?php if ($moderationCounts['orders'] > 0): ?><span class="badge_count"><?= $moderationCounts['orders'] ?></span><?php endif; ?></a>
<a href="admin.php?section=products">Модерация товаров<?php if ($moderationCounts['products'] > 0): ?><span class="badge_count"><?= $moderationCounts['products'] ?></span><?php endif; ?></a>
<a href="admin.php?section=topups">Заявки на криптопополнение<?php if ($moderationCounts['topups'] > 0): ?><span class="badge_count"><?= $moderationCounts['topups'] ?></span><?php endif; ?></a>
</div>
</details><?php endif; ?>
 / <a href="index.php">На главную</a>
<?php if ($viewerId <= 0): ?> / <a href="login.php">Вход</a><?php endif; ?>
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

<?php if ($isOwnProfile): ?>
<?php if (($_GET['wallet_requested'] ?? '') === '1'): ?>
<div class="wallet_success">Запрос на пополнение кошелька отправлен. Администратор или модератор свяжется с вами.</div>
<?php endif; ?>
<div class="wallet_card">
    <div>
        <span class="wallet_card_label">Кошелёк</span>
        <strong class="wallet_card_balance"><?= number_format(get_wallet_balance($pdo, $viewerId), 2, ',', ' ') ?> €</strong>
    </div>
    <span class="wallet_card_hint">Пополнение выполняют администратор или модератор</span>
</div>
<form method="post" action="profile.php?id=<?= (int)$a['Number'] ?>" class="wallet_topup_form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
    <input type="hidden" name="wallet_topup" value="1" />
    <input class="wallet_topup_amount" type="number" name="topup_amount" min="0.01" max="1000000" step="0.01" placeholder="Сумма, €" required />
    <select name="topup_currency" class="wallet_topup_currency" required>
        <option value="BTC">Bitcoin (BTC)</option>
        <option value="ETH">Ethereum (ETH)</option>
        <option value="XRP">XRP</option>
    </select>
    <button type="submit" class="button wallet_topup_button">Запросить пополнение</button>
</form>
<?php endif; ?>

<div id="profile_menu">
<a class="button" href="profile.php?id=<?= $viewerId ?>">Профиль</a>
<a class="button private_messages_button" href="messages_profile.php?id=<?= (int)$a['Number'] ?>">Личные сообщения</a>
<a class="button" href="messages.php">Все диалоги<?php if ($unreadCount > 0): ?> <span class="badge_count"><?= $unreadCount ?></span><?php endif; ?></a>
<a class="button" href="shop_profile.php?id=<?= (int)$a['Number'] ?>">Магазин</a>
<a class="button" href="user_search.php">Поиск</a>
<a class="button" href="team.php">Команда сайта</a>
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

<section class="crypto_rates" id="crypto-rates" aria-label="Курс криптовалют">
    <div class="crypto_rates_head">
        <div>
            <span class="crypto_rates_kicker">КРИПТОВАЛЮТЫ</span>
            <h3>Текущий курс</h3>
        </div>
        <span class="crypto_rates_updated" data-crypto-updated>Загрузка…</span>
    </div>
    <div class="crypto_rates_grid">
        <article class="crypto_rate_card">
            <span class="crypto_rate_symbol">BTC</span>
            <strong data-crypto-price="bitcoin">—</strong>
            <small data-crypto-change="bitcoin">—</small>
        </article>
        <article class="crypto_rate_card">
            <span class="crypto_rate_symbol">ETH</span>
            <strong data-crypto-price="ethereum">—</strong>
            <small data-crypto-change="ethereum">—</small>
        </article>
        <article class="crypto_rate_card">
            <span class="crypto_rate_symbol">XRP</span>
            <strong data-crypto-price="xrp">—</strong>
            <small data-crypto-change="xrp">—</small>
        </article>
    </div>
    <div class="crypto_rates_source">Данные CoinGecko · EUR</div>
</section>

<?php
$p = $a;
$canSell = $viewerId > 0 && $viewerId === (int)$a['Number'] && can_use_shop($pdo, $viewerId, 'sell_products');
$canBuy = can_use_shop($pdo, $viewerId, 'buy_products');
include __DIR__ . '/modules/shop_widget.php';
?>

<div id="comment">
<?php
$hintExists = false;
foreach ($comments as $comment) {
    $hintExists = true;
    // Перенос длинных слов без пробелов (например, спам-строка без пробелов)
    // делает CSS (#comm{overflow-wrap:anywhere}) — резать текст здесь через
    // wordwrap() опасно: comment уже прошёл nl2br()+htmlspecialchars(), и
    // принудительный перенос мог разрезать вставленный <br /> или HTML-сущность
    // (&amp; и т.п.) прямо посередине и показать битую разметку.
    $commentText = (string)$comment['comment'];
    // Комментарий уже полностью экранирован через htmlspecialchars() при сохранении
    // (см. обработку POST выше), поэтому здесь заменяются только смайлы —
    // никакой дополнительной «защиты от XSS» подстановкой не требуется:
    // она была не нужна для безопасности и только портила обычный текст
    // со словами вроде «просто» или «скрипт».
    $commentText = strtr($commentText, [
        ':)' => '<img src="images/smiles/1.gif" alt=":)">',
        ':(' => '<img src="images/smiles/2.gif" alt=":(">',
        ':-D' => '<img src="images/smiles/3.gif" alt=":-D">',
        '*devil*' => '<img src="images/smiles/4.gif" alt="devil">',
        '*yahoo*' => '<img src="images/smiles/yahoo.gif" alt="yahoo">',
    ]);

    $delete = '';
    if ($viewerId > 0 && (user_has_permission($pdo, $viewerId, 'moderate_shop') || user_has_permission($pdo, $viewerId, 'manage_users'))) {
        $delete = '<div id="delete"><a href="?id=' . (int)$a['Number'] .
            '&act=delete&del=' . (int)$comment['id_comment'] .
            '"><img src="images/delete1.gif" width="21" height="21" alt="Удалить" /></a></div>';
    }

    $commenterAvatar = resolve_avatar((string)($comment['commenter_photo'] ?? ''));

    echo '<div id="name"><img class="comment-avatar" src="' . htmlspecialchars($commenterAvatar, ENT_QUOTES, 'UTF-8') . '" alt="" width="32" height="32" />'
        . '<span class="comment-author-name">' . htmlspecialchars($comment['name'], ENT_QUOTES, 'UTF-8') . '</span>' . $delete . '</div>';
    echo '<div id="comm">' . $commentText . '</div>';
}
if (!$hintExists) {
    echo $hint[0];
}
?>
</div>

<div class="comments-pagination-info">
    Всего комментариев: <?= $commentsCount ?>. По 20 комментариев на странице.
</div>

<?php if ($commentsTotalPages > 1): ?>
<div class="pagination" aria-label="Страницы комментариев">
<?php if ($commentsPage > 1): ?>
<a href="?id=<?= (int)$a['Number'] ?>&comments_page=1">&laquo;</a>
<a href="?id=<?= (int)$a['Number'] ?>&comments_page=<?= $commentsPage - 1 ?>">&lsaquo;</a>
<?php endif; ?>
<?php for ($cp = max(1, $commentsPage - 2); $cp <= min($commentsTotalPages, $commentsPage + 2); $cp++): ?>
<?php if ($cp === $commentsPage): ?><span class="current"><?= $cp ?></span><?php else: ?><a href="?id=<?= (int)$a['Number'] ?>&comments_page=<?= $cp ?>"><?= $cp ?></a><?php endif; ?>
<?php endfor; ?>
<?php if ($commentsPage < $commentsTotalPages): ?>
<a href="?id=<?= (int)$a['Number'] ?>&comments_page=<?= $commentsPage + 1 ?>">&rsaquo;</a>
<a href="?id=<?= (int)$a['Number'] ?>&comments_page=<?= $commentsTotalPages ?>">&raquo;</a>
<?php endif; ?>
</div>
<?php endif; ?>

<div id="post">
<?php if ($viewerId > 0): ?>
<form name="comment" method="post" action="">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
<table align="center" border="0" cellspacing="0" cellpadding="2">
<tr>
<td>Имя:</td>
<td><input name="Name" type="text" value="<?= htmlspecialchars((string)($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly /></td>
</tr>
<tr>
<td>Текст:</td>
<td><textarea name="Text" cols="30" rows="4" maxlength="2000" required></textarea></td>
</tr>
<tr>
<td>&nbsp;</td>
</tr>
</table>
<p align="center"><button type="submit" class="button">Добавить комментарий</button></p>
</form>
<?php else: ?>
<div class="comment-login-required">
<strong>Комментарии доступны только авторизованным пользователям.</strong>
<p>Войдите в аккаунт, чтобы оставить комментарий.</p>
<p align="center"><a href="login.php" class="button">Войти</a></p>
</div>
<?php endif; ?>


</div>
</div>
<div id="footer">&copy; Mr.Green</div>
</div>

</div>
<script>
(function () {
    'use strict';

    function formatPrice(value) {
        return new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value) + ' €';
    }

    function renderCryptoRates(data) {
        ['bitcoin', 'ethereum', 'xrp'].forEach(function (coin) {
            var priceEl = document.querySelector('[data-crypto-price="' + coin + '"]');
            var changeEl = document.querySelector('[data-crypto-change="' + coin + '"]');
            var item = data[coin];

            if (!priceEl || !changeEl || !item || typeof item.price !== 'number') return;

            priceEl.textContent = formatPrice(item.price);

            if (typeof item.change_24h === 'number') {
                var sign = item.change_24h > 0 ? '+' : '';
                changeEl.textContent = sign + item.change_24h.toFixed(2) + '% за 24ч';
                changeEl.className = item.change_24h >= 0
                    ? 'crypto_rate_change crypto_rate_change_up'
                    : 'crypto_rate_change crypto_rate_change_down';
            } else {
                changeEl.textContent = 'Изменение за 24ч недоступно';
            }
        });

        var updated = document.querySelector('[data-crypto-updated]');
        if (updated) {
            var date = new Date();
            updated.textContent = 'Обновлено ' + date.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }

    function loadCryptoRates() {
        fetch('crypto_rates.php', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(renderCryptoRates)
            .catch(function () {
                var updated = document.querySelector('[data-crypto-updated]');
                if (updated) updated.textContent = 'Курс временно недоступен';
            });
    }

    loadCryptoRates();
    setInterval(loadCryptoRates, 60000);

    // ---- Поиск товаров (AJAX) ----
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
        fetch('profile.php?ajax=1&type=products&q=' + encodeURIComponent(q), {
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
