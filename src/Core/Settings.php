<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuració editable des del Panell de Gestió (taula `settings`).
 */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        // Esdeveniment
        'event_name'          => 'Sopar de germanor del carrer Pou de s\'Horta',
        'event_tagline'       => 'Gran paellada popular!',
        'event_description'   => "Vine a gaudir del sopar de germanor del carrer Pou de s'Horta. Una gran paellada popular al carrer, música ambient i bingo per a totes les edats.",
        'event_date'          => '',
        'event_date_text'     => '26 de setembre, al vespre',
        'event_location'      => 'Carrer Pou de s\'Horta',
        'event_city'          => '',
        'event_map_url'       => '',
        'event_highlights'    => "Gran paellada popular\nMúsica ambient\nBingo",
        'event_organizer'     => 'Comissió de Festes del carrer Pou de s\'Horta',
        'event_contact_email' => '',
        'event_contact_phone' => '',
        'event_poster'        => '',
        'hero_image'          => '',
        'hero_overlay'        => '55',
        'hero_focus'          => 'center',
        'hero_show_poster'    => '1',
        'event_logo'          => '',

        // Marca / colors (extrets del cartell)
        'brand_primary'   => '#8C1027',
        'brand_secondary' => '#E8621E',
        'brand_accent'    => '#F2A81D',
        'brand_cream'     => '#FBF4E6',
        'brand_olive'     => '#6E8A4E',
        'brand_ink'       => '#20232B',

        // Vendes
        'sales_open'          => '1',
        'sales_closed_message' => 'Les inscripcions estan tancades. Gràcies per l\'interès!',
        'currency'            => 'EUR',
        'max_tickets_order'   => '10',
        'terms_url'           => '',
        'privacy_text'        => '',
        'require_terms'       => '1',

        // Anul·lacions
        'cancellation_enabled'       => '1',
        'cancellation_deadline_days' => '7',
        'cancellation_deadline_date' => '',
        'cancellation_fee_percent'   => '0',
        'cancellation_refund'        => '1',
        'cancellation_policy_text'   => 'Podeu anul·lar la vostra inscripció fins a 7 dies abans de l\'esdeveniment i se us retornarà l\'import íntegre.',
        'cancellation_allow_partial' => '1',

        // Stripe
        'stripe_mode'            => 'test',
        'stripe_test_pk'         => '',
        'stripe_test_sk'         => '',
        'stripe_test_wh_secret'  => '',
        'stripe_live_pk'         => '',
        'stripe_live_sk'         => '',
        'stripe_live_wh_secret'  => '',
        'stripe_locale'          => 'auto',
        'stripe_fee_percent'     => '1.5',
        'stripe_fee_fixed_cents' => '25',
        'show_stripe_fee'        => '1',
        'checkout_notice_enabled' => '0',
        'checkout_notice'         => '',

        // SMTP
        'smtp_host'       => '',
        'smtp_port'       => '587',
        'smtp_secure'     => 'tls',
        'smtp_user'       => '',
        'smtp_pass'       => '',
        'smtp_auth'       => '1',
        'smtp_from_email' => '',
        'smtp_from_name'  => 'Pou de s\'Horta',
        'smtp_reply_to'   => '',
        'smtp_batch_size' => '25',
        'mail_footer'     => 'Rebeu aquest correu perquè us heu inscrit a un esdeveniment del carrer Pou de s\'Horta.',

        // Correus transaccionals
        'mail_confirmation_subject' => 'La vostra inscripció · {{event_name}}',
        'mail_confirmation_body'    => "Hola {{name}},\n\nLa vostra inscripció s'ha confirmat correctament.\n\nReferència: {{reference}}\nEntrades: {{ticket_count}}\nImport: {{total}}\n\nTrobareu les entrades adjuntes en aquest correu en format PDF.\n\nFins aviat!\n{{event_organizer}}",
        'mail_cancellation_subject' => 'Anul·lació de la inscripció {{reference}}',
        'mail_cancellation_body'    => "Hola {{name}},\n\nHem anul·lat la vostra inscripció {{reference}}.\n\n{{refund_note}}\n\nGràcies,\n{{event_organizer}}",

        // Apple / Google Wallet
        'wallet_enabled'            => '0',
        'apple_pass_type_id'        => '',
        'apple_team_id'             => '',
        'apple_organization'        => 'Pou de s\'Horta',
        'apple_cert_path'           => '',
        'apple_key_path'            => '',
        'apple_key_password'        => '',
        'apple_wwdr_path'           => '',
        'google_issuer_id'          => '',
        'google_class_suffix'       => 'poudeshorta_event',
        'google_service_account_json' => '',
        'google_class_registered' => '',

        // OTA
        'ota_repo'          => 'soportic-orb/poudeshorta',
        'ota_branch'        => 'main',
        'ota_channel'       => 'branch',
        'ota_token'         => '',
        'ota_auto_check'    => '1',
        'ota_last_check'    => '',
        'ota_latest_version' => '',
        'ota_latest_sha'    => '',
        'ota_latest_ref'    => '',
        'ota_installed_ref' => '',

        // Sistema
        'maintenance_mode'  => '0',
        'cron_token'        => '',
        'last_cron_run'     => '',
        'checkin_enabled'   => '1',
        'timezone'          => 'Europe/Madrid',
        'google_analytics'  => '',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $stored = [];
        try {
            foreach (Db::all('SELECT `k`, `v` FROM `settings`') as $row) {
                $stored[$row['k']] = $row['v'];
            }
        } catch (\Throwable) {
            // Encara no instal·lat: fem servir només els valors per defecte.
        }
        self::$cache = array_merge(self::DEFAULTS, $stored);
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        $value = $all[$key] ?? self::DEFAULTS[$key] ?? $default;
        return $value === null ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? '1' : '0');
        return in_array((string) $v, ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, (string) $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        Db::run(
            'INSERT INTO `settings` (`k`, `v`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)',
            ['k' => $key, 'v' => $value]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::set((string) $k, $v);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Clau secreta de Stripe segons el mode actiu. */
    public static function stripeSecret(): string
    {
        return (string) self::get(self::get('stripe_mode') === 'live' ? 'stripe_live_sk' : 'stripe_test_sk', '');
    }

    public static function stripePublishable(): string
    {
        return (string) self::get(self::get('stripe_mode') === 'live' ? 'stripe_live_pk' : 'stripe_test_pk', '');
    }

    public static function stripeWebhookSecret(): string
    {
        return (string) self::get(self::get('stripe_mode') === 'live' ? 'stripe_live_wh_secret' : 'stripe_test_wh_secret', '');
    }

    public static function stripeConfigured(): bool
    {
        return self::stripeSecret() !== '' && self::stripePublishable() !== '';
    }
}
