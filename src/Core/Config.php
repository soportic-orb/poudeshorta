<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuració de fitxer (credencials de BD, clau de l'aplicació...).
 * Tota la resta de configuració viu a la taula `settings`.
 */
final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$data = is_file($path) ? (array) require $path : [];
        self::$loaded = true;
    }

    public static function isInstalled(): bool
    {
        return self::$loaded && !empty(self::$data['db']['name']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$data;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$data;
    }

    /** Escriu el fitxer de configuració (l'usa l'instal·lador). */
    public static function write(string $path, array $data): bool
    {
        $export = var_export($data, true);
        $php = "<?php\n// Fitxer generat automàticament per l'instal·lador. No el pugeu al repositori.\nreturn {$export};\n";
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $ok = file_put_contents($path, $php, LOCK_EX) !== false;
        if ($ok) {
            @chmod($path, 0640);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        }
        return $ok;
    }
}
