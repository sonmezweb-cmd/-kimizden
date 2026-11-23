<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Crypto;
use App\DB;
use App\I18n;
use App\Migrator;
use App\Seeder;
use App\Settings;
use App\View;

/** Kurulum sihirbazı. Tamamlanınca storage/install.lock ile kilitlenir. */
final class InstallController
{
    public static function checks(): array
    {
        $c = [];
        $c[] = ['PHP ≥ 8.2 (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.2.0', '>=')];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'sodium', 'gd', 'fileinfo', 'curl', 'json', 'openssl'] as $ext) {
            $c[] = ["PHP eklentisi: $ext", extension_loaded($ext)];
        }
        $c[] = ['GD WebP desteği', function_exists('imagecreatefromwebp')];
        $c[] = ['Composer bağımlılıkları (vendor/)', is_file(APP_ROOT . '/vendor/autoload.php')];
        foreach (['', 'logs', 'uploads', 'cache', 'backups'] as $d) {
            $p = App::storagePath($d);
            if (!is_dir($p)) @mkdir($p, 0775, true);
            $c[] = ['Yazılabilir: storage/' . $d, is_dir($p) && is_writable($p)];
        }
        $themes = App::publicPath('themes');
        $c[] = ['Yazılabilir: themes/ (tema medyası yükleme)', is_dir($themes) && is_writable($themes)];
        return $c;
    }

    public function show(): void
    {
        I18n::$lang = 'tr';
        View::show('install/index', ['checks' => self::checks(), 'errors' => [], 'old' => ['base_url' => App::baseUrl(), 'db_host' => 'localhost', 'db_port' => '3306']], 'bare');
    }

    public function submit(): void
    {
        I18n::$lang = 'tr';
        $old = $_POST;
        unset($old['admin_password'], $old['db_pass'], $old['_csrf']);
        $errors = [];
        foreach (self::checks() as [$label, $ok]) if (!$ok) $errors[] = 'Gereksinim karşılanmıyor: ' . $label;
        $base = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
        if (!filter_var($base, FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $base)) $errors[] = 'Temel URL geçersiz.';
        $email = trim((string)($_POST['admin_email'] ?? ''));
        $name = trim((string)($_POST['admin_name'] ?? ''));
        $pw = (string)($_POST['admin_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Yönetici e-postası geçersiz.';
        if ($name === '') $errors[] = 'Yönetici adı gerekli.';
        if (!Auth::passwordOk($pw)) $errors[] = 'Yönetici parolası en az 10 karakter olmalı.';
        $db = ['host' => trim((string)($_POST['db_host'] ?? 'localhost')), 'port' => (int)($_POST['db_port'] ?? 3306),
            'name' => trim((string)($_POST['db_name'] ?? '')), 'user' => trim((string)($_POST['db_user'] ?? '')), 'pass' => (string)($_POST['db_pass'] ?? '')];
        $pdo = null;
        if (!$errors) {
            try {
                $pdo = DB::connect($db);
                $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
                $existing = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users','orders')")->fetchColumn();
                if ($existing > 0) $errors[] = 'Bu veritabanında zaten İkimizden tabloları var. Boş bir veritabanı seçin.';
                if (!str_contains(strtolower($ver), 'mariadb') && version_compare($ver, '8.0.0', '<')) $errors[] = "MySQL 8 veya MariaDB 10.4+ gerekli (bulunan: $ver).";
            } catch (\Throwable $e) {
                $errors[] = 'Veritabanına bağlanılamadı. Bilgileri kontrol edin.';
            }
        }
        if ($errors) {
            View::show('install/index', ['checks' => self::checks(), 'errors' => $errors, 'old' => $old], 'bare', 422);
            return;
        }
        $config = [
            'base_url' => $base,
            'db' => $db,
            'app_key' => Crypto::newKey(),
            'debug' => false,
            'trust_proxy' => !empty($_POST['trust_proxy']),
        ];
        $file = App::storagePath('config.php');
        $php = "<?php\n// İkimizden yapılandırması — web kökü dışında tutun, paylaşmayın.\n// app_key: sırların şifreleme anahtarıdır; kaybolursa PayTR/SMTP sırlarını yeniden girmeniz gerekir.\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($file, $php, LOCK_EX) === false) {
            View::show('install/index', ['checks' => self::checks(), 'errors' => ['storage/config.php yazılamadı.'], 'old' => $old], 'bare', 500);
            return;
        }
        @chmod($file, 0640);
        App::$config = $config;
        DB::setPdo($pdo);
        Migrator::run();
        App::$installed = true; // tohumlama için
        Seeder::run();
        $now = DB::now();
        DB::insert('users', ['email' => mb_strtolower($email), 'password_hash' => Auth::hash($pw), 'name' => $name, 'role' => 'admin',
            'locale' => 'tr', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        Settings::set('mail.admin_notify', mb_strtolower($email));
        file_put_contents(App::storagePath('install.lock'), gmdate('c') . "\n");
        View::show('install/done', ['base' => $base], 'bare');
    }
}
