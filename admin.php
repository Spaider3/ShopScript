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
require_once __DIR__ . '/modules/messages_functions.php';
require_once __DIR__ . '/modules/bitcoinrpc.php';

$viewerId = current_user_id();
$viewerRole = $viewerId > 0 ? get_user_role($pdo, $viewerId) : 'guest';
$isFullAdmin = $online === 1 || $viewerRole === 'admin';
$isModerator = $viewerRole === 'moderator';
$isPanelUser = $isFullAdmin || $isModerator;

if ($isPanelUser) refresh_all_user_ratings($pdo);

$actionMessage = '';
$actionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action']) && $isPanelUser) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $actionError = 'Сессия устарела. Обновите страницу.';
    } else {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $action = (string)($_POST['admin_action'] ?? '');
        $needsUser = in_array($action, ['set_role', 'ban', 'unban', 'top_up_wallet', 'upload_avatar', 'assign_topup_wallet', 'credit_topup', 'delete_product', 'approve_product', 'reject_product'], true);
        $target = null;
        if ($needsUser) {
            $targetStmt = $pdo->prepare('SELECT Number, User, UserLogin, Photo FROM profiles WHERE Number = ? LIMIT 1');
            $targetStmt->execute([$targetId]);
            $target = $targetStmt->fetch();
        }
        if ($needsUser && !$target) {
            $actionError = 'Пользователь не найден.';
        } elseif ($action === 'set_role') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может менять роли.';
            } elseif ($viewerId > 0 && $targetId === $viewerId) {
                $actionError = 'Нельзя менять собственную роль в этой форме.';
            } else {
                $role = (string)($_POST['role'] ?? 'user');
                if (!in_array($role, ['admin','moderator','user'], true)) {
                    $actionError = 'Недопустимая роль.';
                } else {
                    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
                    $roleStmt->execute([$role]);
                    $roleId = (int)$roleStmt->fetchColumn();
                    if ($roleId <= 0) $actionError = 'Роль не найдена.';
                    else {
                        ensure_user_role($pdo, $targetId, (string)$target['UserLogin']);
                        $stmt = $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?');
                        $stmt->execute([$roleId, $targetId]);
                        $actionMessage = 'Роль пользователя обновлена. Новые права действуют сразу.';
                    }
                }
            }
        } elseif ($action === 'ban' || $action === 'unban') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может блокировать пользователей.';
            } elseif ($viewerId > 0 && $targetId === $viewerId) {
                $actionError = 'Нельзя заблокировать самого себя.';
            } else {
                $banned = $action === 'ban' ? 1 : 0;
                $stmt = $pdo->prepare('UPDATE profiles SET banned = ? WHERE Number = ?');
                $stmt->execute([$banned, $targetId]);
                $actionMessage = $banned ? 'Пользователь заблокирован. Вход запрещён.' : 'Блокировка пользователя снята.';
            }
        } elseif ($action === 'top_up_wallet') {
            $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $amount = $amount === false ? 0.0 : (float)$amount;
            $note = trim((string)($_POST['note'] ?? ''));

            if (!$isPanelUser) {
                $actionError = 'Недостаточно прав для пополнения кошелька.';
            } elseif ($amount <= 0) {
                $actionError = 'Укажите сумму пополнения больше нуля.';
            } else {
                try {
                    $newBalance = top_up_wallet($pdo, $viewerId, $targetId, $amount, $note);
                    $actionMessage = 'Кошелёк пополнен. Новый баланс: ' .
                        number_format($newBalance, 2, ',', ' ') . ' €.';
                } catch (Throwable $e) {
                    $actionError = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Не удалось пополнить кошелёк.';
                }
            }
        } elseif ($action === 'upload_avatar') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может менять аватары из панели.';
            } else {
                $file = $_FILES['avatar'] ?? null;
                if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $actionError = 'Выберите изображение для аватара.';
                } elseif ((int)$file['size'] > 2 * 1024 * 1024) {
                    $actionError = 'Файл слишком большой. Максимум 2 МБ.';
                } else {
                    try {
                        $tmpName = (string)$file['tmp_name'];
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        if (!in_array($mime, $allowed, true) || @getimagesize($tmpName) === false) {
                            throw new RuntimeException('Недопустимый формат изображения.');
                        }
                        $uploadDir = __DIR__ . '/images/upload_profiles';
                        $fileName = save_compressed_image($tmpName, $uploadDir, 'avatar', 210, 210);
                        $relativePath = 'images/upload_profiles/' . $fileName;
                        $oldPhoto = (string)$target['Photo'];
                        $stmt = $pdo->prepare('UPDATE profiles SET Photo = ? WHERE Number = ?');
                        $stmt->execute([$relativePath, $targetId]);
                        $oldFullPath = __DIR__ . '/' . ltrim($oldPhoto, '/');
                        $oldPrefix = __DIR__ . '/images/upload_profiles/';
                        if (is_file($oldFullPath) && str_starts_with($oldFullPath, $oldPrefix) && basename($oldFullPath) !== $fileName) {
                            @unlink($oldFullPath);
                        }
                        $actionMessage = 'Аватар пользователя обновлён.';
                    } catch (Throwable $e) {
                        $actionError = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось обработать изображение.';
                    }
                }
            }
        } elseif ($action === 'assign_topup_wallet') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $walletId = (int)($_POST['wallet_id'] ?? 0);
            try {
                assign_topup_request_wallet($pdo, $viewerId, $requestId, $walletId);
                $actionMessage = 'Реквизиты выбранного кошелька назначены заявке. После подтверждения перевода укажите txid и зачислите сумму.';
            } catch (Throwable $e) {
                $actionError = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось обработать заявку.';
            }
        } elseif ($action === 'credit_topup') {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $txid = trim((string)($_POST['txid'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            try {
                $newBalance = credit_topup_request($pdo, $viewerId, $requestId, $txid, $note);
                $actionMessage = 'Пополнение подтверждено. Новый баланс пользователя: ' . number_format($newBalance, 2, ',', ' ') . ' €.';
            } catch (Throwable $e) {
                $actionError = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось зачислить пополнение.';
            }
        } elseif ($action === 'test_bitcoin_rpc') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может проверять RPC.';
            } else {
                try {
                    $client = bitcoin_rpc_client($pdo);
                    $response = $client->request('getblockchaininfo');
                    $actionMessage = 'Bitcoin RPC отвечает: ' . mb_substr((string)$response->getBody(), 0, 180, 'UTF-8');
                } catch (Throwable $e) {
                    $actionError = 'RPC недоступен: ' . mb_substr($e->getMessage(), 0, 240, 'UTF-8');
                }
            }
        } elseif ($action === 'toggle_module') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может включать и отключать модули.';
            } else {
                $moduleName = (string)($_POST['module_name'] ?? '');
                if ($moduleName !== 'bitcoinrpc') {
                    $actionError = 'Неизвестный модуль.';
                } else {
                    $enabled = (int)($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
                    if ($enabled && ($BITCOIN_RPC_URL === '' || $BITCOIN_RPC_USER === '' || $BITCOIN_RPC_PASS === '')) {
                        $actionError = 'Нельзя включить RPC: задайте BITCOIN_RPC_URL, BITCOIN_RPC_USER и BITCOIN_RPC_PASS.';
                    } else {
                    $stmt = $pdo->prepare('INSERT INTO site_modules (name, enabled) VALUES (:name, :enabled) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)');
                    $stmt->execute([':name' => $moduleName, ':enabled' => $enabled]);
                    $actionMessage = $enabled ? 'Bitcoin RPC модуль включён.' : 'Bitcoin RPC модуль отключён.';
                    }
                }
            }
        } elseif ($action === 'toggle_login_throttle') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может менять защиту от подбора пароля.';
            } else {
                $enabled = (int)($_POST['enabled'] ?? 0) === 1;
                set_login_throttle_enabled($pdo, $enabled);
                $actionMessage = $enabled
                    ? 'Защита от подбора пароля включена.'
                    : 'Защита от подбора пароля отключена. Вход не будет ограничиваться по числу неудачных попыток.';
            }
        } elseif ($action === 'set_admin_credentials') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может менять секретный логин и пароль.';
            } else {
                $newLogin = (string)($_POST['admin_login'] ?? '');
                $newPassword = (string)($_POST['admin_password'] ?? '');
                $confirmPassword = (string)($_POST['admin_password_confirm'] ?? '');
                if ($newPassword !== $confirmPassword) {
                    $actionError = 'Пароли не совпадают.';
                } else {
                    try {
                        set_admin_credentials($pdo, $newLogin, $newPassword);
                        $actionMessage = 'Секретный логин и пароль администратора обновлены.';
                    } catch (RuntimeException $e) {
                        $actionError = $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'set_site_name') {
            if (!$isFullAdmin) {
                $actionError = 'Только администратор может менять название сайта.';
            } else {
                try {
                    set_site_name($pdo, (string)($_POST['site_name'] ?? ''));
                    $actionMessage = 'Название сайта обновлено.';
                } catch (RuntimeException $e) {
                    $actionError = $e->getMessage();
                }
            }
        } elseif ($action === 'approve_order' || $action === 'reject_order') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));
            if ($orderId <= 0) {
                $actionError = 'Заказ не найден.';
            } else {
                try {
                    if ($action === 'approve_order') {
                        approve_shop_order($pdo, $viewerId, $orderId, $note);
                        $actionMessage = 'Заказ #' . $orderId . ' одобрен. Баланс покупателя списан, продавцам начислены средства.';
                    } else {
                        reject_shop_order($pdo, $viewerId, $orderId, $note);
                        $actionMessage = 'Заказ #' . $orderId . ' отклонён. Средства покупателя не списывались.';
                    }
                } catch (Throwable $e) {
                    $actionError = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось обработать заказ.';
                }
            }
        } elseif ($action === 'approve_product' || $action === 'reject_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                $actionError = 'Товар не найден.';
            } else {
                try {
                    $status = $action === 'approve_product' ? 'approved' : 'rejected';
                    moderate_shop_product($pdo, $viewerId, $productId, $status);
                    $actionMessage = $status === 'approved'
                        ? 'Товар одобрен. Теперь его можно оплачивать из корзины.'
                        : 'Товар отклонён и недоступен для покупки.';
                } catch (Throwable $e) {
                    $actionError = $e instanceof RuntimeException ? $e->getMessage() : 'Не удалось изменить статус товара.';
                }
            }
        } elseif ($action === 'delete_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if (!can_approve_shop_orders($pdo, $viewerId)) {
                $actionError = 'Недостаточно прав для управления товарами магазина.';
            } elseif ($productId <= 0) {
                $actionError = 'Товар не найден.';
            } else {
                $stmt = $pdo->prepare('UPDATE shop_products SET is_deleted = 1 WHERE id = ?');
                $stmt->execute([$productId]);
                $actionMessage = 'Товар скрыт из магазина.';
            }
        }
    }
}

// --- Разделы админ-панели ---
$section = (string)($_GET['section'] ?? 'users');
if (!in_array($section, ['topups', 'wallets', 'products', 'orders', 'users'], true)) {
    $section = 'users';
}

// Данные для раздела «Заявки на криптопополнение» и «Кошельки сайта»
$pendingTopups = [];
$cryptoWallets = [];
$bitcoinRpcEnabled = 0;
$loginThrottleEnabled = false;
if ($section === 'topups' || $section === 'wallets') {
    $pendingTopups = get_pending_topup_requests($pdo);
    $cryptoWallets = get_active_crypto_wallets($pdo);
    $moduleStmt = $pdo->prepare("SELECT enabled FROM site_modules WHERE name = 'bitcoinrpc' LIMIT 1");
    $moduleStmt->execute();
    $bitcoinRpcEnabled = (int)$moduleStmt->fetchColumn() === 1;
    $loginThrottleEnabled = is_login_throttle_enabled($pdo);
}

// Данные для раздела «Модерация покупок»
$orderRows = [];
$orderTotal = 1;
$orderPage = 1;
if ($section === 'orders') {
    $orderPage = max(1, (int)($_GET['order_page'] ?? 1));
    $orderNum = 10;
    $orderCountStmt = $pdo->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'pending_approval'");
    $orderRowsCount = (int)$orderCountStmt->fetchColumn();
    $orderTotal = max(1, (int)ceil($orderRowsCount / $orderNum));
    $orderPage = min($orderPage, $orderTotal);
    $orderStart = ($orderPage - 1) * $orderNum;
    $stmt = $pdo->prepare(
        "SELECT o.id, o.buyer_id, o.total, o.status, o.created_at,
                pr.User AS buyer_name, COUNT(oi.id) AS items_count
           FROM shop_orders o
           JOIN profiles pr ON pr.Number = o.buyer_id
           LEFT JOIN shop_order_items oi ON oi.order_id = o.id
          WHERE o.status = 'pending_approval'
          GROUP BY o.id, o.buyer_id, o.total, o.status, o.created_at, pr.User
          ORDER BY o.created_at ASC, o.id ASC
          LIMIT :start, :num"
    );
    $stmt->bindValue(':start', $orderStart, PDO::PARAM_INT);
    $stmt->bindValue(':num', $orderNum, PDO::PARAM_INT);
    $stmt->execute();
    $orderRows = $stmt->fetchAll();
}

// Данные для раздела «Модерация товаров»
$productRows = [];
$productTotal = 1;
$productPage = 1;
if ($section === 'products') {
    $productPage = max(1, (int)($_GET['product_page'] ?? 1));
    $productNum = 10;
    $productCountStmt = $pdo->query("SELECT COUNT(*) FROM shop_products sp JOIN profiles pr ON pr.Number=sp.seller_id WHERE sp.is_deleted=0");
    $productRowsCount = (int)$productCountStmt->fetchColumn();
    $productTotal = max(1, (int)ceil($productRowsCount / $productNum));
    $productPage = min($productPage, $productTotal);
    $productStart = ($productPage - 1) * $productNum;
    $productStmt = $pdo->prepare(
        "SELECT sp.id, sp.name, sp.price, sp.created_at, sp.moderation_status, sp.moderation_note,
                pr.Number AS seller_id, pr.User AS seller_name,
                COALESCE(SUM(CASE WHEN so.status = 'paid' THEN oi.quantity ELSE 0 END), 0) AS purchases
           FROM shop_products sp
           JOIN profiles pr ON pr.Number = sp.seller_id
           LEFT JOIN shop_order_items oi ON oi.product_id = sp.id
           LEFT JOIN shop_orders so ON so.id = oi.order_id
          WHERE sp.is_deleted = 0
          GROUP BY sp.id, sp.name, sp.price, sp.created_at, sp.moderation_status, sp.moderation_note, pr.Number, pr.User
          ORDER BY sp.created_at DESC, sp.id DESC
          LIMIT :start, :num"
    );
    $productStmt->bindValue(':start', $productStart, PDO::PARAM_INT);
    $productStmt->bindValue(':num', $productNum, PDO::PARAM_INT);
    $productStmt->execute();
    $productRows = $productStmt->fetchAll();
}

// Данные для раздела «Поиск и редактирование пользователей»
$rows = [];
$total = 1;
$page = 1;
$searchQuery = '';
if ($section === 'users') {
    $searchQuery = trim((string)($_GET['search'] ?? ''));
    if (mb_strlen($searchQuery, 'UTF-8') > 100) $searchQuery = mb_substr($searchQuery, 0, 100, 'UTF-8');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $num = 10;
    $where = $searchQuery !== '' ? ' WHERE p.User LIKE :search_name OR p.UserLogin LIKE :search_login ' : '';
    $params = $searchQuery !== '' ? [
        ':search_name' => '%' . $searchQuery . '%',
        ':search_login' => '%' . $searchQuery . '%'
    ] : [];
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM profiles p' . $where);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $total = max(1, (int)ceil($totalRows / $num));
    $page = min($page, $total);
    $start = ($page - 1) * $num;
    $stmt = $pdo->prepare('SELECT p.*, COALESCE(r.name,\'user\') AS role_name FROM profiles p LEFT JOIN users u ON u.id=p.Number LEFT JOIN roles r ON r.id=u.role_id' . $where . ' ORDER BY p.Rating DESC, p.Number ASC LIMIT :start,:num');
    foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
    $stmt->bindValue(':start',$start,PDO::PARAM_INT); $stmt->bindValue(':num',$num,PDO::PARAM_INT); $stmt->execute();
    $rows=$stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru"><head>
<link rel="icon" href="favicon.ico" type="image/x-icon"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= htmlspecialchars($siteName,ENT_QUOTES,'UTF-8') ?> | Панель управления</title><link rel="stylesheet" href="css/style_admin.css"></head>
<body><div id="container"><div id="header"><?= htmlspecialchars($siteName,ENT_QUOTES,'UTF-8') ?> <div id="menu"><a href="index.php">На главную</a> / <a href="shop_cart.php">Магазин</a><?php if ($viewerId > 0): ?> / <a href="profile.php?id=<?= (int)$viewerId ?>">Мой профиль</a><?php endif; ?><?php if ($isPanelUser): ?> /
<form method="post" class="admin_logout_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<button type="submit" name="admin_logout" value="1">Выход из админ-панели</button>
</form>
<?php endif; ?></div></div>
<?php if (!$isPanelUser): ?>
<div id="login_box"><h2>Вход для администратора</h2><p class="panel_note">Администратор входит отдельной учётной записью. Модератор — через обычный аккаунт.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><div class="field"><label>Логин</label><input type="text" name="login" maxlength="50" required></div><div class="field"><label>Пароль</label><input type="password" name="password" required></div><button type="submit" name="submit" value="1" class="button">Войти</button></form></div>
<?php else: ?>
<div id="content"><h1 class="page_title">Панель управления — <?= $isFullAdmin ? 'Admin' : 'Moderator' ?></h1>
<?php if ($actionMessage): ?><div class="admin_notice success"><?= htmlspecialchars($actionMessage,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if ($actionError): ?><div class="admin_notice error"><?= htmlspecialchars($actionError,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

<nav class="admin_tabs">
<a href="?section=topups" class="admin_tab<?= $section === 'topups' ? ' active' : '' ?>">Криптопополнения</a>
<a href="?section=wallets" class="admin_tab<?= $section === 'wallets' ? ' active' : '' ?>">Кошельки сайта</a>
<a href="?section=products" class="admin_tab<?= $section === 'products' ? ' active' : '' ?>">Модерация товаров</a>
<a href="?section=orders" class="admin_tab<?= $section === 'orders' ? ' active' : '' ?>">Модерация покупок</a>
<a href="?section=users" class="admin_tab<?= $section === 'users' ? ' active' : '' ?>">Пользователи</a>
</nav>

<?php if ($section === 'topups'): ?>
<section class="admin_card">
<h2 class="section_subtitle">Заявки на криптопополнение</h2>
<p class="panel_note">Сначала выберите адрес из базы сайта и отправьте его пользователю. Баланс не зачисляется автоматически: после проверки blockchain-транзакции укажите txid и подтвердите зачисление.</p>
<div class="table_wrap"><table class="admin_table crypto_admin_table"><thead><tr><th>Заявка</th><th>Пользователь</th><th>Сумма</th><th>Валюта</th><th>Кошелёк</th><th>Действие</th></tr></thead><tbody>
<?php if (!$pendingTopups): ?><tr><td colspan="6" class="empty_row">Активных заявок нет.</td></tr><?php endif; ?>
<?php foreach ($pendingTopups as $request): ?>
<tr>
<td>#<?= (int)$request['id'] ?><br><small><?= htmlspecialchars((string)$request['created_at'], ENT_QUOTES, 'UTF-8') ?></small></td>
<td><a href="profile.php?id=<?= (int)$request['user_id'] ?>"><?= htmlspecialchars((string)$request['user_name'], ENT_QUOTES, 'UTF-8') ?></a><br><small>ID <?= (int)$request['user_id'] ?></small></td>
<td><?= number_format((float)$request['amount_eur'], 2, ',', ' ') ?> €</td>
<td><strong><?= htmlspecialchars((string)$request['currency'], ENT_QUOTES, 'UTF-8') ?></strong></td>
<td>
<?php if ($request['status'] === 'pending'): ?>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="assign_topup_wallet">
<input type="hidden" name="user_id" value="<?= (int)$request['user_id'] ?>">
<input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
<select name="wallet_id" required>
<option value="">Выберите адрес</option>
<?php foreach ($cryptoWallets as $wallet): if ($wallet['currency'] !== $request['currency']) continue; ?>
<option value="<?= (int)$wallet['id'] ?>"><?= htmlspecialchars((string)$wallet['label'],ENT_QUOTES,'UTF-8') ?> — <?= htmlspecialchars((string)$wallet['address'],ENT_QUOTES,'UTF-8') ?><?php if (!empty($wallet['tag'])): ?> (tag: <?= htmlspecialchars((string)$wallet['tag'],ENT_QUOTES,'UTF-8') ?>)<?php endif; ?></option>
<?php endforeach; ?>
</select>
<button class="small_button" type="submit">Назначить</button>
</form>
<?php else: ?>
<div class="wallet_address_admin"><?= htmlspecialchars((string)($request['wallet_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br><code><?= htmlspecialchars((string)($request['wallet_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code><?php if (!empty($request['wallet_tag'])): ?><br><span class="wallet_tag_admin">Tag: <?= htmlspecialchars((string)$request['wallet_tag'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div>
<?php endif; ?>
</td>
<td>
<?php if ($request['status'] === 'wallet_sent'): ?>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="credit_topup">
<input type="hidden" name="user_id" value="<?= (int)$request['user_id'] ?>">
<input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
<input class="txid_input" type="text" name="txid" maxlength="255" placeholder="TXID" required>
<input class="wallet_topup_note" type="text" name="note" maxlength="255" placeholder="Комментарий">
<button class="small_button" type="submit">Зачислить</button>
</form>
<?php else: ?>
<span class="status_pending">Ожидает адреса</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<?php endif; ?>

<?php if ($section === 'wallets'): ?>
<section class="admin_card">
<h2 class="section_subtitle">Криптокошельки сайта</h2>
<div class="table_wrap"><table class="admin_table crypto_wallet_table"><thead><tr><th>Валюта</th><th>Название</th><th>Адрес</th><th>Tag</th><th>Статус</th></tr></thead><tbody>
<?php if (!$cryptoWallets): ?><tr><td colspan="5" class="empty_row">В базе нет активных криптокошельков. Добавьте их через SQL/панель БД.</td></tr><?php endif; ?>
<?php foreach ($cryptoWallets as $wallet): ?>
<tr><td><strong><?= htmlspecialchars((string)$wallet['currency'],ENT_QUOTES,'UTF-8') ?></strong></td><td><?= htmlspecialchars((string)$wallet['label'],ENT_QUOTES,'UTF-8') ?></td><td><code class="wallet_address_code"><?= htmlspecialchars((string)$wallet['address'],ENT_QUOTES,'UTF-8') ?></code></td><td><?= $wallet['tag'] !== null && $wallet['tag'] !== '' ? '<code class="wallet_address_code">' . htmlspecialchars((string)$wallet['tag'],ENT_QUOTES,'UTF-8') . '</code>' : '—' ?></td><td>Активен</td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="admin_card">
<h2 class="section_subtitle">Модули</h2>
<form method="post" class="module_toggle_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="toggle_module">
<input type="hidden" name="module_name" value="bitcoinrpc">
<strong>php-bitcoinrpc-2.2.x</strong>
<span class="module_status <?= $bitcoinRpcEnabled ? 'module_on' : 'module_off' ?>"><?= $bitcoinRpcEnabled ? 'Включён' : 'Выключен' ?></span>
<?php if ($isFullAdmin): ?><button class="small_button" type="submit" name="enabled" value="<?= $bitcoinRpcEnabled ? '0' : '1' ?>"><?= $bitcoinRpcEnabled ? 'Отключить' : 'Включить' ?></button><?php endif; ?>
<?php if ($isFullAdmin && $bitcoinRpcEnabled): ?>
<button type="submit" class="small_button" onclick="this.form.querySelector('input[name=admin_action]').value='test_bitcoin_rpc';">Проверить RPC</button>
<?php endif; ?>
</form>
<p class="panel_note">RPC-подключение не хранит пароль в БД: задайте BITCOIN_RPC_URL, BITCOIN_RPC_USER и BITCOIN_RPC_PASS в окружении. Для работы пакета нужен Composer autoload.</p>
</section>

<section class="admin_card">
<h2 class="section_subtitle">Защита от подбора пароля</h2>
<form method="post" class="module_toggle_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="toggle_login_throttle">
<strong>Ограничение попыток входа</strong>
<span class="module_status <?= $loginThrottleEnabled ? 'module_on' : 'module_off' ?>"><?= $loginThrottleEnabled ? 'Включена' : 'Отключена' ?></span>
<?php if ($isFullAdmin): ?><button class="small_button" type="submit" name="enabled" value="<?= $loginThrottleEnabled ? '0' : '1' ?>"><?= $loginThrottleEnabled ? 'Отключить' : 'Включить' ?></button><?php endif; ?>
</form>
<p class="panel_note">Блокирует вход после 5 неудачных попыток за 15 минут (по логину и по IP) — и для обычных пользователей, и для секретного входа администратора. По умолчанию выключена; включение защищает от подбора пароля, но также ограничивает частые повторные попытки входа.</p>
</section>

<section class="admin_card">
<h2 class="section_subtitle">Название сайта</h2>
<form method="post" class="site_name_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="set_site_name">
<input type="text" name="site_name" class="site_name_input" maxlength="60" value="<?= htmlspecialchars($siteName,ENT_QUOTES,'UTF-8') ?>" <?= $isFullAdmin ? '' : 'disabled' ?> placeholder="Название сайта">
<?php if ($isFullAdmin): ?><button class="small_button" type="submit">Сохранить</button><?php endif; ?>
</form>
<p class="panel_note">Отображается в заголовках страниц и в шапке сайта.</p>
</section>
<?php endif; ?>

<?php if ($section === 'orders'): ?>
<section class="admin_card">
<h2 class="section_subtitle">Покупки, ожидающие одобрения</h2>
<p class="panel_note">Пока заказ не одобрен, деньги с кошелька покупателя не списываются. После одобрения одной транзакцией списывается сумма у покупателя и начисляется продавцам.</p>
<div class="table_wrap"><table class="admin_table"><thead><tr><th>Заказ</th><th>Покупатель</th><th>Товаров</th><th>Сумма</th><th>Действие</th></tr></thead><tbody>
<?php if (!$orderRows): ?><tr><td colspan="5" class="empty_row">Заказов на модерации нет.</td></tr><?php endif; ?>
<?php foreach ($orderRows as $order): ?>
<tr>
<td><strong>#<?= (int)$order['id'] ?></strong><br><small><?= htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8') ?></small></td>
<td><a href="profile.php?id=<?= (int)$order['buyer_id'] ?>"><?= htmlspecialchars((string)$order['buyer_name'], ENT_QUOTES, 'UTF-8') ?></a><br><small>ID <?= (int)$order['buyer_id'] ?></small></td>
<td><?= (int)$order['items_count'] ?></td>
<td><strong><?= number_format((float)$order['total'], 2, ',', ' ') ?> €</strong></td>
<td>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="admin_action" value="approve_order">
<input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
<input type="text" name="note" maxlength="255" placeholder="Комментарий (необязательно)">
<button class="small_button" type="submit">Одобрить и списать</button>
</form>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="admin_action" value="reject_order">
<input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
<input type="text" name="note" maxlength="255" placeholder="Причина отклонения">
<button class="small_button" type="submit">Отклонить</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="pagination" aria-label="Пагинация модерации покупок">
<?php if($orderPage > 1): ?><a href="?section=orders&order_page=<?= $orderPage - 1 ?>">&lsaquo;</a><?php endif; ?>
<?php for($op=max(1,$orderPage-2);$op<=min($orderTotal,$orderPage+2);$op++): ?><?= $op===$orderPage?'<span class="current">'.$op.'</span>':'<a href="?section=orders&order_page='.$op.'">'.$op.'</a>' ?><?php endfor; ?>
<?php if($orderPage < $orderTotal): ?><a href="?section=orders&order_page=<?= $orderPage + 1 ?>">&rsaquo;</a><?php endif; ?>
</div>
</section>
<?php endif; ?>

<?php if ($section === 'products'): ?>
<h2 class="section_subtitle">Модерация товаров</h2>
<p class="panel_note">Новый товар сначала получает статус «На модерации». Оплатить товар из корзины можно только после одобрения модератором или администратором.</p>
<div class="table_wrap"><table class="admin_table"><thead><tr><th>Товар</th><th>Цена</th><th>Статус</th><th>Покупки</th><th>Продавец</th><th>Дата</th><th>Действие</th></tr></thead><tbody>
<?php if (!$productRows): ?><tr><td colspan="7" class="empty_row">Товаров нет.</td></tr><?php endif; ?>
<?php foreach($productRows as $product): ?>
<?php $productStatus = (string)($product['moderation_status'] ?? 'pending'); ?>
<tr>
<td><?= htmlspecialchars((string)$product['name'],ENT_QUOTES,'UTF-8') ?><?php if (!empty($product['moderation_note'])): ?><br><small><?= htmlspecialchars((string)$product['moderation_note'],ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></td>
<td><?= number_format((float)$product['price'],2,',',' ') ?> €</td>
<td><span class="status_<?= htmlspecialchars($productStatus,ENT_QUOTES,'UTF-8') ?>"><?= $productStatus === 'approved' ? 'Одобрен' : ($productStatus === 'rejected' ? 'Отклонён' : 'На модерации') ?></span></td>
<td><?= (int)$product['purchases'] ?></td>
<td><a href="profile.php?id=<?= (int)$product['seller_id'] ?>"><?= htmlspecialchars((string)$product['seller_name'],ENT_QUOTES,'UTF-8') ?></a></td>
<td><?= htmlspecialchars((string)$product['created_at'],ENT_QUOTES,'UTF-8') ?></td>
<td>
<?php if ($productStatus !== 'approved'): ?>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="approve_product">
<input type="hidden" name="user_id" value="<?= (int)$product['seller_id'] ?>">
<input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
<button class="small_button" type="submit">Одобрить</button>
</form>
<?php endif; ?>
<?php if ($productStatus !== 'rejected'): ?>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="reject_product">
<input type="hidden" name="user_id" value="<?= (int)$product['seller_id'] ?>">
<input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
<button class="small_button danger_button" type="submit">Отклонить</button>
</form>
<?php endif; ?>
<form method="post" class="admin_inline_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="delete_product">
<input type="hidden" name="user_id" value="<?= (int)$product['seller_id'] ?>">
<input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
<button class="small_button danger_button" type="submit" onclick="return confirm('Скрыть товар?')">Скрыть</button>
</form>
</td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="pagination" aria-label="Пагинация модерации товаров">
<?php if($productPage > 1): ?><a href="?section=products&product_page=<?= $productPage - 1 ?>">&lsaquo;</a><?php endif; ?>
<?php for($pp=max(1,$productPage-2);$pp<=min($productTotal,$productPage+2);$pp++): ?><?= $pp===$productPage?'<span class="current">'.$pp.'</span>':'<a href="?section=products&product_page='.$pp.'">'.$pp.'</a>' ?><?php endfor; ?>
<?php if($productPage < $productTotal): ?><a href="?section=products&product_page=<?= $productPage + 1 ?>">&rsaquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($section === 'users'): ?>
<?php if ($isFullAdmin): ?>
<section class="admin_card">
<h2 class="section_subtitle">Секретный вход администратора</h2>
<p class="panel_note">Отдельная пара логин/пароль для входа на эту страницу — независимо от роли и обычного аккаунта. Пока не задана здесь, действует резервная пара из переменных окружения ADMIN_NAME/ADMIN_PASS.</p>
<form method="post" class="site_name_form" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="set_admin_credentials">
<input type="text" name="admin_login" class="site_name_input" maxlength="50" placeholder="Новый секретный логин" autocomplete="new-password" required>
<input type="password" name="admin_password" class="site_name_input" minlength="10" placeholder="Новый пароль (мин. 10 символов)" autocomplete="new-password" required>
<input type="password" name="admin_password_confirm" class="site_name_input" minlength="10" placeholder="Повторите пароль" autocomplete="new-password" required>
<button class="small_button" type="submit">Сохранить</button>
</form>
</section>
<?php endif; ?>
<form class="site_search_form" method="get">
<input type="hidden" name="section" value="users">
<input class="site_search_input" type="text" name="search" value="<?= htmlspecialchars($searchQuery,ENT_QUOTES,'UTF-8') ?>" placeholder="Поиск по имени или логину"><button class="button site_search_button">Найти</button></form>
<div class="table_wrap"><table class="admin_table"><thead><tr><th>№</th><th>Пользователь</th><th>Фото</th><th>Покупок</th><th>Баланс</th><th>Роль</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty_row">Пользователи не найдены.</td></tr><?php endif; ?>
<?php foreach($rows as $row): $pid=(int)$row['Number']; $name=htmlspecialchars((string)$row['User'],ENT_QUOTES,'UTF-8'); $photo=htmlspecialchars(resolve_avatar((string)$row['Photo']),ENT_QUOTES,'UTF-8'); $rating=(int)$row['Rating']; $banned=(int)$row['banned']===1; ?>
<tr><td><?= $pid ?></td><td><a href="profile.php?id=<?= $pid ?>"><?= $name ?></a><br><small><?= htmlspecialchars((string)$row['UserLogin'],ENT_QUOTES,'UTF-8') ?></small></td><td>
<img class="table_photo" id="avatar-preview-<?= $pid ?>" src="<?= $photo ?>" alt="">
<?php if ($isFullAdmin): ?>
<form method="post" enctype="multipart/form-data" class="admin_avatar_form" data-avatar-form>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="upload_avatar">
<input type="hidden" name="user_id" value="<?= $pid ?>">
<label class="avatar_file_button" for="avatar-<?= $pid ?>">Выбрать</label>
<input class="admin_avatar_input" id="avatar-<?= $pid ?>" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif">
<button class="small_button" type="submit">Сохранить</button>
</form>
<?php endif; ?>
</td><td class="rating_cell"><?= $rating ?>%</td><td><?= number_format((float)($row['wallet_balance'] ?? 0), 2, ',', ' ') ?> €</td><td><span class="role_badge role_<?= htmlspecialchars((string)$row['role_name'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars((string)$row['role_name'],ENT_QUOTES,'UTF-8') ?></span></td><td><?= $banned ? '<strong class="ban_text">Заблокирован</strong>' : 'Активен' ?></td><td class="action_cell">
<?php if ($isFullAdmin && $viewerId !== $pid): ?><form method="post" class="admin_inline_form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="admin_action" value="set_role"><input type="hidden" name="user_id" value="<?= $pid ?>"><select name="role"><option value="user" <?= $row['role_name']==='user'?'selected':'' ?>>user</option><option value="moderator" <?= $row['role_name']==='moderator'?'selected':'' ?>>moderator</option><option value="admin" <?= $row['role_name']==='admin'?'selected':'' ?>>admin</option></select><button class="small_button" type="submit">Сохранить</button></form><?php endif; ?>
<?php if ($isPanelUser && !($viewerId>0 && $viewerId===$pid)): ?><form method="post" class="admin_inline_form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="admin_action" value="<?= $banned?'unban':'ban' ?>"><input type="hidden" name="user_id" value="<?= $pid ?>"><button class="small_button <?= $banned?'':'danger_button' ?>" type="submit"><?= $banned?'Разбанить':'Забанить' ?></button></form><?php endif; ?>
<?php if ($isPanelUser): ?>
<form method="post" class="admin_inline_form wallet_topup_form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="admin_action" value="top_up_wallet">
<input type="hidden" name="user_id" value="<?= $pid ?>">
<input class="wallet_topup_amount" type="number" name="amount" min="0.01" max="1000000" step="0.01" placeholder="€" required>
<input class="wallet_topup_note" type="text" name="note" maxlength="255" placeholder="Комментарий">
<button class="small_button" type="submit">Пополнить</button>
</form>
<?php endif; ?>
<?php if ($isFullAdmin): ?><a class="edit_link" href="edit.php?num=<?= $pid ?>" data-edit-trigger data-edit-num="<?= $pid ?>">Редактировать</a><?php endif; ?>
</td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="pagination"><?php if($page>1): ?><a href="?section=users&page=<?= $page-1 ?>&search=<?= urlencode($searchQuery) ?>">&lsaquo;</a><?php endif; ?><?php for($p=max(1,$page-2);$p<=min($total,$page+2);$p++): ?><?= $p===$page?'<span class="current">'.$p.'</span>':'<a href="?section=users&page='.$p.'&search='.urlencode($searchQuery).'">'.$p.'</a>' ?><?php endfor; ?><?php if($page<$total): ?><a href="?section=users&page=<?= $page+1 ?>&search=<?= urlencode($searchQuery) ?>">&rsaquo;</a><?php endif; ?></div>
<?php if ($isFullAdmin): ?><p class="panel_note">Admin имеет полный доступ, включая назначение ролей и редактирование профилей. Moderator может управлять контентом и банить пользователей, но не меняет роли.</p><?php endif; ?>
<?php endif; ?>
</div>
<?php endif; ?><div id="footer">&copy; Mr.Green</div></div>

<!-- AJAX-окно редактирования профиля -->
<div id="edit_modal_overlay" class="edit_modal_overlay" onclick="if (event.target === this) closeEditModal()">
<div id="edit_modal" class="edit_modal" onclick="event.stopPropagation()">
<div class="edit_modal_header">
<span id="edit_modal_title">Редактирование записи</span>
<button type="button" class="edit_modal_close" onclick="closeEditModal()" aria-label="Закрыть">&times;</button>
</div>
<div class="edit_modal_body" id="edit_modal_body">
<div class="edit_modal_empty">Загрузка…</div>
</div>
</div>
</div>

<script type="text/javascript">
(function () {
    var overlay = document.getElementById('edit_modal_overlay');
    var body = document.getElementById('edit_modal_body');
    var title = document.getElementById('edit_modal_title');

    function openEditModal(num) {
        title.textContent = 'Редактирование записи №' + num;
        body.innerHTML = '<div class="edit_modal_empty">Загрузка…</div>';
        overlay.classList.add('edit_modal_open');
        document.body.style.overflow = 'hidden';

        fetch('edit.php?num=' + encodeURIComponent(num) + '&ajax=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                body.innerHTML = html;
                bindForm();
            })
            .catch(function () {
                body.innerHTML = '<div class="edit_modal_empty">Не удалось загрузить форму. Попробуйте ещё раз.</div>';
            });
    }

    window.closeEditModal = function () {
        overlay.classList.remove('edit_modal_open');
        document.body.style.overflow = '';
    };

    function bindForm() {
        var form = body.querySelector('[data-ajax-edit]');
        if (!form) return;

        var avatarInput = form.querySelector('.edit_avatar_input');
        var avatarImg = form.querySelector('#edit-avatar-img');
        var avatarName = form.querySelector('#edit-avatar-file-name');
        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                var file = avatarInput.files && avatarInput.files[0];
                if (!file) return;
                if (!/^image\//.test(file.type)) {
                    if (avatarName) avatarName.textContent = 'Недопустимый файл';
                    return;
                }
                if (avatarName) avatarName.textContent = file.name;
                if (avatarImg) avatarImg.src = URL.createObjectURL(file);
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var errorBox = form.querySelector('[data-ajax-edit-error]');
            var submitBtn = form.querySelector('button[type="submit"]');
            if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Сохранение…'; }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok) {
                        closeEditModal();
                        location.reload();
                    } else {
                        if (errorBox) {
                            errorBox.textContent = result.data.error || 'Не удалось сохранить изменения.';
                            errorBox.hidden = false;
                        }
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Сохранить'; }
                    }
                })
                .catch(function () {
                    if (errorBox) {
                        errorBox.textContent = 'Ошибка соединения. Попробуйте ещё раз.';
                        errorBox.hidden = false;
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Сохранить'; }
                });
        });
    }

    document.querySelectorAll('[data-avatar-form]').forEach(function (form) {
        var input = form.querySelector('.admin_avatar_input');
        if (!input) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file || !file.type.match(/^image\//)) return;
            var previewId = 'avatar-preview-' + form.querySelector('input[name="user_id"]').value;
            var preview = document.getElementById(previewId);
            if (preview) preview.src = URL.createObjectURL(file);
        });
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-edit-trigger]');
        if (!trigger) return;
        event.preventDefault();
        openEditModal(trigger.getAttribute('data-edit-num'));
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.classList.contains('edit_modal_open')) {
            closeEditModal();
        }
    });
})();
</script>
</body></html>
