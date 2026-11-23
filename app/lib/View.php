<?php
declare(strict_types=1);

namespace App;

final class View
{
    public static array $shared = [];

    public static function render(string $tpl, array $data = [], ?string $layout = 'site'): string
    {
        $content = self::partial($tpl, $data);
        if ($layout === null) return $content;
        return self::partial('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $tpl, array $data = []): string
    {
        $file = APP_ROOT . '/app/views/' . $tpl . '.php';
        if (!is_file($file)) throw new \RuntimeException("Şablon yok: $tpl");
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    public static function show(string $tpl, array $data = [], ?string $layout = 'site', int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo self::render($tpl, $data, $layout);
    }
}
