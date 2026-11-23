<?php
declare(strict_types=1);

namespace App;

/** Davetiye motoru için görünüm verisi. Vitrin, önizleme ve canlı yayın aynı motoru kullanır. */
final class InviteView
{
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
            'videoWide' => $theme['media']['video_wide'],
            'poster' => $theme['media']['poster'],
            'geometry' => $theme['geometry'],
            'sealSize' => $theme['seal_size'],
            'audio' => $theme['audio'],
            'volume' => ((int)$theme['sound_volume']) / 100,
            'sheen' => $theme['config']['sheen'] ?? 'copper',
            'cues' => ['ornament' => 0.14, 'seal' => 0.30, 'flap' => 0.38, 'paper' => 0.61],
        ];
        return [
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
        $c['rsvp'] = ['enabled' => true, 'deadline' => "$year-05-31", 'ask_counts' => true, 'max_public' => 4];
        return $c;
    }
}
