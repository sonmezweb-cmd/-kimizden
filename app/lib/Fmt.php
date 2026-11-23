<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use NumberFormatter;

/** Dile göre tarih, saat, sayı ve para biçimleri. */
final class Fmt
{
    public static function money(?int $minor, string $currency = 'TRY', ?string $lang = null): string
    {
        if ($minor === null) return '—';
        $f = new NumberFormatter(I18n::locale($lang), NumberFormatter::CURRENCY);
        $out = $f->formatCurrency($minor / 100, $currency);
        return $out === false ? number_format($minor / 100, 2) . ' ' . $currency : $out;
    }

    public static function number(int|float $n, ?string $lang = null): string
    {
        $f = new NumberFormatter(I18n::locale($lang), NumberFormatter::DECIMAL);
        return (string)$f->format($n);
    }

    private static function fmt(string $pattern, ?string $lang, string $tz): IntlDateFormatter
    {
        return new IntlDateFormatter(I18n::locale($lang), IntlDateFormatter::NONE, IntlDateFormatter::NONE, $tz, IntlDateFormatter::GREGORIAN, $pattern);
    }

    /** UTC zaman damgasını verilen saat diliminde, dile uygun uzun tarih olarak biçimler. */
    public static function dateLong(string $utc, string $tz, ?string $lang = null): string
    {
        $lang = $lang ?? I18n::$lang;
        $pattern = match ($lang) {
            'en' => 'EEEE, d MMMM y',
            'ru' => 'd MMMM y, EEEE',
            default => 'd MMMM y, EEEE',
        };
        return self::fmt($pattern, $lang, $tz)->format(new DateTimeImmutable($utc));
    }

    /** Kapak için kısa tarih: 12.06.2027 benzeri yerel biçim */
    public static function dateCover(string $utc, string $tz, ?string $lang = null): string
    {
        $lang = $lang ?? I18n::$lang;
        $pattern = match ($lang) {
            'en' => 'd MMMM y',
            default => 'd MMMM y',
        };
        return self::fmt($pattern, $lang, $tz)->format(new DateTimeImmutable($utc));
    }

    public static function time(string $utc, string $tz, ?string $lang = null): string
    {
        return self::fmt('HH:mm', $lang, $tz)->format(new DateTimeImmutable($utc));
    }

    /** "19:00 – 01:00 (+1 gün)" */
    public static function timeRange(string $startUtc, ?string $endUtc, string $tz, ?string $lang = null): string
    {
        $s = self::time($startUtc, $tz, $lang);
        if (!$endUtc) return $s;
        $e = self::time($endUtc, $tz, $lang);
        $zone = new DateTimeZone($tz);
        $sd = (new DateTimeImmutable($startUtc))->setTimezone($zone)->format('Y-m-d');
        $ed = (new DateTimeImmutable($endUtc))->setTimezone($zone)->format('Y-m-d');
        $plus = '';
        if ($ed > $sd) {
            $days = (int)((new DateTimeImmutable($sd))->diff(new DateTimeImmutable($ed))->days);
            $plus = ' ' . t('date.next_day', ['n' => $days], $lang);
        }
        return $s . ' – ' . $e . $plus;
    }

    /** Panel tarih-saat (kullanıcıya UTC değil, İstanbul saatiyle gösterilir) */
    public static function dt(?string $utc, ?string $lang = null, string $tz = 'Europe/Istanbul'): string
    {
        if (!$utc) return '—';
        return self::fmt('d MMM y HH:mm', $lang, $tz)->format(new DateTimeImmutable($utc . ' UTC'));
    }

    public static function tzLabel(string $tz, string $utc, ?string $lang = null): string
    {
        $d = (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone($tz));
        $city = str_replace('_', ' ', substr($tz, strrpos($tz, '/') !== false ? strrpos($tz, '/') + 1 : 0));
        return $city . ' (UTC' . $d->format('P') . ')';
    }
}
