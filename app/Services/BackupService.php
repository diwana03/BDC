<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

final class BackupService
{
    private string $root;
    private string $backupRoot;
    private string $logFile;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 2);
        $this->backupRoot = $this->root . '/storage/backups';
        $this->logFile = $this->root . '/storage/logs/backups.jsonl';
        foreach (['database', 'site', 'full'] as $type) {
            $dir = $this->backupRoot . '/' . $type;
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create backup directory: ' . $dir);
            }
        }
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0750, true);
    }

    public function createDatabaseBackup(?int $userId = null): array
    {
        $started = microtime(true);
        $stamp = date('Y-m-d_H-i-s');
        $name = 'BDC_DB_' . $stamp . '.sql.gz';
        $path = $this->backupRoot . '/database/' . $name;
        $tmp = $path . '.tmp';
        try {
            $pdo = Database::connection();
            $gz = gzopen($tmp, 'wb9');
            if ($gz === false) throw new RuntimeException('Unable to create compressed database backup.');
            $write = static function (string $text) use ($gz): void {
                if (gzwrite($gz, $text) === false) throw new RuntimeException('Unable to write database backup.');
            };
            $db = (string) Config::get('database.name', 'database');
            $write("-- BDC Competitor Dashboard database backup\n");
            $write('-- Created: ' . date(DATE_ATOM) . "\n");
            $write('-- Database: `' . str_replace('`', '``', $db) . "`\n\n");
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
            $tableCount = 0;
            $rowCount = 0;
            foreach ($tables as $row) {
                $table = (string) $row[0];
                $tableCount++;
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);
                $write("DROP TABLE IF EXISTS {$quoted};\n" . $create[1] . ";\n\n");
                $stmt = $pdo->query('SELECT * FROM ' . $quoted, PDO::FETCH_ASSOC);
                $columns = null;
                $batch = [];
                while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rowCount++;
                    if ($columns === null) $columns = array_keys($record);
                    $values = [];
                    foreach ($record as $value) {
                        $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                    }
                    $batch[] = '(' . implode(',', $values) . ')';
                    if (count($batch) >= 250) {
                        $this->writeInsertBatch($write, $quoted, $columns, $batch);
                        $batch = [];
                    }
                }
                if ($batch !== [] && $columns !== null) $this->writeInsertBatch($write, $quoted, $columns, $batch);
                $write("\n");
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            gzclose($gz);
            if (!rename($tmp, $path)) throw new RuntimeException('Unable to finalize database backup.');
            $result = $this->metadata('database', $name, $path, $started) + ['tables' => $tableCount, 'rows' => $rowCount];
            $this->log('database_backup', 'success', $result, $userId);
            return $result;
        } catch (\Throwable $e) {
            if (is_file($tmp)) @unlink($tmp);
            $this->log('database_backup', 'failed', ['message' => $e->getMessage()], $userId);
            throw $e;
        }
    }

    private function writeInsertBatch(callable $write, string $table, array $columns, array $batch): void
    {
        $quotedColumns = array_map(static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', $columns);
        $write('INSERT INTO ' . $table . ' (' . implode(',', $quotedColumns) . ") VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    public function createSiteBackup(?int $userId = null): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive extension is required for website backups.');
        $started = microtime(true);
        $name = 'BDC_SITE_' . date('Y-m-d_H-i-s') . '.zip';
        $path = $this->backupRoot . '/site/' . $name;
        $tmp = $path . '.tmp';
        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create website ZIP backup.');
            $baseLen = strlen($this->root) + 1;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                    function (\SplFileInfo $current): bool {
                        $relative = str_replace('\\', '/', substr($current->getPathname(), strlen($this->root) + 1));
                        foreach (['storage/backups', 'storage/logs', '.git', 'node_modules', 'cache', 'tmp'] as $excluded) {
                            if ($relative === $excluded || str_starts_with($relative, $excluded . '/')) return false;
                        }
                        return true;
                    }
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            $files = 0;
            foreach ($iterator as $item) {
                $relative = str_replace('\\', '/', substr($item->getPathname(), $baseLen));
                if ($item->isDir()) $zip->addEmptyDir($relative);
                elseif ($item->isFile()) { $zip->addFile($item->getPathname(), $relative); $files++; }
            }
            $zip->setArchiveComment('BDC Competitor Dashboard site backup created ' . date(DATE_ATOM));
            if (!$zip->close()) throw new RuntimeException('Unable to finalize website ZIP backup.');
            if (!rename($tmp, $path)) throw new RuntimeException('Unable to move website backup into place.');
            $result = $this->metadata('site', $name, $path, $started) + ['files' => $files];
            $this->log('site_backup', 'success', $result, $userId);
            return $result;
        } catch (\Throwable $e) {
            if (is_file($tmp)) @unlink($tmp);
            $this->log('site_backup', 'failed', ['message' => $e->getMessage()], $userId);
            throw $e;
        }
    }

    public function createFullBackup(?int $userId = null): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive extension is required for full backups.');
        $started = microtime(true);
        $db = $this->createDatabaseBackup($userId);
        $site = $this->createSiteBackup($userId);
        $name = 'BDC_FULL_' . date('Y-m-d_H-i-s') . '.zip';
        $path = $this->backupRoot . '/full/' . $name;
        $tmp = $path . '.tmp';
        $manifest = [
            'product' => 'BDC Competitor Dashboard',
            'version' => '1.0.0',
            'created_at' => date(DATE_ATOM),
            'database' => $db,
            'site' => $site,
        ];
        try {
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create full backup.');
            $zip->addFile($this->backupRoot . '/database/' . $db['name'], 'database/' . $db['name']);
            $zip->addFile($this->backupRoot . '/site/' . $site['name'], 'site/' . $site['name']);
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('RESTORE-INSTRUCTIONS.txt', "BDC Competitor Dashboard full recovery package\n\n1. Extract the site ZIP into the portal directory.\n2. Preserve or update config/config.php for the target server.\n3. Import the compressed SQL backup into MySQL.\n4. Confirm storage permissions and run health.php.\n");
            if (!$zip->close()) throw new RuntimeException('Unable to finalize full backup.');
            if (!rename($tmp, $path)) throw new RuntimeException('Unable to move full backup into place.');
            $result = $this->metadata('full', $name, $path, $started);
            $this->log('full_backup', 'success', $result, $userId);
            return $result;
        } catch (\Throwable $e) {
            if (is_file($tmp)) @unlink($tmp);
            $this->log('full_backup', 'failed', ['message' => $e->getMessage()], $userId);
            throw $e;
        }
    }

    public function listBackups(): array
    {
        $items = [];
        foreach (['database', 'site', 'full'] as $type) {
            foreach (glob($this->backupRoot . '/' . $type . '/*') ?: [] as $path) {
                if (!is_file($path) || str_ends_with($path, '.tmp')) continue;
                $items[] = [
                    'type' => $type,
                    'name' => basename($path),
                    'size' => filesize($path) ?: 0,
                    'created_at' => filemtime($path) ?: 0,
                    'checksum' => hash_file('sha256', $path) ?: '',
                ];
            }
        }
        usort($items, static fn(array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        return $items;
    }

    public function resolve(string $type, string $name): string
    {
        if (!in_array($type, ['database', 'site', 'full'], true)) throw new RuntimeException('Invalid backup type.');
        if ($name !== basename($name) || !preg_match('/^BDC_(DB|SITE|FULL)_[A-Za-z0-9_.-]+$/', $name)) throw new RuntimeException('Invalid backup name.');
        $path = $this->backupRoot . '/' . $type . '/' . $name;
        if (!is_file($path)) throw new RuntimeException('Backup file not found.');
        return $path;
    }

    public function delete(string $type, string $name, ?int $userId = null): void
    {
        $path = $this->resolve($type, $name);
        if (!unlink($path)) throw new RuntimeException('Unable to delete backup.');
        $this->log('backup_delete', 'success', ['type' => $type, 'name' => $name], $userId);
    }

    public function restoreDatabaseBackup(string $type,string $name,?int $userId=null):array
    {
        if(!in_array($type,['database','full'],true))throw new RuntimeException('Only Database or Full Portal backups can be applied from the web recovery screen.');
        $source=$this->resolve($type,$name);$temporary='';
        if($type==='full'){
            if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP ZipArchive is required to open a Full Portal backup.');
            $zip=new ZipArchive();if($zip->open($source)!==true)throw new RuntimeException('Full backup could not be opened.');
            $databaseEntry='';for($i=0;$i<$zip->numFiles;$i++){ $entry=(string)$zip->getNameIndex($i);if(str_starts_with($entry,'database/')&&str_ends_with($entry,'.sql.gz')){$databaseEntry=$entry;break;} }
            if($databaseEntry===''){$zip->close();throw new RuntimeException('Full backup does not contain a database recovery file.');}
            $temporary=tempnam(sys_get_temp_dir(),'bdc_restore_');if($temporary===false){$zip->close();throw new RuntimeException('Could not prepare the database recovery file.');}
            $stream=$zip->getStream($databaseEntry);$out=fopen($temporary,'wb');if($stream===false||$out===false){$zip->close();if(is_resource($out))fclose($out);@unlink($temporary);throw new RuntimeException('Could not extract the database recovery file.');}
            stream_copy_to_stream($stream,$out);fclose($stream);fclose($out);$zip->close();$source=$temporary;
        }
        $safety=$this->createDatabaseBackup($userId);
        try{
            $compressed=(string)file_get_contents($source);$sql=gzdecode($compressed);
            if($sql===false||!str_contains($sql,'BDC Competitor Dashboard database backup'))throw new RuntimeException('Backup validation failed. No database changes were applied.');
            Database::connection()->exec($sql);
            $result=['applied'=>$name,'safety_backup'=>$safety['name']];
            $this->log('database_restore','success',$result,$userId);return $result;
        }catch(\Throwable $e){$this->log('database_restore','failed',['name'=>$name,'safety_backup'=>$safety['name'],'message'=>$e->getMessage()],$userId);throw $e;}
        finally{if($temporary!==''&&is_file($temporary))@unlink($temporary);}
    }

    public function cleanup(int $keep, ?int $userId = null): int
    {
        $keep = max(1, min(100, $keep));
        $deleted = 0;
        foreach (['database', 'site', 'full'] as $type) {
            $files = glob($this->backupRoot . '/' . $type . '/*') ?: [];
            usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
            foreach (array_slice($files, $keep) as $file) {
                if (is_file($file) && unlink($file)) $deleted++;
            }
        }
        $this->log('backup_cleanup', 'success', ['keep' => $keep, 'deleted' => $deleted], $userId);
        return $deleted;
    }

    public function recentLogs(int $limit = 50): array
    {
        if (!is_file($this->logFile)) return [];
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice(array_reverse($lines), 0, max(1, min(200, $limit)));
        return array_values(array_filter(array_map(static fn(string $line): ?array => json_decode($line, true), $lines)));
    }

    public function systemHealth(): array
    {
        $pdo = Database::connection();
        $dbSizeStmt = $pdo->prepare('SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema=:db');
        $dbSizeStmt->execute(['db' => (string) Config::get('database.name')]);
        $last = $this->listBackups()[0] ?? null;
        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'zip_available' => class_exists(ZipArchive::class),
            'gzip_available' => function_exists('gzopen'),
            'database_size' => (int) $dbSizeStmt->fetchColumn(),
            'disk_free' => disk_free_space($this->root) ?: 0,
            'disk_total' => disk_total_space($this->root) ?: 0,
            'backup_writable' => is_writable($this->backupRoot),
            'last_backup' => $last,
        ];
    }

    private function metadata(string $type, string $name, string $path, float $started): array
    {
        return [
            'type' => $type,
            'name' => $name,
            'size' => filesize($path) ?: 0,
            'checksum' => hash_file('sha256', $path) ?: '',
            'duration_seconds' => round(microtime(true) - $started, 3),
            'created_at' => date(DATE_ATOM),
        ];
    }

    private function log(string $action, string $status, array $details, ?int $userId): void
    {
        $entry = ['created_at' => date(DATE_ATOM), 'action' => $action, 'status' => $status, 'user_id' => $userId, 'details' => $details];
        @file_put_contents($this->logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
