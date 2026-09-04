<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\View;
use App\Services\AppleWallet;
use App\Services\GoogleWallet;
use App\Services\Mailer;
use App\Services\StripeClient;

final class SettingsController
{
    // ------------------------------------------------------------ Esdeveniment

    public function event(): void
    {
        $this->page('admin/settings_event', 'Dades de l\'esdeveniment');
    }

    public function saveEvent(): void
    {
        $this->persist([
            'event_name', 'event_tagline', 'event_description', 'event_date', 'event_date_text',
            'event_location', 'event_city', 'event_map_url', 'event_highlights', 'event_organizer',
            'event_contact_email', 'event_contact_phone', 'timezone', 'google_analytics',
            'terms_url', 'privacy_text', 'sales_closed_message',
        ], [
            'sales_open'        => 'bool',
            'require_terms'     => 'bool',
            'maintenance_mode'  => 'bool',
            'checkin_enabled'   => 'bool',
            'max_tickets_order' => 'int',
        ]);

        Logger::audit('configura_esdeveniment');
        Flash::success('Dades de l\'esdeveniment desades.');
        Response::redirect(Url::to('/admin/configuracio'));
    }

    // ---------------------------------------------------------------- Aparença

    public function appearance(): void
    {
        $this->page('admin/settings_appearance', 'Aparença i marca');
    }

    public function saveAppearance(): void
    {
        foreach (['brand_primary', 'brand_secondary', 'brand_accent', 'brand_cream', 'brand_olive', 'brand_ink'] as $key) {
            $value = trim((string) Request::post($key, ''));
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                Settings::set($key, strtoupper($value));
            }
        }

        Settings::set('hero_overlay', (string) max(0, min(90, (int) Request::post('hero_overlay', 55))));
        Settings::set('hero_focus', in_array((string) Request::post('hero_focus'), ['top', 'center', 'bottom'], true)
            ? (string) Request::post('hero_focus')
            : 'center');
        Settings::set('hero_show_poster', Request::post('hero_show_poster') ? '1' : '0');

        foreach (['event_poster' => 'poster', 'event_logo' => 'logo', 'hero_image' => 'hero'] as $setting => $field) {
            if (!empty($_FILES[$field]['tmp_name'])) {
                $path = $this->storeUpload($field, ['jpg', 'jpeg', 'png', 'webp']);
                if ($path !== null) {
                    Settings::set($setting, $path);
                }
            }
            if (Request::post('remove_' . $field)) {
                Settings::set($setting, '');
            }
        }

        Logger::audit('configura_aparenca');
        Flash::success('Aparença desada.');
        Response::redirect(Url::to('/admin/configuracio/aparenca'));
    }

    // --------------------------------------------------------------- Pagaments

    public function payments(): void
    {
        $this->page('admin/settings_payments', 'Pagaments (Stripe)', [
            'webhookUrl' => Url::full('/webhook/stripe'),
        ]);
    }

    public function savePayments(): void
    {
        $mode = (string) Request::post('stripe_mode', 'test');
        Settings::set('stripe_mode', $mode === 'live' ? 'live' : 'test');
        Settings::set('currency', strtoupper(substr((string) Request::post('currency', 'EUR'), 0, 3)));

        $locale = (string) Request::post('stripe_locale', 'auto');
        Settings::set('stripe_locale', in_array($locale, StripeClient::LOCALES, true) ? $locale : 'auto');

        foreach ([
            'stripe_test_pk', 'stripe_test_sk', 'stripe_test_wh_secret',
            'stripe_live_pk', 'stripe_live_sk', 'stripe_live_wh_secret',
        ] as $key) {
            $value = trim((string) Request::post($key, ''));
            // Els camps secrets es deixen en blanc al formulari: només es desen si s'omplen.
            if ($value !== '' && !str_starts_with($value, '••')) {
                Settings::set($key, $value);
            }
            if (Request::post('clear_' . $key)) {
                Settings::set($key, '');
            }
        }

        Logger::audit('configura_stripe', null, ['mode' => Settings::get('stripe_mode')]);
        Flash::success('Configuració de pagaments desada.');
        Response::redirect(Url::to('/admin/configuracio/pagaments'));
    }

    // ------------------------------------------------------------------ Correu

    public function mail(): void
    {
        $this->page('admin/settings_mail', 'Correu electrònic', [
            'smtpReady' => (new Mailer())->isConfigured(),
        ]);
    }

    public function saveMail(): void
    {
        $this->persist([
            'smtp_host', 'smtp_secure', 'smtp_user', 'smtp_from_email', 'smtp_from_name',
            'smtp_reply_to', 'mail_footer',
            'mail_confirmation_subject', 'mail_confirmation_body',
            'mail_cancellation_subject', 'mail_cancellation_body',
        ], [
            'smtp_port'       => 'int',
            'smtp_auth'       => 'bool',
            'smtp_batch_size' => 'int',
        ]);

        $password = (string) Request::post('smtp_pass', '');
        if ($password !== '' && !str_starts_with($password, '••')) {
            Settings::set('smtp_pass', $password);
        }
        if (Request::post('clear_smtp_pass')) {
            Settings::set('smtp_pass', '');
        }

        Logger::audit('configura_smtp');
        Flash::success('Configuració de correu desada.');
        Response::redirect(Url::to('/admin/configuracio/correu'));
    }

    public function testMail(): void
    {
        $to = trim((string) Request::post('test_email', '')) ?: (string) (Auth::user()['email'] ?? '');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Indiqueu una adreça electrònica vàlida.');
            Response::redirect(Url::to('/admin/configuracio/correu'));
        }

        [$ok, $message] = (new Mailer())->sendTest($to);
        $ok ? Flash::success($message . ' (destinatari: ' . $to . ')') : Flash::error($message);

        Response::redirect(Url::to('/admin/configuracio/correu'));
    }

    // ------------------------------------------------------------- Anul·lacions

    public function cancellations(): void
    {
        $this->page('admin/settings_cancellations', 'Política d\'anul·lacions');
    }

    public function saveCancellations(): void
    {
        $this->persist([
            'cancellation_policy_text', 'cancellation_deadline_date', 'cancellation_fee_percent',
        ], [
            'cancellation_enabled'       => 'bool',
            'cancellation_refund'        => 'bool',
            'cancellation_allow_partial' => 'bool',
            'cancellation_deadline_days' => 'int',
        ]);

        Logger::audit('configura_anullacions');
        Flash::success('Política d\'anul·lacions desada.');
        Response::redirect(Url::to('/admin/configuracio/anullacions'));
    }

    // ------------------------------------------------------------------ Wallet

    public function wallet(): void
    {
        $this->page('admin/settings_wallet', 'Apple Wallet i Google Wallet', [
            'appleHint'  => AppleWallet::configurationHint(),
            'googleHint' => GoogleWallet::configurationHint(),
            'appleOk'    => AppleWallet::isConfigured(),
            'googleOk'   => GoogleWallet::isConfigured(),
        ]);
    }

    public function saveWallet(): void
    {
        $this->persist([
            'apple_pass_type_id', 'apple_team_id', 'apple_organization',
            'google_issuer_id', 'google_class_suffix',
        ], [
            'wallet_enabled' => 'bool',
        ]);

        // La contrasenya primer: la necessitem per obrir el .p12 que puguin pujar.
        $password = (string) Request::post('apple_key_password', '');
        if ($password !== '' && !str_starts_with($password, '••')) {
            Settings::set('apple_key_password', $password);
        } else {
            $password = (string) Settings::get('apple_key_password');
        }

        // Els certificats es desen fora de public/ perquè no siguin accessibles pel web.
        foreach ([
            'apple_cert_path' => ['field' => 'apple_cert', 'ext' => ['p12', 'pfx', 'pem', 'cer', 'crt']],
            'apple_key_path'  => ['field' => 'apple_key',  'ext' => ['pem', 'key']],
            'apple_wwdr_path' => ['field' => 'apple_wwdr', 'ext' => ['pem', 'cer', 'crt']],
        ] as $setting => $spec) {
            if (!empty($_FILES[$spec['field']]['tmp_name'])) {
                $path = $this->storeCertificate($spec['field'], $spec['ext']);
                if ($path !== null) {
                    Settings::set($setting, $path);
                    if ($setting === 'apple_cert_path') {
                        $this->convertPkcs12($path, $password);
                    }
                }
            }
        }

        $this->saveGoogleServiceAccount();

        Logger::audit('configura_wallet');
        Flash::success('Configuració dels passis desada.');
        Response::redirect(Url::to('/admin/configuracio/wallet'));
    }

    /**
     * Converteix un .p12 acabat de pujar en la parella certificat + clau en PEM.
     *
     * Ho fem en el moment de pujar-lo perquè l'error surti aquí i no la primera
     * vegada que algú provi de descarregar-se el passi. A més, els .p12 que
     * exporta el Keychain del Mac fan servir algorismes antics que molts
     * servidors amb OpenSSL 3 no llegeixen directament.
     */
    private function convertPkcs12(string $path, string $password): void
    {
        if (!preg_match('/\.(p12|pfx)$/i', $path)) {
            return;
        }

        try {
            $bundle = AppleWallet::extractPkcs12((string) file_get_contents($path), $password);
        } catch (\Throwable $e) {
            Flash::warning('El certificat s\'ha desat, però no s\'ha pogut obrir: ' . $e->getMessage());
            return;
        }

        $dir = dirname($path);
        $certPath = $dir . '/apple_cert.pem';
        $keyPath  = $dir . '/apple_key.pem';

        if (file_put_contents($certPath, $bundle['cert']) === false
            || file_put_contents($keyPath, $bundle['key']) === false) {
            Flash::warning('No s\'ha pogut desar el certificat convertit; es farà servir el .p12 tal qual.');
            return;
        }

        @chmod($certPath, 0600);
        @chmod($keyPath, 0600);

        Settings::set('apple_cert_path', $certPath);
        Settings::set('apple_key_path', $keyPath);
        // Ja no cal la contrasenya: la clau desada no està xifrada.
        Settings::set('apple_key_password', '');
        @unlink($path);

        Flash::info('El certificat .p12 s\'ha convertit al format que fa servir el servidor.');
    }

    private function saveGoogleServiceAccount(): void
    {
        $serviceAccount = trim((string) Request::post('google_service_account_json', ''));
        if ($serviceAccount !== '' && !str_starts_with($serviceAccount, '••')) {
            if (json_decode($serviceAccount, true) === null) {
                Flash::warning('El JSON del compte de servei de Google no sembla vàlid; l\'hem desat igualment.');
            }
            Settings::set('google_service_account_json', $serviceAccount);
        }

        if (!empty($_FILES['google_json']['tmp_name'])) {
            $contents = (string) file_get_contents($_FILES['google_json']['tmp_name']);
            if (json_decode($contents, true) !== null) {
                Settings::set('google_service_account_json', $contents);
            } else {
                Flash::warning('El fitxer JSON del compte de servei no és vàlid.');
            }
        }
    }

    /** Genera un passi de prova per validar el certificat d'Apple Wallet. */
    public function testWallet(): void
    {
        $result = AppleWallet::selfTest();

        $result['ok']
            ? Flash::success('Apple Wallet: ' . $result['message'])
            : Flash::error('Apple Wallet: ' . $result['message']);

        Response::redirect(Url::to('/admin/configuracio/wallet'));
    }

    // ----------------------------------------------------------------- Ajudants

    private function page(string $view, string $title, array $extra = []): void
    {
        View::render($view, array_merge([
            'title'    => $title,
            'settings' => Settings::all(),
            'errors'   => Flash::errors(),
            'types'    => Db::all('SELECT `id`, `name` FROM `ticket_types` ORDER BY `sort_order`, `id`'),
        ], $extra), 'layouts/admin');
        Flash::clearOld();
    }

    /**
     * Desa camps de text i camps tipats des del formulari.
     *
     * @param string[] $textKeys
     * @param array<string, string> $typed  clau => 'bool'|'int'
     */
    private function persist(array $textKeys, array $typed = []): void
    {
        foreach ($textKeys as $key) {
            Settings::set($key, (string) Request::post($key, ''));
        }
        foreach ($typed as $key => $type) {
            $value = Request::post($key);
            Settings::set($key, match ($type) {
                'bool'  => $value ? '1' : '0',
                'int'   => (string) max(0, (int) $value),
                default => (string) $value,
            });
        }
        Settings::flush();
    }

    /** Puja una imatge a public/uploads i en retorna la ruta pública. */
    private function storeUpload(string $field, array $allowedExtensions): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int) $file['size'] > 6 * 1024 * 1024) {
            Flash::error('La imatge supera els 6 MB.');
            return null;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            Flash::error('Format d\'imatge no admès (' . implode(', ', $allowedExtensions) . ').');
            return null;
        }
        if (@getimagesize($file['tmp_name']) === false) {
            Flash::error('El fitxer no és una imatge vàlida.');
            return null;
        }

        $dir = APP_ROOT . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Flash::error('No es pot escriure al directori public/uploads.');
            return null;
        }

        $name = $field . '-' . Str::token(6) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            Flash::error('No s\'ha pogut desar la imatge.');
            return null;
        }
        @chmod($dir . '/' . $name, 0644);

        return '/uploads/' . $name;
    }

    /** Desa un certificat a storage/ (mai dins de public/) i retorna la ruta absoluta. */
    private function storeCertificate(string $field, array $allowedExtensions): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            Flash::error('Extensió de certificat no admesa (' . implode(', ', $allowedExtensions) . ').');
            return null;
        }

        $dir = APP_ROOT . '/storage/certificates';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            Flash::error('No s\'ha pogut crear el directori de certificats.');
            return null;
        }

        $path = $dir . '/' . $field . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            Flash::error('No s\'ha pogut desar el certificat.');
            return null;
        }
        @chmod($path, 0600);

        return $path;
    }
}
