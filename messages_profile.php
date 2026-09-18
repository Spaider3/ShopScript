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
require_once __DIR__ . '/modules/messages_functions.php';

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
$viewerName = (string)($_SESSION['user_name'] ?? '');

$error = '';
$messages = [];

// Обработка отправки сообщения (только для авторизованных)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewerId > 0) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } elseif ($viewerId === $profileId) {
        $error = 'Нельзя отправить сообщение самому себе.';
    } elseif (user_is_banned($pdo, $viewerId)) {
        $error = 'Заблокированный пользователь не может отправлять сообщения.';
    } else {
        $content = trim((string)($_POST['MessageText'] ?? ''));

        if ($content === '') {
            $error = 'Введите сообщение.';
        } elseif (mb_strlen($content, 'UTF-8') > 1000) {
            $error = 'Сообщение не должно быть длиннее 1000 символов.';
        } elseif ($viewerName === '') {
            $error = 'Не удалось определить ваше имя.';
        } else {
            send_private_message($pdo, $viewerId, $viewerName, $profileId, (string)$a['User'], $content);

            refresh_user_rating($pdo, $viewerId);
            header('Location: messages_profile.php?id=' . $profileId . '#message_form');
            exit;
        }
    }
}

// Загрузка переписки (только для авторизованных).
// Сопоставление в первую очередь по ID профиля (sender_id/receiver_id) — имя
// не уникально, поэтому сопоставление по имени используется только как запасной
// вариант для сообщений, отправленных до появления ID (у них ID ещё NULL).
$msgLimit = 30;
$msgTotalPages = 1;
$msgPage = 1;
if ($viewerId > 0 && $viewerId !== $profileId) {
    $msgTotalCount = count_conversation_messages($pdo, $viewerId, $viewerName, $profileId, (string)$a['User']);
    $msgTotalPages = max(1, (int)ceil($msgTotalCount / $msgLimit));
    // По умолчанию — последняя страница (самые свежие сообщения), как и ожидает
    // пользователь, открывая переписку.
    $msgPage = isset($_GET['msg_page']) ? max(1, (int)$_GET['msg_page']) : $msgTotalPages;
    $msgPage = min($msgPage, $msgTotalPages);
    $messages = get_conversation_messages(
        $pdo, $viewerId, $viewerName, $profileId, (string)$a['User'],
        $msgLimit, ($msgPage - 1) * $msgLimit
    );

    // Открыли переписку — отмечаем входящие сообщения от собеседника прочитанными.
    mark_messages_read($pdo, $profileId, $viewerId);
}

$targetAvatar = resolve_avatar((string)($a['Photo'] ?? ''));
$unreadCount = count_unread_messages($pdo, $viewerId);
$viewerAvatar = 'images/nophoto.jpg';
if ($viewerId > 0 && $viewerId !== $profileId) {
    $stmt = $pdo->prepare('SELECT Photo FROM profiles WHERE Number = ? LIMIT 1');
    $stmt->execute([$viewerId]);
    $viewerAvatar = resolve_avatar((string)($stmt->fetchColumn() ?: ''));
}

// ---- Серверный поиск пользователей (как в admin.php) ----
$searchQuery = trim((string)($_GET['search'] ?? ''));
if (mb_strlen($searchQuery, 'UTF-8') > 100) {
    $searchQuery = mb_substr($searchQuery, 0, 100, 'UTF-8');
}

$searchResults = [];
$searchTotal = 0;
$searchPage = 1;
$searchNum = 5;

if ($searchQuery !== '') {
    $searchPage = max(1, (int)($_GET['page'] ?? 1));

    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM profiles WHERE `User` LIKE :search');
    $stmtCount->execute([':search' => '%' . $searchQuery . '%']);
    $searchTotal = max(1, (int)ceil((int)$stmtCount->fetchColumn() / $searchNum));
    $searchPage = min($searchPage, $searchTotal);

    $searchStart = ($searchPage - 1) * $searchNum;
    $stmt = $pdo->prepare(
        'SELECT `Number`, `User`, `Photo`, `Rating` FROM profiles
         WHERE `User` LIKE :search
         ORDER BY `Rating` DESC
         LIMIT :start, :num'
    );
    $stmt->bindValue(':search', '%' . $searchQuery . '%');
    $stmt->bindValue(':start', $searchStart, PDO::PARAM_INT);
    $stmt->bindValue(':num', $searchNum, PDO::PARAM_INT);
    $stmt->execute();
    $searchResults = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Сообщения | <?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
<link rel="stylesheet" type="text/css" href="css/style_messages.css"/>
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
 / Вы вошли как <?= htmlspecialchars($viewerName, ENT_QUOTES, 'UTF-8') ?>
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
<form class="site_search_form" action="messages_profile.php" method="get">
<input type="hidden" name="id" value="<?= (int)$a['Number'] ?>" />
<input type="text" class="site_search_input" name="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Поиск пользователей…" />
<button type="submit" class="button site_search_button">Найти</button>
</form>

<?php if ($error !== ''): ?>
<div id="error_div" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($searchQuery !== ''): ?>
<div class="search_results_list">
<?php if (!$searchResults): ?>
<div class="search_empty">По запросу «<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>» ничего не найдено.</div>
<?php else: ?>
<?php foreach ($searchResults as $row): ?>
<?php
$searchProfileId = (int)$row['Number'];
$searchName = htmlspecialchars((string)$row['User'], ENT_QUOTES, 'UTF-8');
$searchPhoto = htmlspecialchars((string)$row['Photo'], ENT_QUOTES, 'UTF-8');
$searchRating = (int)$row['Rating'];
?>
<a class="search_result_card" href="profile.php?id=<?= $searchProfileId ?>">
<img class="search_result_photo" src="<?= $searchPhoto ?>" alt="<?= $searchName ?>" />
<span class="search_result_info">
<span class="search_result_name"><?= $searchName ?></span>
<span class="search_result_rating">Рейтинг: <?= $searchRating ?>%</span>
</span>
</a>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if ($searchTotal > 1): ?>
<?php $searchPagination = '&search=' . urlencode($searchQuery); ?>
<div class="pagination">
<?php
if ($searchPage != 1) echo '<a href="?id=' . (int)$a['Number'] . '&page=1' . $searchPagination . '">&laquo;</a> <a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage - 1) . $searchPagination . '">&lsaquo;</a>';

$nav = '';
if ($searchPage - 2 > 0) $nav .= '<a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage - 2) . $searchPagination . '">' . ($searchPage - 2) . '</a>';
if ($searchPage - 1 > 0) $nav .= '<a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage - 1) . $searchPagination . '">' . ($searchPage - 1) . '</a>';
$nav .= '<span class="current">' . $searchPage . '</span>';
if ($searchPage + 1 <= $searchTotal) $nav .= '<a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage + 1) . $searchPagination . '">' . ($searchPage + 1) . '</a>';
if ($searchPage + 2 <= $searchTotal) $nav .= '<a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage + 2) . $searchPagination . '">' . ($searchPage + 2) . '</a>';
echo $nav;

if ($searchPage != $searchTotal) echo '<a href="?id=' . (int)$a['Number'] . '&page=' . ($searchPage + 1) . $searchPagination . '">&rsaquo;</a> <a href="?id=' . (int)$a['Number'] . '&page=' . $searchTotal . $searchPagination . '">&raquo;</a>';
?>
</div>
<?php endif; ?>

<?php elseif ($viewerId <= 0): ?>
<div id="messages_link">
<h3>Личная переписка</h3>
<p>Чтобы отправлять личные сообщения, войдите в аккаунт.</p>
<p align="center"><a class="button" href="login.php">Войти</a></p>
</div>
<?php elseif ($viewerId === $profileId): ?>
<div id="messages_link">
<h3>Личная переписка</h3>
<p>Это ваш профиль. Выберите пользователя, чтобы начать переписку.</p>
<p align="center"><a class="button" href="index.php">На главную</a></p>
</div>
<?php else: ?>
<h3 class="messages_profile_title">Переписка с <?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?></h3>
<?php if ($msgTotalPages > 1): ?>
<div class="pagination" aria-label="Пагинация переписки">
<?php if ($msgPage > 1): ?><a href="?id=<?= $profileId ?>&msg_page=<?= $msgPage - 1 ?>">&lsaquo; Старее</a><?php endif; ?>
<span class="current"><?= $msgPage ?> / <?= $msgTotalPages ?></span>
<?php if ($msgPage < $msgTotalPages): ?><a href="?id=<?= $profileId ?>&msg_page=<?= $msgPage + 1 ?>">Новее &rsaquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<div class="conversation" aria-live="polite">
<?php if (!$messages): ?>
<div class="conversation-empty">
<strong>Переписка пока пуста</strong>
<span>Напишите первое сообщение <?= htmlspecialchars($a['User'], ENT_QUOTES, 'UTF-8') ?>.</span>
</div>
<?php else: ?>
<?php foreach ($messages as $message): ?>
<?php $isMine = is_my_message($message, $viewerId, $viewerName); ?>
<div class="message-row <?= $isMine ? 'message-row-mine' : 'message-row-other' ?>">
<img
  class="message-avatar"
  src="<?= htmlspecialchars($isMine ? $viewerAvatar : $targetAvatar, ENT_QUOTES, 'UTF-8') ?>"
  alt=""
>
<div class="message-bubble">
<div class="message-author">
<?= htmlspecialchars($isMine ? 'Вы' : (string)($message['sender'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8') ?>
</div>
<div class="message-text"><?= nl2br(htmlspecialchars((string)$message['content'], ENT_QUOTES, 'UTF-8')) ?></div>
<time datetime="<?= htmlspecialchars((string)$message['created_at'], ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$message['created_at'])), ENT_QUOTES, 'UTF-8') ?>
</time>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<form method="post" action="messages_profile.php?id=<?= $profileId ?>#message_form" id="message_form" class="message-compose">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<label for="MessageText">Новое сообщение</label>
<textarea id="MessageText" name="MessageText" maxlength="1000" rows="4" placeholder="Напишите сообщение..." required></textarea>
<div class="compose-footer">
<span>Максимум 1000 символов</span>
<button type="submit" class="button">Отправить сообщение</button>
</div>
</form>
<?php endif; ?>
</div>

<div id="footer">&copy; Mr.Green</div>
</div>
<script>
  const messageField = document.getElementById('MessageText');
  if (messageField) {
    messageField.focus();
  }
</script>
</body>
</html>
