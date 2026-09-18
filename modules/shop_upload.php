<?php
declare(strict_types=1);

/**
 * AJAX-эндпоинт загрузки изображения товара для магазина.
 *
 * Принимает multipart/form-data POST с полем product_photo и возвращает JSON:
 *   {"status":"ok","path":"images/shop_products/shop_....jpg"}
 * либо {"status":"error","error":"..."}
 *
 * Требует авторизации и CSRF-токена.
 */

require_once __DIR__ . '/../functions.php';
start_secure_session();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../errorlist.php';
require_once __DIR__ . '/../check.php';
require_once __DIR__ . '/shop_functions.php';
require_once __DIR__ . '/shop_image_handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'error' => 'Метод не разрешён.'], 405);
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    json_response(['status' => 'error', 'error' => 'Необходимо войти в аккаунт.'], 401);
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    json_response(['status' => 'error', 'error' => 'Сессия устарела. Обновите страницу и повторите попытку.'], 403);
}

$viewerId = (int)$_SESSION['user_id'];

// Проверяем право продажи.
if (!user_has_permission($pdo, $viewerId, 'sell_products')) {
    json_response(['status' => 'error', 'error' => 'У вас нет прав на продажу товаров.'], 403);
}

$handler = new ShopImageHandler();
$result = $handler->process($_FILES['product_photo'] ?? null);

if (!$result['ok']) {
    json_response(['status' => 'error', 'error' => $result['error']], 400);
}

json_response([
    'status' => 'ok',
    'path'   => $result['path'],
    'name'   => $result['name'],
]);