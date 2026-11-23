<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Davetiye içerik modeli. Müşteri metni yalnız düz metin olarak saklanır ve
 * gösterimde HTML metin düğümü olarak kaçışlanır; görsele/videoya gömülmez.
 */
final class Content
{
    public const EVENT_TYPES = ['engagement', 'henna', 'civil', 'wedding', 'cocktail', 'dinner', 'other'];
    public const TEXT_FIELDS = ['intro' => 600, 'letter' => 3000, 'quote' => 500, 'quote_by' => 120, 'dress' => 600, 'transport' => 1200, 'stay' => 1200, 'notes' => 1500];
    public const MAX_EVENTS = 6;
    public const MAX_GALLERY = 24;

    public static function defaults(array $langs = ['tr'], string $defaultLang = 'tr'): array
    {
        $texts = [];
        foreach (I18n::LANGS as $l) $texts[$l] = array_fill_keys(array_keys(self::TEXT_FIELDS), '');
        return [
            'names' => ['first' => '', 'second' => '', 'order' => 'ab'],
            'languages' => array_values($langs),
            'default_lang' => $defaultLang,
            'texts' => $texts,
            'events' => [],
            'cover_event_id' => null,
            'cover_photo' => null,
            'gallery' => [],
            'contact' => ['name' => '', 'phone' => ''],
            'rsvp' => ['enabled' => true, 'deadline' => '', 'ask_counts' => true, 'max_public' => 4],
        ];
    }

    public static function normalize(array $c): array
    {
        $d = self::defaults();
        $c = array_replace_recursive($d, $c);
        $c['languages'] = array_values(array_intersect(I18n::LANGS, (array)$c['languages'])) ?: ['tr'];
        if (!in_array($c['default_lang'], $c['languages'], true)) $c['default_lang'] = $c['languages'][0];
        $c['events'] = array_values(array_filter((array)$c['events'], 'is_array'));
        $c['gallery'] = array_values(array_filter((array)$c['gallery'], 'is_string'));
        return $c;
    }

    // ------------------------------------------------------------ yama (patch)

    /** İzin verilen yol mu? */
    public static function allowedPath(string $p): bool
    {
        if (in_array($p, ['names.first', 'names.second', 'names.order', 'languages', 'default_lang', 'events', 'cover_event_id',
            'cover_photo', 'gallery', 'contact.name', 'contact.phone', 'rsvp.enabled', 'rsvp.deadline', 'rsvp.ask_counts', 'rsvp.max_public'], true)) {
            return true;
        }
        return (bool)preg_match('#^texts\.(tr|en|ru)\.(' . implode('|', array_keys(self::TEXT_FIELDS)) . ')$#', $p);
    }

    public static function clean(string $s, int $max): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
        return mb_substr(trim($s), 0, $max);
    }

    public static function validUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '';
        if (!preg_match('#^https://#i', $u)) return '';
        return filter_var($u, FILTER_VALIDATE_URL) ? mb_substr($u, 0, 500) : '';
    }

    /** Değeri yola göre temizler. Medya anahtarları çağıran tarafından doğrulanır. */
    public static function sanitize(string $path, mixed $v): mixed
    {
        if (str_starts_with($path, 'texts.')) {
            $f = explode('.', $path)[2];
            return self::clean((string)$v, self::TEXT_FIELDS[$f]);
        }
        return match ($path) {
            'names.first', 'names.second' => self::clean((string)$v, 80),
            'names.order' => $v === 'ba' ? 'ba' : 'ab',
            'languages' => array_values(array_intersect(I18n::LANGS, array_map('strval', (array)$v))) ?: ['tr'],
            'default_lang' => I18n::valid((string)$v) ? (string)$v : 'tr',
            'cover_event_id' => is_string($v) && preg_match('/^[a-z0-9]{4,16}$/', $v) ? $v : null,
            'cover_photo' => is_string($v) && preg_match('/^[a-f0-9]{32}$/', $v) ? $v : null,
            'gallery' => array_slice(array_values(array_unique(array_filter(array_map('strval', (array)$v), fn($k) => (bool)preg_match('/^[a-f0-9]{32}$/', $k)))), 0, self::MAX_GALLERY),
            'contact.name' => self::clean((string)$v, 120),
            'contact.phone' => self::clean((string)$v, 40),
            'rsvp.enabled', 'rsvp.ask_counts' => (bool)$v,
            'rsvp.deadline' => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '',
            'rsvp.max_public' => max(1, min(10, (int)$v)),
            'events' => self::sanitizeEvents((array)$v),
            default => null,
        };
    }

    public static function sanitizeEvents(array $events): array
    {
        $out = [];
        foreach (array_slice($events, 0, self::MAX_EVENTS) as $e) {
            if (!is_array($e)) continue;
            $id = (isset($e['id']) && preg_match('/^[a-z0-9]{4,16}$/', (string)$e['id'])) ? (string)$e['id'] : strtolower(substr(random_token(8), 0, 8));
            $tz = (string)($e['tz'] ?? 'Europe/Istanbul');
            if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) $tz = 'Europe/Istanbul';
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($e['date'] ?? '')) ? (string)$e['date'] : '';
            $start = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($e['start'] ?? '')) ? (string)$e['start'] : '';
            $end = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($e['end'] ?? '')) ? (string)$e['end'] : '';
            $title = []; $desc = [];
            foreach (I18n::LANGS as $l) {
                $title[$l] = self::clean((string)($e['title'][$l] ?? ''), 120);
                $desc[$l] = self::clean((string)($e['desc'][$l] ?? ''), 600);
            }
            $ev = [
                'id' => $id,
                'type' => in_array($e['type'] ?? '', self::EVENT_TYPES, true) ? $e['type'] : 'wedding',
                'title' => $title,
                'date' => $date, 'start' => $start, 'end' => $end, 'tz' => $tz,
                'venue' => self::clean((string)($e['venue'] ?? ''), 150),
                'address' => self::clean((string)($e['address'] ?? ''), 400),
                'map_url' => self::validUrl((string)($e['map_url'] ?? '')),
                'desc' => $desc,
            ];
            [$ev['start_utc'], $ev['end_utc']] = self::eventUtc($ev);
            $out[] = $ev;
        }
        return $out;
    }

    /** Yerel tarih+saat+IANA dilimi → UTC. Bitiş başlangıçtan önceyse ertesi gün. */
    public static function eventUtc(array $e): array
    {
        if (!$e['date'] || !$e['start']) return [null, null];
        $zone = new DateTimeZone($e['tz']);
        $s = new DateTimeImmutable($e['date'] . ' ' . $e['start'], $zone);
        $end = null;
        if ($e['end']) {
            $en = new DateTimeImmutable($e['date'] . ' ' . $e['end'], $zone);
            if ($en <= $s) $en = $en->modify('+1 day');
            $end = $en->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        return [$s->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), $end];
    }

    public static function setPath(array &$c, string $path, mixed $v): void
    {
        $ref = &$c;
        $parts = explode('.', $path);
        foreach ($parts as $i => $k) {
            if ($i === count($parts) - 1) { $ref[$k] = $v; break; }
            if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
            $ref = &$ref[$k];
        }
    }

    public static function getPath(array $c, string $path): mixed
    {
        foreach (explode('.', $path) as $k) {
            if (!is_array($c) || !array_key_exists($k, $c)) return null;
            $c = $c[$k];
        }
        return $c;
    }

    public static function pathsOverlap(string $a, string $b): bool
    {
        return $a === $b || str_starts_with($a, $b . '.') || str_starts_with($b, $a . '.');
    }

    // ------------------------------------------------------------ okuma yardımcıları

    /** Gösterim sırasına göre iki isim (özgün alfabe ve yazımıyla, çeviri yok). */
    public static function names(array $c): array
    {
        $a = trim((string)($c['names']['first'] ?? ''));
        $b = trim((string)($c['names']['second'] ?? ''));
        return ($c['names']['order'] ?? 'ab') === 'ba' ? [$b, $a] : [$a, $b];
    }

    public static function coverEvent(array $c): ?array
    {
        $events = array_values(array_filter($c['events'] ?? [], fn($e) => !empty($e['start_utc'])));
        if (!$events) return null;
        foreach ($events as $e) if ($e['id'] === ($c['cover_event_id'] ?? null)) return $e;
        usort($events, fn($x, $y) => strcmp($x['start_utc'], $y['start_utc']));
        return $events[0];
    }

    public static function eventTitle(array $e, string $lang): string
    {
        $t = trim((string)($e['title'][$lang] ?? ''));
        return $t !== '' ? $t : t('event.type.' . $e['type'], [], $lang);
    }

    public static function text(array $c, string $lang, string $field): string
    {
        return trim((string)($c['texts'][$lang][$field] ?? ''));
    }

    /**
     * Yayın için eksikler: ['global' => [...], 'tr' => [...], ...] — her biri çeviri anahtarı.
     */
    public static function missing(array $c): array
    {
        $m = ['global' => []];
        if (trim($c['names']['first'] ?? '') === '') $m['global'][] = 'form.names.first';
        if (trim($c['names']['second'] ?? '') === '') $m['global'][] = 'form.names.second';
        $valid = array_filter($c['events'] ?? [], fn($e) => !empty($e['start_utc']) && trim($e['venue']) !== '');
        if (!$valid) $m['global'][] = 'form.events.one_required';
        foreach ($c['events'] ?? [] as $e) {
            if (empty($e['start_utc']) || trim($e['venue']) === '') { $m['global'][] = 'form.events.incomplete'; break; }
        }
        foreach ($c['languages'] as $l) {
            $m[$l] = [];
            if (self::text($c, $l, 'letter') === '') $m[$l][] = 'form.texts.letter';
            foreach ($c['events'] ?? [] as $e) {
                if ($e['type'] === 'other' && trim($e['title'][$l] ?? '') === '') { $m[$l][] = 'form.events.title_other'; break; }
            }
        }
        $m['global'] = array_values(array_unique($m['global']));
        return $m;
    }

    public static function isComplete(array $c): bool
    {
        foreach (self::missing($c) as $list) if ($list) return false;
        return true;
    }

    /** Tüm medya anahtarları (kapak + galeri) */
    public static function mediaKeys(array $c): array
    {
        $k = $c['gallery'] ?? [];
        if (!empty($c['cover_photo'])) $k[] = $c['cover_photo'];
        return array_values(array_unique($k));
    }
}
