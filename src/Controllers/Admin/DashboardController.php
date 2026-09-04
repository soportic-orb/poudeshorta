<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Settings;
use App\Core\View;
use App\Services\AppleWallet;
use App\Services\GoogleWallet;
use App\Services\Mailer;
use App\Services\MailQueue;
use App\Services\TicketService;
use App\Services\Updater;

final class DashboardController
{
    public function index(): void
    {
        View::render('admin/dashboard', [
            'title'      => 'Resum',
            'stats'      => self::stats(),
            'byType'     => self::salesByType(),
            'recent'     => self::recentOrders(),
            'daily'      => self::dailySales(),
            'checklist'  => self::checklist(),
            'pendingMail' => MailQueue::pendingCount(),
        ], 'layouts/admin');
    }

    /** @return array<string, mixed> */
    public static function stats(): array
    {
        $paidOrders = (int) Db::value("SELECT COUNT(*) FROM `orders` WHERE `status` IN ('paid','partially_refunded')", [], 0);
        $tickets    = (int) Db::value("SELECT COUNT(*) FROM `tickets` t JOIN `orders` o ON o.`id` = t.`order_id`
                                        WHERE t.`status` IN ('valid','used') AND o.`status` IN ('paid','partially_refunded')", [], 0);
        $revenue    = (int) Db::value("SELECT COALESCE(SUM(`total_cents` - `refunded_cents`), 0) FROM `orders`
                                        WHERE `status` IN ('paid','partially_refunded')", [], 0);
        $refunded   = (int) Db::value('SELECT COALESCE(SUM(`refunded_cents`), 0) FROM `orders`', [], 0);
        $pending    = (int) Db::value("SELECT COUNT(*) FROM `orders` WHERE `status` = 'pending'", [], 0);
        $cancelled  = (int) Db::value("SELECT COUNT(*) FROM `tickets` WHERE `status` IN ('cancelled','refunded')", [], 0);
        $checkedIn  = (int) Db::value("SELECT COUNT(*) FROM `tickets` WHERE `status` = 'used'", [], 0);
        $last7      = (int) Db::value("SELECT COUNT(*) FROM `orders` WHERE `status` IN ('paid','partially_refunded')
                                        AND `paid_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 0);

        return compact('paidOrders', 'tickets', 'revenue', 'refunded', 'pending', 'cancelled', 'checkedIn', 'last7');
    }

    /** @return array<int, array> */
    public static function salesByType(): array
    {
        return Db::all(
            "SELECT tt.`id`, tt.`name`, tt.`quota`, tt.`price_cents`,
                    COUNT(t.`id`) AS sold,
                    COALESCE(SUM(t.`price_cents`), 0) AS revenue
             FROM `ticket_types` tt
             LEFT JOIN `tickets` t ON t.`ticket_type_id` = tt.`id`
                  AND t.`status` IN ('valid','used')
                  AND t.`order_id` IN (SELECT `id` FROM `orders` WHERE `status` IN ('paid','partially_refunded'))
             GROUP BY tt.`id`, tt.`name`, tt.`quota`, tt.`price_cents`
             ORDER BY tt.`sort_order`, tt.`id`"
        );
    }

    public static function recentOrders(int $limit = 8): array
    {
        return Db::all(
            "SELECT o.*, (SELECT COUNT(*) FROM `tickets` t WHERE t.`order_id` = o.`id`) AS ticket_count
             FROM `orders` o
             WHERE o.`status` <> 'expired'
             ORDER BY o.`created_at` DESC
             LIMIT " . max(1, $limit)
        );
    }

    /** Vendes dels darrers 14 dies, per al gràfic del resum. */
    public static function dailySales(): array
    {
        $rows = Db::all(
            "SELECT DATE(`paid_at`) AS dia, COUNT(*) AS comandes,
                    COALESCE(SUM(`total_cents`), 0) AS import
             FROM `orders`
             WHERE `status` IN ('paid','partially_refunded') AND `paid_at` > DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(`paid_at`) ORDER BY dia"
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['dia']] = $row;
        }

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $series[] = [
                'dia'      => $day,
                'etiqueta' => date('d/m', strtotime($day)),
                'comandes' => (int) ($indexed[$day]['comandes'] ?? 0),
                'import'   => (int) ($indexed[$day]['import'] ?? 0),
            ];
        }
        return $series;
    }

    /** Estat de configuració, per avisar del que falta abans d'obrir les inscripcions. */
    public static function checklist(): array
    {
        $types = (int) Db::value('SELECT COUNT(*) FROM `ticket_types` WHERE `active` = 1', [], 0);

        return [
            [
                'label' => 'Dades de l\'esdeveniment',
                'ok'    => trim((string) Settings::get('event_name')) !== '' && trim((string) Settings::get('event_date_text')) !== '',
                'url'   => '/admin/configuracio',
                'hint'  => 'Nom, data i lloc de l\'esdeveniment.',
            ],
            [
                'label' => 'Tipus d\'inscripció',
                'ok'    => $types > 0,
                'url'   => '/admin/tipus-inscripcio',
                'hint'  => $types > 0 ? $types . ' tipus actius' : 'Encara no n\'hi ha cap d\'actiu.',
            ],
            [
                'label' => 'Pagaments amb Stripe',
                'ok'    => Settings::stripeConfigured(),
                'url'   => '/admin/configuracio/pagaments',
                'hint'  => Settings::stripeConfigured()
                    ? 'Mode ' . (Settings::get('stripe_mode') === 'live' ? 'real' : 'de proves')
                    : 'Falten les claus de Stripe.',
            ],
            [
                'label' => 'Webhook de Stripe',
                'ok'    => Settings::stripeWebhookSecret() !== '',
                'url'   => '/admin/configuracio/pagaments',
                'hint'  => 'Necessari per confirmar els pagaments de forma fiable.',
            ],
            [
                'label' => 'Servidor de correu (SMTP)',
                'ok'    => (new Mailer())->isConfigured(),
                'url'   => '/admin/configuracio/correu',
                'hint'  => 'Per enviar les entrades i els comunicats.',
            ],
            [
                'label' => 'Passis de wallet (opcional)',
                'ok'    => AppleWallet::isConfigured() || GoogleWallet::isConfigured(),
                'url'   => '/admin/configuracio/wallet',
                'hint'  => 'Apple Wallet: ' . AppleWallet::configurationHint() . ' · Google Wallet: ' . GoogleWallet::configurationHint(),
                'optional' => true,
            ],
            [
                'label' => 'Inscripcions obertes',
                'ok'    => TicketService::salesOpen(),
                'url'   => '/admin/configuracio',
                'hint'  => TicketService::salesOpen() ? 'El públic ja pot inscriure\'s.' : 'Les inscripcions estan tancades.',
            ],
        ];
    }

    public static function updateBadge(): array
    {
        return [
            'current'   => Updater::currentVersion(),
            'latest'    => (string) Settings::get('ota_latest_version'),
            'checked'   => (string) Settings::get('ota_last_check'),
        ];
    }
}
