<?php
declare(strict_types=1);

use App\App;
use App\I18n;
use App\Csrf;

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Çeviri: t('nav.home', ['name' => 'x']) */
function t(string $key, array $vars = [], ?string $lang = null): string
{
    return I18n::t($key, $vars, $lang);
}

/** Dil önekli bağlantı (TR varsayılan, önek yok). */
function url(string $path = '/', ?string $lang = null, array $query = []): string
{
    $lang = $lang ?? I18n::$lang;
    $path = '/' . ltrim($path, '/');
    $prefix = ($lang !== I18n::DEFAULT) ? '/' . $lang : '';
    if ($path === '/' && $prefix !== '') $path = '';
    $u = App::baseUrl() . $prefix . $path;
    if ($query) $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    return $u;
}

/** Dil öneki olmayan bağlantı (davetiye, medya, ödeme bildirimi). */
function url_raw(string $path = '/', array $query = []): string
{
    $u = App::baseUrl() . '/' . ltrim($path, '/');
    if ($query) $u .= '?' . http_build_query($query);
    return $u;
}

function asset(string $path): string
{
    $file = App::publicPath('assets/' . ltrim($path, '/'));
    $v = is_file($file) ? substr(md5((string)filemtime($file)), 0, 8) : APP_VERSION;
    return App::baseUrl() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function theme_asset(string $slug, string $file, int $rev = 1): string
{
    return App::baseUrl() . '/themes/' . rawurlencode($slug) . '/' . $file . '?r=' . $rev;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function str_in(string $key, int $max = 2000): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    $v = str_replace("\r\n", "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function abort(int $code, string $msg = ''): never
{
    http_response_code($code);
    if (wants_json()) json_out(['ok' => false, 'error' => $msg ?: t('error.generic')], $code);
    echo \App\View::render('errors/http', ['code' => $code, 'msg' => $msg], 'site');
    exit;
}

/** Tam $chars uzunlukta, URL güvenli alfanümerik rastgele dizi (karakter başına ~5,95 bit). */
function random_token(int $chars = 24): string
{
    $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $out = '';
    for ($i = 0; $i < $chars; $i++) $out .= $alphabet[random_int(0, 61)];
    return $out;
}

function client_ip(): string
{
    if (!empty(App::$config['trust_proxy']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        if (filter_var($parts[0], FILTER_VALIDATE_IP)) return $parts[0];
    }
    if (!empty(App::$config['trust_proxy']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function jdec(?string $json, mixed $default = []): mixed
{
    if ($json === null || $json === '') return $default;
    $v = json_decode($json, true);
    return $v === null ? $default : $v;
}

function jenc(mixed $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Çok dilli JSON alanından seçili dili (yoksa TR, sonra ilk dolu) okur. */
function loc(array|string|null $m, ?string $lang = null): string
{
    if (is_string($m)) $m = jdec($m, []);
    if (!$m) return '';
    $lang = $lang ?? I18n::$lang;
    if (!empty($m[$lang])) return (string)$m[$lang];
    if (!empty($m['tr'])) return (string)$m['tr'];
    foreach ($m as $v) if ($v !== '' && $v !== null) return (string)$v;
    return '';
}

/** Paragraf ve satır sonlarını koruyarak güvenli metin. */
function nl_paras(string $text): string
{
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '') return '';
    $paras = preg_split("/\n{2,}/", $text) ?: [];
    return implode('', array_map(fn($p) => '<p>' . nl2br(e($p), false) . '</p>', $paras));
}

function current_path(): string
{
    return \App\Router::$path ?? '/';
}

function is_active_path(string $prefix): bool
{
    $p = current_path();
    return $p === $prefix || ($prefix !== '/' && str_starts_with($p, rtrim($prefix, '/') . '/'));
}
