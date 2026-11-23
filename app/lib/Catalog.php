<?php
declare(strict_types=1);

namespace App;

/** Tema, paket ve ek hizmet okumaları. */
final class Catalog
{
    public static function themes(bool $activeOnly = true): array
    {
        $rows = DB::all('SELECT * FROM themes' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, id');
        return array_map([self::class, 'hydrateTheme'], $rows);
    }

    public static function theme(int $id): ?array
    {
        $r = DB::one('SELECT * FROM themes WHERE id = ?', [$id]);
        return $r ? self::hydrateTheme($r) : null;
    }

    public static function themeBySlug(string $slug): ?array
    {
        $r = DB::one('SELECT * FROM themes WHERE slug = ?', [$slug]);
        return $r ? self::hydrateTheme($r) : null;
    }

    public static function hydrateTheme(array $r): array
    {
        $r['name'] = jdec($r['name_json']);
        $r['description'] = jdec($r['description_json']);
        $r['config'] = jdec($r['config_json']);
        $dir = App::publicPath('themes/' . $r['slug']);
        $meta = is_file("$dir/theme.json") ? jdec((string)file_get_contents("$dir/theme.json")) : [];
        $r['geometry'] = array_merge($meta['geometry'] ?? [], $r['config']['geometry'] ?? []);
        $r['seal_size'] = $meta['sealSize'] ?? 44;
        $r['colors'] = array_merge($meta['colors'] ?? [], $r['config']['colors'] ?? []);
        $rev = (int)$r['media_rev'];
        $f = fn(string $n) => is_file("$dir/$n") ? theme_asset($r['slug'], $n, $rev) : null;
        $r['media'] = [
            'poster' => $f('poster.webp'),
            'poster_small' => $f('poster-small.webp'),
            'preview' => $f('preview.webp') ?? $f('poster.webp'),
            'video' => $f('opening.mp4') ?? $f('opening.webm'),
            'video_mp4' => $f('opening.mp4'),
            'video_webm' => $f('opening.webm'),
            'video_wide' => $f('opening-wide.mp4'),
            'texture' => $f('texture.webp'),
            'ornament' => $f('ornament.webp'),
            'seal' => $f('seal.webp'),
            'paper' => $f('paper.webp'),
            'sheen' => $f('sheen-mask.webp'),
        ];
        $audio = [];
        foreach (['ornament', 'seal', 'flap', 'paper'] as $a) {
            foreach (['mp3', 'm4a', 'ogg', 'wav'] as $ext) {
                if (is_file("$dir/audio/$a.$ext")) { $audio[$a] = theme_asset($r['slug'], "audio/$a.$ext", $rev); break; }
            }
        }
        $r['audio'] = $audio;
        return $r;
    }

    public static function packages(bool $onSaleOnly = false): array
    {
        $rows = DB::all('SELECT * FROM packages' . ($onSaleOnly ? ' WHERE is_on_sale = 1' : '') . ' ORDER BY sort, id');
        return array_map(function ($r) {
            $r['name'] = jdec($r['name_json']);
            $r['scope'] = jdec($r['scope_json']);
            return $r;
        }, $rows);
    }

    public static function package(int $id): ?array
    {
        foreach (self::packages() as $p) if ((int)$p['id'] === $id) return $p;
        return null;
    }

    public static function addons(bool $activeOnly = true): array
    {
        $rows = DB::all('SELECT * FROM addons' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, id');
        return array_map(function ($r) {
            $r['name'] = jdec($r['name_json']);
            $r['description'] = jdec($r['description_json']);
            return $r;
        }, $rows);
    }

    /** Paket satın alınabilir mi? Fiyatı girilmemiş paket önizlenir ama ödenemez. */
    public static function purchasable(array $p): bool
    {
        return (int)$p['is_on_sale'] === 1 && $p['price_minor'] !== null && (int)$p['price_minor'] > 0;
    }
}
