<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\AppleWallet;
use App\Services\GoogleWallet;
use App\Services\TicketService;

final class WalletController
{
    public function apple(string $reference): void
    {
        [$order, $tickets, $types] = $this->context($reference);

        try {
            [$contingut, $nom, $mime] = (new AppleWallet())->buildAll($order, $tickets, $types);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Apple Wallet');
            Flash::error('No s\'ha pogut generar el passi per a l\'Apple Wallet: ' . $e->getMessage());
            Response::redirect($this->back($order));
        }

        Response::download($contingut, $nom, $mime);
    }

    public function google(string $reference): void
    {
        [$order, $tickets, $types] = $this->context($reference);

        try {
            $url = (new GoogleWallet())->saveUrl($order, $tickets, $types);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Google Wallet');
            Flash::error('No s\'ha pogut generar el passi per al Google Wallet: ' . $e->getMessage());
            Response::redirect($this->back($order));
        }

        Response::redirect($url);
    }

    /**
     * Les entrades de la comanda: totes, o només una si l'enllaç ho demana
     * (des del llistat d'entrades se'n pot afegir una de sola).
     *
     * @return array{0:array,1:array<int,array>,2:array}
     */
    private function context(string $reference): array
    {
        $order = Db::first('SELECT * FROM `orders` WHERE `reference` = :r', ['r' => $reference]);
        $token = (string) Request::get('t', '');

        if ($order === null || !hash_equals((string) $order['manage_token'], $token)) {
            http_response_code(403);
            \App\Core\View::render('web/error', [
                'title'   => 'Enllaç no vàlid',
                'code'    => 403,
                'message' => 'Aquest enllaç no és vàlid o ha caducat.',
            ], 'layouts/public');
            exit;
        }

        $ticketId = (int) Request::get('entrada', 0);

        $tickets = $ticketId > 0
            ? Db::all(
                "SELECT * FROM `tickets` WHERE `id` = :id AND `order_id` = :oid AND `status` IN ('valid','used')",
                ['id' => $ticketId, 'oid' => $order['id']]
            )
            : Db::all(
                "SELECT * FROM `tickets` WHERE `order_id` = :oid AND `status` IN ('valid','used') ORDER BY `id`",
                ['oid' => $order['id']]
            );

        if ($tickets === []) {
            Flash::error('No hem trobat cap entrada per afegir al wallet.');
            Response::redirect($this->back($order));
        }

        return [$order, $tickets, TicketService::ticketTypesById()];
    }

    private function back(array $order): string
    {
        return Url::to('/comanda/' . $order['reference']) . '?t=' . $order['manage_token'];
    }
}
