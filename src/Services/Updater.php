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
     * @return array{available:bool, version:string, current:string, notes:string, published_at:string, sha:string, channel:string}
     */
    public static function check(): array
    {
        $repo    = trim((string) Settings::get('ota_repo'));
        $branch  = trim((string) Settings::get('ota_branch', 'main'));
        $channel = (string) Settings::get('ota_channel', 'branch');

        if ($repo === '') {
            throw new RuntimeException('Cal indicar el repositori de GitHub a la configuració d\'actualitzacions.');
        }

        if ($channel === 'release') {
            $data = self::api("repos/{$repo}/releases/latest");
            $version = ltrim((string) ($data['tag_name'] ?? ''), 'v');
            $result = [
                'version'      => $version,
                'sha'          => '',
                'notes'        => (string) ($data['body'] ?? ''),
                'published_at' => (string) ($data['published_at'] ?? ''),
            ];
        } else {
            $data = self::api("repos/{$repo}/commits/" . rawurlencode($branch));
            $sha = (string) ($data['sha'] ?? '');
            $result = [
                'version'      => substr($sha, 0, 7),
                'sha'          => $sha,
                'notes'        => (string) ($data['commit']['message'] ?? ''),
                'published_at' => (string) ($data['commit']['author']['date'] ?? ''),
            ];
        }

        $current = $channel === 'release' ? self::currentVersion() : (self::currentCommit() ?? self::currentVersion());

        $available = $channel === 'release'
            ? ($result['version'] !== '' && version_compare($result['version'], self::currentVersion(), '>'))
            : ($result['version'] !== '' && $result['version'] !== $current);

        Settings::setMany([
            'ota_last_check'      => date('Y-m-d H:i:s'),
            'ota_latest_version'  => $result['version'],
            'ota_latest_sha'      => $result['sha'],
        ]);

        return array_merge($result, [
            'available' => $available,
            'current'   => $current,
            'channel'   => $channel,
        ]);
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

            if ($strategy === 'git') {
                $this->applyGit();
            } else {
                $this->applyZip();
            }

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

    private function applyGit(): void
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
    }

    // ---------------------------------------------------------------- Zip

    private function applyZip(): void
    {
        $repo = trim((string) Settings::get('ota_repo'));
        $channel = (string) Settings::get('ota_channel', 'branch');
        $ref = $channel === 'release'
            ? (trim((string) Settings::get('ota_latest_version')) ?: trim((string) Settings::get('ota_branch', 'main')))
            : trim((string) Settings::get('ota_branch', 'main'));

        $tmpDir = self::root() . '/storage/tmp/update_' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('No s\'ha pogut crear el directori temporal d\'actualització.');
        }

        try {
            $zipFile = $tmpDir . '/package.zip';
            $this->note('Descarregant el paquet des de GitHub (' . $repo . ' · ' . $ref . ')…');
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

            $copied = $this->copyTree($sourceDir, self::root());
            $this->note($copied . ' fitxers actualitzats.');
        } finally {
            self::removeTree($tmpDir);
        }
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

    private static function api(string $path): array
    {
        $body = self::http('https://api.github.com/' . ltrim($path, '/'));
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta no vàlida de l\'API de GitHub.');
        }
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
        $options = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
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
            throw new RuntimeException('Error de xarxa en contactar amb GitHub: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('GitHub ha respost amb el codi ' . $status . '. Si el repositori és privat, cal configurar un token d\'accés.');
        }

        return $saveTo !== null ? '' : (string) $result;
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
