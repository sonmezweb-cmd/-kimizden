<?php
// Veritabanı güncellemelerini uygular (SSH varsa). SSH yoksa: Yönetim → Sistem → Güncellemeleri uygula.
// Kullanım: php bin/migrate.php
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit("Yalnız komut satırı\n");
if (!App\App::$installed) exit("Önce kurulum sihirbazını tamamlayın.\n");
$done = App\Migrator::run();
App\Seeder::run(); // yeni katalog öğeleri (var olanlara dokunmaz)
echo $done ? "Uygulandı: " . implode(', ', $done) . "\n" : "Veritabanı güncel.\n";
