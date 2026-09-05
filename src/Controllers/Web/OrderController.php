<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\View;
use App\Services\AppleWallet;
use App\Services\GoogleWallet;
use App\Services\Mailer;
use App\Services\TicketService;
use RuntimeException;

final class OrderController
{
    /** Pantalla de compra realitzada correctament (amb confeti). */
    public function confirmation(string $reference): void
    {
        $order = $this->findOrder($reference, requireToken: true);

        if ($order['status'] !== 'paid') {
            Response::redirect(Url::to('/comanda/' . $reference) . '?t=' . $order['manage_token']);
        }

        View::render('web/confirmation', [
            'title'   => 'Inscripció confirmada',
            'order'   => $order,
            'tickets' => $this->ticketsOf($order),
            'walletApple'  => AppleWallet::isConfigured(),
            'walletGoogle' => GoogleWallet::isConfigured(),
            'useConfetti'  => true,
        ], 'layouts/public');
    }

    /** Detall d'una comanda (entrades, estat i opcions d'anul·lació). */
    public function show(string $reference): void
    {
        $order = $this->findOrder($reference, requireToken: true);
        $cancellation = TicketService::cancellationStatus($order);

        View::render('web/order', [
            'title'        => 'Inscripció ' . $order['reference'],
            'order'        => $order,
            'tickets'      => $this->ticketsOf($order),
            'cancellation' => $cancellation,
            'deadline'     => TicketService::cancellationDeadline(),
            'walletApple'  => AppleWallet::isConfigured(),
            'walletGoogle' => GoogleWallet::isConfigured(),
        ], 'layouts/public');
    }

    public function downloadPdf(string $reference): void
    {
        $order = $this->findOrder($reference, requireToken: true);
        try {
            $pdf = TicketService::pdfForOrder($order);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/comanda/' . $reference) . '?t=' . $order['manage_token']);
        }

        Response::download($pdf, TicketService::pdfFilename($order), 'application/pdf');
    }

    /** Envia les entrades per correu electrònic amb el PDF adjunt. */
    public function emailTickets(string $reference): void
    {
        $order = $this->findOrder($reference, requireToken: true);
        $back = Url::to('/comanda/' . $reference) . '?t=' . $order['manage_token'];

        if (!RateLimit::attempt('mail-tickets:' . $order['id'], 5, 900)) {
            Flash::error('Has demanat l\'enviament diverses vegades. Espera uns minuts abans de tornar-ho a provar.');
            Response::redirect($back);
        }

        $destination = trim((string) Request::post('email', ''));
        if ($destination === '' || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            $destination = (string) $order['email'];
        }

        try {
            $pdf = TicketService::pdfForOrder($order);
            $mailer = new Mailer();
            $html = $mailer->wrap(
                'Les teves entrades',
                '<p>Hola ' . Str::e((string) $order['name']) . ',</p>'
                . '<p>T\'adjuntem les entrades de la inscripció <strong>' . Str::e((string) $order['reference']) . '</strong> en format PDF.</p>'
                . '<p>Pots imprimir-les o mostrar el codi QR directament des del mòbil.</p>',
                [['label' => 'Veure les entrades en línia', 'url' => Url::full('/comanda/' . $order['reference']) . '?t=' . $order['manage_token']]]
            );

            $sent = $mailer->send(
                $destination,
                (string) $order['name'],
                'Les teves entrades · ' . Settings::get('event_name'),
                $html,
                [['content' => $pdf, 'name' => TicketService::pdfFilename($order), 'mime' => 'application/pdf']]
            );

            if ($sent) {
                Flash::success('Hem enviat les entrades a ' . Str::maskEmail($destination) . '.');
            } else {
                Flash::error('No s\'han pogut enviar les entrades. Prova de descarregar-les en PDF.');
            }
        } catch (\Throwable $e) {
            Logger::exception($e, 'Enviament d\'entrades');
            Flash::error('No s\'han pogut enviar les entrades en aquest moment.');
        }

        Response::redirect($back);
    }

    /** Anul·lació per part de la persona inscrita, dins dels paràmetres del panell. */
    public function cancel(string $reference): void
    {
        $order = $this->findOrder($reference, requireToken: true);
        $back = Url::to('/comanda/' . $reference) . '?t=' . $order['manage_token'];

        $ticketIds = array_map('intval', Request::postArray('tickets'));

        try {
            $result = TicketService::cancelTickets($order, $ticketIds, 'client');
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect($back);
        }

        $message = $result['cancelled'] === 1
            ? 'S\'ha anul·lat 1 entrada.'
            : 'S\'han anul·lat ' . $result['cancelled'] . ' entrades.';

        if ($result['refunded_cents'] > 0) {
            $message .= ' Et retornarem ' . \App\Core\Money::format($result['refunded_cents'])
                . ' al mateix mitjà de pagament (pot trigar uns dies hàbils).';
        } elseif ($result['refund_error'] !== null) {
            $message .= ' No hem pogut tramitar la devolució automàticament; l\'organització es posarà en contacte amb tu.';
        }

        Flash::success($message);
        $this->notifyCancellation($order, $result);
        Response::redirect($back);
    }

    // ------------------------------------------------ Consulta per correu

    public function lookupForm(): void
    {
        View::render('web/lookup', [
            'title' => 'Les meves entrades',
            'sent'  => (bool) Session::pull('lookup_sent', false),
        ], 'layouts/public');
    }

    /**
     * L'usuari introdueix el correu i li enviem un enllaç d'accés.
     * No revelem mai si l'adreça existeix o no.
     */
    public function lookupSend(): void
    {
        $email = mb_strtolower(trim((string) Request::post('email', '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Introdueix una adreça electrònica vàlida.');
            Response::redirect(Url::to('/les-meves-entrades'));
        }

        if (!RateLimit::attempt('lookup:' . Request::ip(), 8, 900)) {
            Flash::error('Hi ha hagut massa intents des d\'aquesta connexió. Espera uns minuts.');
            Response::redirect(Url::to('/les-meves-entrades'));
        }

        $orders = Db::all(
            "SELECT * FROM `orders`
             WHERE `email` = :e AND `status` IN ('paid','partially_refunded','refunded','cancelled')
             ORDER BY `created_at` DESC",
            ['e' => $email]
        );

        if ($orders !== []) {
            $token = Str::token(24);
            Db::insert('access_links', [
                'token'      => $token,
                'email'      => $email,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'ip'         => Request::ip(),
            ]);

            $list = '';
            foreach ($orders as $order) {
                $list .= '<li style="margin-bottom:6px;"><strong>' . Str::e((string) $order['reference']) . '</strong> · '
                    . date('d/m/Y', strtotime((string) $order['created_at'])) . ' · '
                    . \App\Core\Money::format((int) $order['total_cents']) . '</li>';
            }

            try {
                $mailer = new Mailer();
                $mailer->send(
                    $email,
                    null,
                    'Les teves inscripcions · ' . Settings::get('event_name'),
                    $mailer->wrap(
                        'Les teves inscripcions',
                        '<p>Has demanat consultar les teves entrades de <strong>' . Str::e((string) Settings::get('event_name')) . '</strong>.</p>'
                        . '<ul style="padding-left:18px;">' . $list . '</ul>'
                        . '<p>Fes clic al botó per veure-les, descarregar-les o gestionar-les. L\'enllaç caduca d\'aquí a una hora.</p>',
                        [['label' => 'Veure les meves entrades', 'url' => Url::full('/les-meves-entrades/' . $token)]]
                    )
                );
            } catch (\Throwable $e) {
                Logger::exception($e, 'Consulta d\'entrades');
            }
        }

        Session::set('lookup_sent', true);
        Flash::success('Si aquesta adreça té inscripcions, hi rebràs un correu amb l\'enllaç per consultar-les.');
        Response::redirect(Url::to('/les-meves-entrades'));
    }

    public function lookupResults(string $token): void
    {
        $link = Db::first(
            'SELECT * FROM `access_links` WHERE `token` = :t AND `expires_at` > NOW()',
            ['t' => preg_replace('/[^a-f0-9]/', '', $token)]
        );

        if ($link === null) {
            Flash::error('Aquest enllaç ha caducat. Demana\'n un de nou.');
            Response::redirect(Url::to('/les-meves-entrades'));
        }

        Db::update('access_links', ['used_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $link['id']]);

        $orders = Db::all(
            'SELECT * FROM `orders` WHERE `email` = :e ORDER BY `created_at` DESC',
            ['e' => $link['email']]
        );

        foreach ($orders as $index => $order) {
            $orders[$index]['tickets'] = $this->ticketsOf($order);
        }

        View::render('web/lookup_results', [
            'title'  => 'Les meves entrades',
            'email'  => (string) $link['email'],
            'orders' => $orders,
        ], 'layouts/public');
    }

    // ------------------------------------------------------------- Ajudants

    /** Cerca la comanda i valida el testimoni d'accés de l'enllaç. */
    private function findOrder(string $reference, bool $requireToken = true): array
    {
        $order = Db::first('SELECT * FROM `orders` WHERE `reference` = :r', ['r' => $reference]);
        if ($order === null) {
            http_response_code(404);
            View::render('web/error', [
                'title'   => 'Inscripció no trobada',
                'code'    => 404,
                'message' => 'No hem trobat cap inscripció amb aquesta referència.',
            ], 'layouts/public');
            exit;
        }

        if ($requireToken) {
            $token = (string) Request::input('t', '');
            $sessionOrder = Session::get('last_order');
            $viaSession = is_array($sessionOrder) && ($sessionOrder['reference'] ?? '') === $reference;

            if (!hash_equals((string) $order['manage_token'], $token) && !$viaSession) {
                http_response_code(403);
                View::render('web/error', [
                    'title'   => 'Enllaç no vàlid',
                    'code'    => 403,
                    'message' => 'Aquest enllaç no és vàlid o ha caducat. Pots recuperar les entrades des de «Les meves entrades».',
                ], 'layouts/public');
                exit;
            }
        }

        return $order;
    }

    private function ticketsOf(array $order): array
    {
        return Db::all(
            'SELECT t.*, tt.`name` AS type_name, tt.`includes` AS type_includes
             FROM `tickets` t
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             WHERE t.`order_id` = :id
             ORDER BY t.`id` ASC',
            ['id' => $order['id']]
        );
    }

    private function notifyCancellation(array $order, array $result): void
    {
        try {
            $refundNote = $result['refunded_cents'] > 0
                ? 'Et retornarem ' . \App\Core\Money::format($result['refunded_cents']) . ' al mateix mitjà de pagament.'
                : 'Aquesta anul·lació no comporta cap devolució.';

            $vars = [
                'name'            => (string) $order['name'],
                'reference'       => (string) $order['reference'],
                'refund_note'     => $refundNote,
                'event_name'      => (string) Settings::get('event_name'),
                'event_organizer' => (string) Settings::get('event_organizer'),
            ];

            $mailer = new Mailer();
            $mailer->send(
                (string) $order['email'],
                (string) $order['name'],
                Str::template((string) Settings::get('mail_cancellation_subject'), $vars),
                $mailer->wrap(
                    'Anul·lació confirmada',
                    Mailer::textToHtml(Str::template((string) Settings::get('mail_cancellation_body'), $vars))
                )
            );
        } catch (\Throwable $e) {
            Logger::exception($e, 'Correu d\'anul·lació');
        }
    }
}
