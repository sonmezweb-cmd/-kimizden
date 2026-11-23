<?php
declare(strict_types=1);

namespace App;

final class App
{
    public static array $config = [];
    public static bool $installed = false;
    public static ?string $publicDir = null;
    public static array $shutdown = [];

    public static function boot(): void
    {
        $cfgFile = self::storagePath('config.php');
        if (is_file($cfgFile)) {
            self::$config = require $cfgFile;
        }
        self::$installed = is_file(self::storagePath('install.lock')) && !empty(self::$config['db']);

        $debug = (bool)(self::$config['debug'] ?? false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::storagePath('logs/php-error.log'));

        set_exception_handler(function (\Throwable $e) use ($debug): void {
            Log::error('Yakalanmamış hata: ' . get_class($e) . ': ' . Log::scrub($e->getMessage()) . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            if ($debug) {
                echo '<pre>' . e(Log::scrub((string)$e)) . '</pre>';
            } else {
                echo View::render('errors/500', [], 'bare');
            }
        });
    }

    public static function storagePath(string $rel = ''): string
    {
        $base = self::$config['storage_path'] ?? (APP_ROOT . '/storage');
        return rtrim($base, '/') . ($rel !== '' ? '/' . ltrim($rel, '/') : '');
    }

    public static function publicPath(string $rel = ''): string
    {
        $base = self::$publicDir ?? (self::$config['public_path'] ?? (APP_ROOT . '/public'));
        return rtrim($base, '/') . ($rel !== '' ? '/' . ltrim($rel, '/') : '');
    }

    public static function baseUrl(): string
    {
        if (!empty(self::$config['base_url'])) {
            return rtrim(self::$config['base_url'], '/');
        }
        // Kurulum öncesi: istekten tahmin
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return ($https ? 'https' : 'http') . '://' . $host . $dir;
    }

    public static function basePath(): string
    {
        return rtrim((string)parse_url(self::baseUrl(), PHP_URL_PATH), '/');
    }

    public static function isHttps(): bool
    {
        return str_starts_with(self::baseUrl(), 'https://');
    }

    public static function onShutdown(callable $fn): void
    {
        self::$shutdown[] = $fn;
    }

    public static function runShutdown(): void
    {
        if (!self::$shutdown) return;
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        }
        foreach (self::$shutdown as $fn) {
            try { $fn(); } catch (\Throwable $e) { Log::error('Kapanış görevi hatası: ' . Log::scrub($e->getMessage())); }
        }
    }
}
