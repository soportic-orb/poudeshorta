<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;

/**
 * Control d'accés el dia de l'esdeveniment.
 * Es pot fer servir des del mòbil: escanejant el QR (que porta a /e/{codi})
 * o escrivint el codi a mà.
 */
final class CheckinController
{
    public function index(): void
    {
        View::render('admin/checkin', [
            'title'   => 'Control d\'accés',
            'stats'   => [
                'total'    => (int) Db::value("SELECT COUNT(*) FROM `tickets` t JOIN `orders` o ON o.`id` = t.`order_id`
                                                WHERE t.`status` IN ('valid','used') AND o.`status` IN ('paid','partially_refunded')", [], 0),
                'validades' => (int) Db::value("SELECT COUNT(*) FROM `tickets` WHERE `status` = 'used'", [], 0),
            ],
            'recents' => Db::all(
                "SELECT t.`code`, t.`attendee_name`, t.`checked_in_at`, t.`checked_in_by`, tt.`name` AS type_name, o.`reference`
                 FROM `tickets` t
                 JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
                 JOIN `orders` o ON o.`id` = t.`order_id`
                 WHERE t.`checked_in_at` IS NOT NULL
                 ORDER BY t.`checked_in_at` DESC LIMIT 15"
            ),
            'enabled' => Settings::bool('checkin_enabled', true),
        ], 'layouts/admin');
    }

    /** Valida una entrada. Respon en JSON per poder-ho fer sense recarregar. */
    public function validate(): void
    {
        $code = strtoupper(trim((string) Request::post('code', '')));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        // El camp accepta tant el codi com l'URL sencera del QR.
        if ($code === '') {
            Response::json(['ok' => false, 'estat' => 'buit', 'missatge' => 'Introdueix un codi d\'entrada.'], 422);
        }

        $ticket = Db::first(
            'SELECT t.*, tt.`name` AS type_name, o.`reference`, o.`status` AS order_status,
                    o.`name` AS buyer_name, o.`surname` AS buyer_surname
             FROM `tickets` t
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             JOIN `orders` o ON o.`id` = t.`order_id`
             WHERE t.`code` = :c',
            ['c' => $code]
        );

        if ($ticket === null) {
            Response::json(['ok' => false, 'estat' => 'desconeguda', 'missatge' => 'Aquest codi no existeix.'], 404);
        }

        $attendee = trim((string) ($ticket['attendee_name'] ?: $ticket['buyer_name'] . ' ' . $ticket['buyer_surname']));
        $base = [
            'codi'      => (string) $ticket['code'],
            'assistent' => $attendee,
            'tipus'     => (string) $ticket['type_name'],
            'referencia' => (string) $ticket['reference'],
        ];

        if (!in_array((string) $ticket['order_status'], ['paid', 'partially_refunded'], true)) {
            Response::json(array_merge($base, [
                'ok' => false, 'estat' => 'impagada',
                'missatge' => 'La inscripció no consta com a pagada.',
            ]), 409);
        }

        if (in_array((string) $ticket['status'], ['cancelled', 'refunded'], true)) {
            Response::json(array_merge($base, [
                'ok' => false, 'estat' => 'anullada',
                'missatge' => 'Aquesta entrada està anul·lada.',
            ]), 409);
        }

        if ((string) $ticket['status'] === 'used') {
            Response::json(array_merge($base, [
                'ok' => false, 'estat' => 'repetida',
                'missatge' => 'Aquesta entrada ja es va validar el ' . date('d/m/Y \a \l\e\s H:i', strtotime((string) $ticket['checked_in_at'])) . '.',
            ]), 409);
        }

        Db::update('tickets', [
            'status'         => 'used',
            'checked_in_at'  => date('Y-m-d H:i:s'),
            'checked_in_by'  => (string) (Auth::user()['name'] ?? ''),
        ], '`id` = :id', ['id' => $ticket['id']]);

        Logger::audit('valida_entrada', (string) $ticket['code']);

        Response::json(array_merge($base, [
            'ok' => true, 'estat' => 'valida',
            'missatge' => 'Entrada vàlida. Endavant!',
            'hora' => date('H:i'),
        ]));
    }
}
