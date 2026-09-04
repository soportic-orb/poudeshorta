<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Money;
use App\Core\Settings;
use RuntimeException;

/**
 * Client mínim de l'API de Stripe (només cURL, sense SDK).
 * Fem servir Stripe Checkout: les dades de la targeta mai passen pel nostre servidor.
 */
final class StripeClient
{
    private const API_BASE = 'https://api.stripe.com/v1/';
    private const API_VERSION = '2024-06-20';

    /**
     * Idiomes que admet la passarel·la de Stripe.
     *
     * El català no hi és. Amb «auto», Stripe tria a partir de l'idioma del
     * navegador de qui paga: un navegador en català sol demanar també el
     * castellà, de manera que la passarel·la li surt en castellà.
     */
    public const LOCALES = [
        'auto', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'es', 'es-419', 'et', 'fi',
        'fil', 'fr', 'fr-CA', 'hr', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'ms', 'mt',
        'nb', 'nl', 'pl', 'pt', 'pt-BR', 'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'vi',
        'zh', 'zh-HK', 'zh-TW',
    ];

    /**
     * Import mínim que accepta Stripe, en cèntims de la moneda del compte.
     * Per sota, la passarel·la rebutja el cobrament.
     */
    public const MINIMS = ['EUR' => 50, 'USD' => 50, 'GBP' => 30];

    public static function minimumAmount(): int
    {
        $currency = strtoupper((string) Settings::get('currency', 'EUR'));
        return self::MINIMS[$currency] ?? 50;
    }

    /**
     * Despeses que la passarel·la reté d'un cobrament, segons les tarifes
     * configurades al panell.
     *
     * És només informatiu, per dir-ho a qui s'inscriu: a Stripe se li continua
     * enviant l'import total i qui paga no té cap càrrec addicional.
     */
    public static function estimatedFee(int $totalCents): int
    {
        if ($totalCents <= 0) {
            return 0;
        }

        $percent = max(0.0, min(100.0, (float) Settings::get('stripe_fee_percent', '1.5')));
        $fixed   = max(0, Settings::int('stripe_fee_fixed_cents', 25));

        return min($totalCents, (int) round($totalCents * $percent / 100) + $fixed);
    }

    /** Text que descriu la tarifa aplicada, per mostrar-lo al costat de l'import. */
    public static function feeDescription(): string
    {
        $percent = (float) Settings::get('stripe_fee_percent', '1.5');
        $fixed   = Settings::int('stripe_fee_fixed_cents', 25);

        $percentText = rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');

        return $percentText . '% + ' . Money::format($fixed) . ' per transacció';
    }

    /** Idioma configurat, validat: un valor incorrecte no ha de tombar el pagament. */
    public static function locale(): string
    {
        $locale = trim((string) Settings::get('stripe_locale', 'auto'));
        return in_array($locale, self::LOCALES, true) ? $locale : 'auto';
    }

    public function __construct(private ?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? Settings::stripeSecret();
    }

    public function isConfigured(): bool
    {
        return is_string($this->secretKey) && $this->secretKey !== '';
    }

    /**
     * Crea una sessió de Checkout allotjada per Stripe.
     *
     * @param array<int, array{name:string, description?:string, amount_cents:int, quantity:int}> $items
     */
    public function createCheckoutSession(
        array $items,
        string $customerEmail,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        int $expiresInMinutes = 30
    ): array {
        return $this->request('POST', 'checkout/sessions', $this->buildCheckoutPayload(
            $items,
            $customerEmail,
            $successUrl,
            $cancelUrl,
            $metadata,
            $expiresInMinutes
        ));
    }

    /**
     * Munta els paràmetres de la sessió de Checkout.
     *
     * Va a part de l'enviament perquè es pugui revisar sense trucar a Stripe.
     *
     * @param array<int, array{name:string, description?:string, amount_cents:int, quantity:int}> $items
     */
    public function buildCheckoutPayload(
        array $items,
        string $customerEmail,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        int $expiresInMinutes = 30
    ): array {
        $currency = strtolower((string) Settings::get('currency', 'EUR'));
        $payload = [
            'mode'                        => 'payment',
            'success_url'                 => $successUrl,
            'cancel_url'                  => $cancelUrl,
            'customer_email'              => $customerEmail,
            'locale'                      => self::locale(),
            'billing_address_collection'  => 'auto',
            // Stripe exigeix entre 30 minuts i 24 hores. Demanem-ne 35 com a
            // mínim: amb 30 justos, el temps que triga la petició a arribar
            // ja el deixaria per sota i la rebutjaria.
            'expires_at'                  => time() + min(1440, max(35, $expiresInMinutes)) * 60,
            'payment_intent_data'         => array_filter([
                'description' => mb_substr((string) Settings::get('event_name'), 0, 200),
                'metadata'    => $metadata ?: null,
            ]),
            'metadata'                    => $metadata,
        ];

        foreach (array_values($items) as $i => $item) {
            $payload['line_items'][$i] = [
                'quantity'   => max(1, (int) $item['quantity']),
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => (int) $item['amount_cents'],
                    'product_data' => array_filter([
                        'name'        => mb_substr($item['name'], 0, 250),
                        'description' => isset($item['description']) && $item['description'] !== ''
                            ? mb_substr($item['description'], 0, 500)
                            : null,
                    ]),
                ],
            ];
        }

        return $payload;
    }

    public function retrieveSession(string $sessionId): array
    {
        return $this->request('GET', 'checkout/sessions/' . rawurlencode($sessionId), [
            'expand' => ['payment_intent'],
        ]);
    }

    public function expireSession(string $sessionId): array
    {
        return $this->request('POST', 'checkout/sessions/' . rawurlencode($sessionId) . '/expire');
    }

    /** Retorna un import (o la totalitat si $amountCents és null). */
    public function refund(string $paymentIntentId, ?int $amountCents = null, string $reason = 'requested_by_customer'): array
    {
        $payload = array_filter([
            'payment_intent' => $paymentIntentId,
            'amount'         => $amountCents,
            'reason'         => in_array($reason, ['duplicate', 'fraudulent', 'requested_by_customer'], true) ? $reason : null,
        ], static fn ($v) => $v !== null);

        return $this->request('POST', 'refunds', $payload);
    }

    public function retrieveBalance(): array
    {
        return $this->request('GET', 'balance');
    }

    /**
     * Verifica la signatura d'un webhook i retorna l'esdeveniment descodificat.
     * Llança una excepció si la signatura no és vàlida.
     */
    public static function verifyWebhook(string $payload, ?string $signatureHeader, string $secret, int $tolerance = 300): array
    {
        if ($secret === '') {
            throw new RuntimeException('No hi ha cap secret de webhook configurat.');
        }
        if (!is_string($signatureHeader) || $signatureHeader === '') {
            throw new RuntimeException('Falta la capçalera Stripe-Signature.');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = (int) $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || $signatures === []) {
            throw new RuntimeException('Capçalera de signatura mal formada.');
        }
        if (abs(time() - $timestamp) > $tolerance) {
            throw new RuntimeException('La marca de temps del webhook està fora de tolerància.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new RuntimeException('La signatura del webhook no coincideix.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new RuntimeException('Càrrega útil del webhook no vàlida.');
        }
        return $event;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Les claus de Stripe no estan configurades al Panell de Gestió.');
        }

        $url  = self::API_BASE . $path;
        $body = $payload ? $this->encode($payload) : '';

        if ($method === 'GET' && $body !== '') {
            $url .= '?' . $body;
            $body = '';
        }

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Stripe-Version: ' . self::API_VERSION,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($method === 'POST') {
            // Evita duplicar càrrecs si es reintenta la petició.
            $headers[] = 'Idempotency-Key: ' . bin2hex(random_bytes(16));
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $method === 'POST' ? $body : null,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('Stripe: error de connexió', ['path' => $path, 'curl' => $error]);
            throw new RuntimeException('No s\'ha pogut connectar amb Stripe: ' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta de Stripe no vàlida.');
        }

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? 'Error desconegut de Stripe';
            Logger::error('Stripe: resposta d\'error', ['path' => $path, 'status' => $status, 'message' => $message]);
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    /** Codifica arrays imbricats en el format que espera Stripe (a[b][0][c]=v). */
    private function encode(array $params, string $prefix = ''): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $nested = $this->encode($value, $name);
                if ($nested !== '') {
                    $pairs[] = $nested;
                }
            } elseif (is_bool($value)) {
                $pairs[] = urlencode($name) . '=' . ($value ? 'true' : 'false');
            } else {
                $pairs[] = urlencode($name) . '=' . urlencode((string) $value);
            }
        }
        return implode('&', $pairs);
    }
}
