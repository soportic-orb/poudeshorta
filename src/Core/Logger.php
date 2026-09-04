<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $m, array $c = []): void  { self::write('info', $m, $c); }
    public static function error(string $m, array $c = []): void { self::write('error', $m, $c); }
    public static function warn(string $m, array $c = []): void  { self::write('warning', $m, $c); }

    public static function exception(\Throwable $e, string $prefix = ''): void
    {
        self::write('error', trim($prefix . ' ' . $e->getMessage()), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'class' => $e::class,
        ]);
    }

    public static function audit(string $action, ?string $target = null, array $details = []): void
    {
        try {
            Db::insert('audit_log', [
                'actor'   => Auth::user()['email'] ?? 'sistema',
                'action'  => $action,
                'target'  => $target,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip'      => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            self::exception($e, 'audit');
        }
    }
}
