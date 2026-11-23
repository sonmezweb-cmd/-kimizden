<?php
declare(strict_types=1);

namespace App;

final class I18n
{
    public const LANGS = ['tr', 'en', 'ru'];
    public const DEFAULT = 'tr';
    public const LOCALES = ['tr' => 'tr_TR', 'en' => 'en_GB', 'ru' => 'ru_RU'];
    public const NAMES = ['tr' => 'Türkçe', 'en' => 'English', 'ru' => 'Русский'];

    public static string $lang = 'tr';
    public static bool $fromPrefix = false;
    private static array $cache = [];

    public static function setFromPrefix(string $l): void
    {
        if (in_array($l, self::LANGS, true)) {
            self::$lang = $l;
            self::$fromPrefix = true;
        }
    }

    /** Önek yoksa: TR (varsayılan). Kullanıcı tercihi bağlantılarda önek olarak taşınır. */
    public static function detect(): void
    {
        if (self::$fromPrefix) {
            return;
        }
        self::$lang = self::DEFAULT;
    }

    public static function valid(?string $l): bool
    {
        return $l !== null && in_array($l, self::LANGS, true);
    }

    public static function strings(string $lang): array
    {
        if (isset(self::$cache[$lang])) return self::$cache[$lang];
        $base = require APP_ROOT . "/app/lang/$lang.php";
        $over = [];
        if (App::$installed) {
            $cf = App::storagePath("cache/i18n-$lang.php");
            if (is_file($cf)) {
                $over = require $cf;
            } else {
                try {
                    foreach (DB::all('SELECT k, v FROM translations WHERE lang = ?', [$lang]) as $r) $over[$r['k']] = $r['v'];
                    @file_put_contents($cf, '<?php return ' . var_export($over, true) . ';', LOCK_EX);
                } catch (\Throwable) {
                }
            }
        }
        return self::$cache[$lang] = array_merge($base, $over);
    }

    public static function base(string $lang): array
    {
        return require APP_ROOT . "/app/lang/$lang.php";
    }

    public static function clearCache(): void
    {
        self::$cache = [];
        foreach (self::LANGS as $l) @unlink(App::storagePath("cache/i18n-$l.php"));
    }

    public static function t(string $key, array $vars = [], ?string $lang = null): string
    {
        $lang = $lang ?? self::$lang;
        $s = self::strings($lang)[$key] ?? self::strings('tr')[$key] ?? $key;
        if ($vars) {
            $rep = [];
            foreach ($vars as $k => $v) $rep['{' . $k . '}'] = (string)$v;
            $s = strtr($s, $rep);
        }
        return $s;
    }

    public static function has(string $key, ?string $lang = null): bool
    {
        return isset(self::strings($lang ?? self::$lang)[$key]);
    }

    public static function locale(?string $lang = null): string
    {
        return self::LOCALES[$lang ?? self::$lang];
    }
}
