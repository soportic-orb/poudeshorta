<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Services\StripeClient;
use App\Services\TicketService;
use RuntimeException;

final class CheckoutController
{
    private const CART_KEY = 'checkout_cart';

    public function redirectHome(): void
    {
        Response::redirect(Url::to('/') . '#inscripcions');
    }

    /** Pas 2: dades de la persona que fa la inscripció i dels assistents. */
    public function details(): void
    {
        if (!TicketService::salesOpen()) {
            Flash::error((string) Settings::get('sales_closed_message'));
            Response::redirect(Url::to('/'));
        }

        $quantities = [];
        foreach (Request::postArray('qty') as $typeId => $qty) {
            $quantities[(int) $typeId] = (int) $qty;
        }

        try {
            $cart = TicketService::buildCart($quantities);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/') . '#inscripcions');
        }

        Session::set(self::CART_KEY, [
            'quantities' => $quantities,
            'created_at' => time(),
        ]);

        $this->renderDetails($cart);
    }

    /** Dibuixa el pas 2 amb un carretó ja validat. */
    private function renderDetails(array $cart): void
    {
        View::render('web/checkout_details', [
            'title'    => 'Dades de la inscripció',
            'cart'     => $cart,
            'subtotal' => $cart['subtotal'],
            'errors'   => Flash::errors(),
        ], 'layouts/public');
        Flash::clearOld();
    }

    /** Pas 3: crea la comanda i porta l'usuari a la passarel·la de Stripe. */
    public function pay(): void
    {
        $stored = Session::get(self::CART_KEY);
        if (!is_array($stored) || empty($stored['quantities'])) {
            Flash::error('La selecció d\'entrades ha caducat. Torneu a començar.');
            Response::redirect(Url::to('/') . '#inscripcions');
        }

        try {
            $cart = TicketService::buildCart($stored['quantities']);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/') . '#inscripcions');
        }

        $validator = Validator::make($_POST)
            ->required('name', 'Cal indicar el nom.')
            ->maxLen('name', 120, 'El nom és massa llarg.')
            ->required('surname', 'Cal indicar els cognoms.')
            ->maxLen('surname', 160, 'Els cognoms són massa llargs.')
            ->required('email', 'Cal indicar una adreça electrònica.')
            ->email('email', 'L\'adreça electrònica no és vàlida.')
            ->maxLen('phone', 40, 'El telèfon és massa llarg.');

        if (Settings::bool('require_terms', true)) {
            $validator->accepted('terms', 'Cal acceptar les condicions de la inscripció.');
        }

        $emailConfirm = (string) Request::post('email_confirm', '');
        if ($emailConfirm !== '') {
            $validator->check(
                'email_confirm',
                mb_strtolower($emailConfirm) === mb_strtolower((string) Request::post('email', '')),
                'Les dues adreces electròniques no coincideixen.'
            );
        }

        $attendees = $this->collectAttendees($cart['items'], $validator);

        if ($validator->fails()) {
            Flash::setErrors($validator->errors());
            Flash::setOld($_POST);
            Flash::error('Reviseu les dades marcades del formulari.');
            // Reutilitzem el carretó de la sessió: el formulari de pagament no
            // torna a enviar les quantitats.
            $this->renderDetails($cart);
            return;
        }

        try {
            $order = TicketService::createPendingOrder([
                'email'      => (string) Request::post('email'),
                'name'       => (string) Request::post('name'),
                'surname'    => (string) Request::post('surname'),
                'phone'      => (string) Request::post('phone', ''),
                'ip'         => Request::ip(),
                'user_agent' => Request::userAgent(),
            ], $cart['items'], $attendees);
        } catch (RuntimeException $e) {
            // Places exhaurides mentre l'usuari omplia el formulari: el motiu és útil.
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/') . '#inscripcions');
        } catch (\Throwable $e) {
            Logger::exception($e, 'Creació de la comanda');
            Flash::error('No s\'ha pogut crear la inscripció. Torneu-ho a provar.');
            Response::redirect(Url::to('/') . '#inscripcions');
        }

        Session::forget(self::CART_KEY);
        Session::set('last_order', ['reference' => $order['reference'], 'token' => $order['manage_token']]);

        // Inscripcions gratuïtes: no cal passar per la passarel·la de pagament,
        // però sí que s'ha d'enviar el correu amb les entrades.
        if ((int) $order['total_cents'] === 0) {
            if (TicketService::markPaid((int) $order['id'], null, 0)) {
                $confirmed = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $order['id']]) ?? $order;
                try {
                    TicketService::sendConfirmationEmail($confirmed);
                } catch (\Throwable $e) {
                    Logger::exception($e, 'Correu de confirmació (inscripció gratuïta)');
                }
            }
            $this->afterPayment((string) $order['reference'], (string) $order['manage_token']);
            return;
        }

        if (!Settings::stripeConfigured()) {
            Flash::error('El sistema de pagament encara no està configurat. Poseu-vos en contacte amb l\'organització.');
            Response::redirect(Url::to('/'));
        }

        // Stripe rebutja els cobraments per sota d'un import mínim.
        $minim = StripeClient::minimumAmount();
        if ((int) $order['total_cents'] < $minim) {
            Db::update('orders', ['status' => 'failed'], '`id` = :id', ['id' => $order['id']]);
            Flash::error(
                'L\'import mínim per pagar amb targeta és de ' . Money::format($minim)
                . '. Afegiu alguna inscripció més o poseu-vos en contacte amb l\'organització.'
            );
            Response::redirect(Url::to('/') . '#inscripcions');
        }

        try {
            $items = [];
            foreach ($cart['items'] as $item) {
                $items[] = [
                    'name'         => (string) $item['type']['name'],
                    'description'  => Str::limit(trim((string) ($item['type']['description'] ?? '')), 300),
                    'amount_cents' => (int) $item['unit_cents'],
                    'quantity'     => (int) $item['quantity'],
                ];
            }

            $session = (new StripeClient())->createCheckoutSession(
                $items,
                (string) $order['email'],
                Url::full('/pagament/retorn') . '?session_id={CHECKOUT_SESSION_ID}',
                Url::full('/pagament/cancellat') . '?ref=' . $order['reference'],
                [
                    'order_id'        => (string) $order['id'],
                    'order_reference' => (string) $order['reference'],
                ]
            );

            Db::update('orders', ['stripe_session_id' => (string) $session['id']], '`id` = :id', ['id' => $order['id']]);
            Response::redirect((string) $session['url'], 303);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Stripe Checkout');
            Db::update('orders', ['status' => 'failed'], '`id` = :id', ['id' => $order['id']]);
            Flash::error('No s\'ha pogut iniciar el pagament: ' . $e->getMessage());
            Response::redirect(Url::to('/') . '#inscripcions');
        }
    }

    /** Retorn del navegador des de Stripe. */
    public function return(): void
    {
        $sessionId = (string) Request::get('session_id', '');
        if ($sessionId === '') {
            Response::redirect(Url::to('/'));
        }

        $order = Db::first('SELECT * FROM `orders` WHERE `stripe_session_id` = :s', ['s' => $sessionId]);
        if ($order === null) {
            Flash::error('No hem trobat la inscripció associada a aquest pagament.');
            Response::redirect(Url::to('/'));
        }

        // El webhook és la font de veritat, però confirmem també aquí perquè
        // l'usuari vegi el resultat immediatament encara que el webhook trigui.
        if ($order['status'] !== 'paid') {
            try {
                $session = (new StripeClient())->retrieveSession($sessionId);
                if (($session['payment_status'] ?? '') === 'paid') {
                    $paymentIntent = $session['payment_intent'] ?? null;
                    if (is_array($paymentIntent)) {
                        $paymentIntent = $paymentIntent['id'] ?? null;
                    }
                    if (TicketService::markPaid((int) $order['id'], $paymentIntent ? (string) $paymentIntent : null, (int) ($session['amount_total'] ?? $order['total_cents']))) {
                        $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $order['id']]);
                        try {
                            TicketService::sendConfirmationEmail($order ?? []);
                        } catch (\Throwable $e) {
                            Logger::exception($e, 'Correu de confirmació');
                        }
                    }
                }
            } catch (\Throwable $e) {
                Logger::exception($e, 'Verificació de la sessió de Stripe');
            }
            $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $order['id']]) ?? $order;
        }

        if ($order['status'] !== 'paid') {
            View::render('web/payment_pending', [
                'title' => 'Estem confirmant el pagament',
                'order' => $order,
            ], 'layouts/public');
            return;
        }

        $this->afterPayment((string) $order['reference'], (string) $order['manage_token']);
    }

    public function cancelled(): void
    {
        $reference = (string) Request::get('ref', '');
        if ($reference !== '') {
            Db::run(
                "UPDATE `orders` SET `status` = 'cancelled', `cancelled_at` = NOW()
                 WHERE `reference` = :r AND `status` = 'pending'",
                ['r' => $reference]
            );
        }

        View::render('web/payment_cancelled', [
            'title' => 'Pagament no completat',
        ], 'layouts/public');
    }

    private function afterPayment(string $reference, ?string $token = null): void
    {
        if ($token === null) {
            $token = (string) Db::value('SELECT `manage_token` FROM `orders` WHERE `reference` = :r', ['r' => $reference], '');
        }
        Response::redirect(Url::to('/confirmacio/' . $reference) . '?t=' . $token);
    }

    /**
     * Recull el nom i els camps addicionals de cada assistent.
     *
     * @return array<int, array{type_id:int, name:string, extra:array}>
     */
    private function collectAttendees(array $items, Validator $validator): array
    {
        $names  = Request::postArray('attendee_name');
        $extras = Request::postArray('attendee_extra');
        $attendees = [];
        $index = 0;

        foreach ($items as $item) {
            $type = $item['type'];
            $typeId = (int) $type['id'];
            $fields = HomeController::fieldsForType($typeId);

            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                $key = $typeId . '_' . $i;
                $name = trim((string) ($names[$key] ?? ''));

                if ((int) $type['requires_attendee_name'] === 1 && $name === '') {
                    $validator->check('attendee_name_' . $key, false, 'Cal indicar el nom de l\'assistent.');
                }

                $extra = [];
                foreach ($fields as $field) {
                    $value = $extras[$key][$field['slug']] ?? '';
                    if (is_array($value)) {
                        $value = implode(', ', array_map('strval', $value));
                    }
                    $value = trim((string) $value);
                    if ((int) $field['required'] === 1 && $value === '') {
                        $validator->check(
                            'attendee_extra_' . $key . '_' . $field['slug'],
                            false,
                            'Cal completar «' . $field['label'] . '».'
                        );
                    }
                    if ($value !== '') {
                        $extra[(string) $field['label']] = $value;
                    }
                }

                $attendees[] = ['type_id' => $typeId, 'name' => $name, 'extra' => $extra];
                $index++;
            }
        }

        return $attendees;
    }

    public static function formatSubtotal(int $cents): string
    {
        return Money::format($cents);
    }
}
