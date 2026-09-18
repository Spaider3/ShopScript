<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
start_secure_session();

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT Photo FROM profiles WHERE Number = ? LIMIT 1');
$stmt->execute([$userId]);
$photo = (string)($stmt->fetchColumn() ?: 'images/nophoto.jpg');

$currentAvatar = '../' . ltrim($photo, '/');
if (!is_file(__DIR__ . '/../' . ltrim($photo, '/'))) {
    $currentAvatar = '../images/nophoto.jpg';
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Загрузка аватара</title>
<link rel="stylesheet" href="../css/avatar.css">
</head>
<body>
<main class="avatar-page">
        <h1>Загрузка аватара</h1>

        <form id="avatar-form" action="../modules/uploadimg.php" method="post" enctype="multipart/form-data" class="avatar-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label class="avatar-picker">
                <input
                    type="file"
                    name="avatar"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    id="avatar-input"
                    hidden
                >
                <div class="avatar-preview" id="avatar-preview">
                    <img
                        id="avatar-img"
                        src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Предпросмотр аватара"
                    >
                    <span class="avatar-overlay">Выбрать</span>
                </div>
            </label>

            <p class="avatar-hint">JPEG, PNG, WebP или GIF, до 2 МБ</p>
            <p class="avatar-error" id="avatar-error" hidden></p>
            <p class="avatar-success" id="avatar-success" hidden></p>

            <button type="submit" class="avatar-submit" id="avatar-submit">Сохранить аватар</button>
        </form>
    </main>

       <script>
        const form = document.getElementById('avatar-form');
        const input = document.getElementById('avatar-input');
        const img = document.getElementById('avatar-img');
        const error = document.getElementById('avatar-error');
        const success = document.getElementById('avatar-success');
        const submitBtn = document.getElementById('avatar-submit');

        const MAX_SIZE = 2 * 1024 * 1024; // 2 МБ
        const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        input.addEventListener('change', () => {
            const file = input.files[0];
            error.hidden = true;

            if (!file) return;

            if (!ALLOWED.includes(file.type)) {
                showError('Недопустимый формат. Разрешены JPEG, PNG, WebP, GIF.');
                input.value = '';
                return;
            }

            if (file.size > MAX_SIZE) {
                showError('Файл слишком большой. Максимум 2 МБ.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => img.src = e.target.result;
            reader.readAsDataURL(file);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            error.hidden = true;
            success.hidden = true;

            if (!input.files[0]) {
                showError('Выберите изображение.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Сохранение…';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Не удалось сохранить аватар.');
                }

                success.textContent = data.message || 'Аватар успешно обновлён.';
                success.hidden = false;
            } catch (e) {
                showError(e.message || 'Ошибка соединения с сервером.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Сохранить аватар';
            }
        });

        function showError(message) {
            error.textContent = message;
            error.hidden = false;
        }
    </script>
</body>
</html>
