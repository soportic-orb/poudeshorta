<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;

/**
 * Pàgina pública d'una entrada (destí del codi QR).
 * Mostra si l'entrada és vàlida; el personal amb sessió oberta hi pot fer el
 * control d'accés des de la mateixa pantalla.
 */
final class TicketController
{
    public function show(string $code): void
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');

        $ticket = Db::first(
            'SELECT t.*, tt.`name` AS type_name, tt.`includes` AS type_includes,
                    o.`reference`, o.`name` AS buyer_name, o.`surname` AS buyer_surname,
                    o.`status` AS order_status, o.`email` AS buyer_email
             FROM `tickets` t
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             JOIN `orders` o ON o.`id` = t.`order_id`
             WHERE t.`code` = :c',
            ['c' => $code]
        );

        if ($ticket === null) {
            http_response_code(404);
            View::render('web/ticket_status', [
                'title'   => 'Entrada no trobada',
                'ticket'  => null,
                'state'   => 'unknown',
                'isStaff' => Auth::check(),
            ], 'layouts/public');
            return;
        }

        $state = match (true) {
            $ticket['order_status'] !== 'paid' && $ticket['order_status'] !== 'partially_refunded' => 'unpaid',
            $ticket['status'] === 'cancelled', $ticket['status'] === 'refunded' => 'cancelled',
            $ticket['status'] === 'used'   => 'used',
            default                        => 'valid',
        };

        View::render('web/ticket_status', [
            'title'   => 'Entrada ' . $ticket['code'],
            'ticket'  => $ticket,
            'state'   => $state,
            'isStaff' => Auth::check(),
        ], 'layouts/public');
    }
}
