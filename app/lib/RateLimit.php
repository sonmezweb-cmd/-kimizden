<?php
declare(strict_types=1);

namespace App;

final class RateLimit
{
    /** $max deneme / $minutes dakika. Aşıldıysa false. */
    public static function hit(string $bucket, int $max, int $minutes): bool
    {
        $b = hash('sha256', $bucket);
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $n = (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE bucket = ? AND created_at > ?', [$b, $since]);
        if ($n >= $max) return false;
        DB::insert('login_attempts', ['bucket' => $b, 'created_at' => DB::now()]);
        if (random_int(1, 50) === 1) {
            DB::q('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
        }
        return true;
    }

    public static function clear(string $bucket): void
    {
        DB::q('DELETE FROM login_attempts WHERE bucket = ?', [hash('sha256', $bucket)]);
    }

    public static function blocked(string $bucket, int $max, int $minutes): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        return (int)DB::val('SELECT COUNT(*) FROM login_attempts WHERE bucket = ? AND created_at > ?', [hash('sha256', $bucket), $since]) >= $max;
    }
}
