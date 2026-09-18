<?php
declare(strict_types=1);

/**
 * Database configuration for PHP 8+.
 *
 * You can set these values in the environment:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 * or replace the defaults below.
 */
class Url
{
    public string $BASE_URL = '';
    public string $IMAGES = 'images';
}

/**
 * Обработка ошибок в зависимости от окружения.
 *
 * По умолчанию считаем окружение "боевым" (production) — это безопасное
 * значение по умолчанию: если переменная APP_ENV не задана, сайт не покажет
 * посетителю пути на сервере, текст SQL-запроса или другие внутренние
 * детали при неожиданной ошибке. Чтобы увидеть подробности при разработке,
 * явно укажите APP_ENV=local (или development/dev).
 *
 * Если APP_ENV вообще не задана переменной окружения, дополнительно
 * пытаемся распознать именно это локальное окружение разработки —
 * хост "localhost"/"mysite.local" и/или БД-хост "MySQL-8.4" (так называется
 * контейнер локальной MySQL в docker-compose) — и в этом случае тоже не
 * считаем его продакшеном. Явно заданный APP_ENV (в т.ч. APP_ENV=production)
 * всегда имеет приоритет над автоопределением.
 */
function is_production_environment(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $appEnvRaw = getenv('APP_ENV');
    $appEnv = strtolower((string)($appEnvRaw ?: 'production'));

    if (in_array($appEnv, ['local', 'development', 'dev'], true)) {
        $cached = false;
        return false;
    }

    if ($appEnvRaw !== false) {
        // APP_ENV задана явно и это не local/dev/development — считаем продакшеном.
        $cached = true;
        return true;
    }

    // APP_ENV не задана вообще — пытаемся распознать локальную разработку только
    // по HTTP-хосту, на который реально пришёл запрос (или по CLI-режиму).
    //
    // Раньше сюда же добавлялась проверка DB_HOST на совпадение со значением
    // 'MySQL-8.4' (имя контейнера в локальном docker-compose) — но это то же
    // самое значение, которое используется как ФОЛБЭК, если переменная DB_HOST
    // вообще не задана (см. $DB_HOST = getenv('DB_HOST') ?: 'MySQL-8.4' ниже).
    // Из-за этого любой боевой сервер, на котором просто забыли явно выставить
    // DB_HOST (а многие хостинги/панели это не делают — БД настраивают иначе),
    // автоматически распознавался как «локальный»: включался display_errors
    // (утечка путей/деталей БД посетителям) и полностью отключалась защита от
    // подбора пароля при входе (login_is_throttled() сразу возвращает false).
    // Теперь распознавание опирается только на явный APP_ENV и HTTP-хост.
    $httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $knownLocalHosts = ['localhost', '127.0.0.1', '::1', 'mysite.local'];

    $isLocalHost = in_array($httpHost, $knownLocalHosts, true)
        || str_ends_with($httpHost, '.local')
        || str_ends_with($httpHost, '.test')
        || PHP_SAPI === 'cli';

    $cached = !$isLocalHost;
    return $cached;
}

$isProduction = is_production_environment();

error_reporting(E_ALL);
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');

/**
 * Определяет, ждёт ли текущий запрос JSON-ответ (например, fetch() из
 * login.php с заголовком Accept: application/json). Нужна, чтобы обработчик
 * ошибок ниже и сбой подключения к БД не отдавали HTML туда, где
 * JavaScript делает response.json() — иначе там падает "Unexpected token
 * '<'... is not valid JSON" вместо понятного сообщения об ошибке.
 */
function request_wants_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || str_contains($contentType, 'application/json');
}

// Подстраховка на случай исключения, которое ни один код в проекте не
// обработал try/catch'ем (например, сбой БД посреди запроса, гонка при
// вставке и т.п.). Без этого обработчика PHP покажет посетителю то, что
// разрешает display_errors — в проде мы явно выключаем показ, но лучше
// ещё и вернуть аккуратное сообщение вместо белого экрана/сырого текста.
set_exception_handler(static function (Throwable $e) use ($isProduction): void {
    error_log('Необработанное исключение: ' . $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (request_wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        $payload = $isProduction
            ? ['error' => 'Произошла внутренняя ошибка сервера. Мы уже работаем над её устранением.']
            : ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
        exit(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    if ($isProduction) {
        exit('<p>Произошла внутренняя ошибка сервера. Мы уже работаем над её устранением.</p>');
    }

    exit('<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>');
});

$url = new Url();
$url->BASE_URL = rtrim((string)(getenv('BASE_URL') ?: 'http://mysite.local/'), '/') . '/';

$DB_HOST = getenv('DB_HOST') ?: 'MySQL-8.4';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'hydra';

$date = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y') + 5);

$admin_name = getenv('ADMIN_NAME') ?: 'admin';
$admin_pass = (string)(getenv('ADMIN_PASS') ?: '');
$STRIPE_SECRET_KEY = getenv('STRIPE_SECRET_KEY') ?: '';
$STRIPE_WEBHOOK_SECRET = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
$SHOP_CURRENCY = strtolower(getenv('SHOP_CURRENCY') ?: 'eur');
$BITCOIN_RPC_URL = getenv('BITCOIN_RPC_URL') ?: '';
$BITCOIN_RPC_USER = getenv('BITCOIN_RPC_USER') ?: '';
$BITCOIN_RPC_PASS = getenv('BITCOIN_RPC_PASS') ?: '';


/**
 * PDO connection.
 * Exceptions are enabled so database errors are handled consistently.
 */

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $DB_HOST,
    $DB_PORT,
    $DB_NAME
);

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('PDO connection error: ' . $e->getMessage());
    http_response_code(503);

    if (request_wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        $payload = $isProduction
            ? ['error' => 'В настоящий момент сервер базы данных недоступен.']
            : ['error' => 'В настоящий момент сервер базы данных недоступен: ' . $e->getMessage()];
        exit(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    exit('<p>В настоящий момент сервер базы данных недоступен, поэтому корректное отображение страницы невозможно.</p>');
}
