<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/shop_functions.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM profiles WHERE Number = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile || (int)($profile['banned'] ?? 0) === 1) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$csrf = csrf_token();
$errors = [];
$success = ($_GET['saved'] ?? '') === '1' ? 'Профиль успешно обновлён.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        $formType = (string)($_POST['form_type'] ?? '');
        if ($formType === 'profile') {
            $name = post_string('user');
            $login = post_string('userLogin');
            $password = is_string($_POST['password'] ?? null) ? (string)$_POST['password'] : '';
            if (($error = validate_name($name)) !== null) $errors[] = $error;
            if (($error = validate_login($login)) !== null) $errors[] = $error;
            if ($password !== '' && strlen($password) < 8) $errors[] = 'Новый пароль должен содержать минимум 8 символов.';
            if ($password !== '' && strlen($password) > 4096) $errors[] = 'Новый пароль слишком длинный.';
            if (!$errors) {
                $stmt = $pdo->prepare('SELECT Number FROM profiles WHERE UserLogin = ? AND Number <> ? LIMIT 1');
                $stmt->execute([$login, $userId]);
                if ($stmt->fetch()) $errors[] = 'Этот логин уже занят.';
            }
            if (!$errors) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE profiles SET User = ?, UserLogin = ?, Password = ? WHERE Number = ?');
                    $stmt->execute([$name, $login, password_hash($password, PASSWORD_DEFAULT), $userId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE profiles SET User = ?, UserLogin = ? WHERE Number = ?');
                    $stmt->execute([$name, $login, $userId]);
                }
                $_SESSION['user_name'] = $name;
                $_SESSION['user_login'] = $login;
                header('Location: edit_profile.php?saved=1');
                exit;
            }
        } elseif ($formType === 'avatar') {
            $file = $_FILES['avatar'] ?? null;
            if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $uploadError = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой. Максимум 2 МБ.',
                    UPLOAD_ERR_NO_FILE => 'Выберите изображение.',
                    default => 'Не удалось загрузить изображение.',
                };
            } elseif ((int)$file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Файл слишком большой. Максимум 2 МБ.';
            } else {
                $tmpName = (string)$file['tmp_name'];
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                if (!in_array($mime, $allowed, true)) {
                    $errors[] = 'Недопустимый формат. Разрешены JPEG, PNG, WebP и GIF.';
                } elseif (@getimagesize($tmpName) === false) {
                    $errors[] = 'Файл не является корректным изображением.';
                } else {
                    $uploadDir = __DIR__ . '/images/upload_profiles';
                    try {
                        $fileName = save_compressed_image($tmpName, $uploadDir, 'avatar', 210, 210);
                        $relativePath = 'images/upload_profiles/' . $fileName;
                        $stmt = $pdo->prepare('UPDATE profiles SET Photo = ? WHERE Number = ?');
                        $stmt->execute([$relativePath, $userId]);
                        $oldPhoto = (string)$profile['Photo'];
                        $oldFullPath = __DIR__ . '/' . ltrim($oldPhoto, '/');
                        $oldPrefix = __DIR__ . '/images/upload_profiles/';
                        if (is_file($oldFullPath) && str_starts_with($oldFullPath, $oldPrefix) && basename($oldFullPath) !== $fileName) @unlink($oldFullPath);
                        $profile['Photo'] = $relativePath;
                        $success = 'Аватар успешно обновлён.';
                    } catch (Throwable $e) {
                        $errors[] = 'Не удалось обработать изображение.';
                    }
                }
            }
        } else {
            $errors[] = 'Неизвестный тип формы.';
        }
    }
}

$currentAvatar = resolve_avatar((string)$profile['Photo']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Редактирование профиля</title>
  <link rel="stylesheet" href="css/style_reg.css">
  <link rel="stylesheet" href="css/style_edit_profile.css">
</head>
<body>
  <main class="card profile-edit-page">
    <div class="card-head">
      <div>
        <h1>Редактирование профиля</h1>
        <p class="subtitle">Измените данные аккаунта и аватар</p>
      </div>
      <a class="back-link" href="profile.php?id=<?= $userId ?>">К профилю</a>
    </div>

    <?php if ($errors): ?>
      <div class="server-error" style="display:block" role="alert">
        <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <div class="server-success" style="display:block" role="status">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <section class="section">
      <h2 class="section-title">Основные данные</h2>
      <form method="post" action="edit_profile.php">
        <input type="hidden" name="form_type" value="profile">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="user">Имя пользователя</label>
          <input type="text" id="user" name="user" maxlength="100" autocomplete="name" value="<?= htmlspecialchars((string)$profile['User'], ENT_QUOTES, 'UTF-8') ?>" required>
          <div class="hint">До 100 символов</div>
        </div>

        <div class="field">
          <label for="userLogin">Логин</label>
          <input type="text" id="userLogin" name="userLogin" maxlength="30" autocomplete="username" value="<?= htmlspecialchars((string)$profile['UserLogin'], ENT_QUOTES, 'UTF-8') ?>" required>
          <div class="hint">3–30 символов: латиница, цифры и знак «_»</div>
        </div>

        <div class="field">
          <label for="password">Новый пароль</label>
          <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" placeholder="Оставьте пустым, чтобы не менять">
          <div class="hint">Минимум 8 символов. Поле необязательно.</div>
        </div>

        <button type="submit">Сохранить данные</button>
      </form>
    </section>

    <section class="section">
      <h2 class="section-title">Аватар</h2>
      <form method="post" action="edit_profile.php" enctype="multipart/form-data">
        <input type="hidden" name="form_type" value="avatar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div class="avatar-editor">
          <label class="avatar-picker" for="avatar-input">
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" id="avatar-input" hidden>
            <div class="avatar-preview">
              <img id="avatar-img" src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="Предпросмотр аватара">
              <span class="avatar-overlay">Выбрать</span>
            </div>
          </label>
          <div class="avatar-copy">
            <strong>Выберите новую фотографию</strong>
            JPEG, PNG, WebP или GIF, до 2 МБ.
            <div class="file-name" id="avatar-file-name">Файл не выбран</div>
          </div>
        </div>

        <div class="actions">
          <button type="submit">Сохранить аватар</button>
          <a class="secondary-button" href="profile.php?id=<?= $userId ?>">Отмена</a>
        </div>
      </form>
    </section>
  </main>

  <script>
    (() => {
      const input = document.getElementById('avatar-input');
      const img = document.getElementById('avatar-img');
      const fileName = document.getElementById('avatar-file-name');
      const maxSize = 2 * 1024 * 1024;
      const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

      input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) {
          fileName.textContent = 'Файл не выбран';
          return;
        }
        if (!allowed.includes(file.type)) {
          fileName.textContent = 'Недопустимый формат файла';
          input.value = '';
          return;
        }
        if (file.size > maxSize) {
          fileName.textContent = 'Файл слишком большой (максимум 2 МБ)';
          input.value = '';
          return;
        }
        fileName.textContent = file.name;
        const reader = new FileReader();
        reader.onload = event => { img.src = event.target.result; };
        reader.readAsDataURL(file);
      });
    })();
  </script>
</body>
</html>
