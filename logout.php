<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Метод не разрешён');
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Недействительный CSRF-токен');
}

$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);

if ($userId !== false && $userId !== null) {
    $stmt = $pdo->prepare(
        'UPDATE profiles SET last_seen = ?, is_online = 0 WHERE Number = ?'
    );
    $stmt->execute([date('Y-m-d H:i:s'), $userId]);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'] ?? '',
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
