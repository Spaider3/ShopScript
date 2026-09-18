<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
start_secure_session();

const UPLOAD_DIR = __DIR__ . '/../images/upload_profiles';
const MAX_SIZE = 2 * 1024 * 1024; // 2 МБ
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/**
 * Генерирует уникальное имя файла.
 */
function uniqueName(string $ext): string
{
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

/**
 * Возвращает JSON-ответ и завершает выполнение скрипта.
 */
function respond(array $data, int $status = 200): never
{
    json_response($data, $status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не разрешён'], 405);
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    respond(['error' => 'Необходимо войти в аккаунт.'], 401);
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    respond(['error' => 'Сессия устарела. Обновите страницу и повторите попытку.'], 403);
}

$userId = (int)$_SESSION['user_id'];
$file = $_FILES['avatar'] ?? null;

if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond(['error' => 'Выберите изображение для загрузки.'], 400);
}

if ((int)$file['size'] > MAX_SIZE) {
    respond(['error' => 'Файл слишком большой. Максимум 2 МБ.'], 400);
}

$tmpName = (string)$file['tmp_name'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmpName);

if (!isset(ALLOWED_MIME[$mime])) {
    respond(['error' => 'Недопустимый формат. Разрешены JPEG, PNG, WebP и GIF.'], 400);
}

if (@getimagesize($tmpName) === false) {
    respond(['error' => 'Файл не является корректным изображением.'], 400);
}

if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
    respond(['error' => 'Не удалось подготовить каталог для аватара.'], 500);
}

try {
    $fileName = save_compressed_image($tmpName, UPLOAD_DIR, 'avatar', 210, 210);
} catch (Throwable $e) {
    respond(['error' => 'Не удалось обработать изображение.'], 500);
}
$relativePath = 'images/upload_profiles/' . $fileName;

require_once __DIR__ . '/../config.php';

$stmt = $pdo->prepare('SELECT Photo FROM profiles WHERE Number = ? LIMIT 1');
$stmt->execute([$userId]);
$oldPhoto = $stmt->fetchColumn();

$stmt = $pdo->prepare('UPDATE profiles SET Photo = ? WHERE Number = ?');
$stmt->execute([$relativePath, $userId]);

if ($oldPhoto && $oldPhoto !== 'images/nophoto.jpg') {
    $oldFullPath = __DIR__ . '/../' . ltrim((string)$oldPhoto, '/');
    if (is_file($oldFullPath) && dirname($oldFullPath) === UPLOAD_DIR) {
        @unlink($oldFullPath);
    }
}

respond([
    'status' => 'success',
    'message' => 'Аватар успешно обновлён.',
    'photo' => $relativePath,
]);
