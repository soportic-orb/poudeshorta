<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Response;
use App\Core\Settings;
use App\Services\Tasks;

/**
 * Permet executar les tasques programades per HTTP quan el servidor no
 * disposa d'accés a cron. Es protegeix amb un testimoni secret.
 */
final class CronController
{
    public function run(string $token): void
    {
        $expected = trim((string) Settings::get('cron_token', ''));

        if ($expected === '' || !hash_equals($expected, $token)) {
            Response::json(['error' => 'unauthorized'], 403);
        }

        Response::json(Tasks::runAll());
    }
}
