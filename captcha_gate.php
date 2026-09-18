<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Для проверки CAPTCHA требуется POST-запрос.'], 405);
}

// Для CAPTCHA принимаем CSRF-токен и из POST, и из заголовка.
// Это надёжнее для fetch/FormData на серверах с нестандартной обработкой тела запроса.
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf(is_string($csrf) ? $csrf : null)) {
    json_response(['error' => 'Сессия CAPTCHA устарела. Обновите страницу и попробуйте снова.'], 403);
}

$answer = strtolower(trim((string)($_POST['captcha'] ?? '')));
$key = strtolower((string)($_SESSION['captcha_keystring'] ?? ''));

if ($answer === '' || $key === '' || !hash_equals($key, $answer)) {
    unset($_SESSION['captcha_verified_until']);
    json_response(['error' => 'Неверный код CAPTCHA.'], 422);
}

unset($_SESSION['captcha_keystring']);
$_SESSION['captcha_verified_until'] = time() + 900;
json_response(['status' => 'ok']);
