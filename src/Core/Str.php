<?php
declare(strict_types=1);

namespace App\Core;

final class Str
{
    /** Alfabet sense caràcters ambigus (0/O, 1/I/L) per a codis llegibles a mà. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function randomCode(int $length = 8): string
    {
        $max = strlen(self::CODE_ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::CODE_ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $value, string $separator = '-'): string
    {
        $value = self::ascii($value);
        $value = preg_replace('/[^a-zA-Z0-9]+/', $separator, $value) ?? '';
        return trim(strtolower($value), $separator);
    }

    public static function ascii(string $value): string
    {
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ñ'=>'n','ç'=>'c','·'=>'','º'=>'','ª'=>'',
        ];
        $value = strtr(mb_strtolower($value, 'UTF-8'), $map);
        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out ?: '?';
    }

    public static function limit(string $value, int $length, string $end = '…'): string
    {
        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length) . $end;
    }

    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($user, 0, min(2, mb_strlen($user)));
        return $visible . str_repeat('*', max(1, mb_strlen($user) - 2)) . '@' . $domain;
    }

    /**
     * Converteix text pla escrit per una persona en HTML segur: escapa
     * l'entrada, converteix els enllaços en clicables i respecta els
     * paràgrafs i els salts de línia.
     *
     * @param string $linkAttributes Atributs extra per als enllaços (els
     *                               correus necessiten estils en línia).
     */
    public static function toHtmlParagraphs(string $text, string $linkAttributes = ''): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $attributes = $linkAttributes !== '' ? ' ' . trim($linkAttributes) : '';
        $escaped = preg_replace(
            '#\b(https?://[^\s<]+)#',
            '<a href="$1"' . $attributes . '>$1</a>',
            $escaped
        ) ?? $escaped;

        $html = '';
        foreach (preg_split('/\R{2,}/', trim($escaped)) ?: [] as $paragraph) {
            $html .= '<p>' . nl2br(trim($paragraph)) . '</p>';
        }

        return $html;
    }

    /** Substitueix marcadors {{clau}} dins d'una plantilla de text. */
    public static function template(string $text, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static fn (array $m) => (string) ($vars[$m[1]] ?? $m[0]),
            $text
        ) ?? $text;
    }
}
