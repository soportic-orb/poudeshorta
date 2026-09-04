<?php
declare(strict_types=1);

use App\Core\Money;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;

/** Escapa text per a HTML. */
function e(mixed $value): string
{
    return Str::e($value === null ? '' : (string) $value);
}

/** URL interna de l'aplicació. */
function url(string $path = '/'): string
{
    return Url::to($path);
}

/** Recurs estàtic amb control de memòria cau. */
function asset(string $path): string
{
    return Url::asset($path);
}

/** Import en cèntims formatat com a text. */
function money(int $cents): string
{
    return Money::format($cents);
}

/** Valor de configuració. */
function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/** Data en format català curt. */
function dt(?string $value, string $format = 'd/m/Y H:i'): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts === false ? '—' : date($format, $ts);
}

/** Marca «selected» o «checked» segons una condició. */
function selectedIf(bool $condition): string
{
    return $condition ? ' selected' : '';
}

function checkedIf(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Camp d'un formulari recuperant el valor anterior després d'un error. */
function old(string $key, mixed $default = ''): string
{
    return e(App\Core\Flash::old($key, $default));
}
