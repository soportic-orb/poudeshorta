<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Core\RateLimit;
use App\Core\Settings;

/** Tasques de manteniment periòdiques (cron o crida HTTP protegida). */
final class Tasks
{
    /** @return array<string, mixed> */
    public static function runAll(): array
    {
        $result = [];

        $result['correus'] = MailQueue::process();
        $result['comandes_caducades'] = TicketService::expireStalePendingOrders();
        $result['enllacos_purgats'] = self::purgeAccessLinks();
        $result['fitxers_temporals'] = self::cleanTemp();

        RateLimit::gc();

        if (Settings::bool('ota_auto_check', true)) {
            try {
                $update = Updater::check();
                $result['actualitzacio'] = [
                    'disponible' => $update['available'],
                    'versio'     => $update['version'],
                ];
            } catch (\Throwable $e) {
                $result['actualitzacio'] = ['error' => $e->getMessage()];
            }
        }

        Settings::set('last_cron_run', date('Y-m-d H:i:s'));
        Logger::info('Tasques programades executades', $result);

        return array_merge(['ok' => true, 'moment' => date('c')], $result);
    }

    private static function purgeAccessLinks(): int
    {
        return Db::run('DELETE FROM `access_links` WHERE `expires_at` < DATE_SUB(NOW(), INTERVAL 2 DAY)')->rowCount();
    }

    /** Esborra fitxers temporals abandonats (QR, passis, paquets d'actualització). */
    private static function cleanTemp(): int
    {
        $dir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        $cutoff = time() - 3600;

        foreach (glob($dir . '/*') ?: [] as $path) {
            if (@filemtime($path) > $cutoff) {
                continue;
            }
            if (is_dir($path)) {
                Updater::removeTree($path);
            } else {
                @unlink($path);
            }
            $removed++;
        }

        return $removed;
    }
}
