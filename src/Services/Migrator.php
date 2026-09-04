<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use PDO;
use RuntimeException;

/**
 * Executa els fitxers .sql de /migrations que encara no s'hagin aplicat.
 * S'utilitza tant a la instal·lació com després de cada actualització OTA.
 */
final class Migrator
{
    public static function path(): string
    {
        return dirname(__DIR__, 2) . '/migrations';
    }

    /** @return string[] Noms de fitxer ordenats. */
    public static function available(): array
    {
        $files = glob(self::path() . '/*.sql') ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_NATURAL);
        return $names;
    }

    /** @return string[] */
    public static function applied(): array
    {
        if (!Db::tableExists('migrations')) {
            return [];
        }
        return array_column(Db::all('SELECT `filename` FROM `migrations` ORDER BY `filename`'), 'filename');
    }

    /** @return string[] */
    public static function pending(): array
    {
        return array_values(array_diff(self::available(), self::applied()));
    }

    /**
     * Aplica les migracions pendents.
     *
     * @return array{applied: string[], messages: string[]}
     */
    public static function run(): array
    {
        $applied = [];
        $messages = [];

        foreach (self::pending() as $filename) {
            $sql = (string) file_get_contents(self::path() . '/' . $filename);
            if (trim($sql) === '') {
                continue;
            }

            try {
                foreach (self::splitStatements($sql) as $statement) {
                    Db::pdo()->exec($statement);
                }
                Db::insert('migrations', ['filename' => $filename]);
                $applied[] = $filename;
                $messages[] = 'Migració aplicada: ' . $filename;
            } catch (\Throwable $e) {
                Logger::exception($e, 'Migració ' . $filename);
                throw new RuntimeException('Error a la migració ' . $filename . ': ' . $e->getMessage(), 0, $e);
            }
        }

        return ['applied' => $applied, 'messages' => $messages];
    }

    /**
     * Separa un fitxer SQL en sentències, ignorant els punts i coma que
     * apareguin dins de cadenes o comentaris.
     *
     * @return string[]
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $current .= $char;
                }
                continue;
            }
            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
                if ($char === ';') {
                    if (trim($current) !== '') {
                        $statements[] = trim($current);
                    }
                    $current = '';
                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && !$inBacktick && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }
}
