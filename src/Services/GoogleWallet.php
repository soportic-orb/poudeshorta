<?php
declare(strict_types=1);

namespace App\Services;

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

    public static function isConfigured(): bool
    {
        return Settings::bool('wallet_enabled', false)
            && trim((string) Settings::get('google_issuer_id')) !== ''
            && self::serviceAccount() !== null;
    }

    public function saveUrl(array $order, array $ticket, array $type): string
    {
        $account = self::serviceAccount();
        if (!self::isConfigured() || $account === null) {
            throw new RuntimeException('El Google Wallet no està configurat al Panell de Gestió.');
        }

        $issuerId = trim((string) Settings::get('google_issuer_id'));
        $classId  = $issuerId . '.' . Str::slug((string) Settings::get('google_class_suffix', 'esdeveniment'), '_');
        $objectId = $issuerId . '.' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $ticket['code']);

        $attendee = trim((string) ($ticket['attendee_name'] ?? ''))
            ?: trim((string) $order['name'] . ' ' . (string) ($order['surname'] ?? ''));

        $claims = [
            'iss'     => $account['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'iat'     => time(),
            'origins' => [Url::base()],
            'payload' => [
                'eventTicketClasses' => [$this->classPayload($classId, $issuerId)],
                'eventTicketObjects' => [$this->objectPayload($objectId, $classId, $order, $ticket, $type, $attendee)],
            ],
        ];

        return self::SAVE_URL . $this->signJwt($claims, (string) $account['private_key']);
    }

    private function classPayload(string $classId, string $issuerId): array
    {
        $venue = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'));

        $class = [
            'id'                 => $classId,
            'issuerName'         => (string) Settings::get('event_organizer'),
            'reviewStatus'       => 'UNDER_REVIEW',
            'eventName'          => ['defaultValue' => ['language' => 'ca', 'value' => (string) Settings::get('event_name')]],
            'hexBackgroundColor' => (string) Settings::get('brand_primary'),
            'venue'              => [
                'name'    => ['defaultValue' => ['language' => 'ca', 'value' => $venue !== '' ? $venue : (string) Settings::get('event_name')]],
                'address' => ['defaultValue' => ['language' => 'ca', 'value' => $venue]],
            ],
        ];

        $eventDate = trim((string) Settings::get('event_date'));
        if ($eventDate !== '' && ($ts = strtotime($eventDate)) !== false) {
            $class['dateTime'] = ['start' => date('c', $ts)];
        }

        $homepage = Url::base();
        if ($homepage !== '') {
            $class['homepageUri'] = ['uri' => $homepage, 'description' => Url::host()];
        }

        return $class;
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
            'ticketHolderName' => $attendee,
            'ticketNumber'     => (string) $ticket['code'],
            'ticketType'       => ['defaultValue' => ['language' => 'ca', 'value' => (string) ($type['name'] ?? 'Inscripció')]],
            'barcode' => [
                'type'         => 'QR_CODE',
                'value'        => Url::full('/e/' . $ticket['code']),
                'alternateText' => (string) $ticket['code'],
            ],
            'hexBackgroundColor' => (string) Settings::get('brand_primary'),
            'textModulesData' => [
                [
                    'header' => 'Referència',
                    'body'   => (string) $order['reference'],
                    'id'     => 'reference',
                ],
            ],
        ];

        $includes = trim((string) ($type['includes'] ?? ''));
        if ($includes !== '') {
            $object['textModulesData'][] = [
                'header' => 'Què inclou',
                'body'   => Str::limit(str_replace(["\r\n", "\n"], ' · ', $includes), 400),
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

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('La clau privada del compte de servei de Google no és vàlida.');
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No s\'ha pogut signar el passi del Google Wallet.');
        }

        return $signingInput . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
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
