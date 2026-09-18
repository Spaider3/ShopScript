<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
$csrf = csrf_token();
$siteName = get_site_name($pdo);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/shop_functions.php';

$viewerId = current_user_id();
$fullAdminAccess = $online === 1 || ($viewerId > 0 && get_user_role($pdo, $viewerId) === 'admin');
if (!$fullAdminAccess) {
    header('Location: admin.php');
    exit;
}

$editId = filter_var($_GET['num'] ?? 0, FILTER_VALIDATE_INT);
$profile = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT `Number`, `User`, `Photo` FROM profiles WHERE `Number` = :id LIMIT 1');
    $stmt->execute([':id' => $editId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isAjax = ($_GET['ajax'] ?? '') === '1'
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

// ---- Фрагмент формы (для AJAX-окна в admin.php) — без <html>/<head>/шапки ----
if ($isAjax) {
    if (!$profile) {
        echo '<div class="edit_modal_empty">Запись не найдена.</div>';
        exit;
    }
    ?>
<form action="editor.php" method="post" enctype="multipart/form-data" class="ajax_edit_form" data-ajax-edit>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
<input type="hidden" name="num" value="<?= (int)$profile['Number'] ?>" />
<input type="hidden" name="edit" value="1" />
<div class="edit_modal_error" data-ajax-edit-error hidden></div>
<div class="edit_avatar_editor">
<label class="edit_avatar_picker" for="edit-avatar-input">
<input id="edit-avatar-input" class="edit_avatar_input" type="file" name="Photo" accept="image/jpeg,image/png,image/webp,image/gif" hidden />
<span class="edit_avatar_preview"><img id="edit-avatar-img" src="<?= htmlspecialchars(resolve_avatar((string)$profile['Photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Предпросмотр аватара" /></span>
<span class="edit_avatar_overlay">Выбрать</span>
</label>
<div class="edit_avatar_copy">
<strong>Аватар</strong>
<span id="edit-avatar-file-name">Файл не выбран</span>
<small>JPEG, PNG, WebP или GIF · до 2 МБ</small>
</div>
</div>
<table class="form_table" border="0" cellspacing="0" cellpadding="0">
<tr>
<td>Имя/Фамилия</td>
<td><input name="User" type="text" value="<?= htmlspecialchars((string)$profile['User'], ENT_QUOTES, 'UTF-8') ?>" required /></td>
</tr>
</table>
<p align="center"><button type="submit" class="button">Сохранить</button></p>
</form>
<?php
    exit;
}

// ---- Полная страница (без JS / прямой переход по ссылке) ----
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> | Редактирование</title>
<link rel="stylesheet" type="text/css" href="css/style_admin.css"/>
</head>
<body>
<div id="container">
<div id="content">
<h1 class="page_title">Редактирование записи №<?= (int)$editId ?></h1>
<div id="error_div"><?= $error ?? '' ?></div>
<?php if (!$profile): ?>
<div class="edit_modal_empty">Запись не найдена.</div>
<p align="center"><a class="button" href="admin.php">Вернуться в админ-панель</a></p>
<?php else: ?>
<form action="editor.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
<input type="hidden" name="num" value="<?= (int)$profile['Number'] ?>" />
<input type="hidden" name="edit" value="1" />
<table class="form_table" border="0" cellspacing="0" cellpadding="0" align="center">
<tr>
<td>Имя/Фамилия</td>
<td><input name="User" type="text" value="<?= htmlspecialchars((string)$profile['User'], ENT_QUOTES, 'UTF-8') ?>" required /></td>
</tr>
<tr>
<td>Фото</td>
<td><input name="Photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif" /></td>
</tr>
</table>
<p align="center"><button type="submit" class="button">Изменить</button></p>
</form>
<?php endif; ?>
</div>
</div>
</body>
</html>
