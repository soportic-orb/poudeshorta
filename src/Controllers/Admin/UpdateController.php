<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Url;
use App\Core\View;
use App\Services\Migrator;
use App\Services\Updater;

final class UpdateController
{
    public function index(): void
    {
        View::render('admin/updates', [
            'title'      => 'Actualitzacions',
            'current'    => Updater::currentVersion(),
            'commit'     => Updater::currentCommit(),
            'strategy'   => Updater::strategy(),
            'settings'   => Settings::all(),
            'history'    => Db::all('SELECT * FROM `updates_log` ORDER BY `id` DESC LIMIT 15'),
            'backups'    => Updater::backups(),
            'pendingMigrations' => Migrator::pending(),
            'result'     => Session::pull('update_result'),
            'check'      => Session::pull('update_check'),
            'diagnostics' => Session::pull('update_diagnostics'),
        ], 'layouts/admin');
    }

    /** Comprova un per un tots els requisits i mostra quin falla. */
    public function diagnose(): void
    {
        Session::set('update_diagnostics', Updater::diagnostics());
        Response::redirect(Url::to('/admin/actualitzacions'));
    }

    public function check(): void
    {
        try {
            $result = Updater::check();
            Session::set('update_check', $result);

            $result['available']
                ? Flash::success('Hi ha una versió nova disponible: ' . $result['version'] . '.')
                : Flash::info('La plataforma ja està actualitzada (versió ' . $result['current'] . ').');
        } catch (\Throwable $e) {
            Logger::exception($e, 'Comprovació d\'actualitzacions');
            Flash::error('No s\'ha pogut comprovar: ' . $e->getMessage());
        }

        Response::redirect(Url::to('/admin/actualitzacions'));
    }

    public function apply(): void
    {
        if (!Auth::is('owner', 'admin')) {
            Flash::error('Només els administradors poden aplicar actualitzacions.');
            Response::redirect(Url::to('/admin/actualitzacions'));
        }

        if ((string) Request::post('confirm', '') !== 'ACTUALITZA') {
            Flash::error('Escriviu ACTUALITZA a la casella de confirmació per continuar.');
            Response::redirect(Url::to('/admin/actualitzacions'));
        }

        // L'actualització pot trigar: ampliem el temps màxim d'execució.
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $result = (new Updater())->apply((string) (Auth::user()['email'] ?? ''));
        Session::set('update_result', $result);

        $result['success']
            ? Flash::success('Actualització aplicada correctament. Versió actual: ' . $result['version'] . '.')
            : Flash::error('L\'actualització ha fallat. Reviseu el registre; s\'ha restaurat la versió anterior.');

        Logger::audit('actualitza_plataforma', $result['version'], ['ok' => $result['success']]);
        Response::redirect(Url::to('/admin/actualitzacions'));
    }

    public function saveSettings(): void
    {
        Settings::setMany([
            'ota_repo'       => trim((string) Request::post('ota_repo', '')),
            'ota_branch'     => trim((string) Request::post('ota_branch', 'main')) ?: 'main',
            'ota_channel'    => Request::post('ota_channel') === 'release' ? 'release' : 'branch',
            'ota_auto_check' => Request::post('ota_auto_check') ? '1' : '0',
        ]);

        $token = (string) Request::post('ota_token', '');
        if ($token !== '' && !str_starts_with($token, '••')) {
            Settings::set('ota_token', $token);
        }
        if (Request::post('clear_ota_token')) {
            Settings::set('ota_token', '');
        }

        Settings::flush();
        Logger::audit('configura_actualitzacions');
        Flash::success('Configuració d\'actualitzacions desada.');
        Response::redirect(Url::to('/admin/actualitzacions'));
    }

    /** Diagnòstic del servidor, útil per donar suport a distància. */
    public function system(): void
    {
        $extensions = [];
        foreach (['pdo_mysql', 'curl', 'gd', 'mbstring', 'openssl', 'zip', 'intl', 'fileinfo'] as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        $paths = [];
        foreach (['config', 'storage', 'storage/logs', 'storage/backups', 'public/uploads'] as $relative) {
            $full = APP_ROOT . '/' . $relative;
            $paths[$relative] = ['exists' => is_dir($full), 'writable' => is_writable($full)];
        }

        View::render('admin/system', [
            'title'      => 'Estat del sistema',
            'php'        => PHP_VERSION,
            'extensions' => $extensions,
            'paths'      => $paths,
            'server'     => $_SERVER['SERVER_SOFTWARE'] ?? 'desconegut',
            'db'         => (string) Db::value('SELECT VERSION()', [], 'desconegut'),
            'limits'     => [
                'memory_limit'        => ini_get('memory_limit'),
                'max_execution_time'  => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
            ],
            'diskFree'   => Updater::humanBytes((int) @disk_free_space(APP_ROOT)),
            'cronToken'  => (string) Settings::get('cron_token'),
            'cronUrl'    => Url::full('/tasques/' . Settings::get('cron_token')),
            'lastCron'   => (string) Settings::get('last_cron_run'),
            'logs'       => $this->recentLog(),
            'migrations' => ['pendents' => Migrator::pending(), 'aplicades' => Migrator::applied()],
        ], 'layouts/admin');
    }

    /** @return string[] Darreres línies del registre d'aplicació. */
    private function recentLog(int $lines = 40): array
    {
        $file = APP_ROOT . '/storage/logs/app-' . date('Y-m') . '.log';
        if (!is_file($file)) {
            return [];
        }
        $content = (string) file_get_contents($file);
        $all = array_values(array_filter(explode("\n", $content)));
        return array_slice($all, -$lines);
    }
}
