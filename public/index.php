<?php
declare(strict_types=1);

/*
 * İkimizden — ön denetleyici (web kökü).
 * Uygulama klasörü varsayılan olarak bu klasörün bir üstündedir.
 * cPanel'de uygulamayı public_html dışında başka bir yere koyduysanız
 * bu klasöre app-path.php oluşturup yolunu döndürün:  <?php return '/home/kullanici/ikimizden';
 */
$appRoot = is_file(__DIR__ . '/app-path.php') ? (require __DIR__ . '/app-path.php') : dirname(__DIR__);
require $appRoot . '/app/bootstrap.php';
App\App::$publicDir = __DIR__;
App\Kernel::handle();
