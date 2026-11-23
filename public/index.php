<?php
declare(strict_types=1);

/*
 * İkimizden — ön denetleyici (web kökü).
 * Uygulama klasörü (app/, storage/, vendor/) web kökünün DIŞINDADIR ve şu sırayla aranır:
 *   1) bu klasördeki app-path.php dosyasının döndürdüğü yol:  <?php return '/home/kullanici/ikimizden';
 *   2) bir üst klasör (geliştirme düzeni: public/ ile app/ kardeş)
 *   3) bir üst klasördeki ikimizden/   (public_html/ + ikimizden/)
 *   4) iki üst klasördeki ikimizden/   (public_html/ikimizden/ alt dizin kurulumu)
 */
$candidates = [];
if (is_file(__DIR__ . '/app-path.php')) $candidates[] = (string)(require __DIR__ . '/app-path.php');
$candidates[] = dirname(__DIR__);
$candidates[] = dirname(__DIR__) . '/ikimizden';
$candidates[] = dirname(__DIR__, 2) . '/ikimizden';
$appRoot = null;
foreach ($candidates as $c) {
    if ($c !== '' && is_file(rtrim($c, '/') . '/app/bootstrap.php')) { $appRoot = rtrim($c, '/'); break; }
}
if ($appRoot === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "İkimizden uygulama klasörü bulunamadı. Web kökündeki app-path.php dosyasında uygulama klasörünün tam yolunu belirtin.";
    exit;
}
require $appRoot . '/app/bootstrap.php';
App\App::$publicDir = __DIR__;
App\Kernel::handle();
