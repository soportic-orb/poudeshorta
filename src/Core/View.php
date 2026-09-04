<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $layout = 'layouts/public';
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function layout(string $layout): void
    {
        self::$layout = $layout;
    }

    /** Renderitza una vista dins del layout actiu. */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        echo self::capture($view, $data, $layout ?? self::$layout);
    }

    public static function capture(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** Renderitza una vista sense layout. */
    public static function partial(string $view, array $data = []): string
    {
        $file = dirname(__DIR__) . '/Views/' . str_replace(['..', '\\'], '', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista no trobada: {$view}");
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
