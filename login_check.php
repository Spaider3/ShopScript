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

$login = post_string('userLogin');
$password = $_POST['password'] ?? '';

if (!is_string($password)) {
    $password = '';
}

if (login_is_throttled($pdo, 'user', $login)) {
    json_response(['error' => 'Слишком много неудачных попыток входа. Попробуйте снова через несколько минут.'], 429);
}

$row = authenticate_profile($pdo, $login, $password);

if ($row === null) {
    record_login_attempt($pdo, 'user', $login, false);

    $banStmt = $pdo->prepare('SELECT banned FROM profiles WHERE UserLogin = ? LIMIT 1');
    $banStmt->execute([$login]);
    if ((int)$banStmt->fetchColumn() === 1) {
        json_response(['error' => 'Ваш аккаунт заблокирован. Обратитесь к администратору.'], 403);
    }
    json_response(['error' => 'Неверный логин или пароль.'], 401);
}

record_login_attempt($pdo, 'user', $login, true);
start_user_session($row);
mark_profile_online($pdo, (int)$row['Number']);

// Гарантируем наличие роли в таблице users (по умолчанию — user)
ensure_user_role($pdo, (int)$row['Number'], (string)$row['UserLogin']);

json_response([
    'status' => 'success',
    'user_id' => (int)$row['Number'],
    'name' => (string)$row['User'],
    'login' => (string)$row['UserLogin'],
    'message' => 'Вы успешно вошли.',
]);
