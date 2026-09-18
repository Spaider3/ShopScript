<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
$siteName = get_site_name($pdo);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/shop_functions.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

$viewerId = current_user_id();
$fullAdminAccess = $online === 1 || ($viewerId > 0 && get_user_role($pdo, $viewerId) === 'admin');
if (!$fullAdminAccess) {
    if ($isAjax) {
        json_response(['error' => 'Недостаточно прав.'], 403);
    }
    header('Location: admin.php');
    exit;
}

// CSRF-защита для всех POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
} else {
    $User = trim((string)($_POST['User'] ?? ''));
    $rate = filter_var($_POST['rate'] ?? 0, FILTER_VALIDATE_INT);
    $rate = $rate === false ? 0 : max(0, $rate);
    $edit = isset($_POST['edit']) && $_POST['edit'] !== '';
    $num = filter_var($_POST['num'] ?? 0, FILTER_VALIDATE_INT);
    $hasUpload = isset($_FILES['Photo']) &&
        $_FILES['Photo']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasUpload && $_FILES['Photo']['error'] !== UPLOAD_ERR_OK) {
        $error = $errors[1];
        $hasUpload = false;
    }

    if ($User === '' && !$edit) {
        $error = $errors[1];
    }

    if (!($error ?? false) && $edit && !$num) {
        $error = $errors[1];
    }

    if (!($error ?? false)) {
        try {
            $photoPath = null;

            if ($hasUpload) {
                if ((int)$_FILES['Photo']['size'] > 2 * 1024 * 1024) {
                    $error = 'Файл слишком большой. Максимум 2 МБ.';
                }
                $tmp = (string)$_FILES['Photo']['tmp_name'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                if (!isset($error) && (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || @getimagesize($tmp) === false)) {
                    $error = $errors[1];
                } elseif (!isset($error)) {
                    $uploadDir = __DIR__ . '/images/upload_profiles';
                    try {
                        $fileName = save_compressed_image($tmp, $uploadDir, 'admin_avatar', 210, 210);
                        $photoPath = 'images/upload_profiles/' . $fileName;
                    } catch (Throwable $e) {
                        $error = 'Не удалось обработать изображение.';
                    }
                }
            }

            if (!isset($error)) {
                if ($edit) {
                    $stmt = $pdo->prepare('SELECT Photo FROM profiles WHERE `Number` = :id');
                    $stmt->execute([':id' => $num]);
                    $oldPhoto = $stmt->fetchColumn();

                    if ($photoPath !== null) {
                        $stmt = $pdo->prepare(
                            'UPDATE profiles SET User = :User, Photo = :photo WHERE `Number` = :id'
                        );
                        $stmt->execute([
                            ':User' => $User,
                            ':photo' => $photoPath,
                                    ':id' => $num,
                        ]);

                        if ($oldPhoto && $oldPhoto !== 'images/nophoto.jpg') {
                            $oldFile = __DIR__ . '/' . ltrim((string)$oldPhoto, '/');
                            $oldPrefix = __DIR__ . '/images/upload_profiles/';
                            if (is_file($oldFile) && str_starts_with($oldFile, $oldPrefix) && basename($oldFile) !== basename($photoPath)) {
                                @unlink($oldFile);
                            }
                        }
                    } else {
                        if ($User !== '') {
                            $stmt = $pdo->prepare(
                                'UPDATE profiles SET User = :User WHERE `Number` = :id'
                            );
                            $stmt->execute([
                                ':User' => $User,
                                ':id' => $num,
                            ]);
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE profiles SET User = User WHERE `Number` = :id'
                            );
                            $stmt->execute([
                                ':id' => $num,
                            ]);
                        }
                    }
                } else {
                    $photoPath ??= 'images/nophoto.jpg';

                    // UserLogin = NULL — это профиль без возможности входа (например,
                    // карточка команды, добавленная админом). NULL, а не '', чтобы не
                    // конфликтовать с уникальным индексом uniq_profiles_userlogin —
                    // MySQL допускает сколько угодно NULL-значений в UNIQUE-индексе,
                    // но только одно пустое значение ''.
                    $stmt = $pdo->prepare(
                        "INSERT INTO profiles (User, UserLogin, Password, Photo, Rating) VALUES (:User, NULL, '', :photo, 0)"
                    );
                    $stmt->execute([
                        ':User' => $User,
                        ':photo' => $photoPath,
                    ]);
                }

                $savedId = $num > 0 ? $num : (int)$pdo->lastInsertId();
                if ($savedId > 0) { ensure_user_role($pdo, $savedId, ''); refresh_user_rating($pdo, $savedId); }

                if ($isAjax) {
                    json_response(['status' => 'success', 'message' => 'Изменения сохранены.']);
                }
                header('Location: admin.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Profile editor error: ' . $e->getMessage());
            $error = $errors[1];
        }
    }
}

if ($isAjax) {
    json_response(['error' => $error ?? 'Не удалось сохранить изменения.'], 400);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" type="text/css" href="css/style_admin.css"/>
</head>
<body>
<div id="container">
<div id="content">
<div id="error_div"><?= $error ?? '' ?></div>
<?php if (($error ?? '') === ''): ?>
<div align="center" style="padding:20px;color:#6b7280">Операция выполнена успешно.</div>
<?php endif; ?>
<p align="center"><a class="button" href="admin.php">Вернуться в админ-панель</a></p>
</div>
</div>
</body>
</html>