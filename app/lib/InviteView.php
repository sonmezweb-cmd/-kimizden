<?php
declare(strict_types=1);

namespace App;

/** Davetiye motoru için görünüm verisi. Vitrin, önizleme ve canlı yayın aynı motoru kullanır. */
final class InviteView
{
    /**
     * Hikâye videosu durakları: t = videonun durduğu saniye, zone = boş alan (video yüzdesi x1,y1,x2,y2).
     * Teslim edilen 24 sn'lik resimli video için ölçülmüştür; yönetim panelinden tema başına değiştirilebilir.
     */
    public const DEFAULT_STORY_STOPS = [
        ['type' => 'names', 't' => 3.6, 'zone' => [14, 30, 86, 70]],
        ['type' => 'countdown', 't' => 8.2, 'zone' => [16, 24, 84, 55]],
        ['type' => 'event1', 't' => 10.9, 'zone' => [25, 20, 75, 50]],
        ['type' => 'quote', 't' => 15.6, 'zone' => [16, 24, 84, 54]],
        ['type' => 'event2', 't' => 19.0, 'zone' => [15, 24, 85, 56]],
        ['type' => 'map', 't' => 23.9, 'zone' => [22, 24, 88, 60]],
    ];
    public const STORY_TYPES = ['names', 'countdown', 'event1', 'event2', 'quote', 'map', 'letter'];

    /** Müşteri bilgisi olan duraklar; bilgisi olmayan durak atlanır (video orada beklemez). */
    public static function storyStops(array $theme, array $content, array $events, ?array $cover, string $lang): array
    {
        if (empty($theme['media']['story_mp4']) && empty($theme['media']['story_webm'])) return [];
        $out = [];
        foreach ((array)$theme['story_stops'] as $st) {
            $type = $st['type'] ?? '';
            if (!in_array($type, self::STORY_TYPES, true)) continue;
            $has = match ($type) {
                'names' => true,
                'countdown' => $cover !== null,
                'event1' => isset($events[0]),
                'event2' => isset($events[1]),
                'quote' => Content::text($content, $lang, 'quote') !== '',
                'letter' => Content::text($content, $lang, 'letter') !== '',
                'map' => (bool)array_filter($events, fn($e) => trim($e['venue'] . $e['address']) !== ''),
            };
            if (!$has) continue;
            $z = array_map('floatval', array_slice(array_values((array)($st['zone'] ?? [15, 25, 85, 60])), 0, 4));
            $out[] = ['type' => $type, 't' => max(0, (float)($st['t'] ?? 0)), 'zone' => $z];
        }
        usort($out, fn($a, $b) => $a['t'] <=> $b['t']);
        return $out;
    }

    public static function guestLang(array $content): string
    {
        $l = $_GET['l'] ?? null;
        return (is_string($l) && in_array($l, $content['languages'], true)) ? $l : $content['default_lang'];
    }

    public static function deadlinePassed(array $content): bool
    {
        $d = $content['rsvp']['deadline'] ?? '';
        if (!$d) return false;
        $ev = Content::coverEvent($content);
        $tz = $ev['tz'] ?? 'Europe/Istanbul';
        $end = new \DateTimeImmutable($d . ' 23:59:59', new \DateTimeZone($tz));
        return new \DateTimeImmutable('now') > $end;
    }

    /**
     * @param string $mode live|group|preview|showcase
     */
    public static function build(array $content, array $theme, string $lang, string $mode, array $opts = []): array
    {
        $names = Content::names($content);
        $cover = Content::coverEvent($content);
        $events = array_values(array_filter($content['events'], fn($e) => !empty($e['start_utc'])));
        $pending = array_values(array_filter($content['events'], fn($e) => empty($e['start_utc'])));
        $mediaUrl = $opts['mediaUrl'] ?? fn(string $k, string $s) => Media::url($k, $s);
        $gallery = [];
        foreach ($content['gallery'] as $k) $gallery[] = ['s' => $mediaUrl($k, 's'), 'm' => $mediaUrl($k, 'm'), 'l' => $mediaUrl($k, 'l')];
        $coverPhoto = $content['cover_photo'] ? ['m' => $mediaUrl($content['cover_photo'], 'm'), 'l' => $mediaUrl($content['cover_photo'], 'l')] : null;
        $engine = [
            'theme' => $theme['slug'],
            'video' => $theme['media']['video'],
            'videoMp4' => $theme['media']['video_mp4'],
            'videoWebm' => $theme['media']['video_webm'],
            'videoWide' => $theme['media']['video_wide'],
            'poster' => $theme['media']['poster'],
            'texture' => $theme['media']['texture'],
            'ornament' => $theme['media']['ornament'],
            'body' => $theme['media']['body'],
            'seal' => $theme['media']['seal'],
            'paper' => $theme['media']['paper'],
            'sheenMask' => $theme['media']['sheen'],
            'geometry' => $theme['geometry'],
            'sealSize' => $theme['seal_size'],
            'audio' => $theme['audio'],
            'volume' => ((int)$theme['sound_volume']) / 100,
            'sheen' => $theme['config']['sheen'] ?? 'copper',
            'cues' => ['ornament' => 0.14, 'seal' => 0.30, 'flap' => 0.38, 'paper' => 0.61],
        ];
        usort($events, fn($a, $b) => strcmp($a['start_utc'], $b['start_utc']));
        $storyStops = self::storyStops($theme, $content, $events, $cover, $lang);
        $story = $storyStops ? ['mp4' => $theme['media']['story_mp4'], 'webm' => $theme['media']['story_webm'], 'paper' => $theme['media']['paper'], 'stops' => $storyStops] : null;
        return [
            'story' => $story,
            'content' => $content, 'theme' => $theme, 'glang' => $lang, 'mode' => $mode, 'names' => $names, 'cover' => $cover,
            'events' => $events, 'pendingEvents' => $pending, 'gallery' => $gallery, 'coverPhoto' => $coverPhoto, 'engine' => $engine,
            'rsvpOpen' => !empty($content['rsvp']['enabled']) && !self::deadlinePassed($content),
            'deadlinePassed' => self::deadlinePassed($content),
        ] + $opts;
    }

    /** Vitrin önizlemesi: canlı müşteri kayıtlarından ayrı, kodla gelen örnek içerik. */
    public static function showcaseContent(string $lang): array
    {
        $year = (int)gmdate('Y') + 1;
        $names = ['tr' => ['İpek', 'Çağrı'], 'en' => ['Elizabeth', 'Alexander'], 'ru' => ['Анастасия', 'Александр']][$lang];
        $c = Content::defaults([$lang], $lang);
        $c['names'] = ['first' => $names[0], 'second' => $names[1], 'order' => 'ab'];
        $c['texts'][$lang] = [
            'intro' => t('showcase.intro', [], $lang),
            'letter' => t('showcase.letter', [], $lang),
            'quote' => t('showcase.quote', [], $lang),
            'quote_by' => t('showcase.quote_by', [], $lang),
            'dress' => t('showcase.dress', [], $lang),
            'transport' => '', 'stay' => '', 'notes' => '',
        ];
        $c['events'] = Content::sanitizeEvents([
            ['id' => 'demo0001', 'type' => 'civil', 'date' => "$year-06-12", 'start' => '17:30', 'end' => '18:30', 'tz' => 'Europe/Istanbul',
                'venue' => t('showcase.venue1', [], $lang), 'address' => t('showcase.address1', [], $lang)],
            ['id' => 'demo0002', 'type' => 'dinner', 'date' => "$year-06-12", 'start' => '20:00', 'end' => '01:00', 'tz' => 'Europe/Istanbul',
                'venue' => t('showcase.venue2', [], $lang), 'address' => t('showcase.address2', [], $lang)],
        ]);
        $c['cover_event_id'] = 'demo0001';
        $c['contact'] = ['name' => '', 'phone' => ''];
        $c['rsvp'] = ['enabled' => true, 'deadline' => "$year-05-31", 'ask_counts' => true, 'max_public' => 4];
        return $c;
    }
}
