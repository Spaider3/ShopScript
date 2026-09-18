<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
$online = !empty($_SESSION['admin_logged_in']) ? 1 : 0;

if (isset($_POST['admin_logout'])) {
    if (verify_csrf($_POST['csrf_token'] ?? null)) {
        unset($_SESSION['admin_logged_in']);
    }
    header('Location: admin.php');
    exit;
}

if (isset($_POST['submit'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        $loginInput = trim((string)($_POST['login'] ?? ''));
        $passwordInput = (string)($_POST['password'] ?? '');

        if (login_is_throttled($pdo, 'admin', $loginInput)) {
            $error = 'Слишком много неудачных попыток входа. Попробуйте снова через несколько минут.';
        } else {
            // Логин/пароль, заданные администратором в панели, имеют приоритет.
            // Пока в БД ничего не задано, используется резервная пара из
            // окружения (ADMIN_NAME/ADMIN_PASS), чтобы не потерять доступ
            // сразу после обновления.
            $dbCredentials = get_admin_credentials($pdo);
            $authenticated = false;

            if ($dbCredentials !== null) {
                if (hash_equals($dbCredentials['login'], $loginInput)
                    && password_verify($passwordInput, $dbCredentials['password_hash'])
                ) {
                    $authenticated = true;
                }
            } elseif ($admin_pass !== ''
                && hash_equals((string)$admin_name, $loginInput)
                && hash_equals((string)$admin_pass, $passwordInput)
            ) {
                $authenticated = true;
            }

            if ($authenticated) {
                record_login_attempt($pdo, 'admin', $loginInput, true);
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                header('Location: admin.php');
                exit;
            }

            record_login_attempt($pdo, 'admin', $loginInput, false);
            $error = 'Неверный логин или пароль администратора.';
        }
    }
}
