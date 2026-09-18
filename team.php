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

$viewerId = !empty($_SESSION['logged_in']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
$csrf = csrf_token();
$unreadCount = count_unread_messages($pdo, $viewerId);
$panelAccess = $online === 1 || ($viewerId > 0 && in_array(get_user_role($pdo, $viewerId), ['admin','moderator'], true));

// Собираем команду сайта: администраторы и модераторы.
$stmt = $pdo->query(
    'SELECT u.id, p.User, p.Photo, p.Rating, p.is_online, p.last_seen, r.name AS role_name
       FROM users u
       JOIN profiles p ON p.Number = u.id
       JOIN roles r ON r.id = u.role_id
      WHERE r.name IN ("admin", "moderator")
      ORDER BY FIELD(r.name, "admin", "moderator"), p.Rating DESC'
);
$team = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Команда сайта | <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
<link rel="stylesheet" type="text/css" href="css/style_shop.css"/>
<script type="text/javascript" src="js/function.js" defer></script>
</head>
<body>
<div id="container">
<div id="header">
Команда сайта
<div id="menu">
<?php if ($panelAccess): ?> / <a href="admin.php">В панель управления</a><?php endif; ?>
 / <a href="index.php">На главную</a>
<?php if ($viewerId <= 0): ?> / <a href="login.php">Вход</a><?php endif; ?>
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

<div id="content">
<h2 class="team_title">Администраторы и модераторы</h2>
<p class="team_subtitle">Здесь вы можете найти ссылки на страницы команды сайта.</p>

<?php if (!$team): ?>
<div class="search_empty">Команда сайта пока не назначена.</div>
<?php else: ?>
<div class="team_grid">
<?php foreach ($team as $member): ?>
<?php
$memberId = (int)$member['id'];
$memberName = htmlspecialchars((string)$member['User'], ENT_QUOTES, 'UTF-8');
$memberPhoto = htmlspecialchars(resolve_avatar((string)$member['Photo']), ENT_QUOTES, 'UTF-8');
$memberRating = (int)$member['Rating'];
$memberRole = (string)$member['role_name'] === 'admin' ? 'Администратор' : 'Модератор';
$memberOnline = !empty($member['is_online']);
$memberLastSeen = !empty($member['last_seen']) ? date('d.m.Y H:i', strtotime((string)$member['last_seen'])) : null;
?>
<a class="team_card" href="profile.php?id=<?= $memberId ?>">
<img class="team_photo" src="<?= $memberPhoto ?>" alt="<?= $memberName ?>" />
<span class="team_info">
<span class="team_name"><?= $memberName ?></span>
<span class="team_role"><?= $memberRole ?></span>
<span class="team_rating">Рейтинг: <?= $memberRating ?>%</span>
<span class="team_status">
<span class="status_dot <?= $memberOnline ? 'online' : 'offline' ?>"></span>
<?= $memberOnline ? 'В сети' : ($memberLastSeen !== null ? 'Был(а): ' . htmlspecialchars($memberLastSeen, ENT_QUOTES, 'UTF-8') : 'Не в сети') ?>
</span>
</span>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<div id="footer">&copy; Mr.Green</div>
</div>
</body>
</html>