<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitador de peticions senzill basat en fitxers (finestra lliscant).
 * Protegeix el formulari de consulta d'entrades i l'accés al Panell de Gestió.
 */
final class RateLimit
{
    private static function file(string $key): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    /** Retorna true si encara queden intents disponibles i compta l'actual. */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $file = self::file($key);
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded)) {
                $hits = array_values(array_filter($decoded, static fn ($t) => is_int($t) && $t > $now - $windowSeconds));
            }
        }
        if (count($hits) >= $maxAttempts) {
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        return true;
    }

    public static function clear(string $key): void
    {
        @unlink(self::file($key));
    }

    /** Segons que falten fins que es pugui tornar a provar. */
    public static function retryAfter(string $key, int $windowSeconds): int
    {
        $file = self::file($key);
        if (!is_file($file)) {
            return 0;
        }
        $hits = json_decode((string) @file_get_contents($file), true);
        if (!is_array($hits) || $hits === []) {
            return 0;
        }
        return max(0, (min($hits) + $windowSeconds) - time());
    }

    /** Neteja fitxers de control antics (l'invoca el cron). */
    public static function gc(int $olderThanSeconds = 86400): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (filemtime($file) < time() - $olderThanSeconds) {
                @unlink($file);
            }
        }
    }
}
