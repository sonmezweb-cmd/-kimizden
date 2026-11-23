<?php
declare(strict_types=1);

namespace App;

final class Log
{
    /** Bilinen sırları ve imza benzeri değerleri loglardan temizler. */
    public static function scrub(string $msg): string
    {
        foreach (Settings::secretValues() as $secret) {
            if ($secret !== '' && strlen($secret) >= 4) {
                $msg = str_replace($secret, '[gizli]', $msg);
            }
        }
        $msg = preg_replace('/(merchant_key|merchant_salt|paytr_token|hash|password|pass)=([^&\s]+)/i', '$1=[gizli]', $msg) ?? $msg;
        return $msg;
    }

    public static function write(string $level, string $msg): void
    {
        $dir = App::storagePath('logs');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line = gmdate('c') . " [$level] " . self::scrub($msg) . PHP_EOL;
        @file_put_contents($dir . '/app-' . gmdate('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $m): void { self::write('error', $m); }
    public static function info(string $m): void { self::write('info', $m); }
}
