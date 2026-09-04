#!/usr/bin/env php
<?php
declare(strict_types=1);

// Tasques programades de la plataforma d'inscripcions.
//
// Afegiu aquesta línia al cron de CloudPanel (amb l'usuari del lloc), cada 5 minuts:
//   */5 * * * * /usr/bin/php /home/USUARI/htdocs/poudeshorta.cat/bin/cron.php >> /home/USUARI/htdocs/poudeshorta.cat/storage/logs/cron.log 2>&1

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Aquest script només es pot executar des de la línia d'ordres.\n");
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Config;
use App\Services\Tasks;

if (!Config::isInstalled()) {
    fwrite(STDERR, "La plataforma encara no està instal·lada.\n");
    exit(1);
}

$result = Tasks::runAll();

echo '[' . date('Y-m-d H:i:s') . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
