<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;
use RuntimeException;
use ZipArchive;

/**
 * Actualitzacions OTA des del repositori de GitHub, llançades des del Panell de Gestió.
 *
 * Dues estratègies:
 *  - «git»: si el desplegament és un clon de git i es pot executar el binari git.
 *  - «zip»: descarrega el paquet del repositori i substitueix els fitxers (per defecte).
 *
 * Sempre es fa una còpia de seguretat prèvia i mai es toquen la configuració,
 * els fitxers pujats ni el directori d'emmagatzematge.
 */
final class Updater
{
    /** Rutes que MAI se sobreescriuen en una actualització. */
    private const PROTECTED_PATHS = [
        '.git',
        'config/config.php',
        'storage',
        'public/uploads',
    ];

    private array $log = [];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function currentVersion(): string
    {
        $file = self::root() . '/VERSION';
        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    public static function currentCommit(): ?string
    {
        $head = self::root() . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }
        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $ref = self::root() . '/.git/' . trim(substr($contents, 5));
            return is_file($ref) ? substr(trim((string) file_get_contents($ref)), 0, 7) : null;
        }
        return substr($contents, 0, 7);
    }

    public static function strategy(): string
    {
        return self::gitAvailable() ? 'git' : 'zip';
    }

    public static function gitAvailable(): bool
    {
        if (!is_dir(self::root() . '/.git')) {
            return false;
        }
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return false;
        }
        $output = [];
        $code = 0;
        @exec('git --version 2>&1', $output, $code);
        return $code === 0;
    }

    // ------------------------------------------------------------ Comprovació

    /**
     * Consulta GitHub i desa el resultat a la configuració.
     *
     * @return array{available:bool, version:string, current:string, notes:string, published_at:string, sha:string, ref:string, channel:string}
     */
    public static function check(): array
    {
        $remote  = self::resolveRemote();
        $channel = (string) Settings::get('ota_channel', 'branch');
        $current = self::installedRef();

        $available = $channel === 'release'
            ? ($remote['version'] !== '' && version_compare($remote['version'], self::currentVersion(), '>'))
            : ($remote['version'] !== '' && $remote['version'] !== $current);

        Settings::setMany([
            'ota_last_check'     => date('Y-m-d H:i:s'),
            'ota_latest_version' => $remote['version'],
            'ota_latest_sha'     => $remote['sha'],
            'ota_latest_ref'     => $remote['ref'],
        ]);

        return array_merge($remote, [
            'available' => $available,
            'current'   => $current === '' ? self::currentVersion() : $current,
            'channel'   => $channel,
        ]);
    }

    /**
     * Esbrina quina és l'última versió publicada al repositori.
     *
     * Per a les branques resolem el commit amb el nom de branca com a
     * paràmetre de consulta i no dins del camí: així les branques que
     * contenen barres (per exemple «feina/nova-funcio») també funcionen.
     *
     * @return array{version:string, sha:string, ref:string, notes:string, published_at:string}
     */
    public static function resolveRemote(): array
    {
        $repo    = trim((string) Settings::get('ota_repo'));
        $branch  = trim((string) Settings::get('ota_branch', 'main'));
        $channel = (string) Settings::get('ota_channel', 'branch');

        if ($repo === '') {
            throw new RuntimeException('Cal indicar el repositori de GitHub a la configuració d\'actualitzacions.');
        }

        if ($channel === 'release') {
            $data = self::api("repos/{$repo}/releases/latest");
            $tag = (string) ($data['tag_name'] ?? '');

            return [
                'version'      => ltrim($tag, 'v'),
                'sha'          => '',
                // El paquet es baixa per etiqueta.
                'ref'          => $tag,
                'notes'        => (string) ($data['body'] ?? ''),
                'published_at' => (string) ($data['published_at'] ?? ''),
            ];
        }

        if ($branch === '') {
            throw new RuntimeException('Cal indicar la branca del repositori.');
        }

        $commits = self::api("repos/{$repo}/commits", ['sha' => $branch, 'per_page' => 1]);
        $commit = $commits[0] ?? null;

        if (!is_array($commit) || empty($commit['sha'])) {
            throw new RuntimeException(
                'La branca «' . $branch . '» no existeix al repositori ' . $repo . ', o no té cap commit.'
            );
        }

        $sha = (string) $commit['sha'];

        return [
            'version'      => substr($sha, 0, 7),
            'sha'          => $sha,
            // El paquet es baixa pel commit, que mai conté barres.
            'ref'          => $sha,
            'notes'        => (string) ($commit['commit']['message'] ?? ''),
            'published_at' => (string) ($commit['commit']['author']['date'] ?? ''),
        ];
    }

    /**
     * Revisió instal·lada actualment.
     *
     * En un desplegament amb git la llegim de .git; en un desplegament per
     * còpia de fitxers la desem nosaltres en actualitzar, perquè si no no
     * hi hauria manera de saber-ho i sempre diria que hi ha novetats.
     */
    public static function installedRef(): string
    {
        $commit = self::currentCommit();
        if ($commit !== null) {
            return $commit;
        }

        $stored = trim((string) Settings::get('ota_installed_ref', ''));
        return $stored !== '' ? substr($stored, 0, 7) : '';
    }

    // ------------------------------------------------------------ Diagnòstic

    /**
     * Comprova, un per un, tots els requisits d'una actualització i explica
     * quin falla. Serveix per convertir un «no funciona» en una causa concreta.
     *
     * @return array<int, array{label:string, ok:bool, detail:string}>
     */
    public static function diagnostics(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail) use (&$checks): void {
            $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };

        $repo   = trim((string) Settings::get('ota_repo'));
        $branch = trim((string) Settings::get('ota_branch', 'main'));
        $token  = trim((string) Settings::get('ota_token'));
        $channel = (string) Settings::get('ota_channel', 'branch');

        $add('Repositori configurat', $repo !== '', $repo !== '' ? $repo : 'Falta indicar-lo aquí sota.');
        $add(
            'Token d\'accés',
            true,
            $token !== ''
                ? 'Configurat. Necessari si el repositori és privat.'
                : 'Sense token. Només funcionarà si el repositori és públic.'
        );

        $add('Extensió zip de PHP', class_exists(ZipArchive::class), class_exists(ZipArchive::class)
            ? 'Disponible.'
            : 'Cal activar-la per poder aplicar actualitzacions.');

        $strategy = self::strategy();
        $add(
            'Mètode d\'actualització',
            true,
            $strategy === 'git'
                ? 'git: el desplegament és un clon del repositori.'
                : 'paquet ZIP: es descarrega el codi de GitHub' . (is_dir(self::root() . '/.git')
                    ? ' (hi ha .git, però no es pot executar git en aquest servidor).'
                    : '.')
        );

        $writable = is_writable(self::root());
        $add('Permisos d\'escriptura', $writable, $writable
            ? self::root()
            : 'El servidor no pot escriure a ' . self::root() . '. Executeu: chmod -R u+w ' . self::root());

        $free = @disk_free_space(self::root());
        $enough = $free === false || $free > 50 * 1024 * 1024;
        $add('Espai lliure al disc', $enough, $free === false
            ? 'No s\'ha pogut comprovar.'
            : self::humanBytes((int) $free) . ' disponibles (calen 50 MB).');

        if ($repo === '') {
            return $checks;
        }

        // Connexió i accés reals.
        try {
            self::api("repos/{$repo}");
            $add('Accés al repositori', true, 'GitHub respon i el repositori és accessible.');
        } catch (\Throwable $e) {
            $add('Accés al repositori', false, $e->getMessage());
            return $checks;
        }

        try {
            $remote = self::resolveRemote();
            $add(
                $channel === 'release' ? 'Última versió publicada' : 'Branca «' . $branch . '»',
                $remote['version'] !== '',
                $remote['version'] !== ''
                    ? 'Trobada: ' . $remote['version'] . ($remote['published_at'] !== ''
                        ? ' (' . date('d/m/Y H:i', (int) strtotime($remote['published_at'])) . ')'
                        : '')
                    : 'No s\'ha trobat cap versió.'
            );
        } catch (\Throwable $e) {
            $add($channel === 'release' ? 'Última versió publicada' : 'Branca «' . $branch . '»', false, $e->getMessage());
        }

        return $checks;
    }

    // ----------------------------------------------------------- Actualització

    /**
     * Aplica l'actualització. Retorna el registre d'operacions.
     *
     * @return array{success:bool, log:string[], version:string}
     */
    public function apply(string $actor = ''): array
    {
        $fromVersion = self::currentVersion();
        $strategy = self::strategy();

        $logId = Db::insert('updates_log', [
            'from_version' => $fromVersion,
            'strategy'     => $strategy,
            'status'       => 'running',
            'actor'        => $actor,
        ]);

        $wasMaintenance = Settings::bool('maintenance_mode');
        Settings::set('maintenance_mode', '1');

        $backupPath = null;
        $success = false;
        $toVersion = $fromVersion;

        try {
            $this->preflight();
            $backupPath = $this->backup();
            $this->note('Còpia de seguretat creada: ' . basename((string) $backupPath));

            $installedRef = $strategy === 'git' ? $this->applyGit() : $this->applyZip();

            $this->note('Executant migracions de base de dades…');
            Settings::flush();
            $migrations = Migrator::run();
            foreach ($migrations['messages'] as $message) {
                $this->note($message);
            }
            if ($migrations['applied'] === []) {
                $this->note('No hi havia cap migració pendent.');
            }

            $this->clearCaches();

            if ($installedRef !== '') {
                Settings::set('ota_installed_ref', $installedRef);
            }

            $toVersion = self::currentVersion();
            $this->note('Actualització completada. Versió: ' . $toVersion);
            $success = true;
        } catch (\Throwable $e) {
            $this->note('ERROR: ' . $e->getMessage());
            Logger::exception($e, 'Actualització OTA');

            if ($backupPath !== null && is_file($backupPath)) {
                try {
                    $this->restore($backupPath);
                    $this->note('S\'ha restaurat la còpia de seguretat anterior.');
                } catch (\Throwable $restoreError) {
                    $this->note('ERROR en restaurar la còpia: ' . $restoreError->getMessage());
                }
            }
        } finally {
            Settings::flush();
            Settings::set('maintenance_mode', $wasMaintenance ? '1' : '0');

            Db::update('updates_log', [
                'to_version'  => $toVersion,
                'status'      => $success ? 'success' : ($backupPath !== null ? 'rolled_back' : 'failed'),
                'output'      => implode("\n", $this->log),
                'backup_path' => $backupPath,
                'finished_at' => date('Y-m-d H:i:s'),
            ], '`id` = :id', ['id' => $logId]);
        }

        return ['success' => $success, 'log' => $this->log, 'version' => $toVersion];
    }

    private function preflight(): void
    {
        $root = self::root();
        if (!is_writable($root)) {
            throw new RuntimeException('El directori de l\'aplicació no té permisos d\'escriptura: ' . $root);
        }
        foreach (['src', 'public', 'migrations'] as $dir) {
            if (is_dir($root . '/' . $dir) && !is_writable($root . '/' . $dir)) {
                throw new RuntimeException('El directori ' . $dir . ' no té permisos d\'escriptura.');
            }
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Cal l\'extensió zip de PHP per fer actualitzacions.');
        }
        $free = @disk_free_space($root);
        if ($free !== false && $free < 50 * 1024 * 1024) {
            throw new RuntimeException('No hi ha prou espai lliure al disc (calen 50 MB com a mínim).');
        }
        $this->note('Comprovacions prèvies correctes (estratègia: ' . self::strategy() . ').');
    }

    // ---------------------------------------------------------------- Git

    private function applyGit(): string
    {
        $branch = trim((string) Settings::get('ota_branch', 'main'));
        $root = escapeshellarg(self::root());
        $ref = escapeshellarg('origin/' . $branch);

        $commands = [
            "git -C {$root} fetch --prune origin " . escapeshellarg($branch) . " 2>&1",
            "git -C {$root} reset --hard {$ref} 2>&1",
        ];

        foreach ($commands as $command) {
            $output = [];
            $code = 0;
            @exec($command, $output, $code);
            $this->note('$ ' . preg_replace('/\s+/', ' ', $command));
            foreach ($output as $line) {
                $this->note('  ' . $line);
            }
            if ($code !== 0) {
                throw new RuntimeException('L\'ordre de git ha fallat (codi ' . $code . ').');
            }
        }

        return self::currentCommit() ?? '';
    }

    // ---------------------------------------------------------------- Zip

    private function applyZip(): string
    {
        $repo   = trim((string) Settings::get('ota_repo'));
        $remote = self::resolveRemote();
        $ref    = $remote['ref'];

        if ($ref === '') {
            throw new RuntimeException('No s\'ha pogut determinar quina versió cal baixar del repositori.');
        }

        $tmpDir = self::root() . '/storage/tmp/update_' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No s\'ha pogut crear el directori temporal d\'actualització.');
        }

        try {
            $zipFile = $tmpDir . '/package.zip';
            $this->note('Descarregant el paquet des de GitHub (' . $repo . ' · ' . substr($ref, 0, 12) . ')…');
            self::download("https://api.github.com/repos/{$repo}/zipball/" . rawurlencode($ref), $zipFile);
            $this->note('Paquet descarregat (' . self::humanBytes((int) filesize($zipFile)) . ').');

            $extractDir = $tmpDir . '/extract';
            mkdir($extractDir, 0775, true);

            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new RuntimeException('El paquet descarregat no és un ZIP vàlid.');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            // GitHub empaqueta el codi dins d'una única carpeta arrel.
            $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
            $sourceDir = count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])
                ? $extractDir . '/' . $entries[0]
                : $extractDir;

            if (!is_file($sourceDir . '/VERSION') && !is_dir($sourceDir . '/src')) {
                throw new RuntimeException('El paquet descarregat no sembla una versió vàlida de la plataforma.');
            }
            if (!is_dir($sourceDir . '/vendor')) {
                throw new RuntimeException(
                    'El paquet descarregat no inclou el directori vendor/. Comproveu que la branca '
                    . 'configurada és la de la plataforma i que vendor/ està al repositori.'
                );
            }

            $copied = $this->copyTree($sourceDir, self::root());
            $this->note($copied . ' fitxers actualitzats.');
        } finally {
            self::removeTree($tmpDir);
        }

        return $ref;
    }

    /** Copia recursivament respectant les rutes protegides. Retorna els fitxers escrits. */
    private function copyTree(string $source, string $destination, string $relative = ''): int
    {
        $count = 0;
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $relativePath = ltrim($relative . '/' . $entry, '/');
            if (self::isProtected($relativePath)) {
                continue;
            }

            $from = $source . '/' . $entry;
            $to   = $destination . '/' . $entry;

            if (is_dir($from)) {
                if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
                    throw new RuntimeException('No s\'ha pogut crear el directori ' . $relativePath);
                }
                $count += $this->copyTree($from, $to, $relativePath);
                continue;
            }

            if (!@copy($from, $to)) {
                throw new RuntimeException('No s\'ha pogut escriure el fitxer ' . $relativePath);
            }
            @chmod($to, 0644);
            if (function_exists('opcache_invalidate') && str_ends_with($to, '.php')) {
                @opcache_invalidate($to, true);
            }
            $count++;
        }
        return $count;
    }

    private static function isProtected(string $relativePath): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if ($relativePath === $protected || str_starts_with($relativePath, $protected . '/')) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------- Còpies

    private function backup(): string
    {
        $dir = self::root() . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No s\'ha pogut crear el directori de còpies de seguretat.');
        }

        $path = $dir . '/backup-' . self::currentVersion() . '-' . date('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No s\'ha pogut crear el fitxer de còpia de seguretat.');
        }

        $this->addToZip($zip, self::root(), '');
        $zip->close();

        $this->pruneBackups($dir);
        return $path;
    }

    private function addToZip(ZipArchive $zip, string $dir, string $relative): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $relativePath = ltrim($relative . '/' . $entry, '/');
            // La còpia no inclou ni el propi directori de còpies ni l'historial de git.
            if ($relativePath === 'storage/backups' || $relativePath === '.git' || $relativePath === 'storage/tmp') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (is_dir($full)) {
                $zip->addEmptyDir($relativePath);
                $this->addToZip($zip, $full, $relativePath);
            } elseif (is_file($full)) {
                $zip->addFile($full, $relativePath);
            }
        }
    }

    /** Conserva només les 5 còpies més recents. */
    private function pruneBackups(string $dir): void
    {
        $files = glob($dir . '/backup-*.zip') ?: [];
        if (count($files) <= 5) {
            return;
        }
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, 5) as $old) {
            @unlink($old);
        }
    }

    public function restore(string $backupPath): void
    {
        if (!is_file($backupPath)) {
            throw new RuntimeException('No es troba la còpia de seguretat indicada.');
        }
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new RuntimeException('No s\'ha pogut obrir la còpia de seguretat.');
        }

        $tmp = self::root() . '/storage/tmp/restore_' . bin2hex(random_bytes(6));
        mkdir($tmp, 0775, true);
        try {
            $zip->extractTo($tmp);
            $zip->close();
            $this->copyTree($tmp, self::root());
            $this->clearCaches();
        } finally {
            self::removeTree($tmp);
        }
    }

    /** @return array<int, array{name:string, size:int, date:string}> */
    public static function backups(): array
    {
        $files = glob(self::root() . '/storage/backups/backup-*.zip') ?: [];
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(static fn ($file) => [
            'name' => basename($file),
            'size' => (int) filesize($file),
            'date' => date('d/m/Y H:i', (int) filemtime($file)),
        ], $files);
    }

    private function clearCaches(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        foreach (glob(self::root() . '/storage/cache/*.php') ?: [] as $file) {
            @unlink($file);
        }
        Settings::flush();
        $this->note('Memòria cau buidada.');
    }

    // --------------------------------------------------------------- HTTP

    private static function api(string $path, array $query = []): array
    {
        $url = 'https://api.github.com/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $body = self::http($url);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta no vàlida de l\'API de GitHub.');
        }
        // Les llistes són arrays numèrics; només els objectes porten «message» d'error.
        if (isset($decoded['message']) && !isset($decoded['sha']) && !isset($decoded['tag_name'])) {
            throw new RuntimeException('GitHub: ' . $decoded['message']);
        }

        return $decoded;
    }

    private static function http(string $url, ?string $saveTo = null): string
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: poudeshorta-updater',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        $token = trim((string) Settings::get('ota_token'));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        $responseHeaders = [];
        $options = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];

        $handle = null;
        if ($saveTo !== null) {
            $handle = fopen($saveTo, 'wb');
            if ($handle === false) {
                throw new RuntimeException('No s\'ha pogut obrir el fitxer de destinació de la descàrrega.');
            }
            $options[CURLOPT_FILE] = $handle;
        } else {
            $options[CURLOPT_RETURNTRANSFER] = true;
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($handle !== null) {
            fclose($handle);
        }

        if ($result === false) {
            throw new RuntimeException(
                'No s\'ha pogut contactar amb GitHub: ' . $error
                . '. Comproveu que el servidor té sortida a Internet cap a api.github.com pel port 443.'
            );
        }

        if ($status >= 400) {
            throw new RuntimeException(self::describeHttpError($status, $responseHeaders));
        }

        return $saveTo !== null ? '' : (string) $result;
    }

    /** Tradueix el codi de resposta de GitHub a una explicació accionable. */
    private static function describeHttpError(int $status, array $headers): string
    {
        $hasToken = trim((string) Settings::get('ota_token')) !== '';
        $repo = trim((string) Settings::get('ota_repo'));

        if ($status === 403 && ($headers['x-ratelimit-remaining'] ?? null) === '0') {
            $reset = isset($headers['x-ratelimit-reset'])
                ? ' Es reprèn a les ' . date('H:i', (int) $headers['x-ratelimit-reset']) . '.'
                : '';
            return 'Heu superat el límit de consultes de GitHub' . ($hasToken ? '' : ' (60 per hora sense token)') . '.' . $reset;
        }

        return match (true) {
            $status === 401 => 'GitHub ha rebutjat el token d\'accés: comproveu que no ha caducat i que té permís de lectura del repositori.',
            $status === 403 => 'GitHub ha denegat l\'accés (403).' . ($hasToken
                ? ' El token no té permís sobre ' . $repo . '.'
                : ' Si el repositori és privat, cal configurar un token d\'accés.'),
            $status === 404 => 'GitHub no troba «' . $repo . '» (404). Reviseu que el nom del repositori sigui correcte i, '
                . ($hasToken
                    ? 'si el repositori és privat, que el token hi tingui accés.'
                    : 'si és privat, configureu un token d\'accés: sense token GitHub respon 404 als repositoris privats.'),
            $status >= 500  => 'GitHub està tenint problemes (codi ' . $status . '). Torneu-ho a provar més tard.',
            default         => 'GitHub ha respost amb el codi ' . $status . '.',
        };
    }

    private static function download(string $url, string $destination): void
    {
        self::http($url, $destination);
        if (!is_file($destination) || filesize($destination) < 1024) {
            throw new RuntimeException('La descàrrega del paquet ha fallat o està incompleta.');
        }
    }

    // -------------------------------------------------------------- Utilitats

    private function note(string $message): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $message;
    }

    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
