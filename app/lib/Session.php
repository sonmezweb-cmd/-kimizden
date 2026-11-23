<?php
declare(strict_types=1);

namespace App;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $path = App::basePath() ?: '/';
        session_name('ikz_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => App::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        $dir = App::storagePath('sessions');
        if (is_dir($dir) || @mkdir($dir, 0770, true)) {
            session_save_path($dir);
        }
        session_start();
        // Boşta kalma süresi: 8 saat
        $now = time();
        if (isset($_SESSION['_last']) && $now - $_SESSION['_last'] > 28800) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last'] = $now;
    }
}
