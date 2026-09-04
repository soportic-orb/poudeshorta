<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use RuntimeException;

/**
 * Enllaç «Afegeix-ho al Google Wallet».
 *
 * Es genera un JWT signat (RS256) amb el compte de servei de Google Cloud que
 * conté alhora la classe i l'objecte de l'entrada, de manera que no cal fer
 * cap crida prèvia a l'API de Google.
 */
final class GoogleWallet
{
    private const SAVE_URL = 'https://pay.google.com/gp/v/save/';
    private const API_BASE  = 'https://walletobjects.googleapis.com/walletobjects/v1/';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/wallet_object.issuer';

    /**
     * Google trunca els enllaços «Save to Wallet» amb un JWT de més de 1800
     * caràcters i llavors no es desa res, sense avisar. Ho comprovem abans de
     * donar l'enllaç per no lliurar-ne un de trencat.
     */
    private const MAX_JWT = 1800;

    public static function isConfigured(): bool
    {
        return Settings::bool('wallet_enabled', false)
            && trim((string) Settings::get('google_issuer_id')) !== ''
            && self::serviceAccount() !== null;
    }

    /** Identificador de la classe d'aquest esdeveniment. */
    public static function classId(): string
    {
        $issuerId = trim((string) Settings::get('google_issuer_id'));
        return $issuerId . '.' . Str::slug((string) Settings::get('google_class_suffix', 'esdeveniment'), '_');
    }

    /** La classe ja s'ha creat al compte de Google? */
    public static function classRegistered(): bool
    {
        return trim((string) Settings::get('google_class_registered')) === self::classId();
    }

    /**
     * Enllaç «Add to Google Wallet» amb totes les entrades d'una comanda.
     *
     * L'enllaç porta un JWT dins de l'URL, i això en limita la mida. Amb una
     * sola entrada hi cap la definició sencera i n'hi ha prou de signar-la
     * (no cal parlar amb Google). A partir de dues ja no hi cabria, així que
     * desem les entrades al compte de Google i el JWT només n'ha de portar
     * l'identificador: així hi caben totes les d'una comanda normal.
     *
     * @param array $tickets    Files de `tickets`.
     * @param array $typesById  Tipus d'inscripció indexats per identificador.
     */
    public function saveUrl(array $order, array $tickets, array $typesById): string
    {
        $account = self::serviceAccount();
        if (!self::isConfigured() || $account === null) {
            throw new RuntimeException('El Google Wallet no està configurat al Panell de Gestió.');
        }

        $tickets = array_values($tickets);
        if ($tickets === []) {
            throw new RuntimeException('Aquesta comanda no té cap entrada per afegir al wallet.');
        }

        $issuerId = trim((string) Settings::get('google_issuer_id'));
        $classId  = self::classId();

        $objectes = [];
        foreach ($tickets as $ticket) {
            $attendee = trim((string) ($ticket['attendee_name'] ?? ''))
                ?: trim((string) $order['name'] . ' ' . (string) ($order['surname'] ?? ''));

            $objectes[] = $this->objectPayload(
                $issuerId . '.' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $ticket['code']),
                $classId,
                $order,
                $ticket,
                $typesById[(int) $ticket['ticket_type_id']] ?? [],
                $attendee
            );
        }

        // Pla A: l'enllaç es basta tot sol. És el més ràpid, perquè no cal
        // parlar amb Google, i és el que passa amb una sola entrada.
        $payload = ['eventTicketObjects' => $objectes];
        if (!self::classRegistered()) {
            $payload = ['eventTicketClasses' => [$this->classPayload($classId, $issuerId)]] + $payload;
        }

        $jwt = $this->signJwt($this->claims($account, $payload), (string) $account['private_key']);
        if (strlen($jwt) <= self::MAX_JWT) {
            return self::SAVE_URL . $jwt;
        }

        // Pla B: amb dues entrades o més, la definició sencera ja no hi cap.
        // Les desem al compte de Google i l'enllaç només en porta l'identificador.
        $desats = $this->registerObjects($objectes);

        if ($desats === null) {
            throw new RuntimeException(
                'No s\'han pogut desar les entrades al vostre compte de Google i l\'enllaç, que llavors '
                . 'les ha de portar senceres, queda massa llarg (' . strlen($jwt) . ' caràcters de '
                . self::MAX_JWT . ') per a ' . count($objectes) . ' entrades. Comproveu a Configuració → '
                . 'Wallet que el compte de servei té permís sobre l\'emissor.'
            );
        }

        $jwt = $this->signJwt(
            $this->claims($account, ['eventTicketObjects' => $desats]),
            (string) $account['private_key']
        );

        if (strlen($jwt) > self::MAX_JWT) {
            throw new RuntimeException(
                'Aquesta comanda té massa entrades (' . count($objectes) . ') per afegir-les totes amb un sol '
                . 'enllaç del Google Wallet. Afegiu-les d\'una en una des de «Les meves entrades».'
            );
        }

        return self::SAVE_URL . $jwt;
    }

    /** @param array<string,mixed> $payload */
    private function claims(array $account, array $payload): array
    {
        return [
            'iss'     => $account['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'iat'     => time(),
            'origins' => [Url::base()],
            'payload' => $payload,
        ];
    }

    /**
     * Desa les entrades al compte de Google i en retorna les referències per
     * posar dins del JWT. Retorna null si no s'han pogut desar, perquè qui
     * ens ha cridat pugui provar l'enllaç autònom.
     *
     * @param array<int,array> $objectes
     * @return array<int,array{id:string}>|null
     */
    private function registerObjects(array $objectes): ?array
    {
        try {
            $account = self::serviceAccount();
            if ($account === null) {
                return null;
            }

            // Els objectes no es poden desar si la classe encara no existeix.
            if (!self::classRegistered() && ($this->ensureClass()['ok'] ?? false) !== true) {
                return null;
            }

            $token = $this->accessToken($account);

            foreach ($objectes as $objecte) {
                $ruta = 'eventTicketObject/' . rawurlencode((string) $objecte['id']);
                [$status] = $this->api('GET', $ruta, null, $token);

                [$status, $body] = $status === 404
                    ? $this->api('POST', 'eventTicketObject', $objecte, $token)
                    : $this->api('PUT', $ruta, $objecte, $token);

                if ($status >= 400) {
                    Logger::warn('Google Wallet: no s\'ha pogut desar l\'entrada al compte de Google. '
                        . $this->describeApiError($status, $body));
                    return null;
                }
            }

            return array_map(static fn (array $o): array => ['id' => (string) $o['id']], $objectes);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Google Wallet (desant les entrades)');
            return null;
        }
    }

    /**
     * Cadena localitzada per a Google. Retorna null si el text és buit:
     * Google rebutja les LocalizedString sense valor amb un 400.
     */
    private static function localized(?string $value): ?array
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : ['defaultValue' => ['language' => 'ca', 'value' => $value]];
    }

    /**
     * Comprova que hi ha les dades que Google exigeix, per avisar-ne aquí i no
     * haver d'interpretar un error seu.
     *
     * @return string|null Motiu, o null si tot hi és.
     */
    public static function missingData(): ?string
    {
        if (trim((string) Settings::get('event_name')) === '') {
            return 'Cal indicar el nom de l\'esdeveniment a Configuració → Esdeveniment.';
        }
        if (trim((string) Settings::get('event_organizer')) === '') {
            return 'Cal indicar qui organitza l\'esdeveniment a Configuració → Esdeveniment: '
                . 'Google ho fa servir com a nom de l\'emissor del passi.';
        }

        return null;
    }

    private function classPayload(string $classId, string $issuerId): array
    {
        $class = [
            'id'                 => $classId,
            'issuerName'         => trim((string) Settings::get('event_organizer')),
            'reviewStatus'       => 'UNDER_REVIEW',
            'eventName'          => self::localized((string) Settings::get('event_name')),
            'hexBackgroundColor' => (string) Settings::get('brand_primary'),
        ];

        // El lloc només s'envia si el sabem: Google exigeix que el nom i
        // l'adreça del recinte tinguin contingut si s'inclou el bloc.
        $venue = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'));
        if ($venue !== '') {
            $class['venue'] = [
                'name'    => self::localized($venue),
                'address' => self::localized($venue),
            ];
        }

        $eventDate = trim((string) Settings::get('event_date'));
        if ($eventDate !== '' && ($ts = strtotime($eventDate)) !== false) {
            $class['dateTime'] = ['start' => date('c', $ts)];
        }

        $homepage = Url::base();
        if ($homepage !== '') {
            $class['homepageUri'] = ['uri' => $homepage, 'description' => Url::host()];
        }

        return array_filter($class, static fn ($v) => $v !== null && $v !== '');
    }

    private function objectPayload(
        string $objectId,
        string $classId,
        array $order,
        array $ticket,
        array $type,
        string $attendee
    ): array {
        $object = [
            'id'      => $objectId,
            'classId' => $classId,
            'state'   => ($ticket['status'] ?? 'valid') === 'valid' ? 'ACTIVE' : 'INACTIVE',
            'ticketHolderName' => $attendee !== '' ? $attendee : 'Assistent',
            'ticketNumber'     => (string) $ticket['code'],
            'ticketType'       => self::localized((string) ($type['name'] ?? '')) ?? self::localized('Inscripció'),
            'barcode' => [
                'type'         => 'QR_CODE',
                'value'        => Url::full('/e/' . $ticket['code']),
                'alternateText' => (string) $ticket['code'],
            ],
            'textModulesData' => [
                [
                    'header' => 'Referència',
                    'body'   => (string) $order['reference'],
                    'id'     => 'reference',
                ],
            ],
        ];

        // Passat aquest moment, el Google Wallet arracona el passi als caducats.
        $expira = TicketService::walletExpiry();
        if ($expira !== null) {
            $object['validTimeInterval'] = ['end' => ['date' => date('c', $expira)]];
        }

        $includes = trim((string) ($type['includes'] ?? ''));
        if ($includes !== '') {
            $object['textModulesData'][] = [
                'header' => 'Què inclou',
                'body'   => Str::limit(str_replace(["\r\n", "\n"], ' · ', $includes), 150),
                'id'     => 'includes',
            ];
        }

        return $object;
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $encode = static fn (array $data): string => rtrim(strtr(base64_encode(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ), '+/', '-_'), '=');

        $signingInput = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode($claims);

        $key = @openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('La clau privada del compte de servei de Google no és vàlida.');
        }

        $signature = '';
        if (!@openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No s\'ha pogut signar el passi del Google Wallet.');
        }

        return $signingInput . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    // ------------------------------------------------------- API de Google

    /**
     * Crea la classe de l'esdeveniment al compte de Google, o l'actualitza si
     * ja hi és. Fent-ho una sola vegada, els enllaços de cada entrada queden
     * molt més curts i no arriben al límit que Google trunca.
     *
     * @return array{ok:bool, message:string}
     */
    public function ensureClass(): array
    {
        $account = self::serviceAccount();
        if ($account === null || trim((string) Settings::get('google_issuer_id')) === '') {
            return ['ok' => false, 'message' => self::configurationHint()];
        }

        if (($falta = self::missingData()) !== null) {
            return ['ok' => false, 'message' => $falta];
        }

        $classId = self::classId();

        try {
            $token = $this->accessToken($account);
            $class = $this->classPayload($classId, trim((string) Settings::get('google_issuer_id')));

            [$status, $body] = $this->api('GET', 'eventTicketClass/' . rawurlencode($classId), null, $token);

            if ($status === 404) {
                [$status, $body] = $this->api('POST', 'eventTicketClass', $class, $token);
                if ($status >= 400) {
                    return ['ok' => false, 'message' => $this->describeApiError($status, $body)];
                }
                $accio = 'creada';
            } elseif ($status >= 400) {
                return ['ok' => false, 'message' => $this->describeApiError($status, $body)];
            } else {
                // La classe ja existeix (potser creada a mà a la consola de
                // Google). Provem d'actualitzar-la, però encara que no
                // poguéssim, ja n'hi ha prou per no haver-la d'enviar dins de
                // cada enllaç, que és el que ens interessa.
                [$putStatus, $putBody] = $this->api('PUT', 'eventTicketClass/' . rawurlencode($classId), $class, $token);

                if ($putStatus >= 400) {
                    Settings::set('google_class_registered', $classId);
                    Settings::flush();

                    return [
                        'ok'      => true,
                        'message' => 'La classe ja existeix al Google Wallet (' . $classId . ') i es farà servir, '
                            . 'però no s\'ha pogut actualitzar amb les dades actuals de l\'esdeveniment: '
                            . $this->describeApiError($putStatus, $putBody),
                    ];
                }

                $accio = 'actualitzada';
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        Settings::set('google_class_registered', $classId);
        Settings::flush();

        return ['ok' => true, 'message' => 'Classe ' . $accio . ' correctament al Google Wallet (' . $classId . ').'];
    }

    /** Obté un testimoni d'accés amb el compte de servei (flux JWT bearer). */
    private function accessToken(array $account): string
    {
        $now = time();
        $assertion = $this->signJwt([
            'iss'   => $account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $account['private_key']);

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]),
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('No s\'ha pogut contactar amb Google: ' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if ($status >= 400 || empty($decoded['access_token'])) {
            $motiu = (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'resposta inesperada');
            throw new RuntimeException(
                'Google no ha acceptat el compte de servei (' . $motiu . '). Comproveu que el JSON és el correcte '
                . 'i que teniu activada l\'API de Google Wallet al projecte de Google Cloud.'
            );
        }

        return (string) $decoded['access_token'];
    }

    /** @return array{0:int, 1:array} */
    private function api(string $method, string $path, ?array $payload, string $token): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $payload !== null
                ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('No s\'ha pogut contactar amb l\'API del Google Wallet: ' . $error);
        }

        return [$status, (array) (json_decode((string) $response, true) ?? [])];
    }

    private function describeApiError(int $status, array $body): string
    {
        $detall = (string) ($body['error']['message'] ?? 'sense detall');

        return match (true) {
            $status === 401 => 'Google ha rebutjat les credencials del compte de servei.',
            $status === 403 => 'El compte de servei no té permís sobre aquest emissor. A la Google Wallet Console, '
                . 'aneu a «Users» i doneu-li accés a l\'adreça del compte de servei. (' . $detall . ')',
            $status === 404 => 'Google no troba l\'emissor indicat. Reviseu l\'Issuer ID. (' . $detall . ')',
            default         => 'Google ha respost amb el codi ' . $status . ': ' . $detall,
        };
    }

    /**
     * Prova de configuració: genera un enllaç amb dades fictícies i en
     * comprova la mida, sense crear res al compte de Google.
     *
     * @return array{ok:bool, message:string}
     */
    public static function selfTest(): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'message' => self::configurationHint()];
        }

        try {
            $url = (new self())->saveUrl(
                ['reference' => 'PDSH-PROVA', 'name' => 'Entrada', 'surname' => 'de prova'],
                ['code' => 'PROVA123', 'attendee_name' => 'Entrada de prova', 'status' => 'valid'],
                ['name' => 'Prova de configuració', 'includes' => '']
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $jwt = substr($url, strlen(self::SAVE_URL));

        return [
            'ok'      => true,
            'message' => 'Enllaç generat correctament i signat amb el compte de servei. Mida: '
                . strlen($jwt) . ' de ' . self::MAX_JWT . ' caràcters permesos'
                . (self::classRegistered() ? '.' : ' (creeu la classe per escurçar-lo).'),
        ];
    }

    /** @return array{client_email:string, private_key:string}|null */
    private static function serviceAccount(): ?array
    {
        $raw = trim((string) Settings::get('google_service_account_json'));
        if ($raw === '') {
            return null;
        }
        // Pot ser el JSON directament o el camí a un fitxer del servidor.
        if (!str_starts_with($raw, '{') && is_file($raw)) {
            $raw = (string) file_get_contents($raw);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            return null;
        }
        return [
            'client_email' => (string) $decoded['client_email'],
            'private_key'  => (string) $decoded['private_key'],
        ];
    }

    public static function configurationHint(): string
    {
        if (!Settings::bool('wallet_enabled', false)) {
            return 'Els passis de wallet estan desactivats.';
        }
        if (trim((string) Settings::get('google_issuer_id')) === '') {
            return 'Falta l\'Issuer ID de Google Wallet.';
        }
        if (self::serviceAccount() === null) {
            return 'Falta el JSON del compte de servei de Google (o no és vàlid).';
        }
        return 'Configurat.';
    }
}
