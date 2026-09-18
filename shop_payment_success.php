<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php'; start_secure_session(); require_once __DIR__ . '/config.php';
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$orderId=(int)($_GET['order'] ?? 0); $status='unknown';
if($orderId>0){$stmt=$pdo->prepare('SELECT status,total FROM shop_orders WHERE id=? AND buyer_id=? LIMIT 1');$stmt->execute([$orderId,(int)$_SESSION['user_id']]);$row=$stmt->fetch();if($row)$status=(string)$row['status'];}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Оплата заказа</title><link rel="icon" href="favicon.ico"><link rel="stylesheet" href="css/style_reg.css"></head><body><main class="card"><h1>Заказ #<?= $orderId ?></h1><?php if($status==='paid'||$status==='completed'): ?><p>Оплата подтверждена. Заказ передан магазину.</p><?php else: ?><p>Платёж отправлен. Подтверждение появится после уведомления платёжного шлюза.</p><?php endif; ?><p><a href="shop_cart.php">Вернуться в магазин</a></p></main></body></html>