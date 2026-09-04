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

    /**
     * Totes les entrades d'una comanda en un sol fitxer.
     *
     * L'Apple Wallet obre els paquets .pkpasses (un ZIP amb un .pkpass a
     * dins per cada entrada) i ofereix afegir-les totes de cop. Amb una sola
     * entrada val més enviar el .pkpass de sempre, que és el que entenen
     * també les versions antigues d'iOS.
     *
     * @param array $tickets     Files de `tickets`.
     * @param array $typesById   Tipus d'inscripció indexats per identificador.
     * @return array{0:string,1:string,2:string} Contingut, nom del fitxer i tipus MIME.
     */
    public function buildAll(array $order, array $tickets, array $typesById): array
    {
        $tickets = array_values($tickets);
        if ($tickets === []) {
            throw new RuntimeException('Aquesta comanda no té cap entrada per afegir al wallet.');
        }

        $referencia = strtolower((string) $order['reference']);

        if (count($tickets) === 1) {
            $ticket = $tickets[0];

            return [
                $this->build($order, $ticket, $typesById[(int) $ticket['ticket_type_id']] ?? []),
                'entrada-' . strtolower((string) $ticket['code']) . '.pkpass',
                'application/vnd.apple.pkpass',
            ];
        }

        $workDir = dirname(__DIR__, 2) . '/storage/tmp/pkpasses_' . bin2hex(random_bytes(8));
        if (!mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            throw new RuntimeException('No s\'ha pogut crear el directori temporal dels passis.');
        }

        try {
            $zipPath = $workDir . '/paquet.pkpasses';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No s\'ha pogut crear el paquet de passis.');
            }

            foreach ($tickets as $ticket) {
                $zip->addFromString(
                    'entrada-' . strtolower((string) $ticket['code']) . '.pkpass',
                    $this->build($order, $ticket, $typesById[(int) $ticket['ticket_type_id']] ?? [])
                );
            }

            $zip->close();
            $contingut = (string) file_get_contents($zipPath);

            return [$contingut, 'entrades-' . $referencia . '.pkpasses', 'application/vnd.apple.pkpasses'];
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

    /**
     * Imatges del passi.
     *
     * L'Apple Wallet exigeix icon.png (és el que surt a les notificacions) i
     * mostra logo.png a dalt a l'esquerra, al costat del nom de l'organització.
     * Fem servir el logotip que hi ha configurat a Configuració → Aparença; si
     * no n'hi ha cap, dibuixem una marca amb els colors de la plataforma.
     *
     * Es pot substituir qualsevol imatge deixant-la a public/uploads/wallet/.
     */
    private function writeImages(string $dir): void
    {
        $custom = dirname(__DIR__, 2) . '/public/uploads/wallet';
        $logo = $this->brandLogo();

        // L'icona és quadrada; el logotip pot ser apaïsat (màxim 160 × 50 punts).
        $icones = ['icon.png' => 29, 'icon@2x.png' => 58, 'icon@3x.png' => 87];
        $logos  = ['logo.png' => 1, 'logo@2x.png' => 2, 'logo@3x.png' => 3];

        foreach ($icones as $filename => $size) {
            if ($this->copyProvided($custom, $filename, $dir)) {
                continue;
            }
            file_put_contents($dir . '/' . $filename, $this->iconImage($size, $logo));
        }

        foreach ($logos as $filename => $escala) {
            if ($this->copyProvided($custom, $filename, $dir)) {
                continue;
            }
            file_put_contents($dir . '/' . $filename, $this->logoImage($escala, $logo));
        }

        if ($logo !== null) {
            imagedestroy($logo);
        }
    }

    private function copyProvided(string $dir, string $filename, string $desti): bool
    {
        if (!is_file($dir . '/' . $filename)) {
            return false;
        }

        return copy($dir . '/' . $filename, $desti . '/' . $filename);
    }

    /** El logotip configurat al panell, ja carregat amb GD. Null si no n'hi ha. */
    private function brandLogo(): ?\GdImage
    {
        $ruta = trim((string) Settings::get('event_logo'));
        if ($ruta === '') {
            return null;
        }

        $fitxer = dirname(__DIR__, 2) . '/public/' . ltrim($ruta, '/');
        if (!is_file($fitxer)) {
            Logger::warn('Apple Wallet: no es troba el logotip configurat (' . $ruta . ').');
            return null;
        }

        $imatge = @imagecreatefromstring((string) file_get_contents($fitxer));
        if ($imatge === false) {
            Logger::warn('Apple Wallet: el logotip configurat no es pot llegir (' . $ruta . ').');
            return null;
        }

        return $imatge;
    }

    /** Icona quadrada: el logotip centrat sobre el color de fons de la marca. */
    private function iconImage(int $size, ?\GdImage $logo): string
    {
        $llenc = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = Pdf::rgb((string) Settings::get('brand_cream'));
        imagefilledrectangle($llenc, 0, 0, $size, $size, imagecolorallocate($llenc, $r, $g, $b));

        if ($logo !== null) {
            $marge = (int) round($size * 0.1);
            $this->dibuixaAjustat($llenc, $logo, $marge, $marge, $size - $marge * 2, $size - $marge * 2);
        } else {
            [$r, $g, $b] = Pdf::rgb((string) Settings::get('brand_primary'));
            $inset = max(1, (int) round($size * 0.12));
            imagefilledellipse(
                $llenc,
                (int) ($size / 2),
                (int) ($size / 2),
                $size - $inset * 2,
                $size - $inset * 2,
                imagecolorallocate($llenc, $r, $g, $b)
            );
        }

        return $this->png($llenc);
    }

    /**
     * Logotip de la capçalera. Apple el mostra dins d'un espai de 160 × 50
     * punts: hi encabim la imatge sencera i retallem el llenç a l'amplada que
     * realment ocupa, perquè no quedi desplaçat cap a la dreta.
     */
    private function logoImage(int $escala, ?\GdImage $logo): string
    {
        $alt = 50 * $escala;

        if ($logo === null) {
            return $this->iconImage($alt, null);
        }

        $ample = min(160 * $escala, max(1, (int) round(imagesx($logo) * ($alt / max(1, imagesy($logo))))));

        $llenc = imagecreatetruecolor($ample, $alt);
        imagealphablending($llenc, false);
        imagesavealpha($llenc, true);
        imagefilledrectangle($llenc, 0, 0, $ample, $alt, imagecolorallocatealpha($llenc, 0, 0, 0, 127));
        imagealphablending($llenc, true);

        $this->dibuixaAjustat($llenc, $logo, 0, 0, $ample, $alt);

        return $this->png($llenc);
    }

    /** Copia $logo dins del rectangle indicat sense deformar-lo. */
    private function dibuixaAjustat(\GdImage $desti, \GdImage $logo, int $x, int $y, int $ample, int $alt): void
    {
        $origAmple = imagesx($logo);
        $origAlt = imagesy($logo);
        $factor = min($ample / max(1, $origAmple), $alt / max(1, $origAlt));

        $nouAmple = max(1, (int) round($origAmple * $factor));
        $nouAlt = max(1, (int) round($origAlt * $factor));

        imagecopyresampled(
            $desti,
            $logo,
            $x + (int) (($ample - $nouAmple) / 2),
            $y + (int) (($alt - $nouAlt) / 2),
            0,
            0,
            $nouAmple,
            $nouAlt,
            $origAmple,
            $origAlt
        );
    }

    private function png(\GdImage $imatge): string
    {
        imagesavealpha($imatge, true);
        ob_start();
        imagepng($imatge, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($imatge);

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

        if (@openssl_x509_read($certPem) === false) {
            throw new RuntimeException(
                'El certificat del pass no es pot llegir. Comproveu que heu pujat el certificat '
                . 'del vostre «Pass Type ID» d\'Apple i no un altre fitxer.'
            );
        }

        $wwdrPem = $this->toPem((string) file_get_contents((string) Settings::get('apple_wwdr_path')));
        if (@openssl_x509_read($wwdrPem) === false) {
            throw new RuntimeException(
                'El certificat WWDR d\'Apple no es pot llegir. Descarregueu-lo de nou des de '
                . 'apple.com/certificateauthority (Worldwide Developer Relations, G4) i torneu-lo a pujar.'
            );
        }

        $wwdrFile = $dir . '/.wwdr.pem';
        file_put_contents($wwdrFile, $wwdrPem);

        $signatureFile = $dir . '/.signature.pem';
        $ok = @openssl_pkcs7_sign(
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

        // openssl retorna un missatge S/MIME multipart; el .pkpass necessita
        // només el bloc PKCS#7, i en DER.
        $der = self::derFromSmime((string) file_get_contents($signatureFile));

        file_put_contents($dir . '/signature', $der);
        @unlink($signatureFile);
        @unlink($wwdrFile);
    }

    /**
     * Extreu el bloc PKCS#7 en DER d'un missatge S/MIME signat.
     *
     * openssl_pkcs7_sign retorna un multipart/signed que conté el manifest
     * original i, en una part a part, la signatura en base64. Aquí busquem
     * aquesta part concreta i la descodifiquem.
     */
    private static function derFromSmime(string $smime): string
    {
        $section = null;

        if (preg_match('/boundary="?([^";\r\n]+)"?/i', $smime, $match) === 1) {
            foreach (explode('--' . $match[1], $smime) as $part) {
                // Cal la capçalera Content-Type de la part: la capçalera general
                // del missatge també anomena el tipus, dins de protocol="…".
                if (preg_match('#Content-Type:\s*application/x-pkcs7-signature#i', $part) === 1) {
                    $section = $part;
                    break;
                }
            }
        }

        if ($section === null) {
            // Sense límit de parts reconegut: ens quedem amb el darrer bloc base64.
            $section = $smime;
        }

        // El cos comença després de la línia en blanc que tanca les capçaleres.
        $parts = preg_split('/\R\R/', trim($section), 2);
        $body = $parts[1] ?? $parts[0] ?? '';

        $base64 = preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? '';
        $der = $base64 !== '' ? base64_decode($base64, true) : false;

        // Una signatura PKCS#7 és una SEQUENCE d'ASN.1 (0x30) i mai és curta:
        // si no ho és, hem agafat el bloc equivocat i val més aturar-se.
        if ($der === false || strlen($der) < 256 || $der[0] !== "\x30") {
            throw new RuntimeException('No s\'ha pogut extreure la signatura del certificat.');
        }

        return $der;
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
            $bundle = self::extractPkcs12($contents, $password);
            return [$bundle['cert'], [$bundle['key'], '']];
        }

        $keyPath = (string) Settings::get('apple_key_path');
        if ($keyPath === '' || !is_file($keyPath)) {
            throw new RuntimeException('Falta la clau privada del certificat d\'Apple Wallet.');
        }
        return [$contents, [(string) file_get_contents($keyPath), $password]];
    }

    /**
     * Obté el certificat i la clau privada d'un fitxer .p12.
     *
     * El Keychain del Mac exporta els .p12 amb algorismes antics (RC2-40 i
     * 3DES) que OpenSSL 3 no accepta per defecte, de manera que aquí provem
     * primer la lectura normal i, si falla, la del binari openssl amb l'opció
     * -legacy. Si tampoc és possible, expliquem què cal fer.
     *
     * @return array{cert:string, key:string}
     */
    public static function extractPkcs12(string $contents, string $password): array
    {
        $bundle = [];
        if (@openssl_pkcs12_read($contents, $bundle, $password)
            && !empty($bundle['cert']) && !empty($bundle['pkey'])) {
            return ['cert' => (string) $bundle['cert'], 'key' => (string) $bundle['pkey']];
        }

        $errors = [];
        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }
        $legacyAlgorithm = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'unsupported') || str_contains($error, 'digital envelope')) {
                $legacyAlgorithm = true;
            }
        }

        $fallback = self::extractPkcs12WithCli($contents, $password);
        if ($fallback !== null) {
            return $fallback;
        }

        if ($legacyAlgorithm) {
            throw new RuntimeException(
                'El certificat .p12 utilitza algorismes antics que aquest servidor no accepta '
                . '(és el format que exporta el Keychain del Mac). Torneu-lo a exportar amb: '
                . 'openssl pkcs12 -in original.p12 -nodes -legacy | openssl pkcs12 -export '
                . '-keypbe AES-256-CBC -certpbe AES-256-CBC -macalg sha256 -out nou.p12'
            );
        }

        throw new RuntimeException(
            'No s\'ha pogut llegir el certificat .p12. Comproveu que la contrasenya és correcta '
            . 'i que el fitxer conté el certificat i la clau privada.'
        );
    }

    /**
     * Segona oportunitat fent servir el binari openssl amb -legacy.
     * La contrasenya es passa per variable d'entorn perquè no aparegui a la
     * llista de processos del servidor.
     *
     * @return array{cert:string, key:string}|null
     */
    private static function extractPkcs12WithCli(string $contents, string $password): ?array
    {
        if (!function_exists('proc_open')
            || in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'p12_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $contents);
        @chmod($tmp, 0600);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $command = 'openssl pkcs12 -in ' . escapeshellarg($tmp) . ' -nodes -legacy -passin env:PKCS12_PASSWORD 2>/dev/null';

        $process = @proc_open($command, $descriptors, $pipes, null, ['PKCS12_PASSWORD' => $password]);
        if (!is_resource($process)) {
            @unlink($tmp);
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        @unlink($tmp);

        if ($status !== 0 || $output === '') {
            return null;
        }

        preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $output, $cert);
        preg_match('/-----BEGIN (?:ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED )?PRIVATE KEY-----/s', $output, $key);

        if (empty($cert[0]) || empty($key[0])) {
            return null;
        }

        return ['cert' => $cert[0], 'key' => $key[0]];
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

    /**
     * Genera un passi de prova amb dades fictícies per comprovar que el
     * certificat funciona, sense haver d'esperar a la primera venda.
     *
     * @return array{ok:bool, message:string}
     */
    public static function selfTest(): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'message' => self::configurationHint()];
        }

        try {
            $pass = (new self())->build(
                ['id' => 0, 'reference' => 'PDSH-PROVA', 'name' => 'Entrada', 'surname' => 'de prova'],
                ['code' => 'PROVA123', 'attendee_name' => 'Entrada de prova', 'status' => 'valid'],
                ['name' => 'Prova de configuració', 'includes' => '']
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok'      => true,
            'message' => 'Passi de prova generat correctament (' . strlen($pass) . ' bytes). '
                . 'El certificat, la clau i el certificat WWDR són vàlids i es poden signar passis.',
        ];
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

        // Els camps poden estar plens i el fitxer haver desaparegut del disc,
        // per exemple si s'ha restaurat una còpia sense storage/.
        foreach ([
            'apple_cert_path' => 'el certificat del pass',
            'apple_key_path'  => 'la clau privada',
            'apple_wwdr_path' => 'el certificat WWDR',
        ] as $key => $nom) {
            $path = trim((string) Settings::get($key));
            if ($path !== '' && !is_file($path)) {
                return 'No es troba el fitxer amb ' . $nom . ' (' . basename($path) . '). Torneu-lo a pujar.';
            }
        }

        return 'Configurat.';
    }
}
