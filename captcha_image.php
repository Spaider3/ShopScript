<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
require_once __DIR__ . '/kcaptcha/kcaptcha.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$captcha = new KCAPTCHA();
$_SESSION['captcha_keystring'] = $captcha->getKeyString();
