<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function check(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
    }

    /** Valida el testimoni d'una petició POST o atura l'execució amb un 419. */
    public static function verifyRequest(): void
    {
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::check(is_string($token) ? $token : null)) {
            http_response_code(419);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>419 · Sessió caducada</h1><p>El formulari ha caducat. Torneu enrere i proveu-ho de nou.</p>';
            exit;
        }
    }
}
