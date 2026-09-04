<?php
declare(strict_types=1);

namespace App\Core;

final class Money
{
    /** Formata cèntims com a text llegible (p. ex. 1.250 → "12,50 €"). */
    public static function format(int $cents, ?string $currency = null): string
    {
        $currency = $currency ?: (string) Settings::get('currency', 'EUR');
        $amount = number_format($cents / 100, 2, ',', '.');
        return match (strtoupper($currency)) {
            'EUR'   => $amount . ' €',
            'USD'   => '$' . $amount,
            'GBP'   => '£' . $amount,
            default => $amount . ' ' . strtoupper($currency),
        };
    }

    /** Converteix un import escrit per una persona ("12,50", "12.5") a cèntims. */
    public static function toCents(string|float|int $value): int
    {
        if (is_string($value)) {
            $value = str_replace([' ', '€', "\u{a0}"], '', $value);
            // Amb coma decimal els punts són separadors de milers.
            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        }
        return (int) round(((float) $value) * 100);
    }

    public static function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
