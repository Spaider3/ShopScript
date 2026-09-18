<?php
$password = '12345678';

// Создание хеша с алгоритмом по умолчанию (сейчас это Bcrypt)
$hash = password_hash($password, PASSWORD_DEFAULT);

echo $hash;
// Пример вывода: $2y$10$123456789012345678901ueAbCdEfGhIjKlMnOpQrStUvWxYz012345
?>