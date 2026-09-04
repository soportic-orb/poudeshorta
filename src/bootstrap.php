<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Logger;
use App\Core\Settings;

define('APP_ROOT', dirname(__DIR__));
define('APP_START', microtime(true));

require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/src/helpers.php';

Config::load(APP_ROOT . '/config/config.php');

$debug = (bool) Config::get('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

mb_internal_encoding('UTF-8');

// Els avisos i notices de desús es registren però no aturen l'execució: una
// deprecació d'una versió futura de PHP no ha de tombar la venda d'entrades.
const NON_FATAL_ERRORS = E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED;

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity & NON_FATAL_ERRORS) {
        Logger::write('notice', $message, ['file' => $file . ':' . $line]);
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e) use ($debug): void {
    Logger::exception($e, 'No capturada');
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre style="padding:20px;font:13px/1.5 monospace;background:#20232B;color:#FBF4E6;">';
        echo htmlspecialchars($e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
        return;
    }
    try {
        App\Core\View::render('web/error', [
            'title'   => 'Error del servidor',
            'code'    => 500,
            'message' => 'Hi ha hagut un problema inesperat. Torneu-ho a provar d\'aquí a uns instants.',
        ]);
    } catch (Throwable) {
        echo '<h1>500 · Error del servidor</h1>';
    }
});

// La zona horària viu a la configuració, però l'hem de poder fixar abans
// que la base de dades estigui disponible (per exemple a l'instal·lador).
$timezone = 'Europe/Madrid';
if (Config::isInstalled()) {
    try {
        $timezone = (string) Settings::get('timezone', $timezone);
    } catch (Throwable) {
        // Ignorem: encara no hi ha taules.
    }
}
date_default_timezone_set($timezone);
