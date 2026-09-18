<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/shop_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Метод не разрешён'], 405);
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    json_response(['error' => 'Сессия устарела. Обновите страницу и повторите попытку.'], 403);
}
if (empty($_SESSION['captcha_verified_until']) || (int)$_SESSION['captcha_verified_until'] < time()) {
    json_response(['error' => 'Сначала пройдите CAPTCHA на главной странице.'], 403);
}
unset($_SESSION['captcha_verified_until']);

$user = post_string('user');
$userLogin = post_string('userLogin');
$password = $_POST['password'] ?? '';

if (!is_string($password)) {
    $password = '';
}

if (($error = validate_name($user)) !== null) {
    json_response(['error' => $error], 400);
}

if (($error = validate_login($userLogin)) !== null) {
    json_response(['error' => $error], 400);
}

if (strlen($password) < 8) {
    json_response(['error' => 'Пароль должен содержать минимум 8 символов.'], 400);
}

if (strlen($password) > 4096) {
    json_response(['error' => 'Пароль слишком длинный.'], 400);
}

$stmt = $pdo->prepare('SELECT 1 FROM profiles WHERE UserLogin = ? LIMIT 1');
$stmt->execute([$userLogin]);

if ($stmt->fetchColumn() !== false) {
    json_response(['error' => 'Логин уже занят.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$photo = 'images/nophoto.jpg';
$rating = 0;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO profiles
            (User, UserLogin, Password, Photo, Rating, created_at, last_seen, is_online)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 0)'
    );
    $stmt->execute([$user, $userLogin, $hash, $photo, $rating]);

    // Создаём запись роли (по умолчанию — user, id=3)
    $newId = (int)$pdo->lastInsertId();
    ensure_user_role($pdo, $newId, $userLogin);
} catch (PDOException $e) {
    // Не показываем пользователю внутренние детали БД.
    if ((int)$e->errorInfo[1] === 1062) {
        json_response(['error' => 'Логин уже занят.'], 409);
    }

    error_log($e->getMessage());
    json_response(['error' => 'Не удалось создать аккаунт.'], 500);
}

json_response([
    'status' => 'success',
    'message' => 'Пользователь зарегистрирован.',
], 201);
