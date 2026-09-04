<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function back(string $fallback = '/'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Només acceptem referents del mateix host, per evitar redireccions obertes.
        if ($ref !== '' && (str_starts_with($ref, '/') || parse_url($ref, PHP_URL_HOST) === $host)) {
            self::redirect($ref);
        }
        self::redirect($fallback);
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $content;
        exit;
    }

    public static function inline(string $content, string $filename, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
