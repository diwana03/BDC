<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    public function run(): array
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bdc_schema_migrations(
            version VARCHAR(50) PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $applied = $this->pdo->query('SELECT version,checksum FROM bdc_schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $completed = [];

        foreach ($files as $file) {
            $version = basename($file, '.php');
            $definition = require $file;
            $migration = $definition;
            $dependencies = [];
            if (is_array($definition)) {
                $migration = $definition['up'] ?? null;
                $dependencies = $definition['dependencies'] ?? [];
            }
            if (!is_callable($migration)) {
                throw new RuntimeException("Migration {$version} must provide a callable.");
            }
            if (!is_array($dependencies)) {
                throw new RuntimeException("Migration {$version} dependencies must be an array.");
            }
            $checksumParts = [hash_file('sha256', $file)];
            foreach ($dependencies as $dependency) {
                if (!is_string($dependency) || !is_file($dependency)) {
                    throw new RuntimeException("Migration {$version} has an invalid dependency.");
                }
                $checksumParts[] = hash_file('sha256', $dependency);
            }
            $checksum = hash('sha256', implode(':', $checksumParts));
            if (isset($applied[$version])) {
                if (!hash_equals((string)$applied[$version], $checksum)) {
                    throw new RuntimeException("Applied migration {$version} has been modified.");
                }
                continue;
            }
            $migration($this->pdo);
            $stmt = $this->pdo->prepare('INSERT INTO bdc_schema_migrations(version,checksum) VALUES(:version,:checksum)');
            $stmt->execute(['version'=>$version,'checksum'=>$checksum]);
            $completed[] = $version;
        }
        return $completed;
    }
}
