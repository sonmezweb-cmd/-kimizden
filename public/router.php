<?php
// Yalnız yerel geliştirme: php -S localhost:8080 -t public public/router.php
// (cPanel'de Apache/LiteSpeed statik dosyaları kendisi sunar; bu dosya kullanılmaz.)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = __DIR__ . $path;
if ($path !== '/' && is_file($f) && !str_ends_with($f, '.php')) {
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    // PHP geliştirme sunucusu Range desteklemez; video kaydırması için bayt aralığı gerekir
    if (in_array($ext, ['mp4', 'webm', 'mp3', 'm4a', 'ogg', 'wav'], true)) {
        $types = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'];
        $size = filesize($f);
        $start = 0; $end = $size - 1;
        header('Content-Type: ' . $types[$ext]);
        header('Accept-Ranges: bytes');
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'] ?? '', $m)) {
            if ($m[1] !== '') $start = (int)$m[1];
            if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
            if ($m[1] === '' && $m[2] !== '') { $start = $size - (int)$m[2]; $end = $size - 1; }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($f, 'rb');
        fseek($fp, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fp)) { $chunk = fread($fp, min(65536, $left)); echo $chunk; $left -= strlen($chunk); }
        fclose($fp);
        return true;
    }
    return false;
}
require __DIR__ . '/index.php';
