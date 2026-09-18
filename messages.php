<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/messages_functions.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$viewerId = (int)$_SESSION['user_id'];
$viewerName = (string)($_SESSION['user_name'] ?? '');

if ($viewerName === '') {
    $stmt = $pdo->prepare('SELECT User FROM profiles WHERE Number = ? LIMIT 1');
    $stmt->execute([$viewerId]);
    $viewerName = (string)($stmt->fetchColumn() ?: '');
}

if ($viewerName === '') {
    header('Location: logout.php');
    exit;
}

$csrf = csrf_token();
$error = '';

$targetId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$targetId = $targetId && $targetId > 0 ? $targetId : 0;

// Без ?id — показываем список диалогов (inbox), а не сразу конкретную переписку.
if ($targetId <= 0) {
    $convLimit = 20;
    $convPage = max(1, (int)($_GET['page'] ?? 1));
    $convTotalCount = count_conversations($pdo, $viewerId);
    $convTotalPages = max(1, (int)ceil($convTotalCount / $convLimit));
    $convPage = min($convPage, $convTotalPages);
    $conversations = get_conversations_list($pdo, $viewerId, $convLimit, ($convPage - 1) * $convLimit);
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Сообщения</title>
<link rel="stylesheet" href="css/style_profile.css">
<link rel="stylesheet" href="css/style_messages.css">
</head>
<body>
<div id="container" class="messages-page">
  <div id="header">
    <div class="messages-header">
      <div class="messages-user">
        <div>
          <div class="messages-title">Сообщения</div>
          <a href="profile.php?id=<?= $viewerId ?>">Мой профиль</a>
        </div>
      </div>
      <div id="menu">
        <a href="index.php">На главную</a> /
        <a href="profile.php?id=<?= $viewerId ?>">Мой профиль</a> /
        <form method="post" action="logout.php" class="inline_logout_form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="link_button">Выход</button>
        </form>
      </div>
    </div>
  </div>

  <div id="content" class="messages-content">
    <?php if (!$conversations): ?>
      <div class="inbox-empty">
        <strong>Пока нет ни одной переписки</strong>
        <span>Откройте профиль пользователя и нажмите «Личные сообщения», чтобы начать диалог.</span>
      </div>
    <?php else: ?>
      <div class="inbox-list">
        <?php foreach ($conversations as $conv): ?>
          <?php
          $preview = $conv['last_message'] !== '' ? $conv['last_message'] : '…';
          if (mb_strlen($preview, 'UTF-8') > 80) {
              $preview = mb_substr($preview, 0, 80, 'UTF-8') . '…';
          }
          if ($conv['last_is_mine']) {
              $preview = 'Вы: ' . $preview;
          }
          $time = $conv['last_time'] !== '' ? date('d.m.Y H:i', strtotime($conv['last_time'])) : '';
          ?>
          <a class="inbox-item<?= $conv['unread'] > 0 ? ' inbox-unread' : '' ?>" href="messages.php?id=<?= $conv['other_id'] ?>">
            <img class="inbox-avatar" src="<?= htmlspecialchars($conv['other_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="">
            <div class="inbox-body">
              <div class="inbox-top">
                <span class="inbox-name"><?= htmlspecialchars($conv['other_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="inbox-time"><?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="inbox-preview"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php if ($conv['unread'] > 0): ?>
              <span class="inbox-badge"><?= $conv['unread'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($convTotalPages > 1): ?>
      <div class="pagination" aria-label="Пагинация диалогов">
        <?php if ($convPage > 1): ?><a href="?page=<?= $convPage - 1 ?>">&lsaquo;</a><?php endif; ?>
        <?php for ($cp = max(1, $convPage - 2); $cp <= min($convTotalPages, $convPage + 2); $cp++): ?>
          <?= $cp === $convPage ? '<span class="current">' . $cp . '</span>' : '<a href="?page=' . $cp . '">' . $cp . '</a>' ?>
        <?php endfor; ?>
        <?php if ($convPage < $convTotalPages): ?><a href="?page=<?= $convPage + 1 ?>">&rsaquo;</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div id="footer">&copy; Mr.Green</div>
</div>
</body>
</html>
<?php
    exit;
}

// ---- Конкретная переписка с пользователем $targetId ----

if ($targetId === $viewerId) {
    header('Location: messages.php');
    exit;
}

$stmt = $pdo->prepare('SELECT Number, User, Photo FROM profiles WHERE Number = ? LIMIT 1');
$stmt->execute([$targetId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    exit('Пользователь не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        $content = trim((string)($_POST['MessageText'] ?? ''));

        if (user_is_banned($pdo, $viewerId)) {
            $error = 'Заблокированный пользователь не может отправлять сообщения.';
        } elseif ($content === '') {
            $error = 'Введите сообщение.';
        } elseif (mb_strlen($content, 'UTF-8') > 1000) {
            $error = 'Сообщение не должно быть длиннее 1000 символов.';
        } else {
            send_private_message($pdo, $viewerId, $viewerName, $targetId, (string)$target['User'], $content);
            refresh_user_rating($pdo, $viewerId);
            header('Location: messages.php?id=' . $targetId . '#message_form');
            exit;
        }
    }
}

$msgLimit = 30;
$msgTotalCount = count_conversation_messages($pdo, $viewerId, $viewerName, $targetId, (string)$target['User']);
$msgTotalPages = max(1, (int)ceil($msgTotalCount / $msgLimit));
// По умолчанию показываем последнюю страницу — самые свежие сообщения,
// как и ожидает пользователь, открывая переписку.
$msgPage = isset($_GET['msg_page']) ? max(1, (int)$_GET['msg_page']) : $msgTotalPages;
$msgPage = min($msgPage, $msgTotalPages);
$messages = get_conversation_messages($pdo, $viewerId, $viewerName, $targetId, (string)$target['User'], $msgLimit, ($msgPage - 1) * $msgLimit);

// Открыли переписку — отмечаем входящие сообщения от собеседника прочитанными.
mark_messages_read($pdo, $targetId, $viewerId);
$unreadCount = count_unread_messages($pdo, $viewerId);

$avatar = resolve_avatar((string)($target['Photo'] ?? ''));

$stmt = $pdo->prepare('SELECT Photo FROM profiles WHERE Number = ? LIMIT 1');
$stmt->execute([$viewerId]);
$viewerAvatar = resolve_avatar((string)($stmt->fetchColumn() ?: ''));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Переписка с <?= htmlspecialchars((string)$target['User'], ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/style_profile.css">
<link rel="stylesheet" href="css/style_messages.css">
</head>
<body>
<div id="container" class="messages-page">
  <div id="header">
    <div class="messages-header">
      <div class="messages-user">
        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
        <div>
          <div class="messages-title">Переписка с <?= htmlspecialchars((string)$target['User'], ENT_QUOTES, 'UTF-8') ?></div>
          <a href="profile.php?id=<?= $targetId ?>">Открыть профиль</a>
        </div>
      </div>
      <div id="menu">
        <a href="messages.php">Все сообщения<?php if ($unreadCount > 0): ?> <span class="badge_count"><?= $unreadCount ?></span><?php endif; ?></a> /
        <a href="index.php">На главную</a> /
        <a href="profile.php?id=<?= $viewerId ?>">Мой профиль</a> /
        <form method="post" action="logout.php" class="inline_logout_form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="link_button">Выход</button>
        </form>
      </div>
    </div>
  </div>

  <div id="content" class="messages-content">
    <?php if ($error !== ''): ?>
      <div id="error_div" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($msgTotalPages > 1): ?>
    <div class="pagination" aria-label="Пагинация переписки">
      <?php if ($msgPage > 1): ?><a href="?id=<?= $targetId ?>&msg_page=<?= $msgPage - 1 ?>">&lsaquo; Старее</a><?php endif; ?>
      <span class="current"><?= $msgPage ?> / <?= $msgTotalPages ?></span>
      <?php if ($msgPage < $msgTotalPages): ?><a href="?id=<?= $targetId ?>&msg_page=<?= $msgPage + 1 ?>">Новее &rsaquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="conversation" aria-live="polite">
      <?php if (!$messages): ?>
        <div class="conversation-empty">
          <strong>Переписка пока пуста</strong>
          <span>Напишите первое сообщение <?= htmlspecialchars((string)$target['User'], ENT_QUOTES, 'UTF-8') ?>.</span>
        </div>
      <?php else: ?>
        <?php foreach ($messages as $message): ?>
          <?php $isMine = is_my_message($message, $viewerId, $viewerName); ?>
          <div class="message-row <?= $isMine ? 'message-row-mine' : 'message-row-other' ?>">
            <img
              class="message-avatar"
              src="<?= htmlspecialchars($isMine ? $viewerAvatar : $avatar, ENT_QUOTES, 'UTF-8') ?>"
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

    <form method="post" action="messages.php?id=<?= $targetId ?>#message_form" id="message_form" class="message-compose">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label for="MessageText">Новое сообщение</label>
      <textarea id="MessageText" name="MessageText" maxlength="1000" rows="4" placeholder="Напишите сообщение..." required></textarea>
      <div class="compose-footer">
        <span>Максимум 1000 символов</span>
        <button type="submit" class="button">Отправить сообщение</button>
      </div>
    </form>
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
