<?php
declare(strict_types=1);

function get_user_role(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $role = $stmt->fetchColumn();
    return $role !== false ? (string)$role : 'user';
}

function user_has_permission(PDO $pdo, int $userId, string $permission): bool
{
    static $cache = [];
    if ($userId <= 0) return false;
    if (!isset($cache[$userId])) {
        $stmt = $pdo->prepare(
            'SELECT p.name FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $cache[$userId] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    return in_array($permission, $cache[$userId], true);
}

function get_user_permissions(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT p.name FROM users u
         JOIN role_permissions rp ON rp.role_id = u.role_id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE u.id = :id'
    );
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Возвращает текущий баланс внутреннего кошелька пользователя.
 * Баланс хранится в евро, как и цены магазина.
 */
function get_wallet_balance(PDO $pdo, int $userId): float
{
    if ($userId <= 0) return 0.0;

    $stmt = $pdo->prepare('SELECT wallet_balance FROM profiles WHERE Number = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $value = $stmt->fetchColumn();

    return $value === false ? 0.0 : (float)$value;
}

/**
 * Пополнение кошелька администратором/модератором с записью в журнал.
 */
function top_up_wallet(PDO $pdo, int $actorId, int $userId, float $amount, string $note = ''): float
{
    if ($userId <= 0) {
        throw new RuntimeException('Некорректный пользователь.');
    }

    $isSeparateAdmin = $actorId <= 0 && !empty($_SESSION['admin_logged_in']);
    $actorRole = $actorId > 0 ? get_user_role($pdo, $actorId) : ($isSeparateAdmin ? 'admin' : 'guest');
    if (!in_array($actorRole, ['admin', 'moderator'], true)) {
        throw new RuntimeException('Недостаточно прав для пополнения кошелька.');
    }

    $amount = round($amount, 2);
    if ($amount <= 0 || $amount > 1000000) {
        throw new RuntimeException('Сумма пополнения должна быть от 0,01 до 1 000 000,00 €.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT wallet_balance FROM profiles WHERE Number = :id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':id' => $userId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            throw new RuntimeException('Пользователь не найден.');
        }

        $newBalance = round((float)$current + $amount, 2);

        $stmt = $pdo->prepare(
            'UPDATE profiles SET wallet_balance = :balance WHERE Number = :id'
        );
        $stmt->execute([':balance' => $newBalance, ':id' => $userId]);

        $stmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
                (user_id, actor_id, amount, balance_after, type, note)
             VALUES (:user_id, :actor_id, :amount, :balance_after, :type, :note)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':actor_id' => $actorId > 0 ? $actorId : null,
            ':amount' => $amount,
            ':balance_after' => $newBalance,
            ':type' => 'top_up',
            ':note' => $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
        ]);

        $pdo->commit();
        return $newBalance;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Оплата корзины внутренним балансом.
 *
 * В одной транзакции:
 * - повторно проверяет цены и наличие товаров;
 * - блокирует балансы всех участников;
 * - не допускает отрицательного баланса покупателя;
 * - создаёт оплаченный заказ;
 * - списывает деньги у покупателя и начисляет продавцам;
 * - пишет полный журнал операций.
 */
function can_approve_shop_orders(PDO $pdo, int $actorId): bool
{
    if ($actorId > 0 && !user_is_banned($pdo, $actorId) && user_has_permission($pdo, $actorId, 'moderate_shop')) {
        return true;
    }
    return $actorId <= 0 && !empty($_SESSION['admin_logged_in']);
}

/**
 * Может ли пользователь покупать/продавать товары: должен иметь
 * соответствующее право по роли и не быть заблокированным.
 */
function can_use_shop(PDO $pdo, int $userId, string $permission): bool
{
    if ($userId <= 0 || user_is_banned($pdo, $userId)) {
        return false;
    }
    return user_has_permission($pdo, $userId, $permission);
}

/**
 * Одобряет/отклоняет товар на модерации. Права те же, что и для одобрения
 * покупок (moderate_shop / admin-сессия).
 */
function moderate_shop_product(PDO $pdo, int $actorId, int $productId, string $status): void
{
    if (!can_approve_shop_orders($pdo, $actorId)) {
        throw new RuntimeException('Недостаточно прав для модерации товаров.');
    }
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Недопустимый статус модерации.');
    }
    if ($productId <= 0) {
        throw new RuntimeException('Товар не найден.');
    }

    $stmt = $pdo->prepare(
        'UPDATE shop_products
            SET moderation_status = :status, moderated_by = :actor, moderated_at = NOW()
          WHERE id = :id AND is_deleted = 0'
    );
    $stmt->execute([
        ':status' => $status,
        ':actor' => $actorId > 0 ? $actorId : null,
        ':id' => $productId,
    ]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Товар не найден или уже скрыт.');
    }
}

/**
 * Создаёт заявку на криптопополнение внутреннего кошелька. Реальное
 * зачисление происходит только после того, как администратор/модератор
 * подтвердит транзакцию в панели (см. credit_topup_request()).
 */
function create_topup_request(PDO $pdo, int $userId, float $amountEur, string $currency): int
{
    if ($userId <= 0) {
        throw new RuntimeException('Некорректный пользователь.');
    }

    $amountEur = round($amountEur, 2);
    if ($amountEur <= 0 || $amountEur > 1000000) {
        throw new RuntimeException('Сумма пополнения должна быть от 0,01 до 1 000 000,00 €.');
    }

    $currency = strtoupper(trim($currency));
    $allowedStmt = $pdo->prepare('SELECT 1 FROM crypto_wallets WHERE currency = :currency AND is_active = 1 LIMIT 1');
    $allowedStmt->execute([':currency' => $currency]);
    if ($allowedStmt->fetchColumn() === false) {
        throw new RuntimeException('Пополнение в этой валюте сейчас недоступно.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO topup_requests (user_id, amount_eur, currency, status)
         VALUES (:user_id, :amount, :currency, 'pending')"
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':amount' => $amountEur,
        ':currency' => $currency,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Сохраняет отзыв покупателя на товар. Разрешено только тем, у кого есть
 * оплаченный заказ с этим товаром, и только один отзыв на товар (см.
 * uniq_product_reviews_product_buyer).
 */
function add_product_review(PDO $pdo, int $buyerId, int $productId, int $rating, string $review): void
{
    if ($buyerId <= 0 || $productId <= 0) {
        throw new RuntimeException('Некорректные данные отзыва.');
    }
    if ($rating < 1 || $rating > 5) {
        throw new RuntimeException('Оценка должна быть от 1 до 5.');
    }

    $paidStmt = $pdo->prepare(
        "SELECT 1
           FROM shop_order_items oi
           JOIN shop_orders o ON o.id = oi.order_id
          WHERE oi.product_id = :product_id AND o.buyer_id = :buyer_id AND o.status = 'paid'
          LIMIT 1"
    );
    $paidStmt->execute([':product_id' => $productId, ':buyer_id' => $buyerId]);
    if ($paidStmt->fetchColumn() === false) {
        throw new RuntimeException('Отзыв можно оставить только на оплаченный товар.');
    }

    $review = trim($review);
    if (mb_strlen($review, 'UTF-8') > 1000) {
        $review = mb_substr($review, 0, 1000, 'UTF-8');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO product_reviews (product_id, buyer_id, rating, review)
             VALUES (:product_id, :buyer_id, :rating, :review)'
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':buyer_id' => $buyerId,
            ':rating' => $rating,
            ':review' => $review !== '' ? $review : null,
        ]);
    } catch (PDOException $e) {
        // uniq_product_reviews_product_buyer — один отзыв на товар от покупателя.
        if ((string)$e->getCode() === '23000') {
            throw new RuntimeException('Вы уже оставили отзыв на этот товар.');
        }
        throw $e;
    }
}

/**
 * Средний рейтинг, число отзывов и оценка текущего пользователя (если
 * есть) для каждого товара из $productIds. Ключ — id товара.
 */
function get_product_review_map(PDO $pdo, array $productIds, int $viewerId): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (!$productIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT product_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count
           FROM product_reviews
          WHERE product_id IN ($placeholders)
          GROUP BY product_id"
    );
    $stmt->execute($productIds);

    $map = [];
    foreach ($productIds as $id) {
        $map[$id] = ['avg_rating' => 0.0, 'review_count' => 0, 'my_rating' => null];
    }
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['product_id'];
        $map[$id]['avg_rating'] = (float)$row['avg_rating'];
        $map[$id]['review_count'] = (int)$row['review_count'];
    }

    if ($viewerId > 0) {
        $mineStmt = $pdo->prepare(
            "SELECT product_id, rating FROM product_reviews
              WHERE buyer_id = ? AND product_id IN ($placeholders)"
        );
        $mineStmt->execute(array_merge([$viewerId], $productIds));
        foreach ($mineStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['product_id']]['my_rating'] = (int)$row['rating'];
        }
    }

    return $map;
}

/**
 * До нескольких последних отзывов на каждый товар (для превью в карточке
 * товара). Ключ — id товара, значение — список отзывов, новые сначала.
 */
function get_product_review_preview_map(PDO $pdo, array $productIds, int $limitPerProduct = 3): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (!$productIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT pr.product_id, pr.rating, pr.review, pr.created_at, p.User AS buyer_name
           FROM product_reviews pr
           JOIN profiles p ON p.Number = pr.buyer_id
          WHERE pr.product_id IN ($placeholders)
          ORDER BY pr.created_at DESC, pr.id DESC"
    );
    $stmt->execute($productIds);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['product_id'];
        if (!isset($map[$id])) {
            $map[$id] = [];
        }
        if (count($map[$id]) >= $limitPerProduct) {
            continue;
        }
        $map[$id][] = [
            'buyer_name' => (string)$row['buyer_name'],
            'rating' => (int)$row['rating'],
            'review' => (string)($row['review'] ?? ''),
        ];
    }

    return $map;
}

/**
 * Форматирует дату отзыва («5 августа 2026, 14:32»).
 */
function format_review_date(string $mysqlDate): string
{
    $ts = strtotime($mysqlDate);
    if ($ts === false) {
        return '';
    }

    static $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts)
        . ', ' . date('H:i', $ts);
}

/**
 * Все отзывы на один товар: имя и аватар покупателя, оценка, текст,
 * дата + распределение оценок по звёздам. Новые отзывы первыми.
 * Используется модальным окном «Все отзывы».
 */
function get_product_reviews_full(PDO $pdo, int $productId): array
{
    $empty = [
        'items' => [],
        'avg_rating' => 0.0,
        'review_count' => 0,
        'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    ];
    if ($productId <= 0) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        'SELECT pr.rating, pr.review, pr.created_at, p.User AS buyer_name, p.Photo AS buyer_photo
           FROM product_reviews pr
           JOIN profiles p ON p.Number = pr.buyer_id
          WHERE pr.product_id = :product_id
          ORDER BY pr.created_at DESC, pr.id DESC'
    );
    $stmt->execute([':product_id' => $productId]);

    $items = [];
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rating = max(1, min(5, (int)$row['rating']));
        $distribution[$rating]++;
        $items[] = [
            'buyer_name' => (string)$row['buyer_name'],
            'buyer_photo' => resolve_avatar((string)($row['buyer_photo'] ?? '')),
            'rating' => $rating,
            'review' => (string)($row['review'] ?? ''),
            'created_at_human' => format_review_date((string)$row['created_at']),
        ];
    }

    $count = count($items);
    if ($count === 0) {
        return $empty;
    }

    $sum = 0;
    foreach ($items as $item) {
        $sum += $item['rating'];
    }

    return [
        'items' => $items,
        'avg_rating' => round($sum / $count, 1),
        'review_count' => $count,
        'distribution' => $distribution,
    ];
}

/**
 * Товары из $productIds, оплаченные покупателем $viewerId, на которые он
 * ещё не оставил отзыв — именно эти товары можно оценить.
 */
function get_reviewable_product_ids(PDO $pdo, int $viewerId, array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if ($viewerId <= 0 || !$productIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT oi.product_id
           FROM shop_order_items oi
           JOIN shop_orders o ON o.id = oi.order_id
          WHERE o.buyer_id = ? AND o.status = 'paid' AND oi.product_id IN ($placeholders)
            AND oi.product_id NOT IN (
                SELECT product_id FROM product_reviews WHERE buyer_id = ?
            )"
    );
    $stmt->execute(array_merge([$viewerId], $productIds, [$viewerId]));

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Заявки на криптопополнение, ещё не доведённые до конца (ожидают
 * назначения кошелька или подтверждения зачисления). Используется
 * в админ-панели, раздел «Криптопополнения».
 */
function get_pending_topup_requests(PDO $pdo): array
{
    ensure_crypto_wallet_tag_column($pdo);

    $stmt = $pdo->query(
        "SELECT t.id, t.user_id, t.amount_eur, t.currency, t.status, t.wallet_id, t.created_at,
                p.User AS user_name,
                w.label AS wallet_label, w.address AS wallet_address, w.tag AS wallet_tag
           FROM topup_requests t
           JOIN profiles p ON p.Number = t.user_id
           LEFT JOIN crypto_wallets w ON w.id = t.wallet_id
          WHERE t.status IN ('pending', 'wallet_sent')
          ORDER BY t.created_at ASC, t.id ASC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Счётчики очередей модерации для колокольчика уведомлений в профиле
 * («Покупки, ожидающие одобрения», «Модерация товаров», «Заявки на
 * криптопополнение»). Критерии те же, что и в разделах admin.php.
 */
function get_moderation_notification_counts(PDO $pdo): array
{
    $orders = (int)$pdo->query(
        "SELECT COUNT(*) FROM shop_orders WHERE status = 'pending_approval'"
    )->fetchColumn();

    $products = (int)$pdo->query(
        "SELECT COUNT(*) FROM shop_products WHERE is_deleted = 0 AND moderation_status = 'pending'"
    )->fetchColumn();

    $topups = (int)$pdo->query(
        "SELECT COUNT(*) FROM topup_requests WHERE status IN ('pending', 'wallet_sent')"
    )->fetchColumn();

    return [
        'orders' => $orders,
        'products' => $products,
        'topups' => $topups,
        'total' => $orders + $products + $topups,
    ];
}

/**
 * Добавляет колонку `tag` в `crypto_wallets`, если её ещё нет — она хранится
 * в исходном дампе БД без этой колонки, а тег/memo нужен для валют вроде
 * XRP, где перевод без указания тега может быть потерян биржей получателя.
 */
function ensure_crypto_wallet_tag_column(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $hasTag = (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crypto_wallets' AND COLUMN_NAME = 'tag'"
    )->fetchColumn();

    if (!$hasTag) {
        $pdo->exec('ALTER TABLE crypto_wallets ADD COLUMN tag VARCHAR(64) NULL AFTER address');
    }

    $ensured = true;
}

/**
 * Активные криптокошельки сайта — источник реквизитов, которые
 * администратор/модератор назначает заявкам на пополнение.
 */
function get_active_crypto_wallets(PDO $pdo): array
{
    ensure_crypto_wallet_tag_column($pdo);

    $stmt = $pdo->query(
        'SELECT id, currency, label, address, tag FROM crypto_wallets WHERE is_active = 1 ORDER BY currency, label'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Назначает заявке на пополнение реквизиты выбранного кошелька сайта и
 * переводит её в статус 'wallet_sent' — после этого пользователю
 * показывается адрес, куда отправить перевод.
 */
function assign_topup_request_wallet(PDO $pdo, int $actorId, int $requestId, int $walletId): void
{
    if (!can_approve_shop_orders($pdo, $actorId)) {
        throw new RuntimeException('Недостаточно прав для обработки заявок на пополнение.');
    }
    if ($requestId <= 0 || $walletId <= 0) {
        throw new RuntimeException('Некорректная заявка или кошелёк.');
    }

    $stmt = $pdo->prepare('SELECT user_id, amount_eur, currency, status FROM topup_requests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Заявка не найдена.');
    }
    if ($request['status'] !== 'pending') {
        throw new RuntimeException('Заявка уже обработана.');
    }
    if ($actorId > 0 && $actorId === (int)$request['user_id']) {
        // Без этой проверки модератор/админ мог создать заявку самому себе
        // (форма пополнения на странице профиля ограничивает только тем, что
        // заявку можно подать за себя, а не то, кто её потом обработает) и
        // сам же назначить/зачислить её — то есть начислить себе баланс без
        // реального перевода. Заодно это предотвращает и «диалог с самим
        // собой» в личных сообщениях (sender_id === receiver_id), из-за
        // которого у count_conversations()/get_conversations_list() расходились
        // бы счётчики так же, как ранее с sender_id = 0 у сессии супер-админа.
        throw new RuntimeException('Нельзя обрабатывать собственную заявку на пополнение.');
    }

    ensure_crypto_wallet_tag_column($pdo);
    $walletStmt = $pdo->prepare(
        'SELECT currency, label, address, tag FROM crypto_wallets WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $walletStmt->execute([':id' => $walletId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) {
        throw new RuntimeException('Кошелёк не найден или неактивен.');
    }
    if ((string)$wallet['currency'] !== (string)$request['currency']) {
        throw new RuntimeException('Валюта выбранного кошелька не совпадает с валютой заявки.');
    }

    $update = $pdo->prepare(
        "UPDATE topup_requests
            SET wallet_id = :wallet_id, status = 'wallet_sent', handled_by = :actor, handled_at = NOW()
          WHERE id = :id AND status = 'pending'"
    );
    $update->execute([
        ':wallet_id' => $walletId,
        ':actor' => $actorId > 0 ? $actorId : null,
        ':id' => $requestId,
    ]);
    if ($update->rowCount() === 0) {
        throw new RuntimeException('Не удалось назначить кошелёк — заявка уже изменена.');
    }

    // Присылаем реквизиты кошелька пользователю в личные сообщения, чтобы их
    // не пришлось искать в интерфейсе заявки отдельно.
    $recipientStmt = $pdo->prepare('SELECT User FROM profiles WHERE Number = :id LIMIT 1');
    $recipientStmt->execute([':id' => (int)$request['user_id']]);
    $recipientName = $recipientStmt->fetchColumn();

    if ($recipientName !== false && $actorId > 0) {
        // Отправляем уведомление только от лица настоящего аккаунта
        // модератора/админа (actorId > 0). Сессия супер-администратора
        // (вход через admin_account, actorId === 0) не привязана ни к
        // одному профилю — сообщение с sender_id = 0 не NULL, поэтому
        // count_conversations() посчитал бы его как отдельный диалог,
        // а get_conversations_list() и messages.php наоборот отфильтровывают
        // такие «нулевые» id как несуществующего собеседника. В результате
        // счётчик непрочитанных у получателя навсегда «залипал» бы на +1,
        // без возможности открыть и прочитать это сообщение. Кошелёк при
        // этом всё равно назначен — пользователь увидит реквизиты в статусе
        // своей заявки на странице профиля, просто без дублирующего чата.
        $content = 'По вашей заявке на пополнение #' . $requestId . ' (' .
            number_format((float)$request['amount_eur'], 2, ',', ' ') . ' € через ' . $wallet['currency'] .
            ') назначен кошелёк для перевода' . ($wallet['label'] !== null && $wallet['label'] !== ''
                ? ' «' . $wallet['label'] . '»' : '') . ":\n" .
            'Адрес: ' . $wallet['address'];

        if (!empty($wallet['tag'])) {
            $content .= "\nTag/Memo: " . $wallet['tag']
                . ' — обязательно укажите его при переводе, иначе средства могут быть утеряны.';
        }

        $content .= "\n\nПосле отправки перевода дождитесь подтверждения администратором"
            . ' — баланс зачислится автоматически.';

        send_private_message($pdo, $actorId, 'Администрация', (int)$request['user_id'], (string)$recipientName, $content);
    }
}

/**
 * Подтверждает поступление криптоперевода и зачисляет сумму заявки на
 * внутренний баланс пользователя (в EUR). Один txid в рамках одной валюты
 * нельзя использовать для зачисления дважды.
 */
function credit_topup_request(PDO $pdo, int $actorId, int $requestId, string $txid, string $note = ''): float
{
    if (!can_approve_shop_orders($pdo, $actorId)) {
        throw new RuntimeException('Недостаточно прав для обработки заявок на пополнение.');
    }
    $txid = trim($txid);
    if ($requestId <= 0 || $txid === '') {
        throw new RuntimeException('Укажите TXID транзакции.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM topup_requests WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            throw new RuntimeException('Заявка не найдена.');
        }
        if ($request['status'] !== 'wallet_sent') {
            throw new RuntimeException('Заявку нельзя зачислить в текущем статусе.');
        }
        if ($actorId > 0 && $actorId === (int)$request['user_id']) {
            // См. подробный комментарий в assign_topup_request_wallet() —
            // тот же самый self-approval риск (начисление себе баланса без
            // реального перевода), только на шаге подтверждения зачисления.
            throw new RuntimeException('Нельзя обрабатывать собственную заявку на пополнение.');
        }

        $dupStmt = $pdo->prepare(
            "SELECT 1 FROM topup_requests
              WHERE txid = :txid AND currency = :currency AND status = 'credited' AND id != :id
              LIMIT 1"
        );
        $dupStmt->execute([':txid' => $txid, ':currency' => $request['currency'], ':id' => $requestId]);
        if ($dupStmt->fetchColumn() !== false) {
            throw new RuntimeException('Этот TXID уже был использован для другого зачисления.');
        }

        $userId = (int)$request['user_id'];
        $balanceStmt = $pdo->prepare('SELECT wallet_balance FROM profiles WHERE Number = :id LIMIT 1 FOR UPDATE');
        $balanceStmt->execute([':id' => $userId]);
        $currentBalance = $balanceStmt->fetchColumn();
        if ($currentBalance === false) {
            throw new RuntimeException('Пользователь не найден.');
        }

        $amount = round((float)$request['amount_eur'], 2);
        $newBalance = round((float)$currentBalance + $amount, 2);

        $pdo->prepare('UPDATE profiles SET wallet_balance = :balance WHERE Number = :id')
            ->execute([':balance' => $newBalance, ':id' => $userId]);

        $pdo->prepare(
            "UPDATE topup_requests
                SET status = 'credited', txid = :txid, credited_by = :actor, credited_at = NOW(), note = :note
              WHERE id = :id"
        )->execute([
            ':txid' => $txid,
            ':actor' => $actorId > 0 ? $actorId : null,
            ':note' => $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
            ':id' => $requestId,
        ]);

        $pdo->prepare(
            'INSERT INTO wallet_transactions (user_id, actor_id, amount, balance_after, type, note)
             VALUES (:user_id, :actor_id, :amount, :balance_after, :type, :note)'
        )->execute([
            ':user_id' => $userId,
            ':actor_id' => $actorId > 0 ? $actorId : null,
            ':amount' => $amount,
            ':balance_after' => $newBalance,
            ':type' => 'crypto_top_up',
            ':note' => 'Crypto ' . (string)$request['currency'] . ', txid: ' . $txid,
        ]);

        $pdo->commit();
        return $newBalance;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Создаёт заказ со статусом pending_approval. Деньги НЕ списываются.
 * Списание и зачисление продавцам выполняется только после одобрения
 * заказа модератором/администратором.
 */
function create_pending_order(PDO $pdo, int $buyerId, array $cartInfo): int
{
    if ($buyerId <= 0 || user_is_banned($pdo, $buyerId) || empty($cartInfo['items'])) {
        if ($buyerId > 0 && user_is_banned($pdo, $buyerId)) {
            throw new RuntimeException('Заблокированный пользователь не может покупать товары.');
        }
        throw new RuntimeException('Корзина пуста.');
    }

    $cart = [];
    foreach ($cartInfo['items'] as $item) {
        $productId = (int)($item['product']['id'] ?? 0);
        $quantity = max(1, (int)($item['quantity'] ?? 0));
        if ($productId > 0) $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
    }
    if (!$cart) throw new RuntimeException('Корзина пуста.');

    $productIds = array_keys($cart);
    sort($productIds, SORT_NUMERIC);

    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT sp.id, sp.seller_id, sp.name, sp.price
               FROM shop_products sp
               JOIN profiles seller ON seller.Number = sp.seller_id AND seller.banned = 0
              WHERE sp.id IN (' . $placeholders . ')
                AND sp.is_deleted = 0
                AND sp.moderation_status = \'approved\'
              ORDER BY sp.id
              FOR UPDATE'
        );
        $stmt->execute($productIds);
        $products = $stmt->fetchAll();
        if (count($products) !== count($productIds)) {
            throw new RuntimeException('Один из товаров больше недоступен.');
        }

        $byId = [];
        foreach ($products as $product) $byId[(int)$product['id']] = $product;

        $total = 0.0;
        $items = [];
        foreach ($cart as $productId => $quantity) {
            $product = $byId[$productId];
            $sellerId = (int)$product['seller_id'];
            if ($sellerId === $buyerId) throw new RuntimeException('Нельзя покупать собственный товар.');
            $price = round((float)$product['price'], 2);
            if ($price <= 0) throw new RuntimeException('Цена одного из товаров некорректна.');
            $sum = round($price * $quantity, 2);
            $total = round($total + $sum, 2);
            $items[] = [
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'price' => $price,
                'quantity' => $quantity,
            ];
        }
        if ($total <= 0) throw new RuntimeException('Некорректная сумма заказа.');

        $stmt = $pdo->prepare(
            "INSERT INTO shop_orders (buyer_id, total, status)
             VALUES (:buyer, :total, 'pending_approval')"
        );
        $stmt->execute([':buyer' => $buyerId, ':total' => $total]);
        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO shop_order_items
                (order_id, product_id, seller_id, price, quantity)
             VALUES (:order, :product, :seller, :price, :quantity)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                ':order' => $orderId,
                ':product' => $item['product_id'],
                ':seller' => $item['seller_id'],
                ':price' => $item['price'],
                ':quantity' => $item['quantity'],
            ]);
        }

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Одобряет заказ и только в этой транзакции списывает кошелёк покупателя
 * и начисляет средства продавцам.
 */
function approve_shop_order(PDO $pdo, int $actorId, int $orderId, string $note = ''): int
{
    if (!can_approve_shop_orders($pdo, $actorId)) {
        throw new RuntimeException('Недостаточно прав для одобрения покупок.');
    }
    if ($orderId <= 0) throw new RuntimeException('Заказ не найден.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT o.*
               FROM shop_orders o
              WHERE o.id = :id AND o.status = 'pending_approval'
              LIMIT 1
              FOR UPDATE"
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new RuntimeException('Заказ не найден или уже обработан.');

        $itemStmt = $pdo->prepare(
            'SELECT oi.product_id, oi.seller_id, oi.price, oi.quantity,
                    sp.name, sp.price AS current_price, sp.moderation_status, sp.is_deleted,
                    seller.banned AS seller_banned
               FROM shop_order_items oi
               JOIN shop_products sp ON sp.id = oi.product_id
               JOIN profiles seller ON seller.Number = oi.seller_id
              WHERE oi.order_id = :order
              ORDER BY oi.id
              FOR UPDATE'
        );
        $itemStmt->execute([':order' => $orderId]);
        $items = $itemStmt->fetchAll();
        if (!$items) throw new RuntimeException('В заказе нет товаров.');

        $total = 0.0;
        $sellerTotals = [];
        $participantIds = [(int)$order['buyer_id']];
        foreach ($items as $item) {
            if ((int)$item['is_deleted'] !== 0 || (string)$item['moderation_status'] !== 'approved' || (int)$item['seller_banned'] !== 0) {
                throw new RuntimeException('Один из товаров больше недоступен для покупки.');
            }
            $price = round((float)$item['price'], 2);
            $currentPrice = round((float)$item['current_price'], 2);
            if ($currentPrice <= 0 || abs($currentPrice - $price) > 0.0001) {
                throw new RuntimeException('Цена товара изменилась после отправки заказа. Заказ не одобрен.');
            }
            $sum = round($price * max(1, (int)$item['quantity']), 2);
            $total = round($total + $sum, 2);
            $sellerId = (int)$item['seller_id'];
            $sellerTotals[$sellerId] = round(($sellerTotals[$sellerId] ?? 0.0) + $sum, 2);
            $participantIds[] = $sellerId;
        }
        if (abs($total - round((float)$order['total'], 2)) > 0.0001) {
            throw new RuntimeException('Сумма заказа изменилась. Заказ не одобрен.');
        }

        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));
        sort($participantIds, SORT_NUMERIC);
        $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
        $balanceStmt = $pdo->prepare(
            'SELECT Number, wallet_balance FROM profiles
              WHERE Number IN (' . $placeholders . ')
              ORDER BY Number FOR UPDATE'
        );
        $balanceStmt->execute($participantIds);
        $balances = [];
        foreach ($balanceStmt->fetchAll() as $row) $balances[(int)$row['Number']] = (float)$row['wallet_balance'];

        $buyerId = (int)$order['buyer_id'];
        if (!isset($balances[$buyerId])) throw new RuntimeException('Покупатель не найден.');
        $buyerBalance = round($balances[$buyerId], 2);
        if ($buyerBalance < $total) {
            throw new RuntimeException(
                'Недостаточно средств для одобрения. Нужно ' . number_format($total, 2, ',', ' ') .
                ' €, доступно ' . number_format($buyerBalance, 2, ',', ' ') . ' €.'
            );
        }

        $newBuyerBalance = round($buyerBalance - $total, 2);
        $updateBalance = $pdo->prepare('UPDATE profiles SET wallet_balance = :balance WHERE Number = :id');
        $txStmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
                (user_id, actor_id, amount, balance_after, type, related_order_id, note)
             VALUES (:user_id, :actor_id, :amount, :balance_after, :type, :order_id, :note)'
        );
        $updateBalance->execute([':balance' => $newBuyerBalance, ':id' => $buyerId]);
        $txStmt->execute([
            ':user_id' => $buyerId,
            ':actor_id' => $actorId > 0 ? $actorId : null,
            ':amount' => -$total,
            ':balance_after' => $newBuyerBalance,
            ':type' => 'purchase',
            ':order_id' => $orderId,
            ':note' => 'Одобренная покупка #' . $orderId,
        ]);

        foreach ($sellerTotals as $sellerId => $sellerAmount) {
            if (!isset($balances[$sellerId])) throw new RuntimeException('Продавец не найден.');
            $newSellerBalance = round($balances[$sellerId] + $sellerAmount, 2);
            $updateBalance->execute([':balance' => $newSellerBalance, ':id' => $sellerId]);
            $txStmt->execute([
                ':user_id' => $sellerId,
                ':actor_id' => $actorId > 0 ? $actorId : null,
                ':amount' => $sellerAmount,
                ':balance_after' => $newSellerBalance,
                ':type' => 'sale',
                ':order_id' => $orderId,
                ':note' => 'Продажа по одобренному заказу #' . $orderId,
            ]);
        }

        $stmt = $pdo->prepare(
            "UPDATE shop_orders
                SET status = 'paid', payment_provider = 'wallet', paid_at = NOW(),
                    approved_by = :actor, approved_at = NOW(), approval_note = :note
              WHERE id = :id"
        );
        $stmt->execute([
            ':actor' => $actorId > 0 ? $actorId : null,
            ':note' => $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
            ':id' => $orderId,
        ]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Начисляет продавцам их доли по заказу, оплаченному через Stripe.
 * Вызывается из stripe_webhook.php сразу после того, как заказ переведён
 * в status='paid' — проверка rowCount() > 0 в самом вебхуке гарантирует,
 * что это произойдёт ровно один раз, даже если Stripe продублирует вебхук
 * (обычная практика на их стороне при отсутствии быстрого ответа 200).
 *
 * В отличие от approve_shop_order(): здесь НЕ списывается баланс покупателя
 * (деньги уже реально списаны через Stripe) и не перепроверяется совпадение
 * цены с текущей ценой товара — деньги уже получены и вернуть их нельзя,
 * поэтому начисляем строго по цене, зафиксированной в заказе на момент
 * оформления (shop_order_items.price), как и было согласовано с покупателем
 * на странице оплаты Stripe.
 */
function credit_stripe_order_sellers(PDO $pdo, int $orderId): void
{
    if ($orderId <= 0) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $itemStmt = $pdo->prepare(
            'SELECT seller_id, price, quantity FROM shop_order_items WHERE order_id = :order FOR UPDATE'
        );
        $itemStmt->execute([':order' => $orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            $pdo->commit();
            return;
        }

        // Доп. защита от повторного начисления (например, если функцию
        // вызовут вручную из панели после сбоя) — независимо от того, что
        // сам вебхук уже гарантирует однократный вызов через переход
        // статуса заказа. FOR UPDATE на shop_order_items выше уже
        // сериализует конкурентные вызовы для одного и того же заказа.
        $alreadyCreditedStmt = $pdo->prepare(
            "SELECT 1 FROM wallet_transactions WHERE related_order_id = :order AND type = 'sale' LIMIT 1"
        );
        $alreadyCreditedStmt->execute([':order' => $orderId]);
        if ($alreadyCreditedStmt->fetchColumn() !== false) {
            $pdo->commit();
            return;
        }

        $sellerTotals = [];
        foreach ($items as $item) {
            $sellerId = (int)$item['seller_id'];
            $sum = round((float)$item['price'] * max(1, (int)$item['quantity']), 2);
            $sellerTotals[$sellerId] = round(($sellerTotals[$sellerId] ?? 0.0) + $sum, 2);
        }

        $sellerIds = array_keys($sellerTotals);
        sort($sellerIds, SORT_NUMERIC);
        $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $balanceStmt = $pdo->prepare(
            'SELECT Number, wallet_balance FROM profiles
              WHERE Number IN (' . $placeholders . ')
              ORDER BY Number FOR UPDATE'
        );
        $balanceStmt->execute($sellerIds);
        $balances = [];
        foreach ($balanceStmt->fetchAll() as $row) {
            $balances[(int)$row['Number']] = (float)$row['wallet_balance'];
        }

        $updateBalance = $pdo->prepare('UPDATE profiles SET wallet_balance = :balance WHERE Number = :id');
        $txStmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
                (user_id, actor_id, amount, balance_after, type, related_order_id, note)
             VALUES (:user_id, NULL, :amount, :balance_after, :type, :order_id, :note)'
        );

        foreach ($sellerTotals as $sellerId => $sellerAmount) {
            if (!isset($balances[$sellerId])) {
                // Продавец не найден (маловероятно) — не роняем весь вебхук
                // из-за одной позиции, остальным продавцам всё равно начислим.
                continue;
            }
            $newBalance = round($balances[$sellerId] + $sellerAmount, 2);
            $updateBalance->execute([':balance' => $newBalance, ':id' => $sellerId]);
            $txStmt->execute([
                ':user_id' => $sellerId,
                ':amount' => $sellerAmount,
                ':balance_after' => $newBalance,
                ':type' => 'sale',
                ':order_id' => $orderId,
                ':note' => 'Продажа по заказу #' . $orderId . ', оплаченному через Stripe',
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reject_shop_order(PDO $pdo, int $actorId, int $orderId, string $note = ''): void
{
    if (!can_approve_shop_orders($pdo, $actorId)) {
        throw new RuntimeException('Недостаточно прав для обработки покупок.');
    }
    $stmt = $pdo->prepare(
        "UPDATE shop_orders
            SET status = 'rejected', approved_by = :actor, approved_at = NOW(), approval_note = :note
          WHERE id = :id AND status = 'pending_approval'"
    );
    $stmt->execute([
        ':actor' => $actorId > 0 ? $actorId : null,
        ':note' => $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
        ':id' => $orderId,
    ]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Заказ не найден или уже обработан.');
}

/**
 * Совместимость со старым кодом: теперь эта функция не списывает деньги.
 * Она создаёт заявку на покупку, которая ждёт одобрения.
 */
function purchase_cart_with_wallet(PDO $pdo, int $buyerId, array $cartInfo): int
{
    return create_pending_order($pdo, $buyerId, $cartInfo);
}

function get_seller_products(PDO $pdo, int $sellerId, int $viewerId = 0): array
{
    // Покупателям показываем только одобренные товары. Владелец магазина
    // дополнительно видит свои товары в ожидании модерации, чтобы понимать
    // их текущий статус.
    $sql = 'SELECT sp.* FROM shop_products sp
              JOIN profiles p ON p.Number = sp.seller_id AND p.banned = 0
             WHERE sp.seller_id = :id AND sp.is_deleted = 0
               AND (sp.moderation_status = \'approved\' OR sp.seller_id = :viewer)
             ORDER BY sp.created_at DESC, sp.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $sellerId, ':viewer' => $viewerId]);
    return $stmt->fetchAll();
}

/**
 * Ищет товары по названию/описанию (для общего поиска по сайту, наряду с
 * поиском пользователей). Скрытые (is_deleted = 1) товары не участвуют.
 */
function search_products(PDO $pdo, string $query, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $like = '%' . addcslashes($query, "\\%_") . '%';
    $stmt = $pdo->prepare(
        "SELECT sp.id, sp.name, sp.description, sp.price, sp.photo, sp.seller_id,
                p.User AS seller_name,
                COALESCE((
                    SELECT SUM(oi2.quantity)
                      FROM shop_order_items oi2
                      JOIN shop_orders o2 ON o2.id = oi2.order_id
                     WHERE oi2.product_id = sp.id
                       AND o2.status = 'paid'
                ), 0) AS purchases,
                COALESCE((
                    SELECT ROUND(AVG(pr2.rating), 1)
                      FROM product_reviews pr2
                     WHERE pr2.product_id = sp.id
                ), 0) AS review_rating,
                COALESCE((
                    SELECT COUNT(*)
                      FROM product_reviews pr3
                     WHERE pr3.product_id = sp.id
                ), 0) AS review_count
           FROM shop_products sp
           JOIN profiles p ON p.Number = sp.seller_id AND p.banned = 0
          WHERE sp.is_deleted = 0
            AND sp.moderation_status = 'approved'
            AND (sp.name LIKE :q1 ESCAPE '\\\\' OR sp.description LIKE :q2 ESCAPE '\\\\')
          ORDER BY purchases DESC, review_rating DESC, sp.created_at DESC, sp.id DESC
          LIMIT :lim"
    );
    $stmt->bindValue(':q1', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function count_search_products(PDO $pdo, string $query): int
{
    $like = '%' . addcslashes($query, "\\%_") . '%';
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM shop_products
          WHERE is_deleted = 0 AND moderation_status = 'approved' AND (name LIKE :q1 ESCAPE '\\\\' OR description LIKE :q2 ESCAPE '\\\\')"
    );
    $stmt->bindValue(':q1', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function init_cart(): void
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
}
function get_cart(): array { init_cart(); return $_SESSION['cart']; }
function cart_add(int $productId): void { init_cart(); $_SESSION['cart'][$productId] = (int)($_SESSION['cart'][$productId] ?? 0) + 1; }
function cart_remove(int $productId): void { init_cart(); unset($_SESSION['cart'][$productId]); }
function cart_clear(): void { $_SESSION['cart'] = []; }

function cart_details(PDO $pdo): array
{
    $cart = get_cart();
    if (!$cart) return ['items' => [], 'total' => 0.0];
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT p.*, pr.User AS seller_name FROM shop_products p JOIN profiles pr ON pr.Number = p.seller_id AND pr.banned = 0 WHERE p.id IN (' . $placeholders . ') AND p.is_deleted = 0 AND p.moderation_status = \'approved\'');
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    $items = []; $total = 0.0;
    foreach ($rows as $row) {
        $productId = (int)$row['id']; $quantity = max(1, (int)$cart[$productId]);
        $sum = (float)$row['price'] * $quantity; $total += $sum;
        $items[] = ['product' => $row, 'quantity' => $quantity, 'sum' => $sum];
    }
    return ['items' => $items, 'total' => $total];
}

function create_stripe_checkout(PDO $pdo, int $orderId, array $cartInfo, string $successUrl, string $cancelUrl): string
{
    global $STRIPE_SECRET_KEY, $SHOP_CURRENCY;
    if ($STRIPE_SECRET_KEY === '') throw new RuntimeException('Не задан STRIPE_SECRET_KEY.');
    $fields=[
        'mode'=>'payment',
        'success_url'=>$successUrl,
        'cancel_url'=>$cancelUrl,
        'client_reference_id'=>(string)$orderId,
        'metadata[order_id]'=>$orderId,
    ];
    foreach($cartInfo['items'] as $i=>$item){
        $fields["line_items[$i][price_data][currency]"]=$SHOP_CURRENCY;
        $fields["line_items[$i][price_data][unit_amount]"]=max(1,(int)round((float)$item['product']['price']*100));
        $fields["line_items[$i][price_data][product_data][name]"]=mb_substr((string)$item['product']['name'],0,200,'UTF-8');
        $fields["line_items[$i][quantity]"]=max(1,(int)$item['quantity']);
    }
    $ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$STRIPE_SECRET_KEY,'Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_TIMEOUT=>20]);
    $body=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlError=curl_error($ch); curl_close($ch);
    if($body===false || $curlError!=='' || $status<200 || $status>=300){ throw new RuntimeException('Платёжный шлюз недоступен.'); }
    $data=json_decode((string)$body,true);
    if(!is_array($data) || empty($data['id']) || empty($data['url'])) throw new RuntimeException('Платёжная сессия не создана.');
    $stmt=$pdo->prepare("UPDATE shop_orders SET payment_provider='stripe', payment_id=:payment, status='pending_payment' WHERE id=:id");
    $stmt->execute([':payment'=>(string)$data['id'],':id'=>$orderId]);
    return (string)$data['url'];
}

