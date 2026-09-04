<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Services\StripeClient;
use App\Services\TicketService;

/**
 * Punt d'entrada dels esdeveniments de Stripe.
 * És la font de veritat de l'estat dels pagaments: el retorn del navegador
 * pot no arribar mai (l'usuari tanca la pestanya), però el webhook sí.
 */
final class WebhookController
{
    public function stripe(): void
    {
        $payload = Request::rawBody();
        $secret = Settings::stripeWebhookSecret();

        if ($secret === '') {
            Logger::warn('Webhook de Stripe rebut sense secret configurat.');
            Response::json(['error' => 'webhook_not_configured'], 500);
        }

        try {
            $event = StripeClient::verifyWebhook($payload, Request::header('Stripe-Signature'), $secret);
        } catch (\Throwable $e) {
            Logger::error('Webhook de Stripe rebutjat', ['motiu' => $e->getMessage()]);
            Response::json(['error' => 'invalid_signature'], 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        // Stripe pot reenviar el mateix esdeveniment: el processem una sola vegada.
        try {
            Db::insert('webhook_events', [
                'stripe_event_id' => $eventId,
                'type'            => $type,
                'payload'         => mb_substr($payload, 0, 65000),
            ]);
        } catch (\Throwable) {
            Response::json(['received' => true, 'duplicate' => true]);
        }

        try {
            $this->handle($type, (array) ($event['data']['object'] ?? []));
            Db::update('webhook_events', ['processed_at' => date('Y-m-d H:i:s')], '`stripe_event_id` = :e', ['e' => $eventId]);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Processant el webhook ' . $type);
            // Retornem 500 perquè Stripe ho torni a intentar més tard.
            Response::json(['error' => 'processing_failed'], 500);
        }

        Response::json(['received' => true]);
    }

    private function handle(string $type, array $object): void
    {
        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->completeCheckout($object);
                break;

            case 'checkout.session.expired':
                $this->expireCheckout($object);
                break;

            case 'checkout.session.async_payment_failed':
                $this->failCheckout($object);
                break;

            case 'charge.refunded':
                $this->syncRefund($object);
                break;

            default:
                Logger::info('Webhook de Stripe ignorat', ['tipus' => $type]);
        }
    }

    private function completeCheckout(array $session): void
    {
        if (($session['payment_status'] ?? '') !== 'paid') {
            return;
        }

        $order = $this->findOrder($session);
        if ($order === null) {
            Logger::warn('Webhook sense comanda associada', ['sessio' => $session['id'] ?? '']);
            return;
        }

        $paymentIntent = $session['payment_intent'] ?? null;
        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        $newlyPaid = TicketService::markPaid(
            (int) $order['id'],
            $paymentIntent !== null ? (string) $paymentIntent : null,
            (int) ($session['amount_total'] ?? $order['total_cents'])
        );

        if ($newlyPaid) {
            $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $order['id']]) ?? $order;
            try {
                TicketService::sendConfirmationEmail($order);
            } catch (\Throwable $e) {
                Logger::exception($e, 'Correu de confirmació (webhook)');
            }
        }
    }

    private function expireCheckout(array $session): void
    {
        $order = $this->findOrder($session);
        if ($order !== null && $order['status'] === 'pending') {
            Db::update('orders', ['status' => 'expired'], '`id` = :id', ['id' => $order['id']]);
        }
    }

    private function failCheckout(array $session): void
    {
        $order = $this->findOrder($session);
        if ($order !== null && $order['status'] === 'pending') {
            Db::update('orders', ['status' => 'failed'], '`id` = :id', ['id' => $order['id']]);
        }
    }

    /** Manté sincronitzades les devolucions fetes directament des del tauler de Stripe. */
    private function syncRefund(array $charge): void
    {
        $paymentIntent = (string) ($charge['payment_intent'] ?? '');
        if ($paymentIntent === '') {
            return;
        }

        $order = Db::first('SELECT * FROM `orders` WHERE `stripe_payment_intent` = :p', ['p' => $paymentIntent]);
        if ($order === null) {
            return;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        if ($refunded <= (int) $order['refunded_cents']) {
            return;
        }

        $isFull = $refunded >= (int) $order['total_cents'];
        Db::update('orders', [
            'refunded_cents' => $refunded,
            'status'         => $isFull ? 'refunded' : 'partially_refunded',
        ], '`id` = :id', ['id' => $order['id']]);

        if ($isFull) {
            Db::run(
                "UPDATE `tickets` SET `status` = 'refunded', `cancelled_at` = NOW()
                 WHERE `order_id` = :id AND `status` = 'valid'",
                ['id' => $order['id']]
            );
        }

        Logger::info('Devolució sincronitzada des de Stripe', ['comanda' => $order['reference'], 'import' => $refunded]);
    }

    private function findOrder(array $session): ?array
    {
        $orderId = (int) ($session['metadata']['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $orderId]);
            if ($order !== null) {
                return $order;
            }
        }
        $sessionId = (string) ($session['id'] ?? '');
        return $sessionId !== ''
            ? Db::first('SELECT * FROM `orders` WHERE `stripe_session_id` = :s', ['s' => $sessionId])
            : null;
    }
}
