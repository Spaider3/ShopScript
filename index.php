<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
start_secure_session();
$siteName = get_site_name($pdo);
$csrf = csrf_token();
$viewerId = current_user_id();
$viewerName = (string)($_SESSION['user_name'] ?? '');
if ($viewerId > 0) {
    $stmt = $pdo->prepare('SELECT User, Photo, Rating FROM profiles WHERE Number = ? LIMIT 1');
    $stmt->execute([$viewerId]);
    $me = $stmt->fetch();
    if (!$me) { $viewerId = 0; }
    else { $viewerName = (string)$me['User']; }
}
?><!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title><link rel="icon" href="favicon.ico" type="image/x-icon"><link rel="stylesheet" href="css/style_reg.css"><style>
body{background:radial-gradient(circle at top,#eef2ff,#f7f8fb 45%,#eef1f5);}.landing{max-width:560px}.hero-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}.hero-actions button,.hero-actions a{min-width:160px;text-align:center;text-decoration:none}.modal{position:fixed;inset:0;background:rgba(15,23,42,.58);display:none;align-items:center;justify-content:center;padding:20px;z-index:10}.modal.open{display:flex}.captcha-card{width:min(420px,100%);padding:26px;border-radius:22px;background:linear-gradient(145deg,#fff,#eef2ff);box-shadow:0 25px 80px rgba(15,23,42,.25);transform:perspective(900px) rotateX(2deg);border:1px solid #dbeafe}.captcha-card h2{text-align:center;margin:0 0 8px}.captcha-card p{text-align:center;color:#64748b}.captcha-image-wrap{margin:18px auto;display:flex;justify-content:center;transform:perspective(500px) rotateY(-4deg);}.captcha-image-wrap img{border-radius:14px;box-shadow:8px 12px 25px rgba(15,23,42,.18);width:180px;height:72px;image-rendering:auto}.captcha-input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font-size:18px;text-align:center;letter-spacing:4px}.captcha-actions{display:flex;gap:10px;margin-top:12px}.captcha-actions button{flex:1}.auth-links{margin-top:20px;text-align:center;color:#64748b}.logged-box{text-align:center}.logged-box .avatar{width:120px;height:120px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 14px;border:4px solid #dbeafe}.server-error{display:none}.mini{font-size:13px;color:#64748b}.button.secondary{background:#64748b}.button.secondary:hover{background:#475569}
</style></head><body>
<main class="card landing">
<?php if ($viewerId > 0): ?>
<div class="logged-box"><img class="avatar" src="<?= htmlspecialchars(resolve_avatar((string)$me['Photo']),ENT_QUOTES,'UTF-8') ?>" alt=""><h1>Вы уже вошли</h1><p class="subtitle"><?= htmlspecialchars($viewerName,ENT_QUOTES,'UTF-8') ?> · рейтинг <?= (int)$me['Rating'] ?>%</p><div class="hero-actions"><a class="button" href="profile.php?id=<?= $viewerId ?>">Мой профиль</a><a class="button" href="shop_profile.php?id=<?= $viewerId ?>">Мой магазин</a><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><button class="button secondary" type="submit">Выйти</button></form></div></div>
<?php else: ?>
<h1><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></h1><p class="subtitle">Покупайте, продавайте, общайтесь и развивайте свой профиль.</p>
<div class="hero-actions"><button class="button" type="button" data-auth="login">Войти</button><button class="button" type="button" data-auth="register">Регистрация</button></div>
<p class="auth-links mini">Перед открытием формы система проверяет CAPTCHA.</p>
<?php endif; ?>
</main>
<?php if ($viewerId <= 0): ?>
<div class="modal" id="captchaModal"><div class="captcha-card"><h2>Проверка безопасности</h2><p>Пройдите CAPTCHA, после чего откроется выбранная форма.</p><div class="captcha-image-wrap"><img id="captchaImage" src="captcha_image.php?t=<?= time() ?>" alt="CAPTCHA"></div><input class="captcha-input" id="captchaInput" maxlength="6" autocomplete="off" placeholder="Введите код"><div class="captcha-actions"><button class="button" type="button" id="captchaSubmit">Проверить</button><button class="button secondary" type="button" id="captchaRefresh">Новый код</button></div><div class="server-error" id="captchaError" style="margin-top:12px"></div></div></div>
<script>
(() => {let wanted='login';const modal=document.getElementById('captchaModal');const input=document.getElementById('captchaInput');const err=document.getElementById('captchaError');const img=document.getElementById('captchaImage');
function open(mode){wanted=mode;modal.classList.add('open');input.focus();}
document.querySelectorAll('[data-auth]').forEach(b=>b.addEventListener('click',()=>open(b.dataset.auth)));
document.getElementById('captchaRefresh').addEventListener('click',()=>{img.src='captcha_image.php?t='+Date.now();input.value='';err.style.display='none';input.focus();});
document.getElementById('captchaSubmit').addEventListener('click',async()=>{err.style.display='none';const fd=new FormData();fd.append('csrf_token',<?= json_encode($csrf) ?>);fd.append('captcha',input.value.trim());try{const r=await fetch('captcha_gate.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-Token':<?= json_encode($csrf) ?>}});const d=await r.json();if(!r.ok)throw new Error(d.error||'CAPTCHA не пройдена');window.location.href=wanted==='login'?'login.php':'register.php';}catch(e){err.textContent=e.message;err.style.display='block';img.src='captcha_image.php?t='+Date.now();input.value='';input.focus();}});
})();
</script>
<?php endif; ?>
</body></html>
