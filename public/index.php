<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Url;
use App\Core\View;
use App\Controllers\Admin;
use App\Controllers\Web;

require dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

$router = new Router();
$path = Request::path();

// ---------------------------------------------------------------- Instal·lació
if (!Config::isInstalled()) {
    if (!str_starts_with($path, '/install')) {
        Response::redirect(Url::to('/install'));
    }
    $router->any('/install', [Web\InstallController::class, 'handle']);
    $router->dispatch(Request::method(), $path);
    return;
}

if (str_starts_with($path, '/install')) {
    // Ja instal·lat: l'instal·lador queda inhabilitat.
    Response::redirect(Url::to('/'));
}

// ------------------------------------------------------------------ Middleware
$csrf = static function (): void {
    if (Request::isPost()) {
        Csrf::verifyRequest();
    }
};

$maintenance = static function (): void {
    if (Settings::bool('maintenance_mode') && !Auth::check()) {
        http_response_code(503);
        header('Retry-After: 600');
        View::render('web/maintenance', [], 'layouts/public');
        exit;
    }
};

// ---------------------------------------------------------------------- Públic
$router->group([$maintenance, $csrf], static function (Router $r): void {
    $r->get('/',                      [Web\HomeController::class, 'index']);
    $r->post('/inscripcio',           [Web\CheckoutController::class, 'details']);
    $r->get('/inscripcio',            [Web\CheckoutController::class, 'redirectHome']);
    $r->post('/inscripcio/pagament',  [Web\CheckoutController::class, 'pay']);
    $r->get('/pagament/retorn',       [Web\CheckoutController::class, 'return']);
    $r->get('/pagament/cancellat',    [Web\CheckoutController::class, 'cancelled']);
    $r->get('/confirmacio/{reference}', [Web\OrderController::class, 'confirmation']);

    $r->get('/les-meves-entrades',    [Web\OrderController::class, 'lookupForm']);
    $r->post('/les-meves-entrades',   [Web\OrderController::class, 'lookupSend']);
    $r->get('/les-meves-entrades/{token}', [Web\OrderController::class, 'lookupResults']);

    $r->get('/comanda/{reference}',   [Web\OrderController::class, 'show']);
    $r->get('/comanda/{reference}/pdf', [Web\OrderController::class, 'downloadPdf']);
    $r->post('/comanda/{reference}/enviar', [Web\OrderController::class, 'emailTickets']);
    $r->post('/comanda/{reference}/anullar', [Web\OrderController::class, 'cancel']);
    $r->get('/comanda/{reference}/wallet/apple', [Web\WalletController::class, 'apple']);
    $r->get('/comanda/{reference}/wallet/google', [Web\WalletController::class, 'google']);

    $r->get('/e/{code}',              [Web\TicketController::class, 'show']);
    $r->get('/informacio',            [Web\HomeController::class, 'info']);
});

// Stripe crida aquest punt sense sessió ni testimoni CSRF: es valida per signatura.
$router->post('/webhook/stripe', [Web\WebhookController::class, 'stripe']);

// Tasques programades per si el servidor no té accés a cron (s'autentiquen amb testimoni).
$router->get('/tasques/{token}', [Web\CronController::class, 'run']);

// ----------------------------------------------------------- Panell de Gestió
$router->group([$csrf], static function (Router $r): void {
    $r->get('/admin/login',  [Admin\AuthController::class, 'form']);
    $r->post('/admin/login', [Admin\AuthController::class, 'login']);
    $r->post('/admin/logout', [Admin\AuthController::class, 'logout']);
});

$router->group([$csrf, [Auth::class, 'requireLogin']], static function (Router $r): void {
    $r->get('/admin',                          [Admin\DashboardController::class, 'index']);

    $r->get('/admin/inscripcions',             [Admin\RegistrationController::class, 'index']);
    $r->get('/admin/inscripcions/pdf',         [Admin\RegistrationController::class, 'pdf']);
    $r->get('/admin/inscripcions/csv',         [Admin\RegistrationController::class, 'csv']);
    $r->get('/admin/inscripcions/{id:\d+}',    [Admin\RegistrationController::class, 'show']);
    $r->post('/admin/inscripcions/{id:\d+}/anullar',  [Admin\RegistrationController::class, 'cancel']);
    $r->post('/admin/inscripcions/{id:\d+}/reenviar', [Admin\RegistrationController::class, 'resend']);
    $r->post('/admin/inscripcions/{id:\d+}/nota',     [Admin\RegistrationController::class, 'note']);
    $r->get('/admin/inscripcions/{id:\d+}/pdf',       [Admin\RegistrationController::class, 'ticketPdf']);

    $r->get('/admin/control-acces',            [Admin\CheckinController::class, 'index']);
    $r->post('/admin/control-acces/validar',   [Admin\CheckinController::class, 'validate']);

    $r->get('/admin/tipus-inscripcio',         [Admin\TicketTypeController::class, 'index']);
    $r->get('/admin/tipus-inscripcio/nou',     [Admin\TicketTypeController::class, 'create']);
    $r->post('/admin/tipus-inscripcio/nou',    [Admin\TicketTypeController::class, 'store']);
    $r->get('/admin/tipus-inscripcio/{id:\d+}',        [Admin\TicketTypeController::class, 'edit']);
    $r->post('/admin/tipus-inscripcio/{id:\d+}',       [Admin\TicketTypeController::class, 'update']);
    $r->post('/admin/tipus-inscripcio/{id:\d+}/estat', [Admin\TicketTypeController::class, 'toggle']);
    $r->post('/admin/tipus-inscripcio/{id:\d+}/eliminar', [Admin\TicketTypeController::class, 'destroy']);

    $r->get('/admin/comunicacions',            [Admin\CampaignController::class, 'index']);
    $r->get('/admin/comunicacions/nova',       [Admin\CampaignController::class, 'create']);
    $r->post('/admin/comunicacions/nova',      [Admin\CampaignController::class, 'store']);
    $r->get('/admin/comunicacions/{id:\d+}',   [Admin\CampaignController::class, 'show']);
    $r->post('/admin/comunicacions/{id:\d+}/prova',  [Admin\CampaignController::class, 'test']);
    $r->post('/admin/comunicacions/{id:\d+}/enviar', [Admin\CampaignController::class, 'send']);
    $r->post('/admin/comunicacions/{id:\d+}/processar', [Admin\CampaignController::class, 'process']);
    $r->post('/admin/comunicacions/{id:\d+}/eliminar',  [Admin\CampaignController::class, 'destroy']);

    $r->get('/admin/configuracio',             [Admin\SettingsController::class, 'event']);
    $r->post('/admin/configuracio',            [Admin\SettingsController::class, 'saveEvent']);
    $r->get('/admin/configuracio/aparenca',    [Admin\SettingsController::class, 'appearance']);
    $r->post('/admin/configuracio/aparenca',   [Admin\SettingsController::class, 'saveAppearance']);
    $r->get('/admin/configuracio/pagaments',   [Admin\SettingsController::class, 'payments']);
    $r->post('/admin/configuracio/pagaments',  [Admin\SettingsController::class, 'savePayments']);
    $r->get('/admin/configuracio/correu',      [Admin\SettingsController::class, 'mail']);
    $r->post('/admin/configuracio/correu',     [Admin\SettingsController::class, 'saveMail']);
    $r->post('/admin/configuracio/correu/prova', [Admin\SettingsController::class, 'testMail']);
    $r->get('/admin/configuracio/anullacions', [Admin\SettingsController::class, 'cancellations']);
    $r->post('/admin/configuracio/anullacions',[Admin\SettingsController::class, 'saveCancellations']);
    $r->get('/admin/configuracio/wallet',      [Admin\SettingsController::class, 'wallet']);
    $r->post('/admin/configuracio/wallet',     [Admin\SettingsController::class, 'saveWallet']);
    $r->post('/admin/configuracio/wallet/prova', [Admin\SettingsController::class, 'testWallet']);
    $r->post('/admin/configuracio/wallet/classe-google', [Admin\SettingsController::class, 'googleClass']);

    $r->get('/admin/usuaris',                  [Admin\UserController::class, 'index']);
    $r->post('/admin/usuaris',                 [Admin\UserController::class, 'store']);
    $r->post('/admin/usuaris/{id:\d+}/eliminar', [Admin\UserController::class, 'destroy']);
    $r->post('/admin/usuaris/contrasenya',     [Admin\UserController::class, 'changePassword']);

    $r->get('/admin/actualitzacions',          [Admin\UpdateController::class, 'index']);
    $r->post('/admin/actualitzacions/comprovar', [Admin\UpdateController::class, 'check']);
    $r->post('/admin/actualitzacions/diagnostic', [Admin\UpdateController::class, 'diagnose']);
    $r->post('/admin/actualitzacions/aplicar', [Admin\UpdateController::class, 'apply']);
    $r->post('/admin/actualitzacions/configuracio', [Admin\UpdateController::class, 'saveSettings']);
    $r->get('/admin/sistema',                  [Admin\UpdateController::class, 'system']);
});

$router->dispatch(Request::method(), $path);
