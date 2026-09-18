<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
$csrf = csrf_token();
$siteName = get_site_name($pdo);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
require_once __DIR__ . '/modules/shop_functions.php';

$viewerId = !empty($_SESSION['logged_in']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
$view = (string)($_GET['view'] ?? 'cart');
$notice = '';

if ($viewerId > 0) {
    ensure_user_role($pdo, $viewerId, (string)($_SESSION['user_login'] ?? ''));
}

// Обработка POST-действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $viewerId > 0) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $notice = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        $cartInfo = cart_details($pdo);
        if ($cartInfo && $cartInfo['total'] > 0 && can_use_shop($pdo, $viewerId, 'buy_products')) {
            $canOrder = true;
            foreach ($cartInfo['items'] as $item) {
                if ((int)$item['product']['seller_id'] === $viewerId) {
                    $canOrder = false;
                    $notice = 'В корзине есть собственный товар.';
                    break;
                }
            }

            if ($canOrder) {
                try {
                     $orderId = create_pending_order($pdo, $viewerId, $cartInfo);
                    cart_clear();
                    $notice = 'Заказ #' . $orderId . ' отправлен на одобрение. Баланс кошелька пока не списан.';
                } catch (Throwable $e) {
                    error_log('Shop wallet checkout error: ' . $e->getMessage());
                    $notice = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Не удалось оформить заказ. Попробуйте ещё раз.';
                }
            }
        } else {
            $notice = 'Корзина пуста или у вас нет прав на покупку.';
        }
    }
}

// Удаление из корзины
if (($_GET['remove'] ?? 0) > 0) {
    cart_remove((int)$_GET['remove']);
    header('Location: shop_cart.php');
    exit;
}

$cartInfo = cart_details($pdo);
$cartItems = get_cart();
$cartCount = array_sum($cartItems);

// Мои заказы
$orders = [];
$ordersPage = 1;
$ordersTotal = 1;
if ($view === 'orders' && $viewerId > 0) {
    $ordersNum = 15;
    $ordersPage = max(1, (int)($_GET['page'] ?? 1));
    $ordersCountStmt = $pdo->prepare('SELECT COUNT(*) FROM shop_orders WHERE buyer_id = :buyer');
    $ordersCountStmt->execute([':buyer' => $viewerId]);
    $ordersTotal = max(1, (int)ceil((int)$ordersCountStmt->fetchColumn() / $ordersNum));
    $ordersPage = min($ordersPage, $ordersTotal);
    $stmt = $pdo->prepare(
        'SELECT o.*, SUM(oi.quantity) AS items_count
           FROM shop_orders o
           LEFT JOIN shop_order_items oi ON oi.order_id = o.id
          WHERE o.buyer_id = :buyer
          GROUP BY o.id
          ORDER BY o.id DESC
          LIMIT :start, :num'
    );
    $stmt->bindValue(':buyer', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':start', ($ordersPage - 1) * $ordersNum, PDO::PARAM_INT);
    $stmt->bindValue(':num', $ordersNum, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
}

// Мои продажи
$sales = [];
$salesPage = 1;
$salesTotal = 1;
if ($view === 'sales' && $viewerId > 0) {
    $salesNum = 15;
    $salesPage = max(1, (int)($_GET['page'] ?? 1));
    $salesCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM shop_order_items oi JOIN shop_orders o ON o.id = oi.order_id WHERE oi.seller_id = :seller AND o.status = 'paid'"
    );
    $salesCountStmt->execute([':seller' => $viewerId]);
    $salesTotal = max(1, (int)ceil((int)$salesCountStmt->fetchColumn() / $salesNum));
    $salesPage = min($salesPage, $salesTotal);
    $stmt = $pdo->prepare(
        "SELECT oi.*, o.buyer_id, o.created_at AS order_date,
                p.name AS product_name,
                pr.User AS buyer_name
           FROM shop_order_items oi
           JOIN shop_orders o ON o.id = oi.order_id
           LEFT JOIN shop_products p ON p.id = oi.product_id
           LEFT JOIN profiles pr ON pr.Number = o.buyer_id
          WHERE oi.seller_id = :seller
            AND o.status = 'paid'
          ORDER BY o.id DESC
          LIMIT :start, :num"
    );
    $stmt->bindValue(':seller', $viewerId, PDO::PARAM_INT);
    $stmt->bindValue(':start', ($salesPage - 1) * $salesNum, PDO::PARAM_INT);
    $stmt->bindValue(':num', $salesNum, PDO::PARAM_INT);
    $stmt->execute();
    $sales = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> | Магазин</title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
<link rel="stylesheet" type="text/css" href="css/style_shop.css"/>
</head>
<body>
<div id="container" class="shop_cart_page">
<div id="header">
<span class="shop_header_title">🛒 Магазин</span>
<div id="menu">
<?php if ($viewerId > 0): ?>
<a href="profile.php?id=<?= $viewerId ?>">Мой профиль</a>
 / <a href="shop_profile.php?id=<?= $viewerId ?>">Мой магазин</a>
<?php endif; ?>
<?php if ($viewerId > 0 && in_array(get_user_role($pdo, $viewerId), ['admin','moderator'], true)): ?> / <a href="admin.php">Админка</a><?php endif; ?>
 / <a href="index.php">На главную</a>
</div>
</div>

<div id="content" class="shop_cart_content">
<div class="shop_cart_intro">
    <div>
        <span class="shop_cart_kicker">PHOTO VOTE · SHOP</span>
        <h1>Корзина и покупки</h1>
        <p>Управляйте товарами, заказами и продажами в едином интерфейсе.</p>
    </div>
    <div class="shop_cart_icon">🛍️</div>
</div>

<?php if ($viewerId > 0): ?>
<div class="wallet_card shop_cart_wallet">
    <div>
        <span class="wallet_card_label">Баланс кошелька</span>
        <strong class="wallet_card_balance"><?= number_format(get_wallet_balance($pdo, $viewerId), 2, ',', ' ') ?> €</strong>
    </div>
    <span class="wallet_card_hint">Пополнение выполняют администратор или модератор</span>
</div>
<?php endif; ?>

<div class="shop_cart_nav">
<a href="shop_cart.php" class="<?= $view === 'cart' ? 'active' : '' ?>">Корзина<?= $cartCount > 0 ? ' <span class="shop_cart_badge">' . $cartCount . '</span>' : '' ?></a>
<?php if ($viewerId > 0): ?>
<a href="shop_cart.php?view=orders" class="<?= $view === 'orders' ? 'active' : '' ?>">Мои заказы</a>
<a href="shop_cart.php?view=sales" class="<?= $view === 'sales' ? 'active' : '' ?>">Мои продажи</a>
<?php endif; ?>
<a href="shop_profile.php?id=<?= $viewerId ?>">Мой магазин</a>
</div>

<?php if ($notice !== ''): ?>
<div class="shop_notice <?= str_contains($notice, 'успешно') ? 'shop_notice_success' : 'shop_notice_error' ?>">
    <span class="shop_notice_icon"><?= str_contains($notice, 'успешно') ? '✓' : '!' ?></span>
    <span><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></span>
</div>
<?php endif; ?>

<?php if ($viewerId <= 0): ?>
<div class="shop_empty shop_panel_empty">
    <div class="shop_empty_icon">🔐</div>
    <strong>Войдите в аккаунт</strong>
    <span>Для работы с корзиной и заказами необходимо авторизоваться.</span>
    <a class="button" href="login.php">Войти</a>
</div>

<?php elseif ($view === 'orders'): ?>
<div class="shop_section_head">
    <div><span class="shop_section_kicker">ИСТОРИЯ</span><h2>Мои заказы</h2></div>
    <span class="shop_section_count"><?= count($orders) ?> заказ(ов)</span>
</div>
<?php if (!$orders): ?>
<div class="shop_empty shop_panel_empty"><div class="shop_empty_icon">📦</div><strong>Заказов пока нет</strong><span>После покупки здесь появится история ваших заказов.</span></div>
<?php else: ?>
<div class="shop_table_card"><table class="wide_table shop_table">
<thead><tr><th>№ заказа</th><th>Дата</th><th>Товаров</th><th>Сумма</th><th>Статус</th></tr></thead>
<tbody>
<?php foreach ($orders as $order): ?>
<tr>
<td><strong>#<?= (int)$order['id'] ?></strong></td>
<td><?= htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
<td><?= (int)$order['items_count'] ?></td>
<td><strong><?= number_format((float)$order['total'], 2, ',', ' ') ?> €</strong></td>
<td><span class="shop_status"><?php $orderStatus=(string)$order['status']; echo htmlspecialchars(match($orderStatus){'pending_approval'=>'На одобрении','paid'=>'Оплачен','rejected'=>'Отклонён','cancelled'=>'Отменён','pending_payment'=>'Ожидает оплаты',default=>$orderStatus}, ENT_QUOTES, 'UTF-8'); ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($ordersTotal > 1): ?>
<div class="pagination" aria-label="Пагинация заказов">
<?php if($ordersPage > 1): ?><a href="?view=orders&page=<?= $ordersPage - 1 ?>">&lsaquo;</a><?php endif; ?>
<?php for($op=max(1,$ordersPage-2);$op<=min($ordersTotal,$ordersPage+2);$op++): ?><?= $op===$ordersPage?'<span class="current">'.$op.'</span>':'<a href="?view=orders&page='.$op.'">'.$op.'</a>' ?><?php endfor; ?>
<?php if($ordersPage < $ordersTotal): ?><a href="?view=orders&page=<?= $ordersPage + 1 ?>">&rsaquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($view === 'sales'): ?>
<div class="shop_section_head">
    <div><span class="shop_section_kicker">ПРОДАЖИ</span><h2>Мои продажи</h2></div>
    <span class="shop_section_count"><?= count($sales) ?> позиций</span>
</div>
<?php if (!$sales): ?>
<div class="shop_empty shop_panel_empty"><div class="shop_empty_icon">💼</div><strong>Продаж пока нет</strong><span>Проданные товары будут отображаться здесь.</span></div>
<?php else: ?>
<div class="shop_table_card"><table class="wide_table shop_table">
<thead><tr><th>№ заказа</th><th>Товар</th><th>Покупатель</th><th>Кол-во</th><th>Цена</th><th>Дата</th></tr></thead>
<tbody>
<?php foreach ($sales as $sale): ?>
<tr>
<td><strong>#<?= (int)$sale['order_id'] ?></strong></td>
<td><?= htmlspecialchars((string)($sale['product_name'] ?? 'Товар удалён'), ENT_QUOTES, 'UTF-8') ?></td>
<td><a href="profile.php?id=<?= (int)$sale['buyer_id'] ?>"><?= htmlspecialchars((string)($sale['buyer_name'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></a></td>
<td><?= (int)$sale['quantity'] ?></td>
<td><strong><?= number_format((float)$sale['price'], 2, ',', ' ') ?> €</strong></td>
<td><?= htmlspecialchars((string)$sale['order_date'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($salesTotal > 1): ?>
<div class="pagination" aria-label="Пагинация продаж">
<?php if($salesPage > 1): ?><a href="?view=sales&page=<?= $salesPage - 1 ?>">&lsaquo;</a><?php endif; ?>
<?php for($sp=max(1,$salesPage-2);$sp<=min($salesTotal,$salesPage+2);$sp++): ?><?= $sp===$salesPage?'<span class="current">'.$sp.'</span>':'<a href="?view=sales&page='.$sp.'">'.$sp.'</a>' ?><?php endfor; ?>
<?php if($salesPage < $salesTotal): ?><a href="?view=sales&page=<?= $salesPage + 1 ?>">&rsaquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php else: ?>
<div class="shop_section_head">
    <div><span class="shop_section_kicker">ПОКУПКИ</span><h2>Корзина</h2></div>
    <?php if ($cartCount > 0): ?><span class="shop_section_count"><?= $cartCount ?> шт.</span><?php endif; ?>
</div>
<?php if (!$cartInfo['items']): ?>
<div class="shop_empty shop_panel_empty"><div class="shop_empty_icon">🛒</div><strong>Корзина пуста</strong><span>Добавляйте товары из магазинов пользователей, чтобы оформить заказ.</span><a class="button" href="shop_profile.php?id=<?= $viewerId ?>">Перейти в магазин</a></div>
<?php else: ?>
<div class="shop_table_card"><table class="wide_table shop_table shop_cart_table">
<thead><tr><th>Товар</th><th>Продавец</th><th>Цена</th><th>Кол-во</th><th>Сумма</th><th></th></tr></thead>
<tbody>
<?php foreach ($cartInfo['items'] as $item): ?>
<tr>
<td><strong><?= htmlspecialchars((string)$item['product']['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
<td><a href="profile.php?id=<?= (int)$item['product']['seller_id'] ?>"><?= htmlspecialchars((string)($item['product']['seller_name'] ?? 'Продавец'), ENT_QUOTES, 'UTF-8') ?></a></td>
<td><?= number_format((float)$item['product']['price'], 2, ',', ' ') ?> €</td>
<td><?= (int)$item['quantity'] ?></td>
<td><strong><?= number_format((float)$item['sum'], 2, ',', ' ') ?> €</strong></td>
<td><a class="shop_remove_link" href="?remove=<?= (int)$item['product']['id'] ?>" onclick="return confirm('Удалить из корзины?')">Удалить</a></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="4">Итого</td><td colspan="2"><strong><?= number_format((float)$cartInfo['total'], 2, ',', ' ') ?> €</strong></td></tr></tfoot>
</table></div>

<form method="post" action="shop_cart.php" class="shop_checkout_card">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
<?php $walletBalance = get_wallet_balance($pdo, $viewerId); ?>
<div>
    <span class="shop_checkout_kicker">ОПЛАТА</span>
    <strong>Сумма заказа: <?= number_format((float)$cartInfo['total'], 2, ',', ' ') ?> €</strong>
    <span>Баланс кошелька: <?= number_format((float)$walletBalance, 2, ',', ' ') ?> €</span>
    <span class="shop_checkout_hint">Сначала заказ проверит и одобрит модератор или администратор. После одобрения сумма будет списана с кошелька.</span>
</div>
<?php if ($walletBalance >= (float)$cartInfo['total']): ?>
<button type="submit" class="button shop_checkout_button">Отправить покупку на одобрение</button>
<?php else: ?>
<div class="shop_insufficient">
    <strong>Сейчас недостаточно средств</strong>
    <span>Не хватает <?= number_format((float)$cartInfo['total'] - $walletBalance, 2, ',', ' ') ?> €. Заявку всё равно можно отправить, но модератор сможет одобрить её только после пополнения кошелька.</span>
</div>
<button type="submit" class="button shop_checkout_button">Отправить покупку на одобрение</button>
<?php endif; ?>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
</body>
</html>
