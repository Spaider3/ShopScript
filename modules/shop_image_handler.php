<?php
declare(strict_types=1);

/**
 * Качественный загрузчик изображений для магазина.
 *
 * Что делает:
 *  - валидирует загружаемый файл (размер, MIME по содержимому, целостность изображения);
 *  - защищает от «декомпрессионных бомб» (огромные картинки, съедающие память);
 *  - исправляет EXIF-ориентацию JPEG (фото со смартфонов не ложатся на бок);
 *  - кадрирует изображение по центру в квадрат и приводит к 400×400 px
 *    (ShopImageHandler::PRODUCT_IMAGE_SIZE);
 *  - уменьшает через imagecopyresampled и слегка повышает резкость,
 *    чтобы миниатюра оставалась чёткой;
 *  - сохраняет с высоким качеством (JPEG 88 progressive, WebP 85, PNG 6),
 *    поддерживая прозрачность PNG/WebP/GIF;
 *  - генерирует уникальное имя файла и кладёт его в images/shop_products.
 *
 * Использование:
 *   $handler = new ShopImageHandler();
 *   $result  = $handler->process($_FILES['product_photo'] ?? null);
 *   if ($result['ok']) { $relativePath = $result['path']; }
 */

final class ShopImageHandler
{
    /** Каталог, куда сохраняются изображения товаров (относительно корня сайта). */
    public const UPLOAD_DIR = 'images/shop_products';

    /** Максимальный размер файла — 5 МБ. */
    public const MAX_SIZE = 5 * 1024 * 1024;

    /** Допустимые MIME-типы и соответствующие расширения. */
    public const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /** Сторона квадратного фото товара после обработки. */
    public const PRODUCT_IMAGE_SIZE = 400;

    /** Качество JPEG при сохранении. */
    public const JPEG_QUALITY = 88;

    /** Ограничения на исходник: защита памяти от гигантских изображений. */
    private const MAX_SOURCE_SIDE = 12000;
    private const MAX_SOURCE_PIXELS = 40_000_000; // ~40 Мп

    private string $rootDir;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = rtrim((string)($rootDir ?? dirname(__DIR__)), DIRECTORY_SEPARATOR);
    }

    /**
     * Обрабатывает загруженный файл: валидация → EXIF-поворот →
     * квадратный кроп по центру → 400×400 → сохранение.
     *
     * @param array|null $file Элемент из $_FILES.
     *
     * @return array{ok: bool, path?: string, name?: string, error?: string}
     */
    public function process(?array $file): array
    {
        if (!is_array($file)) {
            return ['ok' => false, 'error' => 'Файл не был передан.'];
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->uploadErrorMessage($error)];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'error' => 'Файл пустой.'];
        }
        if ($size > self::MAX_SIZE) {
            return ['ok' => false, 'error' => 'Файл слишком большой. Максимум 5 МБ.'];
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'error' => 'Некорректный файл.'];
        }

        return $this->processPath($tmpName);
    }

    /**
     * Обрабатывает файл по пути на диске (ядро обработки без проверки
     * is_uploaded_file — удобно для тестов и внутренних импортов).
     *
     * @return array{ok: bool, path?: string, name?: string, error?: string}
     */
    public function processPath(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            return ['ok' => false, 'error' => 'Файл не найден.'];
        }

        // Проверка MIME по реальному содержимому, а не по расширению.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($path);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Недопустимый формат. Разрешены JPEG, PNG, WebP и GIF.'];
        }

        // Быстрая проверка заголовков + габаритов до загрузки в память.
        $dimensions = @getimagesize($path);
        if ($dimensions === false) {
            return ['ok' => false, 'error' => 'Файл не является корректным изображением.'];
        }
        [$srcWidth, $srcHeight] = $dimensions;
        if ($srcWidth < 1 || $srcHeight < 1) {
            return ['ok' => false, 'error' => 'Некорректные размеры изображения.'];
        }
        if ($srcWidth > self::MAX_SOURCE_SIDE || $srcHeight > self::MAX_SOURCE_SIDE || $srcWidth * $srcHeight > self::MAX_SOURCE_PIXELS) {
            return ['ok' => false, 'error' => 'Изображение слишком большое. Максимум 12000×12000 px.'];
        }

        try {
            $fileName = $this->saveImage($path, $mime);
        } catch (Throwable $e) {
            error_log('ShopImageHandler: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Не удалось обработать изображение.'];
        }

        return [
            'ok'   => true,
            'path' => self::UPLOAD_DIR . '/' . $fileName,
            'name' => $fileName,
        ];
    }

    /**
     * Полный конвейер обработки: загрузка → ориентация → кроп → ресемплинг → запись.
     */
    private function saveImage(string $tmpFile, string $mime): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD недоступна на сервере.');
        }

        $raw = @file_get_contents($tmpFile);
        if ($raw === false) {
            throw new RuntimeException('Не удалось прочитать изображение.');
        }

        $src = @imagecreatefromstring($raw);
        if (!$src instanceof \GdImage) {
            throw new RuntimeException('Некорректное изображение.');
        }

        try {
            // Фото со смартфонов часто сохраняются повёрнутыми — исправляем по EXIF.
            $src = $this->applyExifOrientation($src, $tmpFile, $mime);

            $width = imagesx($src);
            $height = imagesy($src);

            // Квадратный кроп по центру: берём меньшую сторону.
            $side = min($width, $height);
            $srcX = (int)floor(($width - $side) / 2);
            $srcY = (int)floor(($height - $side) / 2);

            $targetSize = self::PRODUCT_IMAGE_SIZE;
            $dst = imagecreatetruecolor($targetSize, $targetSize);

            // Сохраняем прозрачность для PNG/WebP/GIF.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));

            // Высококачественный ресемплинг (бикубический).
            imagecopyresampled(
                $dst,
                $src,
                0,
                0,
                $srcX,
                $srcY,
                $targetSize,
                $targetSize,
                $side,
                $side
            );

            // При сильном уменьшении картинка «мылится» — добавляем лёгкую резкость.
            if ($side >= $targetSize * 2) {
                $this->sharpen($dst);
            }

            // Уникальное имя: префикс + случайные байты + расширение исходника.
            $ext = self::ALLOWED_MIME[$mime] ?? 'jpg';
            $fileName = 'shop_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $target = $this->rootDir . DIRECTORY_SEPARATOR . self::UPLOAD_DIR . DIRECTORY_SEPARATOR . $fileName;

            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Не удалось создать каталог изображений.');
            }

            $saved = $this->writeImage($dst, $target, $ext);
            if (!$saved) {
                throw new RuntimeException('Не удалось сохранить изображение.');
            }

            return $fileName;
        } finally {
            // imagedestroy() больше не нужен: начиная с PHP 8.0 GD-изображения — это
            // объекты (\GdImage), которые освобождаются сборщиком мусора автоматически.
            // Сам вызов с PHP 8.5 объявлен deprecated, поэтому просто убираем его,
            // а не оборачиваем в @ (это лишь скрыло бы предупреждение, не устранив причину).
        }
    }

    /**
     * Поворачивает/отражает JPEG согласно EXIF-ориентации.
     * Для не-JPEG или без расширения exif возвращает изображение как есть.
     */
    private function applyExifOrientation(\GdImage $src, string $tmpFile, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $src;
        }

        $exif = @exif_read_data($tmpFile);
        if (!is_array($exif) || empty($exif['Orientation'])) {
            return $src;
        }

        $orientation = (int)$exif['Orientation'];
        $rotated = null;

        switch ($orientation) {
            case 2: // зеркально по горизонтали
                imageflip($src, IMG_FLIP_HORIZONTAL);
                break;
            case 3: // 180°
                $rotated = imagerotate($src, 180, 0);
                break;
            case 4: // зеркально по вертикали
                imageflip($src, IMG_FLIP_VERTICAL);
                break;
            case 5: // 90° против часовой + зеркало
                $rotated = imagerotate($src, -90, 0);
                if ($rotated instanceof \GdImage) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 6: // 90° по часовой
                $rotated = imagerotate($src, -90, 0);
                break;
            case 7: // 90° по часовой + зеркало
                $rotated = imagerotate($src, 90, 0);
                if ($rotated instanceof \GdImage) {
                    imageflip($rotated, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 8: // 90° против часовой
                $rotated = imagerotate($src, 90, 0);
                break;
            default:
                return $src;
        }

        if ($rotated instanceof \GdImage) {
            // imagedestroy() не нужен по той же причине, что и выше — GD-объект
            // освобождается сам, а вызов на PHP 8.5 считается deprecated.
            return $rotated;
        }

        return $src;
    }

    /**
     * Лёгкое повышение резкости (unsharp-подобная свёртка 3×3).
     */
    private function sharpen(\GdImage $image): void
    {
        if (!function_exists('imageconvolution')) {
            return;
        }

        $matrix = [
            [0.0, -0.25, 0.0],
            [-0.25, 2.0, -0.25],
            [0.0, -0.25, 0.0],
        ];
        // Сумма матрицы равна 1.0 — яркость не меняется, только контраст деталей.
        @imageconvolution($image, $matrix, 1.0, 0);
    }

    /**
     * Сохраняет ресурс в файл в зависимости от формата.
     */
    private function writeImage(\GdImage $image, string $target, string $ext): bool
    {
        switch ($ext) {
            case 'png':
                return (bool)imagepng($image, $target, 6);
            case 'webp':
                return function_exists('imagewebp') ? (bool)imagewebp($image, $target, 85) : false;
            case 'gif':
                return (bool)imagegif($image, $target);
            case 'jpg':
            default:
                // Progressive JPEG лучше выглядит при постепенной загрузке страницы.
                imageinterlace($image, true);
                return (bool)imagejpeg($image, $target, self::JPEG_QUALITY);
        }
    }

    /**
     * Возвращает человекочитаемое сообщение об ошибке загрузки.
     */
    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой.',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен частично.',
            UPLOAD_ERR_NO_FILE => 'Выберите изображение для загрузки.',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере отсутствует временная папка.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением.',
            default => 'Неизвестная ошибка загрузки файла.',
        };
    }
}