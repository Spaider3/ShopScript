<?php
declare(strict_types=1);

/**
 * Опциональный адаптер php-bitcoinrpc-2.2.x.
 *
 * Модуль по умолчанию выключен. Включение хранится в site_modules,
 * а RPC credentials берутся только из ENV: BITCOIN_RPC_URL/USER/PASS.
 * Само наличие библиотеки не означает, что RPC доступен.
 */
function bitcoin_rpc_enabled(PDO $pdo): bool
{
    static $enabled;
    if ($enabled !== null) return $enabled;
    try {
        $stmt = $pdo->prepare("SELECT enabled FROM site_modules WHERE name = 'bitcoinrpc' LIMIT 1");
        $stmt->execute();
        $enabled = (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        $enabled = false;
    }
    return $enabled;
}

function bitcoin_rpc_client(PDO $pdo)
{
    if (!bitcoin_rpc_enabled($pdo)) {
        throw new RuntimeException('Модуль Bitcoin RPC отключён.');
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    $clientFile = __DIR__ . '/../php-bitcoinrpc-2.2.x/src/Client.php';
    if (is_file($autoload)) require_once $autoload;
    if (!class_exists('\Denpa\Bitcoin\Client') && is_file($clientFile)) {
        // Пакет использует Guzzle/PSR и штатно должен подключаться через Composer.
        // Не пытаемся самовольно подгружать зависимости из tests или dev-папок.
        require_once $clientFile;
    }

    if (!class_exists('\Denpa\Bitcoin\Client')) {
        throw new RuntimeException('php-bitcoinrpc-2.2.x или его Composer-зависимости не установлены.');
    }

    $url = trim((string)(getenv('BITCOIN_RPC_URL') ?: ''));
    $user = (string)(getenv('BITCOIN_RPC_USER') ?: '');
    $pass = (string)(getenv('BITCOIN_RPC_PASS') ?: '');
    if ($url === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Не настроены BITCOIN_RPC_URL/USER/PASS.');
    }

    return new \Denpa\Bitcoin\Client([
        'url' => $url,
        'username' => $user,
        'password' => $pass,
        'timeout' => 10,
    ]);
}
