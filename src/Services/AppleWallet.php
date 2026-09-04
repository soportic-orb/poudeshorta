<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Settings;
use App\Core\Url;
use RuntimeException;
use ZipArchive;

/**
 * Genera passis .pkpass per a l'Apple Wallet.
 *
 * Requereix un certificat de tipus «Pass Type ID» d'Apple i el certificat
 * intermedi WWDR, que es configuren al Panell de Gestió.
 */
final class AppleWallet
{
    public static function isConfigured(): bool
    {
        if (!Settings::bool('wallet_enabled', false)) {
            return false;
        }
        foreach (['apple_pass_type_id', 'apple_team_id', 'apple_cert_path', 'apple_wwdr_path'] as $key) {
            if (trim((string) Settings::get($key)) === '') {
                return false;
            }
        }
        return is_file((string) Settings::get('apple_cert_path'))
            && is_file((string) Settings::get('apple_wwdr_path'))
            && class_exists(ZipArchive::class);
    }

    /**
     * @param array $order   Fila de `orders`.
     * @param array $ticket  Fila de `tickets`.
     * @param array $type    Fila de `ticket_types`.
     */
    public function build(array $order, array $ticket, array $type): string
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('L\'Apple Wallet no està configurat al Panell de Gestió.');
        }

        $workDir = dirname(__DIR__, 2) . '/storage/tmp/pkpass_' . bin2hex(random_bytes(8));
        if (!mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            throw new RuntimeException('No s\'ha pogut crear el directori temporal del pass.');
        }

        try {
            file_put_contents($workDir . '/pass.json', json_encode(
                $this->passPayload($order, $ticket, $type),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ));

            $this->writeImages($workDir);
            $this->writeManifest($workDir);
            $this->writeSignature($workDir);

            return $this->zip($workDir);
        } finally {
            $this->removeDir($workDir);
        }
    }

    private function passPayload(array $order, array $ticket, array $type): array
    {
        $primary = Pdf::rgb((string) Settings::get('brand_primary'));
        $cream   = Pdf::rgb((string) Settings::get('brand_cream'));
        $accent  = Pdf::rgb((string) Settings::get('brand_accent'));

        $attendee = trim((string) ($ticket['attendee_name'] ?? ''))
            ?: trim((string) $order['name'] . ' ' . (string) ($order['surname'] ?? ''));

        $pass = [
            'formatVersion'       => 1,
            'passTypeIdentifier'  => (string) Settings::get('apple_pass_type_id'),
            'teamIdentifier'      => (string) Settings::get('apple_team_id'),
            'organizationName'    => (string) Settings::get('apple_organization'),
            'serialNumber'        => (string) $ticket['code'],
            'description'         => (string) Settings::get('event_name'),
            'backgroundColor'     => sprintf('rgb(%d,%d,%d)', ...$primary),
            'foregroundColor'     => sprintf('rgb(%d,%d,%d)', ...$cream),
            'labelColor'          => sprintf('rgb(%d,%d,%d)', ...$accent),
            'sharingProhibited'   => true,
            'barcodes'            => [[
                'format'          => 'PKBarcodeFormatQR',
                'message'         => Url::full('/e/' . $ticket['code']),
                'messageEncoding' => 'iso-8859-1',
                'altText'         => (string) $ticket['code'],
            ]],
            'eventTicket' => [
                'headerFields' => [[
                    'key'   => 'date',
                    'label' => 'DATA',
                    'value' => (string) Settings::get('event_date_text'),
                ]],
                'primaryFields' => [[
                    'key'   => 'event',
                    'label' => 'ESDEVENIMENT',
                    'value' => (string) Settings::get('event_name'),
                ]],
                'secondaryFields' => [
                    ['key' => 'attendee', 'label' => 'ASSISTENT', 'value' => $attendee],
                    ['key' => 'type', 'label' => 'TIPUS', 'value' => (string) ($type['name'] ?? 'Inscripció')],
                ],
                'auxiliaryFields' => [
                    ['key' => 'place', 'label' => 'LLOC', 'value' => trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))],
                    ['key' => 'reference', 'label' => 'REFERÈNCIA', 'value' => (string) $order['reference']],
                ],
                'backFields' => [
                    ['key' => 'code', 'label' => 'Codi d\'entrada', 'value' => (string) $ticket['code']],
                    ['key' => 'includes', 'label' => 'Què inclou', 'value' => (string) ($type['includes'] ?? '')],
                    ['key' => 'organizer', 'label' => 'Organitza', 'value' => (string) Settings::get('event_organizer')],
                    ['key' => 'contact', 'label' => 'Contacte', 'value' => (string) Settings::get('event_contact_email')],
                    ['key' => 'terms', 'label' => 'Condicions', 'value' => (string) Settings::get('cancellation_policy_text')],
                ],
            ],
        ];

        $eventDate = trim((string) Settings::get('event_date'));
        if ($eventDate !== '' && ($ts = strtotime($eventDate)) !== false) {
            $pass['relevantDate'] = date('c', $ts);
        }

        $mapUrl = trim((string) Settings::get('event_map_url'));
        if ($mapUrl !== '' && preg_match('#[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)#', $mapUrl, $m)) {
            $pass['locations'] = [['latitude' => (float) $m[1], 'longitude' => (float) $m[2]]];
        }

        return $pass;
    }

    /** L'Apple Wallet exigeix com a mínim icon.png; generem les imatges amb els colors de la marca. */
    private function writeImages(string $dir): void
    {
        $custom = dirname(__DIR__, 2) . '/public/uploads/wallet';
        $sizes = ['icon.png' => 29, 'icon@2x.png' => 58, 'logo.png' => 50, 'logo@2x.png' => 100];

        foreach ($sizes as $filename => $size) {
            $provided = $custom . '/' . $filename;
            if (is_file($provided)) {
                copy($provided, $dir . '/' . $filename);
                continue;
            }
            file_put_contents($dir . '/' . $filename, $this->brandSquare($size));
        }
    }

    private function brandSquare(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = Pdf::rgb((string) Settings::get('brand_cream'));
        $bg = imagecolorallocate($image, $r, $g, $b);
        imagefilledrectangle($image, 0, 0, $size, $size, $bg);

        [$r, $g, $b] = Pdf::rgb((string) Settings::get('brand_primary'));
        $fg = imagecolorallocate($image, $r, $g, $b);
        $inset = max(1, (int) round($size * 0.12));
        imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), $size - $inset * 2, $size - $inset * 2, $fg);

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($image);
        return $png;
    }

    private function writeManifest(string $dir): void
    {
        $manifest = [];
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === 'manifest.json' || $file === 'signature') {
                continue;
            }
            $manifest[$file] = sha1_file($dir . '/' . $file);
        }
        file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES));
    }

    /** Signatura PKCS#7 separada del manifest, tal com exigeix Apple. */
    private function writeSignature(string $dir): void
    {
        [$certPem, $keyPem] = $this->loadCertificate();
        $wwdr = (string) file_get_contents((string) Settings::get('apple_wwdr_path'));

        $wwdrFile = $dir . '/.wwdr.pem';
        file_put_contents($wwdrFile, $this->toPem($wwdr));

        $signatureFile = $dir . '/.signature.pem';
        $ok = openssl_pkcs7_sign(
            $dir . '/manifest.json',
            $signatureFile,
            $certPem,
            $keyPem,
            [],
            PKCS7_BINARY | PKCS7_DETACHED,
            $wwdrFile
        );

        if (!$ok) {
            throw new RuntimeException('No s\'ha pogut signar el pass: ' . (openssl_error_string() ?: 'error desconegut'));
        }

        // openssl retorna S/MIME; el .pkpass necessita el bloc PKCS#7 en DER.
        $smime = (string) file_get_contents($signatureFile);
        $parts = preg_split('/\r?\n\r?\n/', $smime, 2);
        $base64 = preg_replace('/-----(BEGIN|END)[^-]+-----|\s+/', '', $parts[1] ?? '') ?? '';
        $der = base64_decode($base64, true);

        if ($der === false || $der === '') {
            throw new RuntimeException('La signatura generada no és vàlida.');
        }

        file_put_contents($dir . '/signature', $der);
        @unlink($signatureFile);
        @unlink($wwdrFile);
    }

    /**
     * Carrega el certificat de pass, acceptant tant .p12 com parella .pem.
     *
     * @return array{0:string,1:array{0:string,1:string}|string}
     */
    private function loadCertificate(): array
    {
        $certPath = (string) Settings::get('apple_cert_path');
        $password = (string) Settings::get('apple_key_password');
        $contents = (string) file_get_contents($certPath);

        if (str_ends_with(strtolower($certPath), '.p12') || str_ends_with(strtolower($certPath), '.pfx')) {
            $bundle = [];
            if (!openssl_pkcs12_read($contents, $bundle, $password)) {
                throw new RuntimeException('No s\'ha pogut llegir el certificat .p12 (comproveu la contrasenya).');
            }
            return [$bundle['cert'], [$bundle['pkey'], '']];
        }

        $keyPath = (string) Settings::get('apple_key_path');
        if ($keyPath === '' || !is_file($keyPath)) {
            throw new RuntimeException('Falta la clau privada del certificat d\'Apple Wallet.');
        }
        return [$contents, [(string) file_get_contents($keyPath), $password]];
    }

    /** Accepta un certificat en DER i el converteix a PEM si cal. */
    private function toPem(string $contents): string
    {
        if (str_contains($contents, '-----BEGIN')) {
            return $contents;
        }
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($contents), 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    private function zip(string $dir): string
    {
        $zipPath = $dir . '.pkpass';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No s\'ha pogut crear el fitxer .pkpass.');
        }
        foreach (scandir($dir) ?: [] as $file) {
            if ($file !== '.' && $file !== '..' && !str_starts_with($file, '.')) {
                $zip->addFile($dir . '/' . $file, $file);
            }
        }
        $zip->close();

        $contents = (string) file_get_contents($zipPath);
        @unlink($zipPath);
        return $contents;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink($dir . '/' . $file);
            }
        }
        @rmdir($dir);
    }

    /** Motiu pel qual el pass no està disponible, per mostrar-lo al panell. */
    public static function configurationHint(): string
    {
        if (!Settings::bool('wallet_enabled', false)) {
            return 'Els passis de wallet estan desactivats.';
        }
        foreach ([
            'apple_pass_type_id' => 'Falta el Pass Type ID.',
            'apple_team_id'      => 'Falta el Team ID d\'Apple.',
            'apple_cert_path'    => 'Falta el certificat del pass.',
            'apple_wwdr_path'    => 'Falta el certificat WWDR d\'Apple.',
        ] as $key => $message) {
            if (trim((string) Settings::get($key)) === '') {
                return $message;
            }
        }
        return 'Configurat.';
    }
}
