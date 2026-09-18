<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/kcaptcha.php';

$sessionKey = session_name();
$hasSessionId = isset($_REQUEST[$sessionKey]) && $_REQUEST[$sessionKey] !== '';

if ($hasSessionId && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$captcha = new KCAPTCHA();

if ($hasSessionId) {
    $_SESSION['captcha_keystring'] = $captcha->getKeyString();
}
?>
