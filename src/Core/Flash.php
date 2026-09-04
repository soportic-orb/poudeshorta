<?php
declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $all = Session::get(self::KEY, []);
        $all[] = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $all);
    }

    public static function success(string $m): void { self::add('success', $m); }
    public static function error(string $m): void   { self::add('error', $m); }
    public static function info(string $m): void    { self::add('info', $m); }
    public static function warning(string $m): void { self::add('warning', $m); }

    /** @return array<int, array{type:string,message:string}> */
    public static function pull(): array
    {
        $all = Session::get(self::KEY, []);
        Session::forget(self::KEY);
        return is_array($all) ? $all : [];
    }

    public static function setOld(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirm']);
        Session::set('_old', $input);
    }

    /** Valor anterior d'un camp; accepta notació amb punts per a camps agrupats. */
    public static function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old', []);
        if (!is_array($old)) {
            return $default;
        }

        $value = $old;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return is_scalar($value) ? $value : $default;
    }

    public static function clearOld(): void
    {
        Session::forget('_old');
    }

    public static function setErrors(array $errors): void
    {
        Session::set('_errors', $errors);
    }

    public static function errors(): array
    {
        $e = Session::pull('_errors', []);
        return is_array($e) ? $e : [];
    }
}
