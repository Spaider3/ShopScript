<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
start_secure_session();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../errorlist.php';
require_once __DIR__ . '/../check.php';
require_once __DIR__ . '/shop_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Метод не разрешён'], 405);
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    json_response(['error' => 'Сессия устарела. Обновите страницу и повторите попытку.'], 403);
}

$viewerId = !empty($_SESSION['logged_in']) ? (int)($_SESSION['user_id'] ?? 0) : 0;

if ($viewerId <= 0) {
    json_response(['error' => 'Необходимо войти в аккаунт.'], 401);
}

ensure_user_role($pdo, $viewerId, (string)($_SESSION['user_login'] ?? ''));

$action = (string)($_POST['action'] ?? '');

switch ($action) {
    // -------- Добавление товара в корзину --------
    case 'add_to_cart':
        $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        $productId = $productId === false ? 0 : $productId;

        if ($productId <= 0) {
            json_response(['error' => 'Некорректный товар.'], 400);
        }

        if (!can_use_shop($pdo, $viewerId, 'buy_products')) {
            json_response(['error' => 'У вас нет прав на покупку товаров.'], 403);
        }

        $stmt = $pdo->prepare('SELECT sp.seller_id FROM shop_products sp JOIN profiles seller ON seller.Number = sp.seller_id AND seller.banned = 0 WHERE sp.id = :id AND sp.is_deleted = 0 AND sp.moderation_status = \'approved\' LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $sellerId = $stmt->fetchColumn();

        if ($sellerId === false) {
            json_response(['error' => 'Товар не найден.'], 404);
        }

        if ((int)$sellerId === $viewerId) {
            json_response(['error' => 'Нельзя купить собственный товар.'], 400);
        }

        $priceStmt = $pdo->prepare('SELECT sp.price FROM shop_products sp JOIN profiles seller ON seller.Number = sp.seller_id AND seller.banned = 0 WHERE sp.id = :id AND sp.is_deleted = 0 AND sp.moderation_status = \'approved\' LIMIT 1');
        $priceStmt->execute([':id' => $productId]);
        $productPrice = (float)$priceStmt->fetchColumn();
        if ($productPrice <= 0) {
            json_response(['error' => 'Цена товара некорректна.'], 400);
        }

        cart_add($productId);
        json_response(['status' => 'ok', 'message' => 'Товар добавлен в корзину.']);

    // -------- Удаление товара из корзины --------
    case 'remove_from_cart':
        $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($productId) {
            cart_remove($productId);
            json_response(['status' => 'ok', 'message' => 'Товар удалён из корзины.']);
        }
        json_response(['error' => 'Некорректный товар.'], 400);

    // -------- Оформление заказа --------
    case 'checkout':
        $cart = cart_details($pdo);
        if (!$cart || $cart['total'] <= 0) json_response(['error' => 'Корзина пуста.'], 400);
        if (!can_use_shop($pdo, $viewerId, 'buy_products')) json_response(['error' => 'У вас нет прав на покупку товаров.'], 403);
        foreach ($cart['items'] as $item) if ((int)$item['product']['seller_id'] === $viewerId) json_response(['error' => 'В корзине есть собственный товар.'], 400);
        try {
            $orderId = create_pending_order($pdo, $viewerId, $cart);
            cart_clear();
            json_response([
                'status' => 'ok',
                'message' => 'Заказ #' . $orderId . ' отправлен на одобрение. Баланс будет списан после одобрения.',
                'order_id' => $orderId
            ]);
        } catch(Throwable $e) {
            error_log('Wallet checkout error: '.$e->getMessage());
            json_response([
                'error' => $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Не удалось оформить заказ. Попробуйте ещё раз.'
            ], 400);
        }

    // -------- Список всех отзывов на товар (для модального окна) --------
    case 'get_reviews':
        $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            json_response(['error' => 'Некорректный товар.'], 400);
        }
        json_response(get_product_reviews_full($pdo, (int)$productId));

    // -------- Отзыв на оплаченный товар --------
    case 'add_review':
        if (user_is_banned($pdo, $viewerId)) {
            json_response(['error' => 'Заблокированный аккаунт не может оставлять отзывы.'], 403);
        }
        $productId = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
        $rating = filter_var($_POST['rating'] ?? 0, FILTER_VALIDATE_INT);
        $review = trim((string)($_POST['review'] ?? ''));
        if ($productId === false || $productId <= 0 || $rating === false || $rating < 1 || $rating > 5) {
            json_response(['error' => 'Некорректные данные отзыва.'], 400);
        }
        try {
            add_product_review($pdo, $viewerId, (int)$productId, (int)$rating, $review);
            json_response(['status' => 'ok', 'message' => 'Спасибо! Отзыв сохранён.']);
        } catch (Throwable $e) {
            error_log('Product review error: ' . $e->getMessage());
            json_response(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось сохранить отзыв.'], 400);
        }

    default:
        json_response(['error' => 'Неизвестное действие.'], 400);
}
?>