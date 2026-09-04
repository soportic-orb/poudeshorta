<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Camí sol·licitat, normalitzat i sense query string. */
    public static function path(): string
    {
        if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
            $path = $_GET['r'];
        } else {
            $uri  = $_SERVER['REQUEST_URI'] ?? '/';
            $path = (string) parse_url($uri, PHP_URL_PATH);
            $base = self::basePath();
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Subdirectori d'instal·lació, si n'hi ha (p. ex. /inscripcions).
     *
     * Només deduïm el prefix quan SCRIPT_NAME apunta realment al controlador
     * frontal: alguns servidors (com el servidor integrat de PHP) hi posen el
     * camí sol·licitat i, si no ho comprovéssim, retallaríem la ruta.
     */
    public static function basePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (!str_ends_with($script, '.php')) {
            return '';
        }
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $dir === '/' ? '' : $dir;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return self::post($key, self::get($key, $default));
    }

    public static function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    }

    public static function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function wantsJson(): bool
    {
        return str_contains((string) self::header('Accept'), 'application/json')
            || self::header('X-Requested-With') === 'XMLHttpRequest';
    }
}
