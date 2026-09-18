<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
start_secure_session();
$siteName = get_site_name($pdo);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errorlist.php';
require_once __DIR__ . '/check.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> | help</title>
<link rel="stylesheet" type="text/css" href="css/style_profile.css"/>
</head>
<body>
<div id="container">
  <div id="header">Помощь*<div id="menu"><?php if (isset($online) && $online == 1) {echo " / <a href=\"admin.php\">В админку</a>";};?> / <a href="index.php">На главную</a> / <a href="admin.php">Смотреть рейтинги</a><?php if (isset($online) && $online == 1) {echo " / <a href=\"?act=logout\">Выход</a> / ";};?></div></div>
   <div id="content"><div id="avatar" style="width:500px"><img src="images/help_image.jpg" /></div></div>
   <div id="photo">
   <div id="avatar">
   <img src="images/help.jpg" />
   </div>
   </div>
   <div id="footer">&copy; Mr.Green</div>
</div>
</body>
</html>