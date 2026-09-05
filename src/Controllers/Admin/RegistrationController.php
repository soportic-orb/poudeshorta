<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Services\RegistrationListPdf;
use App\Services\TicketService;
use RuntimeException;

/**
 * Llistat i gestió de les inscripcions realitzades.
 * El llistat treballa a nivell d'entrada (una fila per assistent), que és
 * el que interessa per al control d'accés i per als llistats impresos.
 */
final class RegistrationController
{
    private const PER_PAGE = 40;

    public function index(): void
    {
        $filters = $this->filters();
        [$where, $params] = $this->buildWhere($filters);

        $total = (int) Db::value(
            'SELECT COUNT(*) FROM `tickets` t
             JOIN `orders` o ON o.`id` = t.`order_id`
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             WHERE ' . $where,
            $params,
            0
        );

        $page = max(1, (int) Request::get('pagina', 1));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = Db::all(
            $this->baseSelect() . ' WHERE ' . $where .
            ' ORDER BY o.`created_at` DESC, t.`id` ASC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );

        $totals = Db::first(
            'SELECT COUNT(*) AS entrades,
                    COALESCE(SUM(t.`price_cents`), 0) AS import
             FROM `tickets` t
             JOIN `orders` o ON o.`id` = t.`order_id`
             JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
             WHERE ' . $where,
            $params
        ) ?? ['entrades' => 0, 'import' => 0];

        View::render('admin/registrations', [
            'title'   => 'Inscripcions',
            'rows'    => $rows,
            'filters' => $filters,
            'types'   => Db::all('SELECT `id`, `name` FROM `ticket_types` ORDER BY `sort_order`, `id`'),
            'total'   => $total,
            'totals'  => $totals,
            'page'    => $page,
            'pages'   => $pages,
            'potEsborrar' => Auth::is('owner', 'admin'),
        ], 'layouts/admin');
    }

    public function pdf(): void
    {
        $filters = $this->filters();
        [$where, $params] = $this->buildWhere($filters);

        $rows = Db::all($this->baseSelect() . ' WHERE ' . $where . ' ORDER BY o.`created_at` DESC, t.`id` ASC', $params);

        $formatted = [];
        $sum = 0;
        foreach ($rows as $row) {
            $sum += (int) $row['price_cents'];
            $formatted[] = [
                'created_at'   => date('d/m/Y H:i', strtotime((string) $row['created_at'])),
                'reference'    => (string) $row['reference'],
                'code'         => (string) $row['code'],
                'attendee'     => (string) ($row['attendee_name'] ?: trim((string) $row['buyer_name'] . ' ' . (string) $row['buyer_surname'])),
                'email'        => (string) $row['email'],
                'phone'        => (string) ($row['phone'] ?? ''),
                'type_name'    => (string) $row['type_name'],
                'status_label' => TicketService::statusLabel((string) $row['ticket_status']),
                'amount'       => Money::format((int) $row['price_cents']),
            ];
        }

        $pdf = (new RegistrationListPdf())->build(
            $formatted,
            $this->filterSummary($filters),
            ['Import total' => Money::format($sum)],
            (string) (\App\Core\Auth::user()['name'] ?? '')
        );

        Logger::audit('exporta_llistat_pdf', null, ['files' => count($formatted)]);
        Response::download($pdf, 'inscripcions-' . date('Ymd-Hi') . '.pdf', 'application/pdf');
    }

    public function csv(): void
    {
        $filters = $this->filters();
        [$where, $params] = $this->buildWhere($filters);
        $rows = Db::all($this->baseSelect() . ' WHERE ' . $where . ' ORDER BY o.`created_at` DESC, t.`id` ASC', $params);

        $handle = fopen('php://temp', 'r+');
        // BOM perquè l'Excel obri l'UTF-8 correctament.
        fwrite($handle, "\xEF\xBB\xBF");
        // L'escape buit produeix CSV conforme a l'RFC i evita l'avís de desús de PHP 8.4.
        fputcsv($handle, ['Data', 'Referència', 'Codi', 'Assistent', 'Comprador', 'Correu', 'Telèfon', 'Tipus', 'Estat entrada', 'Estat pagament', 'Import', 'Validada', 'Extres'], ';', '"', '');

        foreach ($rows as $row) {
            $extra = json_decode((string) ($row['extra_json'] ?? ''), true);
            $extraText = is_array($extra)
                ? implode(' | ', array_map(static fn ($k, $v) => $k . ': ' . (is_array($v) ? implode(',', $v) : $v), array_keys($extra), $extra))
                : '';

            fputcsv($handle, [
                date('d/m/Y H:i', strtotime((string) $row['created_at'])),
                $row['reference'],
                $row['code'],
                $row['attendee_name'] ?: trim((string) $row['buyer_name'] . ' ' . (string) $row['buyer_surname']),
                trim((string) $row['buyer_name'] . ' ' . (string) $row['buyer_surname']),
                $row['email'],
                $row['phone'] ?? '',
                $row['type_name'],
                TicketService::statusLabel((string) $row['ticket_status']),
                TicketService::statusLabel((string) $row['order_status']),
                Money::toDecimal((int) $row['price_cents']),
                $row['checked_in_at'] ? date('d/m/Y H:i', strtotime((string) $row['checked_in_at'])) : '',
                $extraText,
            ], ';', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Logger::audit('exporta_llistat_csv', null, ['files' => count($rows)]);
        Response::download($csv, 'inscripcions-' . date('Ymd-Hi') . '.csv', 'text/csv; charset=utf-8');
    }

    public function show(string $id): void
    {
        $order = $this->findOrder((int) $id);

        View::render('admin/registration_detail', [
            'title'    => 'Inscripció ' . $order['reference'],
            'order'    => $order,
            'tickets'  => Db::all(
                'SELECT t.*, tt.`name` AS type_name FROM `tickets` t
                 JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`
                 WHERE t.`order_id` = :id ORDER BY t.`id`',
                ['id' => $order['id']]
            ),
            'refunds'  => Db::all('SELECT * FROM `refunds` WHERE `order_id` = :id ORDER BY `id` DESC', ['id' => $order['id']]),
            'cancellation' => TicketService::cancellationStatus($order),
        ], 'layouts/admin');
    }

    public function cancel(string $id): void
    {
        $order = $this->findOrder((int) $id);
        $ticketIds = array_map('intval', Request::postArray('tickets'));

        try {
            $result = TicketService::cancelTickets($order, $ticketIds, 'panell');
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/admin/inscripcions/' . $order['id']));
        }

        $message = 'S\'han anul·lat ' . $result['cancelled'] . ' entrades.';
        if ($result['refunded_cents'] > 0) {
            $message .= ' Devolució tramitada: ' . Money::format($result['refunded_cents']) . '.';
        }
        if ($result['refund_error'] !== null) {
            Flash::warning('La devolució a Stripe ha fallat: ' . $result['refund_error']);
        }

        Logger::audit('anulla_inscripcio', (string) $order['reference'], $result);
        Flash::success($message);
        Response::redirect(Url::to('/admin/inscripcions/' . $order['id']));
    }

    public function resend(string $id): void
    {
        $order = $this->findOrder((int) $id);

        try {
            $sent = TicketService::sendConfirmationEmail($order);
            $sent
                ? Flash::success('S\'han reenviat les entrades a ' . $order['email'] . '.')
                : Flash::error('No s\'han pogut reenviar les entrades. Revisa la configuració SMTP.');
        } catch (\Throwable $e) {
            Logger::exception($e, 'Reenviament d\'entrades');
            Flash::error('No s\'han pogut reenviar les entrades: ' . $e->getMessage());
        }

        Logger::audit('reenvia_entrades', (string) $order['reference']);
        Response::redirect(Url::to('/admin/inscripcions/' . $order['id']));
    }

    public function ticketPdf(string $id): void
    {
        $order = $this->findOrder((int) $id);

        try {
            $pdf = TicketService::pdfForOrder($order);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect(Url::to('/admin/inscripcions/' . $order['id']));
        }

        Response::inline($pdf, TicketService::pdfFilename($order), 'application/pdf');
    }

    public function note(string $id): void
    {
        $order = $this->findOrder((int) $id);
        Db::update('orders', ['notes' => (string) Request::post('notes', '')], '`id` = :id', ['id' => $order['id']]);
        Flash::success('Nota desada.');
        Response::redirect(Url::to('/admin/inscripcions/' . $order['id']));
    }

    // ----------------------------------------------------------------- Filtres

    /** @return array<string, string> */
    /**
     * Esborrat definitiu de les entrades seleccionades al llistat.
     *
     * Es fa en dos temps: la primera crida mostra què s'esborrarà i la segona,
     * amb «confirmar», ho executa. No és el mateix que anul·lar: aquí no es
     * retorna cap diner ni queda rastre de l'entrada, i per això només hi
     * poden arribar els comptes de gestió.
     */
    public function destroy(): void
    {
        if (!Auth::is('owner', 'admin')) {
            Flash::error('Només els comptes de gestió poden esborrar inscripcions.');
            Response::redirect(Url::to('/admin/inscripcions'));
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', Request::postArray('tickets')),
            static fn (int $id): bool => $id > 0
        )));

        // Tornar al llistat amb els mateixos filtres que tenia obert.
        $retorn = Url::to('/admin/inscripcions');
        $filtres = array_filter($this->filters(), static fn ($v): bool => $v !== '');
        if ($filtres !== []) {
            $retorn .= '?' . http_build_query($filtres);
        }

        if ($ids === []) {
            Flash::warning('No has seleccionat cap entrada.');
            Response::redirect($retorn);
        }

        $rows = Db::all(
            $this->baseSelect() . ' WHERE t.`id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            . ' ORDER BY o.`created_at` DESC, t.`id` ASC',
            $ids
        );

        if ($rows === []) {
            Flash::warning('Les entrades seleccionades ja no hi són.');
            Response::redirect($retorn);
        }

        if ((string) Request::post('confirmar', '') !== '1') {
            View::render('admin/registrations_delete', [
                'title'  => 'Esborrar inscripcions',
                'rows'   => $rows,
                'ids'    => $ids,
                'retorn' => $retorn,
            ], 'layouts/admin');
            return;
        }

        $esborrades = Db::transaction(static function () use ($rows): array {
            $codis = [];
            $comandes = [];

            foreach ($rows as $row) {
                Db::run('DELETE FROM `tickets` WHERE `id` = :id', ['id' => (int) $row['ticket_id']]);
                $codis[] = (string) $row['code'];
                $comandes[(int) $row['order_id']] = (string) $row['reference'];
            }

            // Una comanda sense cap entrada ja no representa res: se'n va
            // també ella, i amb ella les devolucions que hi pengen.
            $buides = [];
            foreach ($comandes as $orderId => $referencia) {
                $queden = (int) Db::value('SELECT COUNT(*) FROM `tickets` WHERE `order_id` = :o', ['o' => $orderId], 0);
                if ($queden === 0) {
                    Db::run('DELETE FROM `orders` WHERE `id` = :o', ['o' => $orderId]);
                    $buides[] = $referencia;
                }
            }

            return ['codis' => $codis, 'comandes' => $buides];
        });

        Logger::audit('esborra_inscripcions', implode(', ', $esborrades['codis']), [
            'entrades' => count($esborrades['codis']),
            'comandes' => $esborrades['comandes'],
        ]);

        $missatge = count($esborrades['codis']) === 1
            ? 'S\'ha esborrat 1 entrada.'
            : 'S\'han esborrat ' . count($esborrades['codis']) . ' entrades.';

        if ($esborrades['comandes'] !== []) {
            $missatge .= ' ' . (count($esborrades['comandes']) === 1
                ? 'La inscripció ' . $esborrades['comandes'][0] . ' ha quedat sense entrades i també s\'ha esborrat.'
                : count($esborrades['comandes']) . ' inscripcions han quedat sense entrades i també s\'han esborrat.');
        }

        Flash::success($missatge);
        Response::redirect($retorn);
    }

    /**
     * Els filtres actius. Es llegeixen tant de la query com del cos de la
     * petició: l'esborrat múltiple arriba per POST i ha de poder tornar al
     * llistat tal com el tenia l'usuari.
     */
    private function filters(): array
    {
        $filtres = [];
        foreach (['cerca', 'tipus', 'estat', 'entrada', 'des_de', 'fins_a', 'validada'] as $nom) {
            $filtres[$nom] = trim((string) Request::input($nom, ''));
        }

        return $filtres;
    }

    private function baseSelect(): string
    {
        return 'SELECT t.`id` AS ticket_id, t.`code`, t.`attendee_name`, t.`price_cents`,
                       t.`status` AS ticket_status, t.`checked_in_at`, t.`extra_json`,
                       o.`id` AS order_id, o.`reference`, o.`email`, o.`phone`,
                       o.`name` AS buyer_name, o.`surname` AS buyer_surname,
                       o.`status` AS order_status, o.`created_at`, o.`paid_at`,
                       tt.`name` AS type_name
                FROM `tickets` t
                JOIN `orders` o ON o.`id` = t.`order_id`
                JOIN `ticket_types` tt ON tt.`id` = t.`ticket_type_id`';
    }

    /** @return array{0:string, 1:array} */
    private function buildWhere(array $filters): array
    {
        $conditions = ["o.`status` <> 'expired'"];
        $params = [];

        if ($filters['cerca'] !== '') {
            // Cada columna necessita el seu propi marcador: amb les consultes
            // preparades de debò (sense emulació) un nom no es pot repetir.
            $camps = ['o.`email`', 'o.`name`', 'o.`surname`', 'o.`reference`',
                      't.`code`', 't.`attendee_name`', 'o.`phone`'];
            $cerca = [];
            foreach ($camps as $i => $camp) {
                $cerca[] = $camp . ' LIKE :q' . $i;
                $params['q' . $i] = '%' . $filters['cerca'] . '%';
            }
            $conditions[] = '(' . implode(' OR ', $cerca) . ')';
        }
        if ($filters['tipus'] !== '') {
            $conditions[] = 't.`ticket_type_id` = :tipus';
            $params['tipus'] = (int) $filters['tipus'];
        }
        if ($filters['estat'] !== '') {
            $conditions[] = 'o.`status` = :estat';
            $params['estat'] = $filters['estat'];
        }
        if ($filters['entrada'] !== '') {
            $conditions[] = 't.`status` = :entrada';
            $params['entrada'] = $filters['entrada'];
        }
        if ($filters['des_de'] !== '') {
            $conditions[] = 'o.`created_at` >= :desde';
            $params['desde'] = $filters['des_de'] . ' 00:00:00';
        }
        if ($filters['fins_a'] !== '') {
            $conditions[] = 'o.`created_at` <= :finsa';
            $params['finsa'] = $filters['fins_a'] . ' 23:59:59';
        }
        if ($filters['validada'] === 'si') {
            $conditions[] = 't.`checked_in_at` IS NOT NULL';
        } elseif ($filters['validada'] === 'no') {
            $conditions[] = 't.`checked_in_at` IS NULL';
        }

        return [implode(' AND ', $conditions), $params];
    }

    /** @return array<string, string> Resum llegible per al PDF. */
    private function filterSummary(array $filters): array
    {
        $labels = [
            'cerca'    => 'Cerca',
            'tipus'    => 'Tipus',
            'estat'    => 'Estat del pagament',
            'entrada'  => 'Estat de l\'entrada',
            'des_de'   => 'Des del',
            'fins_a'   => 'Fins al',
            'validada' => 'Validada',
        ];

        $summary = [];
        foreach ($filters as $key => $value) {
            if ($value === '') {
                continue;
            }
            if ($key === 'tipus') {
                $value = (string) Db::value('SELECT `name` FROM `ticket_types` WHERE `id` = :id', ['id' => (int) $value], $value);
            } elseif ($key === 'estat' || $key === 'entrada') {
                $value = TicketService::statusLabel($value);
            } elseif ($key === 'des_de' || $key === 'fins_a') {
                $value = date('d/m/Y', strtotime($value));
            }
            $summary[$labels[$key]] = $value;
        }

        return $summary ?: ['Filtres' => 'cap'];
    }

    private function findOrder(int $id): array
    {
        $order = Db::first('SELECT * FROM `orders` WHERE `id` = :id', ['id' => $id]);
        if ($order === null) {
            Flash::error('No hem trobat aquesta inscripció.');
            Response::redirect(Url::to('/admin/inscripcions'));
        }
        return $order;
    }
}
