<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/modules/shop_functions.php';

$payload = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if ($STRIPE_WEBHOOK_SECRET === '' || $payload === '' || $sig === '') {
    http_response_code(400);
    exit('bad request');
}

$timestamp = 0;
$signatures = [];
foreach (explode(',', $sig) as $part) {
    [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
    if ($k === 't') $timestamp = (int)$v;
    if ($k === 'v1') $signatures[] = $v;
}
if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
    http_response_code(400);
    exit('expired');
}

$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $STRIPE_WEBHOOK_SECRET);
$valid = false;
foreach ($signatures as $v) {
    if (hash_equals($expected, $v)) { $valid = true; break; }
}
if (!$valid) {
    http_response_code(400);
    exit('invalid signature');
}

$event = json_decode($payload, true);
$type = (string)($event['type'] ?? '');
$object = $event['data']['object'] ?? [];

try {
    if ($type === 'checkout.session.completed') {
        $orderId = (int)($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);
        $paymentId = (string)($object['id'] ?? '');

        if ($orderId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE shop_orders SET status='paid', payment_provider='stripe', payment_id=?, paid_at=NOW()
                  WHERE id=? AND status='pending_payment'"
            );
            $stmt->execute([$paymentId, $orderId]);

            if ($stmt->rowCount() > 0) {
                // Продавцам ещё не были начислены деньги за оплату через Stripe —
                // раньше вебхук только помечал заказ оплаченным и обновлял рейтинг
                // покупателя, а доля продавца никуда не зачислялась. Собственная
                // идемпотентность credit_stripe_order_sellers() (проверка уже
                // существующей записи в wallet_transactions) защищает от двойного
                // начисления, даже если Stripe продублирует вебхук.
                try {
                    credit_stripe_order_sellers($pdo, $orderId);
                } catch (Throwable $creditError) {
                    error_log('Stripe seller credit error: ' . $creditError->getMessage());
                }

                $buyerStmt = $pdo->prepare('SELECT buyer_id FROM shop_orders WHERE id=? LIMIT 1');
                $buyerStmt->execute([$orderId]);
                $buyerId = (int)$buyerStmt->fetchColumn();

                if ($buyerId > 0) {
                    try {
                        refresh_user_rating($pdo, $buyerId);
                    } catch (Throwable $ratingError) {
                        error_log('Stripe rating refresh error: ' . $ratingError->getMessage());
                    }
                }
            }
        }
    } elseif ($type === 'checkout.session.expired') {
        $orderId = (int)($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);
        if ($orderId > 0) {
            $stmt = $pdo->prepare("UPDATE shop_orders SET status='cancelled' WHERE id=? AND status='pending_payment'");
            $stmt->execute([$orderId]);
        }
    }
} catch (Throwable $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    http_response_code(500);
    exit('server error');
}

http_response_code(200);
echo 'ok';
