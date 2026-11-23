<?php
declare(strict_types=1);

namespace App;

final class Router
{
    public static ?string $path = null;
    private array $routes = [];

    public function add(string $method, string $pattern, string $handler, array $opts = []): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9_\-\.]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler, $opts];
    }

    public function get(string $p, string $h, array $o = []): void { $this->add('GET', $p, $h, $o); }
    public function post(string $p, string $h, array $o = []): void { $this->add('POST', $p, $h, $o); }

    /** İstek yolunu çözer: temel yol ve dil öneki ayrılır. */
    public static function resolvePath(): string
    {
        $uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $base = App::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . ltrim($uri, '/');
        if (preg_match('#^/(tr|en|ru)(/.*)?$#', $uri, $m)) {
            I18n::setFromPrefix($m[1]);
            $uri = $m[2] ?? '/';
            if ($uri === '') $uri = '/';
        }
        if ($uri !== '/' && str_ends_with($uri, '/')) $uri = rtrim($uri, '/');
        return $uri;
    }

    public function dispatch(string $method, string $path): void
    {
        self::$path = $path;
        $allowed = [];
        foreach ($this->routes as [$m, $regex, $handler, $opts]) {
            if (!preg_match($regex, $path, $mm)) continue;
            if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) { $allowed[] = $m; continue; }
            $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST' && empty($opts['no_csrf'])) {
                Csrf::check();
            }
            if (!empty($opts['auth'])) {
                Auth::require($opts['auth'] === true ? null : (array)$opts['auth']);
            }
            [$cls, $fn] = explode('@', $handler);
            $cls = 'App\\' . $cls;
            (new $cls())->$fn(...array_values($params));
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            abort(405);
        }
        abort(404, t('error.not_found'));
    }
}
