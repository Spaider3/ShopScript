<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) { header('Location: profile.php?id=' . (int)$_SESSION['user_id']); exit; }
if (empty($_SESSION['captcha_verified_until']) || (int)$_SESSION['captcha_verified_until'] < time()) { header('Location: index.php'); exit; }
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход</title>
  <link rel="stylesheet" href="css/style_reg.css">
</head>
<body>
  <main class="card">
    <h1>Войти</h1>
    <p class="subtitle">Введите логин и пароль</p>

    <div class="server-error" id="serverError" role="alert" aria-live="polite"></div>
    <div class="server-success" id="serverSuccess" role="status" aria-live="polite"></div>

    <form id="loginForm" novalidate method="POST" action="login_check.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="userLogin">Логин</label>
        <input type="text" id="userLogin" name="userLogin" maxlength="30"
               autocomplete="username" placeholder="ivanov_123" required>
        <div class="hint">3–30 символов: латиница, цифры и знак «_»</div>
        <div class="error" id="userLoginError"></div>
      </div>

      <div class="field">
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
        <div class="error" id="passwordError"></div>
      </div>

      <button type="submit" id="submitBtn">Войти</button>
    </form>
  </main>

  <script>
    (() => {
      const form = document.getElementById('loginForm');
      const serverError = document.getElementById('serverError');
      const serverSuccess = document.getElementById('serverSuccess');
      const submitBtn = document.getElementById('submitBtn');

      function showError(id, message) {
        const input = document.getElementById(id);
        const error = document.getElementById(id + 'Error');

        input.classList.toggle('invalid', Boolean(message));
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        error.textContent = message || '';
      }

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        serverError.style.display = 'none';
        serverSuccess.style.display = 'none';
        showError('userLogin', '');
        showError('password', '');

        const login = document.getElementById('userLogin').value.trim();
        const password = document.getElementById('password').value;
        let valid = true;

        if (!/^[A-Za-z0-9_]{3,30}$/.test(login)) {
          showError('userLogin', 'Введите корректный логин.');
          valid = false;
        }

        if (!password) {
          showError('password', 'Введите пароль.');
          valid = false;
        }

        if (!valid) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Вход...';

        try {
          const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
          });

          const data = await response.json();

          if (!response.ok) {
            throw new Error(data.error || 'Ошибка входа.');
          }

          serverSuccess.textContent = data.message || 'Вы успешно вошли.';
          serverSuccess.style.display = 'block';

          window.location.href = 'profile.php?id=' + encodeURIComponent(data.user_id);
        } catch (error) {
          serverError.textContent = error.message || 'Ошибка соединения с сервером.';
          serverError.style.display = 'block';
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Войти';
        }
      });
    })();
  </script>
</body>
</html>
