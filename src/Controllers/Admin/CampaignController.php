<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Services\Mailer;
use App\Services\MailQueue;

/**
 * Comunicats per correu als inscrits.
 * El missatge s'escriu en text pla amb marcadors i s'encua per lots.
 */
final class CampaignController
{
    public function index(): void
    {
        View::render('admin/campaigns', [
            'title'      => 'Comunicats',
            'campaigns'  => Db::all('SELECT * FROM `campaigns` ORDER BY `id` DESC LIMIT 50'),
            'pending'    => MailQueue::pendingCount(),
            'smtpReady'  => (new Mailer())->isConfigured(),
        ], 'layouts/admin');
    }

    public function create(): void
    {
        View::render('admin/campaign_form', [
            'title'      => 'Nou comunicat',
            'recipients' => $this->recipientOptions(),
            'errors'     => Flash::errors(),
            'smtpReady'  => (new Mailer())->isConfigured(),
        ], 'layouts/admin');
        Flash::clearOld();
    }

    public function store(): void
    {
        $validator = Validator::make($_POST)
            ->required('subject', 'Cal indicar l\'assumpte del correu.')
            ->maxLen('subject', 200, 'L\'assumpte és massa llarg.')
            ->required('body', 'Cal escriure el missatge.');

        if ($validator->fails()) {
            Flash::setErrors($validator->errors());
            Flash::setOld($_POST);
            Flash::error('Revisa les dades del formulari.');
            $this->create();
            return;
        }

        $filters = [
            'audiencia' => (string) Request::post('audiencia', 'paid'),
            'tipus'     => (string) Request::post('tipus', ''),
        ];

        $id = Db::insert('campaigns', [
            'subject'      => (string) Request::post('subject'),
            'body'         => (string) Request::post('body'),
            'filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'status'       => 'draft',
            'total'        => count($this->recipients($filters)),
            'created_by'   => (string) (Auth::user()['name'] ?? ''),
        ]);

        Logger::audit('crea_comunicat', (string) $id);
        Flash::success('Comunicat desat com a esborrany. Revisa\'l i envia\'l quan vulguis.');
        Response::redirect(Url::to('/admin/comunicacions/' . $id));
    }

    public function show(string $id): void
    {
        $campaign = $this->find((int) $id);
        $filters = json_decode((string) $campaign['filters_json'], true) ?: [];

        View::render('admin/campaign_detail', [
            'title'      => 'Comunicat · ' . Str::limit((string) $campaign['subject'], 40),
            'campaign'   => $campaign,
            'filters'    => $filters,
            'recipients' => $campaign['status'] === 'draft' ? $this->recipients($filters) : [],
            'preview'    => $this->renderBody($campaign, ['name' => 'Marta', 'reference' => 'PDSH-XXXXXX']),
            'queue'      => Db::first(
                "SELECT COUNT(*) AS total,
                        SUM(`status` = 'sent') AS enviats,
                        SUM(`status` = 'failed') AS fallits,
                        SUM(`status` IN ('pending','sending')) AS pendents
                 FROM `email_queue` WHERE `campaign_id` = :id",
                ['id' => $campaign['id']]
            ),
            'smtpReady'  => (new Mailer())->isConfigured(),
        ], 'layouts/admin');
    }

    /** Envia una còpia de prova a l'administrador. */
    public function test(string $id): void
    {
        $campaign = $this->find((int) $id);
        $to = trim((string) Request::post('email', '')) ?: (string) (Auth::user()['email'] ?? '');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Flash::error('Indica una adreça electrònica vàlida per a la prova.');
            Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
        }

        $mailer = new Mailer();
        $sent = $mailer->send(
            $to,
            null,
            '[PROVA] ' . $campaign['subject'],
            $this->renderBody($campaign, ['name' => 'Marta', 'reference' => 'PDSH-XXXXXX'])
        );

        $sent
            ? Flash::success('Correu de prova enviat a ' . $to . '.')
            : Flash::error('No s\'ha pogut enviar la prova: ' . ($mailer->lastError() ?? 'error desconegut'));

        Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
    }

    /** Encua el comunicat per a tots els destinataris del filtre. */
    public function send(string $id): void
    {
        $campaign = $this->find((int) $id);

        if ($campaign['status'] !== 'draft') {
            Flash::error('Aquest comunicat ja s\'ha enviat.');
            Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
        }
        if (!(new Mailer())->isConfigured()) {
            Flash::error('Cal configurar el servidor SMTP abans d\'enviar comunicats.');
            Response::redirect(Url::to('/admin/configuracio/correu'));
        }

        $filters = json_decode((string) $campaign['filters_json'], true) ?: [];
        $recipients = $this->recipients($filters);

        if ($recipients === []) {
            Flash::error('No hi ha cap destinatari que coincideixi amb el filtre triat.');
            Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
        }

        foreach ($recipients as $recipient) {
            MailQueue::push(
                (string) $recipient['email'],
                trim((string) $recipient['name'] . ' ' . (string) $recipient['surname']),
                Str::template((string) $campaign['subject'], $this->varsFor($recipient)),
                $this->renderBody($campaign, $this->varsFor($recipient)),
                (int) $campaign['id']
            );
        }

        Db::update('campaigns', [
            'status'     => 'queued',
            'total'      => count($recipients),
            'started_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $campaign['id']]);

        Logger::audit('envia_comunicat', (string) $campaign['id'], ['destinataris' => count($recipients)]);
        Flash::success('S\'han encuat ' . count($recipients) . ' correus. S\'aniran enviant per lots.');

        // Primer lot immediat perquè es vegi que funciona.
        MailQueue::process(min(10, Settings::int('smtp_batch_size', 25)));

        Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
    }

    /** Processa manualment un lot de la cua (útil si no hi ha cron). */
    public function process(string $id): void
    {
        $campaign = $this->find((int) $id);
        $result = MailQueue::process();

        Flash::success('Lot processat: ' . $result['sent'] . ' enviats, ' . $result['failed'] . ' fallits, ' . $result['remaining'] . ' pendents.');
        Response::redirect(Url::to('/admin/comunicacions/' . $campaign['id']));
    }

    public function destroy(string $id): void
    {
        $campaign = $this->find((int) $id);

        if (in_array((string) $campaign['status'], ['queued', 'sending'], true)) {
            Db::run("DELETE FROM `email_queue` WHERE `campaign_id` = :id AND `status` = 'pending'", ['id' => $campaign['id']]);
        }

        Db::run('DELETE FROM `campaigns` WHERE `id` = :id', ['id' => $campaign['id']]);
        Logger::audit('elimina_comunicat', (string) $campaign['id']);
        Flash::success('Comunicat eliminat.');
        Response::redirect(Url::to('/admin/comunicacions'));
    }

    // ----------------------------------------------------------------- Ajudants

    private function find(int $id): array
    {
        $campaign = Db::first('SELECT * FROM `campaigns` WHERE `id` = :id', ['id' => $id]);
        if ($campaign === null) {
            Flash::error('No hem trobat aquest comunicat.');
            Response::redirect(Url::to('/admin/comunicacions'));
        }
        return $campaign;
    }

    /** @return array<int, array{email:string, name:string, surname:string, reference:string}> */
    private function recipients(array $filters): array
    {
        $audience = (string) ($filters['audiencia'] ?? 'paid');
        $typeId = (int) ($filters['tipus'] ?? 0);

        $conditions = [];
        $params = [];

        $conditions[] = match ($audience) {
            'all'       => "o.`status` IN ('paid','partially_refunded','refunded','cancelled')",
            'cancelled' => "o.`status` IN ('refunded','cancelled')",
            'pending'   => "o.`status` = 'pending'",
            default     => "o.`status` IN ('paid','partially_refunded')",
        };

        $join = '';
        if ($typeId > 0) {
            $join = ' JOIN `tickets` t ON t.`order_id` = o.`id`';
            $conditions[] = 't.`ticket_type_id` = :tipus';
            $params['tipus'] = $typeId;
        }

        // Una sola adreça per persona, encara que tingui diverses inscripcions.
        return Db::all(
            'SELECT o.`email`, MIN(o.`name`) AS name, MIN(o.`surname`) AS surname, MIN(o.`reference`) AS reference
             FROM `orders` o' . $join . '
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY o.`email`
             ORDER BY o.`email`',
            $params
        );
    }

    /** @return array<int, array{value:string, label:string}> */
    private function recipientOptions(): array
    {
        return [
            ['value' => 'paid',      'label' => 'Inscrits amb el pagament confirmat'],
            ['value' => 'all',       'label' => 'Totes les inscripcions (incloses les anul·lades)'],
            ['value' => 'cancelled', 'label' => 'Només inscripcions anul·lades o retornades'],
            ['value' => 'pending',   'label' => 'Comandes pendents de pagament'],
        ];
    }

    private function varsFor(array $recipient): array
    {
        return [
            'name'            => (string) $recipient['name'],
            'surname'         => (string) $recipient['surname'],
            'email'           => (string) $recipient['email'],
            'reference'       => (string) $recipient['reference'],
            'event_name'      => (string) Settings::get('event_name'),
            'event_date'      => (string) Settings::get('event_date_text'),
            'event_location'  => (string) Settings::get('event_location'),
            'event_organizer' => (string) Settings::get('event_organizer'),
        ];
    }

    private function renderBody(array $campaign, array $vars): string
    {
        $text = Str::template((string) $campaign['body'], $vars);
        return (new Mailer())->wrap((string) $campaign['subject'], Mailer::textToHtml($text));
    }

    /** Marcadors disponibles, per mostrar-los a l'editor. */
    public static function placeholders(): array
    {
        return [
            '{{name}}'            => 'Nom de la persona',
            '{{surname}}'         => 'Cognoms',
            '{{email}}'           => 'Adreça electrònica',
            '{{reference}}'       => 'Referència de la inscripció',
            '{{event_name}}'      => 'Nom de l\'esdeveniment',
            '{{event_date}}'      => 'Data de l\'esdeveniment',
            '{{event_location}}'  => 'Lloc',
            '{{event_organizer}}' => 'Organitzador',
        ];
    }
}
