<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
$siteName = get_site_name($pdo);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/shop_functions.php';

$viewerId = current_user_id();
if ($viewerId <= 0) {
    header('Location: login.php');
    exit;
}

function user_search_rows(PDO $pdo, string $query, int $limit = 20): array
{
    $query = trim($query);
    $limit = max(1, min(50, $limit));
    $like = '%' . addcslashes($query, "\\%_") . '%';

    $stmt = $pdo->prepare(
        "SELECT p.Number, p.User, p.UserLogin, p.Photo, p.Rating,
                COALESCE(SUM(CASE WHEN o.status = 'paid' THEN oi.quantity ELSE 0 END), 0) AS purchases
           FROM profiles p
           LEFT JOIN shop_order_items oi ON oi.seller_id = p.Number
           LEFT JOIN shop_orders o ON o.id = oi.order_id
          WHERE p.banned = 0
            AND (p.User LIKE :q ESCAPE '\\\\' OR p.UserLogin LIKE :q2 ESCAPE '\\\\')
          GROUP BY p.Number, p.User, p.UserLogin, p.Photo, p.Rating
          ORDER BY purchases DESC, p.Rating DESC, p.Number ASC
          LIMIT :limit"
    );
    $stmt->bindValue(':q', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (($_GET['ajax'] ?? '') === '1') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') > 100) $q = mb_substr($q, 0, 100, 'UTF-8');

    $rows = $q === '' ? [] : user_search_rows($pdo, $q, 20);
    $users = [];
    foreach ($rows as $row) {
        $users[] = [
            'id' => (int)$row['Number'],
            'name' => (string)$row['User'],
            'login' => (string)$row['UserLogin'],
            'photo' => resolve_avatar((string)$row['Photo']),
            'rating' => (int)$row['Rating'],
            'purchases' => (int)$row['purchases'],
            'url' => 'profile.php?id=' . (int)$row['Number'],
        ];
    }

    json_response(['status' => 'ok', 'users' => $users]);
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Поиск пользователей | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/style_profile.css">
<link rel="stylesheet" href="css/style_shop.css">
<link rel="stylesheet" href="css/style_user_search.css">
</head>
<body>
<div id="container">
<header id="header">
    Поиск пользователей
    <div id="menu">
        <a href="profile.php?id=<?= $viewerId ?>">Профиль</a> /
        <a href="messages.php">Сообщения</a> /
        <a href="index.php">На главную</a>
    </div>
</header>
<main id="content">
    <div class="user_search_head">
        <span>AJAX ПОИСК</span>
        <h1>Найти пользователя</h1>
        <p>Пользователи сортируются по количеству оплаченных покупок и рейтингу.</p>
    </div>
    <form class="user_search_form" id="user-search-form" autocomplete="off">
        <input id="user-search-input" type="search" maxlength="100" placeholder="Имя или логин…" aria-label="Поиск пользователей">
        <button type="submit" class="button">Найти</button>
    </form>
    <div id="user-search-status" class="user_search_status">Начните вводить запрос.</div>

    <h2 class="search_section_title">Пользователи</h2>
    <div id="user-search-results" class="user_search_results" aria-live="polite"></div>
</main>
<footer id="footer">&copy; Mr.Green</footer>
</div>
<script>
(function () {
    var input = document.getElementById('user-search-input');
    var form = document.getElementById('user-search-form');
    var userResults = document.getElementById('user-search-results');
    var status = document.getElementById('user-search-status');
    var timer = null;
    var controller = null;

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    function renderUsers(items) {
        userResults.innerHTML = '';
        items.forEach(function (item) {
            var card = document.createElement('a');
            card.className = 'user_search_card';
            card.href = item.url;
            card.innerHTML =
                '<img src="' + escapeHtml(item.photo) + '" alt="" loading="lazy">' +
                '<span class="user_search_info">' +
                '<strong>' + escapeHtml(item.name) + '</strong>' +
                '<small>@' + escapeHtml(item.login || '—') + '</small>' +
                '<em>Покупок: ' + item.purchases + ' · Рейтинг: ' + item.rating + '%</em>' +
                '</span>';
            userResults.appendChild(card);
        });
    }

    function render(data) {
        var users = data.users || [];
        renderUsers(users);
        if (!users.length) {
            status.textContent = 'Ничего не найдено.';
            return;
        }
        status.textContent = 'Найдено пользователей: ' + users.length;
    }

    function search() {
        var q = input.value.trim();
        if (!q) {
            if (controller) controller.abort();
            userResults.innerHTML = '';
            status.textContent = 'Начните вводить запрос.';
            return;
        }
        if (controller) controller.abort();
        controller = new AbortController();
        status.textContent = 'Поиск…';
        fetch('user_search.php?ajax=1&q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: controller.signal
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (data.status !== 'ok') throw new Error('Ошибка поиска');
            render(data);
        })
        .catch(function (error) {
            if (error.name === 'AbortError') return;
            status.textContent = 'Не удалось выполнить поиск. Попробуйте ещё раз.';
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        search();
    });
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(search, 220);
    });
})();
</script>
</body>
</html>