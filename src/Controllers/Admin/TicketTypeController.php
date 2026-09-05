<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Money;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Services\TicketService;

final class TicketTypeController
{
    public function index(): void
    {
        $types = Db::all('SELECT * FROM `ticket_types` ORDER BY `sort_order`, `id`');
        foreach ($types as $i => $type) {
            $types[$i]['sold'] = TicketService::soldCount((int) $type['id']);
            $types[$i]['remaining'] = TicketService::remaining((int) $type['id']);
        }

        View::render('admin/ticket_types', [
            'title' => 'Tipus d\'inscripció',
            'types' => $types,
        ], 'layouts/admin');
    }

    public function create(): void
    {
        View::render('admin/ticket_type_form', [
            'title'  => 'Nou tipus d\'inscripció',
            'type'   => $this->blank(),
            'fields' => [],
            'errors' => \App\Core\Flash::errors(),
        ], 'layouts/admin');
        Flash::clearOld();
    }

    public function store(): void
    {
        $data = $this->validated();
        if ($data === null) {
            $this->create();
            return;
        }

        $id = Db::insert('ticket_types', $data);
        $this->saveFields($id);

        Logger::audit('crea_tipus_inscripcio', (string) $id, ['nom' => $data['name']]);
        Flash::success('S\'ha creat el tipus d\'inscripció «' . $data['name'] . '».');
        Response::redirect(Url::to('/admin/tipus-inscripcio'));
    }

    public function edit(string $id): void
    {
        $type = $this->find((int) $id);

        View::render('admin/ticket_type_form', [
            'title'  => 'Editar · ' . $type['name'],
            'type'   => $type,
            'fields' => Db::all('SELECT * FROM `form_fields` WHERE `ticket_type_id` = :id ORDER BY `sort_order`, `id`', ['id' => $type['id']]),
            'sold'   => TicketService::soldCount((int) $type['id']),
            'errors' => Flash::errors(),
        ], 'layouts/admin');
        Flash::clearOld();
    }

    public function update(string $id): void
    {
        $type = $this->find((int) $id);
        $data = $this->validated();
        if ($data === null) {
            $this->edit($id);
            return;
        }

        Db::update('ticket_types', $data, '`id` = :id', ['id' => $type['id']]);
        $this->saveFields((int) $type['id']);

        Logger::audit('edita_tipus_inscripcio', (string) $type['id'], ['nom' => $data['name']]);
        Flash::success('Canvis desats.');
        Response::redirect(Url::to('/admin/tipus-inscripcio'));
    }

    public function toggle(string $id): void
    {
        $type = $this->find((int) $id);
        $active = (int) $type['active'] === 1 ? 0 : 1;
        Db::update('ticket_types', ['active' => $active], '`id` = :id', ['id' => $type['id']]);

        Flash::success($active === 1
            ? 'S\'ha activat «' . $type['name'] . '».'
            : 'S\'ha desactivat «' . $type['name'] . '»; ja no apareixerà al web públic.');
        Response::redirect(Url::to('/admin/tipus-inscripcio'));
    }

    public function destroy(string $id): void
    {
        $type = $this->find((int) $id);
        $sold = (int) Db::value('SELECT COUNT(*) FROM `tickets` WHERE `ticket_type_id` = :id', ['id' => $type['id']], 0);

        if ($sold > 0) {
            Flash::error('No es pot eliminar «' . $type['name'] . '» perquè ja té ' . $sold . ' entrades. Desactiva\'l en comptes d\'eliminar-lo.');
            Response::redirect(Url::to('/admin/tipus-inscripcio'));
        }

        Db::run('DELETE FROM `ticket_types` WHERE `id` = :id', ['id' => $type['id']]);
        Logger::audit('elimina_tipus_inscripcio', (string) $type['id'], ['nom' => $type['name']]);
        Flash::success('S\'ha eliminat «' . $type['name'] . '».');
        Response::redirect(Url::to('/admin/tipus-inscripcio'));
    }

    // ----------------------------------------------------------------- Ajudants

    private function blank(): array
    {
        return [
            'id' => 0, 'name' => '', 'description' => '', 'includes' => '',
            'price_cents' => 0, 'quota' => null, 'min_per_order' => 0, 'max_per_order' => 10,
            'sales_start' => null, 'sales_end' => null, 'requires_attendee_name' => 1,
            'active' => 1, 'sort_order' => (int) Db::value('SELECT COALESCE(MAX(`sort_order`), 0) + 1 FROM `ticket_types`', [], 1),
        ];
    }

    private function find(int $id): array
    {
        $type = Db::first('SELECT * FROM `ticket_types` WHERE `id` = :id', ['id' => $id]);
        if ($type === null) {
            Flash::error('No hem trobat aquest tipus d\'inscripció.');
            Response::redirect(Url::to('/admin/tipus-inscripcio'));
        }
        return $type;
    }

    /** @return array<string, mixed>|null */
    private function validated(): ?array
    {
        $validator = Validator::make($_POST)
            ->required('name', 'Cal indicar el nom del tipus d\'inscripció.')
            ->maxLen('name', 160, 'El nom és massa llarg.')
            ->check('price', is_numeric(str_replace(',', '.', (string) Request::post('price', '0'))), 'El preu ha de ser un número.');

        $quota = trim((string) Request::post('quota', ''));
        if ($quota !== '') {
            $validator->check('quota', ctype_digit($quota), 'Les places han de ser un número enter.');
        }

        if ($validator->fails()) {
            Flash::setErrors($validator->errors());
            Flash::setOld($_POST);
            Flash::error('Revisa les dades del formulari.');
            return null;
        }

        return [
            'name'                   => (string) Request::post('name'),
            'description'            => (string) Request::post('description', ''),
            'includes'               => (string) Request::post('includes', ''),
            'price_cents'            => Money::toCents((string) Request::post('price', '0')),
            'quota'                  => $quota === '' ? null : (int) $quota,
            'min_per_order'          => max(0, (int) Request::post('min_per_order', 0)),
            'max_per_order'          => max(1, (int) Request::post('max_per_order', 10)),
            'sales_start'            => $this->dateOrNull((string) Request::post('sales_start', '')),
            'sales_end'              => $this->dateOrNull((string) Request::post('sales_end', '')),
            'requires_attendee_name' => Request::post('requires_attendee_name') ? 1 : 0,
            'active'                 => Request::post('active') ? 1 : 0,
            'sort_order'             => (int) Request::post('sort_order', 0),
        ];
    }

    private function dateOrNull(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    /** Desa els camps addicionals del formulari d'inscripció d'aquest tipus. */
    private function saveFields(int $typeId): void
    {
        $labels   = Request::postArray('field_label');
        $types    = Request::postArray('field_type');
        $options  = Request::postArray('field_options');
        $required = Request::postArray('field_required');
        $ids      = Request::postArray('field_id');

        $kept = [];
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $data = [
                'ticket_type_id' => $typeId,
                'label'          => $label,
                'slug'           => Str::slug($label, '_') ?: 'camp_' . ($index + 1),
                'type'           => in_array((string) ($types[$index] ?? 'text'), ['text', 'number', 'select', 'checkbox', 'textarea'], true)
                    ? (string) $types[$index]
                    : 'text',
                'options'        => trim((string) ($options[$index] ?? '')) ?: null,
                'required'       => !empty($required[$index]) ? 1 : 0,
                'sort_order'     => $index,
                'active'         => 1,
            ];

            $existingId = (int) ($ids[$index] ?? 0);
            if ($existingId > 0) {
                Db::update('form_fields', $data, '`id` = :id AND `ticket_type_id` = :tt', ['id' => $existingId, 'tt' => $typeId]);
                $kept[] = $existingId;
            } else {
                $kept[] = Db::insert('form_fields', $data);
            }
        }

        // Els camps que ja no apareixen al formulari s'eliminen.
        if ($kept === []) {
            Db::run('DELETE FROM `form_fields` WHERE `ticket_type_id` = :tt', ['tt' => $typeId]);
            return;
        }

        $placeholders = [];
        $params = ['tt' => $typeId];
        foreach (array_values($kept) as $i => $id) {
            $placeholders[] = ':k' . $i;
            $params['k' . $i] = $id;
        }
        Db::run(
            'DELETE FROM `form_fields` WHERE `ticket_type_id` = :tt AND `id` NOT IN (' . implode(',', $placeholders) . ')',
            $params
        );
    }
}
