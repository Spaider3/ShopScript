<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, stale-while-revalidate=60');

$cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'photovote_crypto_rates.json';
$cacheTtl = 30;

if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $cacheTtl) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

$url = 'https://api.coingecko.com/api/v3/simple/price?' . http_build_query([
    'ids' => 'bitcoin,ethereum,ripple',
    'vs_currencies' => 'eur',
    'include_24hr_change' => 'true',
    'include_last_updated_at' => 'true',
]);

$ch = curl_init($url);
$headers = ['Accept: application/json'];
$apiKey = trim((string)(getenv('COINGECKO_API_KEY') ?: ''));

if ($apiKey !== '') {
    // Для Demo API CoinGecko использует этот заголовок; для Pro его можно
    // заменить на x-cg-pro-api-key в окружении/конфигурации при необходимости.
    $headers[] = 'x-cg-demo-api-key: ' . $apiKey;
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'PhotoVote/1.0 crypto-rates',
]);

$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false || $curlError !== '' || $status < 200 || $status >= 300) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Не удалось получить курс криптовалют.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode((string)$body, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Сервис курсов вернул некорректные данные.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = [
    'currency' => 'EUR',
    'updated_at' => time(),
    'bitcoin' => [
        'price' => isset($data['bitcoin']['eur']) ? (float)$data['bitcoin']['eur'] : null,
        'change_24h' => isset($data['bitcoin']['eur_24h_change']) ? (float)$data['bitcoin']['eur_24h_change'] : null,
        'source_updated_at' => isset($data['bitcoin']['last_updated_at']) ? (int)$data['bitcoin']['last_updated_at'] : null,
    ],
    'ethereum' => [
        'price' => isset($data['ethereum']['eur']) ? (float)$data['ethereum']['eur'] : null,
        'change_24h' => isset($data['ethereum']['eur_24h_change']) ? (float)$data['ethereum']['eur_24h_change'] : null,
        'source_updated_at' => isset($data['ethereum']['last_updated_at']) ? (int)$data['ethereum']['last_updated_at'] : null,
    ],
    'xrp' => [
        'price' => isset($data['ripple']['eur']) ? (float)$data['ripple']['eur'] : null,
        'change_24h' => isset($data['ripple']['eur_24h_change']) ? (float)$data['ripple']['eur_24h_change'] : null,
        'source_updated_at' => isset($data['ripple']['last_updated_at']) ? (int)$data['ripple']['last_updated_at'] : null,
    ],
];

$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка формирования ответа.'], JSON_UNESCAPED_UNICODE);
    exit;
}

@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
