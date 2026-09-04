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
        [$order, $ticket, $type] = $this->context($reference);

        try {
            $pass = (new AppleWallet())->build($order, $ticket, $type);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Apple Wallet');
            Flash::error('No s\'ha pogut generar el passi per a l\'Apple Wallet: ' . $e->getMessage());
            Response::redirect($this->back($order));
        }

        Response::download($pass, 'entrada-' . strtolower((string) $ticket['code']) . '.pkpass', 'application/vnd.apple.pkpass');
    }

    public function google(string $reference): void
    {
        [$order, $ticket, $type] = $this->context($reference);

        try {
            $url = (new GoogleWallet())->saveUrl($order, $ticket, $type);
        } catch (\Throwable $e) {
            Logger::exception($e, 'Google Wallet');
            Flash::error('No s\'ha pogut generar el passi per al Google Wallet: ' . $e->getMessage());
            Response::redirect($this->back($order));
        }

        Response::redirect($url);
    }

    /** @return array{0:array,1:array,2:array} */
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
        $ticket = $ticketId > 0
            ? Db::first("SELECT * FROM `tickets` WHERE `id` = :id AND `order_id` = :oid AND `status` IN ('valid','used')", ['id' => $ticketId, 'oid' => $order['id']])
            : Db::first("SELECT * FROM `tickets` WHERE `order_id` = :oid AND `status` IN ('valid','used') ORDER BY `id` LIMIT 1", ['oid' => $order['id']]);

        if ($ticket === null) {
            Flash::error('No hem trobat aquesta entrada.');
            Response::redirect($this->back($order));
        }

        $types = TicketService::ticketTypesById();
        return [$order, $ticket, $types[(int) $ticket['ticket_type_id']] ?? []];
    }

    private function back(array $order): string
    {
        return Url::to('/comanda/' . $order['reference']) . '?t=' . $order['manage_token'];
    }
}
