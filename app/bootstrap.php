<?php
declare(strict_types=1);

/*
 * İkimizden — uygulama başlangıcı.
 * APP_ROOT: web kökü dışındaki uygulama klasörü (app/, storage/, database/, vendor/).
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $rel = str_replace('\\', '/', substr($class, 4));
        foreach (['lib', 'controllers'] as $dir) {
            $f = APP_ROOT . "/app/$dir/$rel.php";
            if (is_file($f)) { require $f; return; }
        }
    }
});

if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}
require APP_ROOT . '/app/helpers.php';

App\App::boot();
