<?php
declare(strict_types=1);

namespace App;

final class Settings
{
    private static ?array $cache = null;
    private static array $secretPlain = [];

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (App::$installed) {
                foreach (DB::all('SELECT k, v, is_secret FROM settings') as $r) {
                    self::$cache[$r['k']] = $r;
                }
            }
        }
        return self::$cache;
    }

    public static function get(string $k, mixed $default = null): mixed
    {
        $r = self::load()[$k] ?? null;
        if (!$r) return $default;
        if ((int)$r['is_secret'] === 1) {
            return self::secret($k) ?? $default;
        }
        return $r['v'] ?? $default;
    }

    /** Sırlar yalnız sunucu tarafında çözülür; tarayıcıya hiçbir zaman gönderilmez. */
    public static function secret(string $k): ?string
    {
        if (array_key_exists($k, self::$secretPlain)) return self::$secretPlain[$k];
        $r = self::load()[$k] ?? null;
        if (!$r || $r['v'] === null || $r['v'] === '') return null;
        $plain = Crypto::decrypt($r['v']);
        self::$secretPlain[$k] = $plain;
        return $plain;
    }

    public static function hasSecret(string $k): bool
    {
        $r = self::load()[$k] ?? null;
        return $r && $r['v'] !== null && $r['v'] !== '';
    }

    public static function set(string $k, ?string $v, bool $secret = false): void
    {
        $stored = $v;
        if ($secret && $v !== null && $v !== '') {
            $stored = Crypto::encrypt($v);
        }
        DB::q('INSERT INTO settings (k, v, is_secret, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE v = VALUES(v), is_secret = VALUES(is_secret), updated_at = VALUES(updated_at)',
            [$k, $stored, $secret ? 1 : 0, DB::now()]);
        self::$cache = null;
        unset(self::$secretPlain[$k]);
    }

    /** Log temizliği için çözülmüş sırlar (yalnız bu istekte çözülmüş olanlar). */
    public static function secretValues(): array
    {
        return array_values(array_filter(self::$secretPlain, fn($v) => is_string($v) && $v !== ''));
    }

    public static function reset(): void
    {
        self::$cache = null;
        self::$secretPlain = [];
    }
}
