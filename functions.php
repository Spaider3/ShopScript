<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Хранит сессии в БД (таблица `sessions`), а не в файлах на диске.
 *
 * Почему это нужно: на Windows/OSPanel файлы сессий (по умолчанию —
 * системная временная папка) периодически исчезают между запросами —
 * их подчищает антивирус или сторонние "уборщики" временных файлов.
 * Поскольку у нас включён session.use_strict_mode, PHP в такой ситуации
 * не восстанавливает потерянную сессию, а молча создаёт новую пустую —
 * из-за этого CSRF-токен переставал совпадать со значением в сессии
 * СЛУЧАЙНО, без всякой закономерности (именно так, как и наблюдалось).
 * Хранение в БД снимает зависимость от файловой системы Windows.
 */
final class PdoSessionHandler implements \SessionHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetchColumn();

        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, last_activity) VALUES (:id, :data, :ts)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
        );

        return $stmt->execute([':id' => $id, ':data' => $data, ':ts' => time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < :cutoff');
        $stmt->execute([':cutoff' => time() - $max_lifetime]);

        return $stmt->rowCount();
    }
}

function ensure_sessions_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL,
            data MEDIUMTEXT NOT NULL,
            last_activity INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_sessions_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ensured = true;
}

function start_secure_session(): void
{
    global $pdo;

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Единое имя сессии для всего сайта. Его можно переопределить через ENV,
    // чтобы исключить конфликт с другой установкой PHP на том же домене.
    $sessionName = (string)(getenv('SESSION_NAME') ?: 'HYDRASESSID');
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionName)) {
        session_name($sessionName);
    }

    // Если сайт работает за reverse proxy (Nginx/Cloudflare/etc.),
    // HTTPS может находиться в X-Forwarded-Proto, а не в $_SERVER['HTTPS'].
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

    // На локальных панелях (OSPanel/OpenServer и т.п.) $_SERVER['HTTPS'] или
    // X-Forwarded-Proto иногда выставляются некорректно внутренними
    // rewrite-правилами панели, даже когда сайт реально открыт по чистому
    // http://. Если куке сессии в такой ситуации ошибочно выставить флаг
    // Secure, браузер ПЕРЕСТАЁТ отправлять её по http — сессия и CSRF-токен
    // молча теряются на каждом запросе (именно так, как и наблюдалось).
    // Поэтому для распознанных локальных dev-хостов Secure отключаем
    // принудительно — та же логика хостов, что и в is_production_environment()
    // из config.php.
    $httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $httpHost = explode(':', $httpHost)[0];
    $isLocalDevHost = in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($httpHost, '.local')
        || str_ends_with($httpHost, '.test');

    $secure = !$isLocalDevHost && (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
    );

    // Явно задаём параметры до session_start(), чтобы PHP не подхватывал
    // несовместимые настройки хостинга.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cache_limiter', 'nocache');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Сессии храним в БД, а не в файлах — см. комментарий к PdoSessionHandler.
    if ($pdo instanceof PDO) {
        ensure_sessions_table($pdo);
        session_set_save_handler(new PdoSessionHandler($pdo), true);
    }

    if (!session_start()) {
        error_log('Unable to start PHP session. session.save_path=' . (string)ini_get('session.save_path'));
        http_response_code(503);
        exit('Не удалось запустить сессию. Проверьте настройки PHP-сессий на сервере.');
    }

    // Если хостинг вернул другой статус, лучше остановить запрос сразу,
    // чем продолжать с пустой $_SESSION и получать ложные ошибки CSRF/CAPTCHA.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        error_log('PHP session is not active after session_start(). save_path=' . (string)ini_get('session.save_path'));
        http_response_code(503);
        exit('Сессия недоступна. Проверьте session.save_path на сервере.');
    }
}


/**
 * Гарантирует наличие роли в таблице `users` для профиля из `profiles`.
 * Роль не назначается заново, если запись уже есть — иначе права,
 * выданные администратором (moderator/admin), сбрасывались бы обратно
 * на 'user' при каждом входе. Профилям без логина (например, карточкам
 * команды, добавленным через старый редактор без учётной записи) запись
 * в users не нужна — get_user_role() и так возвращает 'user' по умолчанию,
 * если строки нет.
 */
function ensure_user_role(PDO $pdo, int $userId, string $login): void
{
    if ($userId <= 0) {
        return;
    }

    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $userId]);
    if ($checkStmt->fetchColumn() !== false) {
        return;
    }

    $login = trim($login);
    if ($login === '') {
        return;
    }

    $roleId = (int)($pdo->query("SELECT id FROM roles WHERE name = 'user' LIMIT 1")->fetchColumn() ?: 3);

    $stmt = $pdo->prepare(
        'INSERT INTO users (id, username, role_id) VALUES (:id, :username, :role_id)
         ON DUPLICATE KEY UPDATE id = VALUES(id)'
    );
    $stmt->execute([
        ':id' => $userId,
        ':username' => mb_substr($login, 0, 50, 'UTF-8'),
        ':role_id' => $roleId,
    ]);
}

function csrf_token(): string
{
    start_secure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    start_secure_session();

    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function validate_login(string $login): ?string
{
    if ($login === '') {
        return 'Введите логин.';
    }

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $login)) {
        return 'Логин должен содержать 3–30 символов: латиница, цифры и _.';
    }

    return null;
}

function validate_name(string $name): ?string
{
    if ($name === '') {
        return 'Введите имя пользователя.';
    }

    if (mb_strlen($name, 'UTF-8') > 100) {
        return 'Имя пользователя должно быть не длиннее 100 символов.';
    }

    return null;
}

/**
 * Проверяет логин и пароль пользователя против таблицы profiles.
 * Возвращает строку профиля при успехе, либо null при неверных данных.
 */
function authenticate_profile(PDO $pdo, string $login, string $password): ?array
{
    if (validate_login($login) !== null || $password === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT Number, User, UserLogin, Password, banned
         FROM profiles
         WHERE UserLogin = ?
         LIMIT 1'
    );
    $stmt->execute([$login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)($row['banned'] ?? 0) === 1 || !password_verify($password, (string)$row['Password'])) {
        return null;
    }

    return $row;
}

function mark_profile_online(PDO $pdo, int $profileNumber): void
{
    $stmt = $pdo->prepare('UPDATE profiles SET last_seen = ?, is_online = 1 WHERE Number = ?');
    $stmt->execute([date('Y-m-d H:i:s'), $profileNumber]);
}

/**
 * Записывает данные пользователя в сессию после успешного входа.
 * $row должен быть строкой из profiles с ключами Number, User, UserLogin.
 */
function start_user_session(array $row): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$row['Number'];
    $_SESSION['user_name'] = (string)$row['User'];
    $_SESSION['user_login'] = (string)$row['UserLogin'];
    $_SESSION['logged_in'] = true;
}

/**
 * Возвращает относительный путь к аватару, который реально существует на диске.
 * Если сохранённый в БД путь пуст или файла больше нет — возвращает заглушку.
 */
function resolve_avatar(?string $photo): string
{
    $photo = trim((string)$photo);

    if ($photo !== '' && is_file(__DIR__ . '/' . ltrim($photo, '/'))) {
        return $photo;
    }

    return 'images/nophoto.jpg';
}

/**
 * Возвращает количество непрочитанных личных сообщений пользователя.
 * Учитываются только сообщения с заполненным receiver_id (т.е. отправленные
 * после появления ID-сопоставления переписки) — у более старых сообщений
 * нет надёжной привязки к получателю по ID.
 */


function current_user_id(): int
{
    start_secure_session();
    return !empty($_SESSION['logged_in']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
}

function user_is_banned(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) return false;
    $stmt = $pdo->prepare('SELECT banned FROM profiles WHERE Number = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() === 1;
}

/**
 * Сохраняет изображение с уникальным именем, JPEG-сжатием и безопасным размером.
 * Для аватара используется квадратная обрезка 210x210.
 */
function save_compressed_image(string $tmpFile, string $targetDir, string $prefix = 'img', ?int $cropSize = null, int $maxDimension = 1600): string
{
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('GD недоступна на сервере.');
    }

    $raw = @file_get_contents($tmpFile);
    if ($raw === false) throw new RuntimeException('Не удалось прочитать изображение.');
    $src = @imagecreatefromstring($raw);
    if (!$src) throw new RuntimeException('Некорректное изображение.');

    $width = imagesx($src);
    $height = imagesy($src);
    if ($width < 1 || $height < 1) {
        throw new RuntimeException('Некорректные размеры изображения.');
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Не удалось создать каталог изображений.');
    }

    $dstW = $width;
    $dstH = $height;
    $srcX = 0;
    $srcY = 0;
    $srcW = $width;
    $srcH = $height;

    if ($cropSize !== null) {
        $side = min($width, $height);
        $srcX = (int)floor(($width - $side) / 2);
        $srcY = (int)floor(($height - $side) / 2);
        $srcW = $side;
        $srcH = $side;
        $dstW = $dstH = $cropSize;
    } else {
        $scale = min(1.0, $maxDimension / max($width, $height));
        $dstW = max(1, (int)round($width * $scale));
        $dstH = max(1, (int)round($height * $scale));
    }

    $dst = imagecreatetruecolor($dstW, $dstH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);

    $fileName = $prefix . '_' . bin2hex(random_bytes(12)) . '.jpg';
    $target = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    if (!imagejpeg($dst, $target, 82)) {
        throw new RuntimeException('Не удалось сохранить изображение.');
    }

    // imagedestroy() не используется: с PHP 8.0 GD-объекты (\GdImage) освобождаются
    // сборщиком мусора автоматически, а сам вызов начиная с PHP 8.5 — deprecated.
    return $fileName;
}

function calculate_user_rating(PDO $pdo, int $userId): int
{
    if ($userId <= 0) return 0;

    // Рейтинг = количество фактически оплаченных единиц товара.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(oi.quantity), 0)
           FROM shop_order_items oi
           JOIN shop_orders o ON o.id = oi.order_id
          WHERE o.buyer_id = ?
            AND o.status = 'paid'"
    );
    $stmt->execute([$userId]);
    $purchases = (int)$stmt->fetchColumn();

    return max(0, $purchases);
}

function refresh_user_rating(PDO $pdo, int $userId): int
{
    $rating = calculate_user_rating($pdo, $userId);
    $stmt = $pdo->prepare('UPDATE profiles SET Rating = ? WHERE Number = ?');
    $stmt->execute([$rating, $userId]);
    return $rating;
}

function refresh_all_user_ratings(PDO $pdo): void
{
    // Одним SQL-запросом обновляем рейтинг всех пользователей, чтобы панель
    // администратора не выполняла N+1 запросов.
    $pdo->exec(
        "UPDATE profiles p
         LEFT JOIN (
             SELECT o.buyer_id, SUM(oi.quantity) AS new_rating
               FROM shop_orders o
               JOIN shop_order_items oi ON oi.order_id = o.id
              WHERE o.status = 'paid'
              GROUP BY o.buyer_id
         ) x ON x.buyer_id = p.Number
            SET p.Rating = COALESCE(x.new_rating, 0)"
    );
}

function count_unread_messages(PDO $pdo, int $viewerId): int
{
    if ($viewerId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM messages WHERE receiver_id = :viewer AND is_read = 0'
    );
    $stmt->execute([':viewer' => $viewerId]);

    return (int)$stmt->fetchColumn();
}

/**
 * Отмечает как прочитанные все сообщения, отправленные $fromId в адрес $toId.
 */
function mark_messages_read(PDO $pdo, int $fromId, int $toId): void
{
    if ($fromId <= 0 || $toId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE messages SET is_read = 1
          WHERE sender_id = :from AND receiver_id = :to AND is_read = 0'
    );
    $stmt->execute([':from' => $fromId, ':to' => $toId]);
}

/**
 * Защита от подбора пароля (brute force).
 *
 * Считает неудачные попытки входа за последние 15 минут отдельно по логину
 * и отдельно по IP-адресу. Если любой из счётчиков достиг лимита — вход
 * временно блокируется, даже если пара логин/пароль верна. Это не даёт
 * перебирать пароли ни по одному аккаунту, ни массово с одного IP.
 */
const LOGIN_THROTTLE_WINDOW_SECONDS = 900; // 15 минут
const LOGIN_THROTTLE_MAX_ATTEMPTS = 5;

function ensure_login_attempts_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(20) NOT NULL,
            identifier VARCHAR(191) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_attempts_lookup (scope, identifier, created_at),
            KEY idx_login_attempts_ip (scope, ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ensured = true;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? substr($ip, 0, 45) : '0.0.0.0';
}

/**
 * Возвращает true, если для данного логина или IP превышен лимит неудачных
 * попыток за последние 15 минут — в этом случае вход нужно отклонить,
 * не проверяя пароль.
 */
function login_is_throttled(PDO $pdo, string $scope, string $identifier): bool
{
    // В локальном окружении разработки (localhost/mysite.local и т.п.)
    // защита от подбора пароля отключена автоматически — она только мешает
    // при ручном тестировании входа. На проде эта функция по-прежнему
    // работает по обычным правилам ниже.
    if (!is_production_environment()) {
        return false;
    }

    if (!is_login_throttle_enabled($pdo)) {
        return false;
    }

    ensure_login_attempts_table($pdo);
    $ip = client_ip();
    $since = date('Y-m-d H:i:s', time() - LOGIN_THROTTLE_WINDOW_SECONDS);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE scope = :scope AND success = 0 AND created_at >= :since
            AND (identifier = :identifier OR ip = :ip)'
    );
    $stmt->execute([
        ':scope' => $scope,
        ':since' => $since,
        ':identifier' => $identifier,
        ':ip' => $ip,
    ]);

    return (int)$stmt->fetchColumn() >= LOGIN_THROTTLE_MAX_ATTEMPTS;
}

/**
 * Включена ли защита от подбора пароля (троттлинг входа). По умолчанию —
 * да, это безопасное значение по умолчанию: пока администратор явно не
 * отключил защиту в панели, она работает.
 */
function is_login_throttle_enabled(PDO $pdo): bool
{
    ensure_site_settings_table($pdo);

    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => 'login_throttle_enabled']);
    $value = $stmt->fetchColumn();

    // Безопасное значение по умолчанию (пока запись отсутствует, т.е. админ ни
    // разу не трогал переключатель) — защита ВКЛЮЧЕНА. Раньше здесь было
    // наоборот (return $value === '1'), из-за чего на свежей установке форма
    // входа была не защищена от подбора пароля, пока администратор сам не
    // зайдёт в панель и не включит защиту явно — вопреки комментарию к этой
    // функции, который как раз обещал безопасный дефолт.
    return $value !== '0';
}

function set_login_throttle_enabled(PDO $pdo, bool $enabled): void
{
    ensure_site_settings_table($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':key' => 'login_throttle_enabled', ':value' => $enabled ? '1' : '0']);
}

function record_login_attempt(PDO $pdo, string $scope, string $identifier, bool $success): void
{
    ensure_login_attempts_table($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (scope, identifier, ip, success) VALUES (:scope, :identifier, :ip, :success)'
    );
    $stmt->execute([
        ':scope' => $scope,
        ':identifier' => mb_substr($identifier, 0, 191, 'UTF-8'),
        ':ip' => client_ip(),
        ':success' => $success ? 1 : 0,
    ]);

    // Удачный вход обнуляет счётчик неудач по этому логину, чтобы не держать
    // пользователя заблокированным после того, как он всё же ввёл верный пароль.
    if ($success) {
        $clear = $pdo->prepare(
            'DELETE FROM login_attempts WHERE scope = :scope AND success = 0 AND identifier = :identifier'
        );
        $clear->execute([':scope' => $scope, ':identifier' => mb_substr($identifier, 0, 191, 'UTF-8')]);
    }
}

/**
 * Название сайта, настраиваемое администратором.
 *
 * Хранится как обычная пара ключ/значение, чтобы потом можно было добавить
 * другие общесайтовые настройки без новой таблицы под каждую.
 */
const SITE_NAME_DEFAULT = 'PhotoVote';
const SITE_NAME_MAX_LENGTH = 60;

function ensure_site_settings_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) NOT NULL,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ensured = true;
}

function get_site_name(PDO $pdo): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    ensure_site_settings_table($pdo);

    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => 'site_name']);
    $value = $stmt->fetchColumn();

    $cached = $value !== false && trim((string)$value) !== '' ? (string)$value : SITE_NAME_DEFAULT;
    return $cached;
}

/**
 * @throws RuntimeException если название не проходит проверку
 */
function set_site_name(PDO $pdo, string $name): void
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Название сайта не может быть пустым.');
    }
    if (mb_strlen($name, 'UTF-8') > SITE_NAME_MAX_LENGTH) {
        throw new RuntimeException('Название сайта не может быть длиннее ' . SITE_NAME_MAX_LENGTH . ' символов.');
    }
    // Запрещаем управляющие символы и переносы строк — название выводится
    // в <title>, заголовках страниц и меню, многострочный текст там сломает вёрстку.
    if (preg_match('/[\x00-\x1F\x7F]/', $name)) {
        throw new RuntimeException('Название сайта содержит недопустимые символы.');
    }

    ensure_site_settings_table($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':key' => 'site_name', ':value' => $name]);
}

/**
 * "Секретный" вход администратора — отдельная от обычных пользователей учётная
 * запись (логин + пароль), которая даёт полный доступ к admin.php независимо
 * от роли в profiles/users. Пока администратор её не задал через панель,
 * используется резервная пара ADMIN_NAME/ADMIN_PASS из окружения (см.
 * config.php) — это гарантирует, что после обновления никто не окажется
 * отрезан от панели. Как только запись в БД появляется, она полностью
 * заменяет собой переменные окружения.
 */
const ADMIN_LOGIN_MIN_LENGTH = 3;
const ADMIN_LOGIN_MAX_LENGTH = 50;
const ADMIN_PASSWORD_MIN_LENGTH = 10;

function ensure_admin_account_table(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_account (
            id TINYINT UNSIGNED NOT NULL,
            login VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // CREATE TABLE IF NOT EXISTS не трогает таблицу, если она уже была создана
    // (например, импортом старого дампа) с колонкой `admin` вместо `login`.
    // Подчищаем это несоответствие схемы автоматически, чтобы запросы ниже
    // не падали с "Unknown column 'login' in 'field list'".
    $hasLogin = (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_account' AND COLUMN_NAME = 'login'"
    )->fetchColumn();

    if (!$hasLogin) {
        $hasOldAdminColumn = (bool)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_account' AND COLUMN_NAME = 'admin'"
        )->fetchColumn();

        if ($hasOldAdminColumn) {
            $pdo->exec('ALTER TABLE admin_account CHANGE `admin` `login` VARCHAR(50) NOT NULL');
        } else {
            $pdo->exec('ALTER TABLE admin_account ADD COLUMN `login` VARCHAR(50) NOT NULL AFTER `id`');
        }
    }

    $ensured = true;
}

/**
 * @return array{login: string, password_hash: string}|null null — если логин/пароль ещё не заданы через панель
 */
function get_admin_credentials(PDO $pdo): ?array
{
    ensure_admin_account_table($pdo);

    $stmt = $pdo->prepare('SELECT login, password_hash FROM admin_account WHERE id = 1 LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return ['login' => (string)$row['login'], 'password_hash' => (string)$row['password_hash']];
}

/**
 * @throws RuntimeException если логин или пароль не проходят проверку
 */
function set_admin_credentials(PDO $pdo, string $login, string $password): void
{
    $login = trim($login);
    $loginLength = mb_strlen($login, 'UTF-8');
    if ($loginLength < ADMIN_LOGIN_MIN_LENGTH || $loginLength > ADMIN_LOGIN_MAX_LENGTH) {
        throw new RuntimeException(
            'Логин должен быть от ' . ADMIN_LOGIN_MIN_LENGTH . ' до ' . ADMIN_LOGIN_MAX_LENGTH . ' символов.'
        );
    }
    if (preg_match('/[\x00-\x1F\x7F\s]/', $login)) {
        throw new RuntimeException('Логин не должен содержать пробелы и управляющие символы.');
    }
    if (mb_strlen($password, 'UTF-8') < ADMIN_PASSWORD_MIN_LENGTH) {
        throw new RuntimeException('Пароль должен быть не короче ' . ADMIN_PASSWORD_MIN_LENGTH . ' символов.');
    }

    ensure_admin_account_table($pdo);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_account (id, login, password_hash) VALUES (1, :login, :hash)
         ON DUPLICATE KEY UPDATE login = VALUES(login), password_hash = VALUES(password_hash)'
    );
    $stmt->execute([':login' => $login, ':hash' => $hash]);
}
