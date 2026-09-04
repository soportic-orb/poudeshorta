<?php
declare(strict_types=1);

namespace App\Core;

final class Url
{
    /** URL absoluta de l'aplicació, deduïda de la petició o de la configuració. */
    public static function base(): string
    {
        $configured = trim((string) Config::get('base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $scheme = Request::isSecure() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . Request::basePath();
    }

    /** URL relativa a l'arrel de l'aplicació (per a href dins de les pàgines). */
    public static function to(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        return (Request::basePath() ?: '') . ($path === '/' ? '/' : rtrim($path, '/'));
    }

    /** URL absoluta (per a correus, PDF, webhooks, passes de wallet). */
    public static function full(string $path = '/'): string
    {
        return self::base() . ('/' . ltrim($path, '/'));
    }

    public static function asset(string $path): string
    {
        $rel = '/assets/' . ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public' . $rel;
        $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
        return self::to($rel) . '?v=' . $version;
    }

    /** Conserva els paràmetres actuals de la query afegint-ne o substituint-ne alguns. */
    public static function withQuery(array $params, ?string $path = null): string
    {
        $current = $_GET;
        unset($current['r']);
        $merged = array_filter(
            array_merge($current, $params),
            static fn ($v) => $v !== null && $v !== ''
        );
        $qs = http_build_query($merged);
        return self::to($path ?? Request::path()) . ($qs !== '' ? '?' . $qs : '');
    }
}
