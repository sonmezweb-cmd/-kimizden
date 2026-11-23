<?php
declare(strict_types=1);

namespace App;

/**
 * Müşteri fotoğrafları web kökü dışında saklanır; GD ile yeniden kodlanır (EXIF/gömülü içerik atılır).
 * Tema medyası (poster/video/ses) herkese açık themes/ klasöründedir.
 */
final class Media
{
    public const MAX_PHOTO_BYTES = 15 * 1024 * 1024;
    public const SIZES = ['s' => 480, 'm' => 1080, 'l' => 1800];

    public static function dir(): string
    {
        $d = App::storagePath('uploads/photos');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    /** @return array{ok:bool, error?:string, media?:array} */
    public static function storePhoto(int $orderId, array $file, ?int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'upload.err.size' : 'upload.err.failed'];
        }
        if (!is_uploaded_file($file['tmp_name']) && empty(App::$config['testing'])) return ['ok' => false, 'error' => 'upload.err.failed'];
        if ($file['size'] > self::MAX_PHOTO_BYTES) return ['ok' => false, 'error' => 'upload.err.size'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return ['ok' => false, 'error' => 'upload.err.type'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return ['ok' => false, 'error' => 'upload.err.type'];
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] < 200 || $info[1] < 200 || $info[0] * $info[1] > 60_000_000) return ['ok' => false, 'error' => 'upload.err.dimensions'];
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png' => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        };
        if (!$img) return ['ok' => false, 'error' => 'upload.err.type'];
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            $o = (int)($exif['Orientation'] ?? 1);
            if ($o === 3) $img = imagerotate($img, 180, 0);
            elseif ($o === 6) $img = imagerotate($img, -90, 0);
            elseif ($o === 8) $img = imagerotate($img, 90, 0);
        }
        $img = self::fit($img, 2400);
        $key = bin2hex(random_bytes(16));
        $sub = substr($key, 0, 2);
        @mkdir(self::dir() . '/' . $sub, 0770, true);
        $rel = "photos/$sub/$key.jpg";
        $path = App::storagePath('uploads/' . $rel);
        imageinterlace($img, true);
        imagejpeg($img, $path, 86);
        $w = imagesx($img); $h = imagesy($img);
        imagedestroy($img);
        $id = DB::insert('media', [
            'order_id' => $orderId, 'mkey' => $key, 'path' => $rel, 'mime' => 'image/jpeg', 'width' => $w, 'height' => $h,
            'bytes' => (int)filesize($path), 'original_name' => mb_substr(basename((string)$file['name']), 0, 180), 'uploaded_by' => $userId, 'created_at' => DB::now(),
        ]);
        return ['ok' => true, 'media' => ['id' => $id, 'key' => $key, 'width' => $w, 'height' => $h]];
    }

    private static function fit(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img); $h = imagesy($img);
        if ($w <= $max && $h <= $max) {
            $out = imagecreatetruecolor($w, $h);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
            return $out;
        }
        $s = $max / max($w, $h);
        $nw = (int)round($w * $s); $nh = (int)round($h * $s);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }

    public static function forOrder(int $orderId): array
    {
        return DB::all('SELECT * FROM media WHERE order_id = ? AND deleted_at IS NULL ORDER BY id', [$orderId]);
    }

    public static function url(string $key, string $size = 'm'): string
    {
        return url_raw('/m/' . $key . '/' . $size);
    }

    /** Boyutlandırılmış sürümü (önbellekli) diske yazar, yolunu döner. */
    public static function sized(array $m, string $size): string
    {
        $src = App::storagePath('uploads/' . $m['path']);
        $max = self::SIZES[$size] ?? 1080;
        if (max($m['width'], $m['height']) <= $max) return $src;
        $cache = App::storagePath('cache/img/' . $m['mkey'] . '-' . $size . '.jpg');
        if (is_file($cache)) return $cache;
        @mkdir(dirname($cache), 0770, true);
        $img = @imagecreatefromjpeg($src);
        if (!$img) return $src;
        $out = self::fit($img, $max);
        imageinterlace($out, true);
        imagejpeg($out, $cache, 82);
        return $cache;
    }
}
