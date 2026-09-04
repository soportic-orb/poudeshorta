<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Db;
use App\Core\Flash;
use App\Core\Settings;
use App\Core\View;
use App\Services\TicketService;

final class HomeController
{
    public function index(): void
    {
        $types = TicketService::publicTicketTypes();

        View::render('web/home', [
            'title'       => (string) Settings::get('event_name'),
            'types'       => $types,
            'salesOpen'   => Settings::bool('sales_open', true),
            'anyOnSale'   => array_reduce($types, static fn ($carry, $t) => $carry || $t['on_sale'], false),
            'highlights'  => self::highlights(),
            'errors'      => Flash::errors(),
        ], 'layouts/public');
        Flash::clearOld();
    }

    public function info(): void
    {
        View::render('web/info', [
            'title'      => 'Informació de l\'esdeveniment',
            'highlights' => self::highlights(),
            'policy'     => (string) Settings::get('cancellation_policy_text'),
            'privacy'    => (string) Settings::get('privacy_text'),
        ], 'layouts/public');
    }

    /** @return string[] */
    public static function highlights(): array
    {
        $raw = (string) Settings::get('event_highlights');
        $lines = preg_split('/\R/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $lines)));
    }

    /** @var array<string, array>|null Memòria cau dels camps durant la petició. */
    private static ?array $fieldCache = null;

    /** Camps addicionals del formulari, agrupats per tipus d'inscripció. */
    public static function formFields(): array
    {
        if (self::$fieldCache !== null) {
            return self::$fieldCache;
        }

        $rows = Db::all('SELECT * FROM `form_fields` WHERE `active` = 1 ORDER BY `sort_order`, `id`');
        $grouped = ['*' => []];
        foreach ($rows as $row) {
            $key = $row['ticket_type_id'] === null ? '*' : (string) (int) $row['ticket_type_id'];
            $grouped[$key][] = $row;
        }

        self::$fieldCache = $grouped;
        return $grouped;
    }

    /** @return array<int, array> Camps aplicables a un tipus concret. */
    public static function fieldsForType(int $typeId): array
    {
        $grouped = self::formFields();
        return array_merge($grouped['*'] ?? [], $grouped[(string) $typeId] ?? []);
    }
}
