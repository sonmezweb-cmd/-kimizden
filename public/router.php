<?php
// Yalnız yerel geliştirme: php -S localhost:8080 -t public public/router.php
$f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($f !== __DIR__ . '/' && is_file($f) && !str_ends_with($f, '.php')) return false;
require __DIR__ . '/index.php';
