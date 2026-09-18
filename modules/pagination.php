<?php
declare(strict_types=1);

// Количество записей на странице.
$num = 5;

$page = max(1, (int)($_GET['page'] ?? 1));

// Поисковый запрос по существующей таблице profiles
$searchQuery = trim((string)($_GET['search'] ?? ''));
if (mb_strlen($searchQuery, 'UTF-8') > 100) {
    $searchQuery = mb_substr($searchQuery, 0, 100, 'UTF-8');
}

$whereSql = '';
$params = [];

if ($searchQuery !== '') {
    $whereSql = 'WHERE `User` LIKE :search';
    $params[':search'] = '%' . $searchQuery . '%';
}

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM profiles ' . $whereSql);
$stmtCount->execute($params);
$posts = (int)$stmtCount->fetchColumn();
$total = max(1, (int)ceil($posts / $num));
$page = min($page, $total);

$start = ($page - 1) * $num;

if ($searchQuery === '' && (($_GET['act'] ?? '') === 'rate')) {
    $stmt = $pdo->query('SELECT * FROM profiles ORDER BY Rating DESC LIMIT 10');
} else {
    $stmt = $pdo->prepare('SELECT * FROM profiles ' . $whereSql . ' ORDER BY `Number` ASC LIMIT :start, :num');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':num', $num, PDO::PARAM_INT);
    $stmt->execute();
}

$rows = $stmt->fetchAll();

if (!$rows) {
    if ($searchQuery !== '') {
        echo '<tr><td colspan="5" class="empty_row">По запросу «' .
            htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') .
            '» ничего не найдено.</td></tr>';
    } else {
        echo '<tr><td colspan="5" class="empty_row">Профилей пока нет. Добавьте первую запись выше.</td></tr>';
    }
} else {
    foreach ($rows as $id) {
        $profileId = (int)$id['Number'];
        $name = htmlspecialchars((string)$id['User'], ENT_QUOTES, 'UTF-8');
        $photo = htmlspecialchars((string)$id['Photo'], ENT_QUOTES, 'UTF-8');
        $rating = (int)$id['Rating'];

        echo '<tr>';
        echo '<td>' . $profileId . '</td>';
        echo '<td><a href="profile.php?id=' . $profileId . '">' . $name . '</a></td>';
        echo '<td class="photo_cell"><a href="profile.php?id=' . $profileId . '"><img class="table_photo" src="' . $photo . '" alt="' . $name . '" /></a></td>';
        echo '<td class="rating_cell">' . $rating . '</td>';

        if ($online === 1) {
            echo '<td class="action_cell"><a class="edit_link" href="admin.php?act=edit&num=' . $profileId . '">Редактировать</a></td>';
        }

        echo '</tr>';
    }
}
?>