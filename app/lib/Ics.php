<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/** RFC 5545 takvim dosyası. Başlangıç/bitiş TZID ile, VTIMEZONE tanımı geçişlerden üretilir. */
final class Ics
{
    private static function esc(string $s): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
    }

    private static function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 74) {
            $cut = 74;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; // UTF-8 karakter bölme
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $out . $line;
    }

    private static function offset(int $sec): string
    {
        $sign = $sec < 0 ? '-' : '+';
        $sec = abs($sec);
        return sprintf('%s%02d%02d', $sign, intdiv($sec, 3600), intdiv($sec % 3600, 60));
    }

    public static function vtimezone(string $tz, int $year): array
    {
        $zone = new DateTimeZone($tz);
        $from = (new DateTimeImmutable("$year-01-01 00:00:00", new DateTimeZone('UTC')))->getTimestamp();
        $to = (new DateTimeImmutable(($year + 1) . "-12-31 23:59:59", new DateTimeZone('UTC')))->getTimestamp();
        $tr = $zone->getTransitions($from, $to) ?: [];
        $lines = ['BEGIN:VTIMEZONE', 'TZID:' . $tz];
        $prev = $tr[0]['offset'] ?? 0;
        if (count($tr) <= 1) {
            $off = $tr[0]['offset'] ?? 0;
            array_push($lines, 'BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:' . self::offset($off), 'TZOFFSETTO:' . self::offset($off), 'TZNAME:' . ($tr[0]['abbr'] ?? 'LT'), 'END:STANDARD');
        } else {
            foreach (array_slice($tr, 1) as $t) {
                $local = (new DateTimeImmutable('@' . $t['ts']))->modify(($prev >= 0 ? '+' : '-') . abs($prev) . ' seconds');
                $kind = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
                array_push($lines, "BEGIN:$kind", 'DTSTART:' . $local->format('Ymd\THis'), 'TZOFFSETFROM:' . self::offset($prev), 'TZOFFSETTO:' . self::offset($t['offset']), 'TZNAME:' . $t['abbr'], "END:$kind");
                $prev = $t['offset'];
            }
        }
        $lines[] = 'END:VTIMEZONE';
        return $lines;
    }

    public static function event(array $e, string $title, string $location, string $desc, string $uid, string $url): string
    {
        $zone = new DateTimeZone($e['tz']);
        $s = (new DateTimeImmutable($e['start_utc']))->setTimezone($zone);
        $en = $e['end_utc'] ? (new DateTimeImmutable($e['end_utc']))->setTimezone($zone) : $s->modify('+3 hours');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Ikimizden//Dijital Davetiye//TR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        $lines = array_merge($lines, self::vtimezone($e['tz'], (int)$s->format('Y')));
        array_push($lines,
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART;TZID=' . $e['tz'] . ':' . $s->format('Ymd\THis'),
            'DTEND;TZID=' . $e['tz'] . ':' . $en->format('Ymd\THis'),
            'SUMMARY:' . self::esc($title),
            'LOCATION:' . self::esc($location),
            'DESCRIPTION:' . self::esc($desc),
            'URL:' . $url,
            'END:VEVENT', 'END:VCALENDAR');
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    public static function googleUrl(array $e, string $title, string $location, string $details): string
    {
        $s = (new DateTimeImmutable($e['start_utc']))->format('Ymd\THis\Z');
        $en = $e['end_utc'] ? (new DateTimeImmutable($e['end_utc']))->format('Ymd\THis\Z') : (new DateTimeImmutable($e['start_utc']))->modify('+3 hours')->format('Ymd\THis\Z');
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE', 'text' => $title, 'dates' => "$s/$en", 'ctz' => $e['tz'], 'location' => $location, 'details' => $details,
        ]);
    }

    public static function directionsUrl(array $e): string
    {
        if (!empty($e['map_url'])) return $e['map_url'];
        $q = trim(($e['venue'] ?? '') . ', ' . ($e['address'] ?? ''), ', ');
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
    }
}
