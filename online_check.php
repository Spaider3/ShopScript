<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();

$stmt = $pdo->prepare(
    'SELECT Number, User, last_seen
     FROM profiles
     WHERE is_online = 1
       AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY last_seen DESC'
);
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

json_response([
    'status' => 'success',
    'online_count' => count($users),
    'users' => $users,
]);
