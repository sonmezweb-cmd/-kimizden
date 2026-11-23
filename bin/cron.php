<?php
// İsteğe bağlı: bekleyen e-postaları gönderir. cPanel → Cron Jobs: */10 * * * * php /home/KULLANICI/ikimizden/bin/cron.php
// Cron kurmasanız da e-postalar ilgili istekten sonra ve Yönetim → E-postalar ekranından gönderilir.
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit("Yalnız komut satırı\n");
if (!App\App::$installed) exit;
$r = App\Mailer::processQueue(50);
echo "Gönderilen: {$r['sent']}, başarısız: {$r['failed']}\n";
