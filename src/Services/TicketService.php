<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use RuntimeException;

/**
 * Lògica de negoci de les inscripcions: disponibilitat, creació de comandes,
 * confirmació del pagament, anul·lacions i correus de l'entrada.
 */
final class TicketService
{
    /** Minuts que una comanda pendent manté reservada la seva plaça. */
    public const RESERVATION_MINUTES = 40;

    // ---------------------------------------------------------------- Catàleg

    /** @return array<int, array> Tipus d'entrada visibles al públic, amb places restants. */
    public static function publicTicketTypes(): array
    {
        $rows = Db::all(
            'SELECT * FROM `ticket_types` WHERE `active` = 1 ORDER BY `sort_order` ASC, `id` ASC'
        );
        $now = date('Y-m-d H:i:s');
        $out = [];
        foreach ($rows as $row) {
            $row['on_sale'] = true;
            $row['sale_note'] = '';
            if (!empty($row['sales_start']) && $row['sales_start'] > $now) {
                $row['on_sale'] = false;
                $row['sale_note'] = 'A la venda a partir del ' . date('d/m/Y', strtotime((string) $row['sales_start']));
            } elseif (!empty($row['sales_end']) && $row['sales_end'] < $now) {
                $row['on_sale'] = false;
                $row['sale_note'] = 'Venda tancada';
            }
            $row['remaining'] = self::remaining((int) $row['id']);
            if ($row['remaining'] !== null && $row['remaining'] <= 0) {
                $row['on_sale'] = false;
                $row['sale_note'] = 'Exhaurides';
            }
            $out[] = $row;
        }
        return $out;
    }

    public static function ticketTypesById(): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM `ticket_types`') as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /** Places restants d'un tipus, o null si no té límit. */
    public static function remaining(int $typeId): ?int
    {
        $quota = Db::value('SELECT `quota` FROM `ticket_types` WHERE `id` = :id', ['id' => $typeId]);
        if ($quota === null || $quota === '' || (int) $quota <= 0) {
            return null;
        }
        return max(0, (int) $quota - self::committedCount($typeId));
    }

    /** Entrades ja pagades més les reservades per comandes pendents recents. */
    public static function committedCount(int $typeId): int
    {
        return (int) Db::value(
            "SELECT COUNT(*)
             FROM `tickets` t
             JOIN `orders` o ON o.`id` = t.`order_id`
             WHERE t.`ticket_type_id` = :id
               AND t.`status` IN ('valid','used')
               AND (
                    o.`status` = 'paid'
                    OR (o.`status` = 'pending' AND o.`created_at` > DATE_SUB(NOW(), INTERVAL :mins MINUTE))
               )",
            ['id' => $typeId, 'mins' => self::RESERVATION_MINUTES],
            0
        );
    }

    public static function soldCount(int $typeId): int
    {
        return (int) Db::value(
            "SELECT COUNT(*) FROM `tickets` t JOIN `orders` o ON o.`id` = t.`order_id`
             WHERE t.`ticket_type_id` = :id AND o.`status` = 'paid' AND t.`status` IN ('valid','used')",
            ['id' => $typeId],
            0
        );
    }

    public static function salesOpen(): bool
    {
        if (!Settings::bool('sales_open', true)) {
            return false;
        }
        foreach (self::publicTicketTypes() as $type) {
            if ($type['on_sale']) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- Comandes

    /**
     * Valida la selecció d'entrades i retorna les línies normalitzades.
     *
     * @param array<int,int> $quantities  [ticket_type_id => quantitat]
     * @return array{items: array<int, array>, subtotal: int}
     * @throws RuntimeException si la selecció no és vàlida.
     */
    public static function buildCart(array $quantities): array
    {
        $types = self::ticketTypesById();
        $items = [];
        $subtotal = 0;
        $totalQty = 0;

        foreach ($quantities as $typeId => $qty) {
            $typeId = (int) $typeId;
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $type = $types[$typeId] ?? null;
            if ($type === null || (int) $type['active'] !== 1) {
                throw new RuntimeException('Un dels tipus d\'inscripció seleccionats ja no està disponible.');
            }

            $now = date('Y-m-d H:i:s');
            if (!empty($type['sales_start']) && $type['sales_start'] > $now) {
                throw new RuntimeException('Encara no s\'ha obert la venda de «' . $type['name'] . '».');
            }
            if (!empty($type['sales_end']) && $type['sales_end'] < $now) {
                throw new RuntimeException('La venda de «' . $type['name'] . '» ja s\'ha tancat.');
            }

            $max = (int) $type['max_per_order'];
            if ($max > 0 && $qty > $max) {
                throw new RuntimeException('Només pots comprar un màxim de ' . $max . ' entrades de «' . $type['name'] . '» per comanda.');
            }
            $min = (int) $type['min_per_order'];
            if ($min > 0 && $qty < $min) {
                throw new RuntimeException('Cal comprar com a mínim ' . $min . ' entrades de «' . $type['name'] . '».');
            }

            $remaining = self::remaining($typeId);
            if ($remaining !== null && $qty > $remaining) {
                throw new RuntimeException($remaining > 0
                    ? 'Només queden ' . $remaining . ' places de «' . $type['name'] . '».'
                    : 'Les places de «' . $type['name'] . '» s\'han exhaurit.');
            }

            $items[] = [
                'type'         => $type,
                'quantity'     => $qty,
                'unit_cents'   => (int) $type['price_cents'],
                'line_cents'   => (int) $type['price_cents'] * $qty,
            ];
            $subtotal += (int) $type['price_cents'] * $qty;
            $totalQty += $qty;
        }

        if ($items === []) {
            throw new RuntimeException('Cal seleccionar almenys una inscripció.');
        }

        $globalMax = Settings::int('max_tickets_order', 10);
        if ($globalMax > 0 && $totalQty > $globalMax) {
            throw new RuntimeException('Pots comprar un màxim de ' . $globalMax . ' entrades per comanda.');
        }

        return ['items' => $items, 'subtotal' => $subtotal];
    }

    /**
     * Crea la comanda pendent i les seves entrades (reservant plaça).
     *
     * @param array{email:string,name:string,surname?:string,phone?:string,ip?:string,user_agent?:string} $buyer
     * @param array<int, array> $items       Sortida de buildCart()['items'].
     * @param array<int, array> $attendees   Dades per entrada: [['type_id'=>, 'name'=>, 'extra'=>[]], ...]
     */
    public static function createPendingOrder(array $buyer, array $items, array $attendees = []): array
    {
        return Db::transaction(static function () use ($buyer, $items, $attendees) {
            // Bloquegem les files dels tipus implicats: així dues compres
            // simultànies del mateix tipus es processen una darrere l'altra i
            // no es poden vendre més places de les que hi ha.
            $typeIds = [];
            foreach ($items as $item) {
                $typeIds[] = (int) $item['type']['id'];
            }
            sort($typeIds); // Ordre estable per evitar bloquejos creuats.

            $placeholders = [];
            $params = [];
            foreach ($typeIds as $i => $typeId) {
                $placeholders[] = ':lock' . $i;
                $params['lock' . $i] = $typeId;
            }
            Db::run(
                'SELECT `id` FROM `ticket_types` WHERE `id` IN (' . implode(', ', $placeholders) . ') FOR UPDATE',
                $params
            );

            // Amb el bloqueig actiu, tornem a comprovar la disponibilitat.
            foreach ($items as $item) {
                $typeId = (int) $item['type']['id'];
                $remaining = self::remaining($typeId);
                if ($remaining !== null && (int) $item['quantity'] > $remaining) {
                    throw new RuntimeException($remaining > 0
                        ? 'Mentre completaves les dades algú s\'ha avançat: només queden ' . $remaining . ' places de «' . $item['type']['name'] . '».'
                        : 'Mentre completaves les dades s\'han exhaurit les places de «' . $item['type']['name'] . '».');
                }
            }

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (int) $item['line_cents'];
            }

            $orderId = Db::insert('orders', [
                'reference'      => self::generateReference(),
                'email'          => mb_strtolower(trim($buyer['email'])),
                'name'           => trim($buyer['name']),
                'surname'        => trim((string) ($buyer['surname'] ?? '')) ?: null,
                'phone'          => trim((string) ($buyer['phone'] ?? '')) ?: null,
                'status'         => 'pending',
                'subtotal_cents' => $subtotal,
                'total_cents'    => $subtotal,
                'currency'       => (string) Settings::get('currency', 'EUR'),
                'manage_token'   => Str::token(32),
                'ip'             => $buyer['ip'] ?? null,
                'user_agent'     => $buyer['user_agent'] ?? null,
            ]);

            // Repartim les dades d'assistent introduïdes entre les entrades del seu tipus.
            $byType = [];
            foreach ($attendees as $attendee) {
                $byType[(int) ($attendee['type_id'] ?? 0)][] = $attendee;
            }

            foreach ($items as $item) {
                $typeId = (int) $item['type']['id'];
                for ($i = 0; $i < (int) $item['quantity']; $i++) {
                    $attendee = array_shift($byType[$typeId]) ?? [];
                    $extra = array_filter((array) ($attendee['extra'] ?? []), static fn ($v) => $v !== '' && $v !== null);

                    Db::insert('tickets', [
                        'order_id'       => $orderId,
                        'ticket_type_id' => $typeId,
                        'code'           => self::generateTicketCode(),
                        'attendee_name'  => trim((string) ($attendee['name'] ?? '')) ?: null,
                        'price_cents'    => (int) $item['unit_cents'],
                        'status'         => 'valid',
                        'extra_json'     => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                    ]);
                }
            }

            return Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $orderId]) ?? [];
        });
    }

    /** Marca la comanda com a pagada. Idempotent: es pot cridar des del webhook i des del retorn del navegador. */
    public static function markPaid(int $orderId, ?string $paymentIntent = null, ?int $amountCents = null): bool
    {
        $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $orderId]);
        if ($order === null) {
            return false;
        }
        if ($order['status'] === 'paid') {
            return false;
        }

        Db::update('orders', array_filter([
            'status'                => 'paid',
            'paid_at'               => date('Y-m-d H:i:s'),
            'stripe_payment_intent' => $paymentIntent,
            'total_cents'           => $amountCents,
        ], static fn ($v) => $v !== null), '`id` = :id', ['id' => $orderId]);

        Logger::info('Comanda pagada', ['order' => $order['reference']]);
        return true;
    }

    /** Allibera les places de les comandes pendents caducades (l'invoca el cron). */
    public static function expireStalePendingOrders(int $minutes = 120): int
    {
        return Db::run(
            "UPDATE `orders` SET `status` = 'expired'
             WHERE `status` = 'pending' AND `created_at` < DATE_SUB(NOW(), INTERVAL :m MINUTE)",
            ['m' => $minutes]
        )->rowCount();
    }

    // ------------------------------------------------------------ Anul·lacions

    /**
     * Comprova si una comanda pagada es pot anul·lar segons la política del panell.
     *
     * @return array{allowed:bool, reason:string, refund:bool, fee_cents:int, refund_cents:int}
     */
    public static function cancellationStatus(array $order): array
    {
        $deny = static fn (string $reason): array => [
            'allowed' => false, 'reason' => $reason, 'refund' => false, 'fee_cents' => 0, 'refund_cents' => 0,
        ];

        if (!Settings::bool('cancellation_enabled', true)) {
            return $deny('Les anul·lacions no estan habilitades per a aquest esdeveniment.');
        }
        // Una comanda amb una entrada ja anul·lada i retornada queda en
        // «partially_refunded», i les entrades que hi queden s'han de poder
        // anul·lar igualment mentre no s'acabi el termini.
        if (!in_array((string) ($order['status'] ?? ''), ['paid', 'partially_refunded'], true)) {
            return $deny('Només es poden anul·lar inscripcions pagades.');
        }

        $cancellable = (int) Db::value(
            "SELECT COUNT(*) FROM `tickets` WHERE `order_id` = :id AND `status` = 'valid'",
            ['id' => $order['id']],
            0
        );
        if ($cancellable === 0) {
            $used = (int) Db::value(
                "SELECT COUNT(*) FROM `tickets` WHERE `order_id` = :id AND `status` = 'used'",
                ['id' => $order['id']],
                0
            );
            return $deny($used > 0
                ? 'Ja no queda cap entrada que es pugui anul·lar: la resta ja s\'han utilitzat.'
                : 'Aquesta inscripció ja està anul·lada.');
        }

        $deadline = self::cancellationDeadline();
        if ($deadline !== null && time() > $deadline) {
            return $deny('El termini per anul·lar va acabar el ' . date('d/m/Y', $deadline) . '.');
        }

        $refundEnabled = Settings::bool('cancellation_refund', true);
        $feePercent = max(0.0, min(100.0, (float) Settings::get('cancellation_fee_percent', '0')));

        return [
            'allowed'      => true,
            'reason'       => '',
            'refund'       => $refundEnabled,
            'fee_percent'  => $feePercent,
            'fee_cents'    => 0,
            'refund_cents' => 0,
        ];
    }

    /**
     * Moment en què els passis de wallet han de deixar de ser vàlids.
     *
     * Ni l'Apple Wallet ni el Google Wallet permeten esborrar un passi del
     * telèfon de ningú: el que sí que es pot és marcar-los com a caducats, i
     * llavors el mòbil els treu de la llista de passis actius i els arracona
     * als caducats. Es compta a partir de la data de l'esdeveniment.
     *
     * @return int|null Marca de temps unix, o null si no se sap la data.
     */
    public static function walletExpiry(): ?int
    {
        $date = trim((string) Settings::get('event_date'));
        if ($date === '') {
            return null;
        }

        $ts = strtotime($date);
        if ($ts === false) {
            return null;
        }

        return $ts + max(0, Settings::int('wallet_expire_hours', 48)) * 3600;
    }

    /** Data límit per anul·lar (marca de temps unix) o null si no n'hi ha. */
    public static function cancellationDeadline(): ?int
    {
        $fixed = trim((string) Settings::get('cancellation_deadline_date'));
        if ($fixed !== '') {
            $ts = strtotime($fixed);
            if ($ts !== false) {
                return $ts;
            }
        }
        $days = Settings::int('cancellation_deadline_days', 0);
        $eventDate = trim((string) Settings::get('event_date'));
        if ($days > 0 && $eventDate !== '') {
            $ts = strtotime($eventDate);
            if ($ts !== false) {
                return $ts - $days * 86400;
            }
        }
        return null;
    }

    /**
     * Anul·la entrades concretes d'una comanda i, si escau, en retorna l'import.
     *
     * @param int[] $ticketIds  Entrades a anul·lar (buit = totes les vàlides).
     * @return array{cancelled:int, refunded_cents:int, refund_error:?string}
     */
    public static function cancelTickets(array $order, array $ticketIds, string $actor = 'client'): array
    {
        $status = self::cancellationStatus($order);
        if (!$status['allowed'] && $actor === 'client') {
            throw new RuntimeException($status['reason']);
        }

        $valid = Db::all(
            "SELECT * FROM `tickets` WHERE `order_id` = :id AND `status` = 'valid'",
            ['id' => $order['id']]
        );
        if ($valid === []) {
            throw new RuntimeException('Aquesta inscripció no té entrades actives.');
        }

        $selected = $ticketIds === []
            ? $valid
            : array_values(array_filter($valid, static fn ($t) => in_array((int) $t['id'], array_map('intval', $ticketIds), true)));

        if ($selected === []) {
            throw new RuntimeException('No s\'ha seleccionat cap entrada vàlida per anul·lar.');
        }
        if (!Settings::bool('cancellation_allow_partial', true) && count($selected) !== count($valid)) {
            throw new RuntimeException('Cal anul·lar la inscripció sencera; no es permeten anul·lacions parcials.');
        }

        $amount = 0;
        foreach ($selected as $ticket) {
            $amount += (int) $ticket['price_cents'];
        }

        $feePercent = max(0.0, min(100.0, (float) Settings::get('cancellation_fee_percent', '0')));
        $fee = (int) round($amount * $feePercent / 100);
        $refundable = max(0, $amount - $fee);

        $refundError = null;
        $refundedCents = 0;

        if (Settings::bool('cancellation_refund', true) && $refundable > 0 && !empty($order['stripe_payment_intent'])) {
            // No retornem mai més del que resta per retornar d'aquesta comanda.
            $available = max(0, (int) $order['total_cents'] - (int) $order['refunded_cents']);
            $toRefund = min($refundable, $available);

            if ($toRefund > 0) {
                try {
                    $refund = (new StripeClient())->refund((string) $order['stripe_payment_intent'], $toRefund);
                    $refundedCents = $toRefund;
                    Db::insert('refunds', [
                        'order_id'         => (int) $order['id'],
                        'amount_cents'     => $toRefund,
                        'stripe_refund_id' => (string) ($refund['id'] ?? ''),
                        'reason'           => 'Anul·lació de la inscripció',
                        'initiated_by'     => $actor,
                        'status'           => (string) ($refund['status'] ?? 'pending'),
                    ]);
                } catch (\Throwable $e) {
                    $refundError = $e->getMessage();
                    Logger::exception($e, 'Devolució Stripe');
                }
            }
        }

        Db::transaction(static function () use ($selected, $order, $refundedCents) {
            $now = date('Y-m-d H:i:s');
            foreach ($selected as $ticket) {
                Db::update('tickets', [
                    'status'       => 'cancelled',
                    'cancelled_at' => $now,
                ], '`id` = :id', ['id' => $ticket['id']]);
            }

            // Una entrada ja validada a la porta segueix comptant com a activa:
            // la comanda només queda anul·lada si no en queda cap.
            $stillActive = (int) Db::value(
                "SELECT COUNT(*) FROM `tickets` WHERE `order_id` = :id AND `status` IN ('valid','used')",
                ['id' => $order['id']],
                0
            );

            $totalRefunded = (int) $order['refunded_cents'] + $refundedCents;
            $newStatus = $stillActive === 0
                ? ($totalRefunded > 0 ? 'refunded' : 'cancelled')
                : ($totalRefunded > 0 ? 'partially_refunded' : 'paid');

            Db::update('orders', [
                'status'          => $newStatus,
                'refunded_cents'  => $totalRefunded,
                'cancelled_at'    => $stillActive === 0 ? $now : $order['cancelled_at'],
            ], '`id` = :id', ['id' => $order['id']]);
        });

        Logger::info('Entrades anul·lades', [
            'order'    => $order['reference'],
            'entrades' => count($selected),
            'retornat' => $refundedCents,
            'per'      => $actor,
        ]);

        return ['cancelled' => count($selected), 'refunded_cents' => $refundedCents, 'refund_error' => $refundError];
    }

    // ------------------------------------------------------------------ Correu

    /** Genera el PDF amb totes les entrades vàlides d'una comanda. */
    public static function pdfForOrder(array $order, array $ticketIds = []): string
    {
        $params = ['oid' => (int) $order['id']];
        $sql = "SELECT * FROM `tickets` WHERE `order_id` = :oid AND `status` IN ('valid','used')";

        if ($ticketIds !== []) {
            $placeholders = [];
            foreach (array_values(array_unique(array_map('intval', $ticketIds))) as $i => $id) {
                $placeholders[] = ':tid' . $i;
                $params['tid' . $i] = $id;
            }
            $sql .= ' AND `id` IN (' . implode(', ', $placeholders) . ')';
        }

        $tickets = Db::all($sql . ' ORDER BY `id` ASC', $params);

        if ($tickets === []) {
            throw new RuntimeException('Aquesta inscripció no té entrades vàlides per descarregar.');
        }

        return (new TicketPdf())->render($order, $tickets, self::ticketTypesById());
    }

    public static function pdfFilename(array $order): string
    {
        return 'entrades-' . strtolower((string) $order['reference']) . '.pdf';
    }

    /** Envia el correu de confirmació amb les entrades adjuntes. */
    public static function sendConfirmationEmail(array $order): bool
    {
        $tickets = Db::all(
            "SELECT t.*, tt.`name` AS type_name FROM `tickets` t
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             WHERE t.`order_id` = :id AND t.`status` IN ('valid','used') ORDER BY t.`id`",
            ['id' => $order['id']]
        );
        if ($tickets === []) {
            return false;
        }

        $vars = [
            'name'             => (string) $order['name'],
            'surname'          => (string) ($order['surname'] ?? ''),
            'reference'        => (string) $order['reference'],
            'ticket_count'     => (string) count($tickets),
            'total'            => Money::format((int) $order['total_cents']),
            'event_name'       => (string) Settings::get('event_name'),
            'event_date'       => (string) Settings::get('event_date_text'),
            'event_location'   => (string) Settings::get('event_location'),
            'event_organizer'  => (string) Settings::get('event_organizer'),
        ];

        $subject = Str::template((string) Settings::get('mail_confirmation_subject'), $vars);
        $bodyText = Str::template((string) Settings::get('mail_confirmation_body'), $vars);

        $mailer = new Mailer();
        $html = $mailer->wrap(
            'Inscripció confirmada',
            Mailer::textToHtml($bodyText) . self::ticketSummaryHtml($tickets, $order),
            [['label' => 'Veure i descarregar les entrades', 'url' => Url::full('/comanda/' . $order['reference'] . '?t=' . $order['manage_token'])]]
        );

        $pdf = self::pdfForOrder($order);

        $sent = $mailer->send(
            (string) $order['email'],
            trim((string) $order['name'] . ' ' . (string) ($order['surname'] ?? '')),
            $subject,
            $html,
            [['content' => $pdf, 'name' => self::pdfFilename($order), 'mime' => 'application/pdf']]
        );

        if ($sent) {
            // Ens permet dir a l'usuari si realment té el correu a la safata.
            Db::update('orders', ['confirmation_sent_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $order['id']]);
        }

        return $sent;
    }

    private static function ticketSummaryHtml(array $tickets, array $order): string
    {
        $rows = '';
        foreach ($tickets as $ticket) {
            $rows .= '<tr>'
                . '<td style="padding:8px 0;border-bottom:1px solid #EDE5D8;">'
                . '<strong>' . Str::e((string) $ticket['type_name']) . '</strong><br>'
                . '<span style="color:#7A7268;font-size:13px;">' . Str::e((string) ($ticket['attendee_name'] ?: $order['name'])) . '</span>'
                . '</td>'
                . '<td style="padding:8px 0;border-bottom:1px solid #EDE5D8;text-align:right;font-family:monospace;font-size:13px;">'
                . Str::e((string) $ticket['code'])
                . '</td></tr>';
        }
        return '<table role="presentation" width="100%" style="border-collapse:collapse;margin:18px 0;">' . $rows . '</table>';
    }

    // ------------------------------------------------------------------ Codis

    private static function generateReference(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $reference = 'PDSH-' . Str::randomCode(6);
            $exists = Db::value('SELECT 1 FROM `orders` WHERE `reference` = :r', ['r' => $reference]);
            if (!$exists) {
                return $reference;
            }
        }
        throw new RuntimeException('No s\'ha pogut generar una referència única.');
    }

    private static function generateTicketCode(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = Str::randomCode(8);
            $exists = Db::value('SELECT 1 FROM `tickets` WHERE `code` = :c', ['c' => $code]);
            if (!$exists) {
                return $code;
            }
        }
        throw new RuntimeException('No s\'ha pogut generar un codi d\'entrada únic.');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid'               => 'Pagada',
            'pending'            => 'Pendent de pagament',
            'cancelled'          => 'Anul·lada',
            'refunded'           => 'Retornada',
            'partially_refunded' => 'Retornada parcialment',
            'failed'             => 'Pagament fallit',
            'expired'            => 'Caducada',
            'valid'              => 'Vàlida',
            'used'               => 'Utilitzada',
            default              => ucfirst($status),
        };
    }
}
