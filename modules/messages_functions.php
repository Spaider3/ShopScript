<?php
declare(strict_types=1);

/**
 * Общие функции личных сообщений.
 * Вынесены сюда, чтобы messages.php и messages_profile.php не дублировали
 * один и тот же SQL и одну и ту же логику отправки/чтения переписки.
 */

/**
 * Возвращает переписку между $viewerId и $targetId, отсортированную по времени.
 * Сопоставление в первую очередь по ID профиля (sender_id/receiver_id) — имя
 * пользователя не уникально, поэтому сопоставление по имени используется
 * только как запасной вариант для сообщений, отправленных до появления ID
 * (у них sender_id/receiver_id ещё NULL).
 */
function conversation_where_clause(): string
{
    return '(sender_id = :viewer_id_1 AND receiver_id = :target_id_1)
             OR (sender_id = :target_id_2 AND receiver_id = :viewer_id_2)
             OR (
                 (sender_id IS NULL OR receiver_id IS NULL)
                 AND (
                     (sender = :viewer_name_1 AND receiver = :target_name_1)
                     OR (sender = :target_name_2 AND receiver = :viewer_name_2)
                 )
             )';
}

function conversation_where_params(int $viewerId, string $viewerName, int $targetId, string $targetName): array
{
    return [
        ':viewer_id_1' => $viewerId,
        ':target_id_1' => $targetId,
        ':target_id_2' => $targetId,
        ':viewer_id_2' => $viewerId,
        ':viewer_name_1' => $viewerName,
        ':target_name_1' => $targetName,
        ':target_name_2' => $targetName,
        ':viewer_name_2' => $viewerName,
    ];
}

/**
 * Считает общее число сообщений в переписке — нужно для пагинации.
 */
function count_conversation_messages(PDO $pdo, int $viewerId, string $viewerName, int $targetId, string $targetName): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE ' . conversation_where_clause());
    $stmt->execute(conversation_where_params($viewerId, $viewerName, $targetId, $targetName));

    return (int)$stmt->fetchColumn();
}

/**
 * Возвращает страницу переписки. Сообщения всегда отсортированы по времени
 * по возрастанию (старые сверху); $offset считается от начала переписки —
 * вызывающий код сам решает, какую страницу показать (по умолчанию —
 * последнюю, чтобы новые сообщения были видны сразу).
 */
function get_conversation_messages(PDO $pdo, int $viewerId, string $viewerName, int $targetId, string $targetName, ?int $limit = null, int $offset = 0): array
{
    $sql = 'SELECT id, sender, receiver, sender_id, receiver_id, content, created_at
           FROM messages
          WHERE ' . conversation_where_clause() . '
          ORDER BY created_at ASC, id ASC';

    if ($limit !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach (conversation_where_params($viewerId, $viewerName, $targetId, $targetName) as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    if ($limit !== null) {
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Определяет, является ли сообщение "моим" (отправленным текущим пользователем),
 * с тем же приоритетом ID → имя, что и в get_conversation_messages().
 */
function is_my_message(array $message, int $viewerId, string $viewerName): bool
{
    return $message['sender_id'] !== null
        ? (int)$message['sender_id'] === $viewerId
        : (string)$message['sender'] === $viewerName;
}

/**
 * Сохраняет новое личное сообщение.
 */
function send_private_message(PDO $pdo, int $senderId, string $senderName, int $receiverId, string $receiverName, string $content): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO messages (sender, receiver, sender_id, receiver_id, content, is_read)
         VALUES (:sender_name, :receiver_name, :sender_id, :receiver_id, :content, 0)'
    );
    $stmt->execute([
        ':sender_name' => $senderName,
        ':receiver_name' => $receiverName,
        ':sender_id' => $senderId,
        ':receiver_id' => $receiverId,
        ':content' => $content,
    ]);
}

/**
 * Возвращает список диалогов пользователя (inbox): по одному на каждого
 * собеседника, с последним сообщением и числом непрочитанных, отсортированный
 * по времени последнего сообщения (сначала новые).
 *
 * Учитываются только сообщения с проставленными sender_id/receiver_id —
 * у более старых сообщений (до появления ID-сопоставления) нет надёжной
 * привязки к конкретному собеседнику по ID, поэтому в список диалогов
 * они не попадают (сама переписка при этом по-прежнему видна через
 * get_conversation_messages() по запасному варианту сопоставления по имени).
 */
/**
 * Считает общее число диалогов пользователя — нужно для пагинации inbox.
 */
function count_conversations(PDO $pdo, int $viewerId): int
{
    if ($viewerId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT CASE WHEN sender_id = :me1 THEN receiver_id ELSE sender_id END)
           FROM messages
          WHERE (sender_id = :me2 OR receiver_id = :me3)
            AND sender_id IS NOT NULL AND receiver_id IS NOT NULL'
    );
    $stmt->execute([':me1' => $viewerId, ':me2' => $viewerId, ':me3' => $viewerId]);

    return (int)$stmt->fetchColumn();
}

function get_conversations_list(PDO $pdo, int $viewerId, int $limit = 20, int $offset = 0): array
{
    if ($viewerId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    // Один запрос вместо N: оконная функция забирает только последнее
    // сообщение на каждого собеседника (партиционирование по other_id),
    // а не тянет всю переписку, чтобы взять из неё максимум в PHP.
    // LIMIT/OFFSET снаружи — постраничный вывод inbox.
    $lastMsgStmt = $pdo->prepare(
        'SELECT other_id, content, sender_id, created_at
           FROM (
               SELECT
                   CASE WHEN sender_id = :me1 THEN receiver_id ELSE sender_id END AS other_id,
                   content, sender_id, created_at,
                   ROW_NUMBER() OVER (
                       PARTITION BY CASE WHEN sender_id = :me2 THEN receiver_id ELSE sender_id END
                       ORDER BY created_at DESC, id DESC
                   ) AS rn
               FROM messages
              WHERE (sender_id = :me3 OR receiver_id = :me4)
                AND sender_id IS NOT NULL AND receiver_id IS NOT NULL
           ) ranked
          WHERE rn = 1
          ORDER BY created_at DESC
          LIMIT :limit OFFSET :offset'
    );
    $lastMsgStmt->bindValue(':me1', $viewerId, PDO::PARAM_INT);
    $lastMsgStmt->bindValue(':me2', $viewerId, PDO::PARAM_INT);
    $lastMsgStmt->bindValue(':me3', $viewerId, PDO::PARAM_INT);
    $lastMsgStmt->bindValue(':me4', $viewerId, PDO::PARAM_INT);
    $lastMsgStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $lastMsgStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $lastMsgStmt->execute();
    $lastMessages = $lastMsgStmt->fetchAll(PDO::FETCH_ASSOC);

    $otherIds = [];
    foreach ($lastMessages as $row) {
        $otherId = (int)$row['other_id'];
        if ($otherId > 0 && $otherId !== $viewerId) {
            $otherIds[] = $otherId;
        }
    }
    if (!$otherIds) {
        return [];
    }

    // Непрочитанные сразу по всем собеседникам одним запросом.
    $unreadStmt = $pdo->prepare(
        'SELECT sender_id AS other_id, COUNT(*) AS unread
           FROM messages
          WHERE receiver_id = :me AND is_read = 0
          GROUP BY sender_id'
    );
    $unreadStmt->execute([':me' => $viewerId]);
    $unreadMap = [];
    foreach ($unreadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unreadMap[(int)$row['other_id']] = (int)$row['unread'];
    }

    // Профили всех собеседников одним запросом.
    $placeholders = implode(',', array_fill(0, count($otherIds), '?'));
    $profileStmt = $pdo->prepare(
        "SELECT Number, User, Photo FROM profiles WHERE Number IN ($placeholders)"
    );
    $profileStmt->execute($otherIds);
    $profiles = [];
    foreach ($profileStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $profiles[(int)$row['Number']] = $row;
    }

    $conversations = [];
    foreach ($lastMessages as $row) {
        $otherId = (int)$row['other_id'];
        if ($otherId <= 0 || $otherId === $viewerId || !isset($profiles[$otherId])) {
            continue; // Собеседник удалён.
        }
        $profile = $profiles[$otherId];

        $conversations[] = [
            'other_id' => $otherId,
            'other_name' => (string)$profile['User'],
            'other_photo' => resolve_avatar((string)($profile['Photo'] ?? '')),
            'last_message' => (string)$row['content'],
            'last_is_mine' => (int)$row['sender_id'] === $viewerId,
            'last_time' => (string)$row['created_at'],
            'unread' => $unreadMap[$otherId] ?? 0,
        ];
    }

    return $conversations;
}
