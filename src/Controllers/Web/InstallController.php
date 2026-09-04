<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Config;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Services\Migrator;
use PDO;

/**
 * Instal·lador web. Només és accessible mentre no existeix config/config.php.
 */
final class InstallController
{
    public function handle(): void
    {
        $requirements = self::requirements();
        $ready = !in_array(false, array_column($requirements, 'ok'), true);

        if (Request::isPost() && $ready) {
            $this->install();
            return;
        }

        $this->renderForm();
    }

    /** Dibuixa el formulari d'instal·lació (mai torna a intentar instal·lar). */
    private function renderForm(): void
    {
        $requirements = self::requirements();

        View::render('web/install', [
            'title'        => 'Instal·lació',
            'requirements' => $requirements,
            'ready'        => !in_array(false, array_column($requirements, 'ok'), true),
            'errors'       => Flash::errors(),
            'baseUrl'      => Url::base(),
        ], 'layouts/bare');
        Flash::clearOld();
    }

    private function install(): void
    {
        $validator = Validator::make($_POST)
            ->required('db_host', 'Cal indicar el servidor de base de dades.')
            ->required('db_name', 'Cal indicar el nom de la base de dades.')
            ->required('db_user', 'Cal indicar l\'usuari de la base de dades.')
            ->required('admin_name', 'Cal indicar el nom de l\'administrador.')
            ->required('admin_email', 'Cal indicar el correu de l\'administrador.')
            ->email('admin_email', 'El correu de l\'administrador no és vàlid.')
            ->required('admin_password', 'Cal indicar una contrasenya.')
            ->minLen('admin_password', 10, 'La contrasenya ha de tenir com a mínim 10 caràcters.')
            ->check(
                'admin_password_confirm',
                (string) Request::post('admin_password') === (string) Request::post('admin_password_confirm'),
                'Les dues contrasenyes no coincideixen.'
            );

        if ($validator->fails()) {
            Flash::setErrors($validator->errors());
            Flash::setOld($_POST);
            Flash::error('Reviseu les dades del formulari.');
            $this->renderForm();
            return;
        }

        $dbConfig = [
            'host'    => (string) Request::post('db_host'),
            'port'    => (int) (Request::post('db_port') ?: 3306),
            'name'    => (string) Request::post('db_name'),
            'user'    => (string) Request::post('db_user'),
            'pass'    => (string) Request::post('db_pass', ''),
            'charset' => 'utf8mb4',
        ];

        // Provem la connexió abans d'escriure res al disc.
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['name']),
                $dbConfig['user'],
                $dbConfig['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (\PDOException $e) {
            Flash::setErrors(['db_host' => 'No s\'ha pogut connectar: ' . $e->getMessage()]);
            Flash::setOld($_POST);
            Flash::error('No s\'ha pogut connectar amb la base de dades.');
            $this->renderForm();
            return;
        }

        Db::setPdo($pdo);

        $config = [
            'db'       => $dbConfig,
            'app_key'  => Str::token(32),
            'base_url' => rtrim((string) (Request::post('base_url') ?: Url::base()), '/'),
            'debug'    => false,
        ];

        $configPath = APP_ROOT . '/config/config.php';
        if (!Config::write($configPath, $config)) {
            Flash::error('No s\'ha pogut escriure config/config.php. Comproveu els permisos del directori config/.');
            $this->renderForm();
            return;
        }

        Config::load($configPath);

        try {
            Migrator::run();
        } catch (\Throwable $e) {
            @unlink($configPath);
            Flash::error('Error creant les taules: ' . $e->getMessage());
            $this->renderForm();
            return;
        }

        Db::insert('admins', [
            'name'          => (string) Request::post('admin_name'),
            'email'         => mb_strtolower((string) Request::post('admin_email')),
            'password_hash' => password_hash((string) Request::post('admin_password'), PASSWORD_DEFAULT),
            'role'          => 'owner',
        ]);

        Settings::setMany([
            'event_contact_email' => (string) Request::post('admin_email'),
            'smtp_from_email'     => (string) Request::post('admin_email'),
            'cron_token'          => Str::token(16),
        ]);

        $this->seedTicketTypes();

        View::render('web/install_done', [
            'title'    => 'Instal·lació completada',
            'loginUrl' => Url::to('/admin/login'),
        ], 'layouts/bare');
    }

    /** Crea dos tipus d'inscripció d'exemple per no començar amb la pantalla buida. */
    private function seedTicketTypes(): void
    {
        if ((int) Db::value('SELECT COUNT(*) FROM `ticket_types`', [], 0) > 0) {
            return;
        }

        Db::insert('ticket_types', [
            'name'        => 'Sopar adult',
            'description' => 'Inscripció per a persones adultes.',
            'includes'    => "Paella popular i pa\nBeguda (aigua, vi o refresc)\nPostres i cafè\nParticipació al bingo",
            'price_cents' => 1500,
            'sort_order'  => 1,
        ]);

        Db::insert('ticket_types', [
            'name'        => 'Sopar infantil (fins a 12 anys)',
            'description' => 'Inscripció per a infants de fins a 12 anys.',
            'includes'    => "Ració infantil de paella\nBeguda\nPostres",
            'price_cents' => 800,
            'sort_order'  => 2,
        ]);
    }

    /** @return array<int, array{label:string, ok:bool, hint:string}> */
    public static function requirements(): array
    {
        $configDir = APP_ROOT . '/config';
        $checks = [
            [
                'label' => 'PHP 8.1 o superior',
                'ok'    => PHP_VERSION_ID >= 80100,
                'hint'  => 'Versió actual: ' . PHP_VERSION,
            ],
        ];

        foreach (['pdo_mysql' => 'Base de dades', 'curl' => 'Connexió amb Stripe', 'gd' => 'Codis QR', 'mbstring' => 'Text multilingüe', 'openssl' => 'Seguretat i wallet', 'zip' => 'Actualitzacions OTA'] as $ext => $why) {
            $checks[] = [
                'label' => 'Extensió PHP ' . $ext,
                'ok'    => extension_loaded($ext),
                'hint'  => $why,
            ];
        }

        $checks[] = [
            'label' => 'Directori config/ amb permisos d\'escriptura',
            'ok'    => is_dir($configDir) && is_writable($configDir),
            'hint'  => $configDir,
        ];
        $checks[] = [
            'label' => 'Directori storage/ amb permisos d\'escriptura',
            'ok'    => is_writable(APP_ROOT . '/storage'),
            'hint'  => APP_ROOT . '/storage',
        ];
        $checks[] = [
            'label' => 'Directori public/uploads/ amb permisos d\'escriptura',
            'ok'    => is_writable(APP_ROOT . '/public/uploads'),
            'hint'  => APP_ROOT . '/public/uploads',
        ];

        return $checks;
    }
}
