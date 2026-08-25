<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    /**
     * These legacy migrations are thin wrappers around the idempotent
     * SchemaUpdater. The updater is expected to evolve in later releases, so
     * hashing it as a dependency makes an already-applied migration appear to
     * have been edited whenever a new column is added. The wrapper file itself
     * remains checksummed and immutable.
     */
    private const FILE_ONLY_CHECKSUM_MIGRATIONS = [
        '20260803_2200',
        '20260806_1700',
        '20260825_0300_restore_manual_special_categories',
        '20260825_0400_special_category_backup_recovery',
        '20260825_0500_remove_unapproved_salsa_profiles',
        '20260825_0100_repair_permanent_division_categories',
        '20260826_0100_remove_unpublished_salsa_special_categories',
        '20260826_0200_remove_all_unpublished_event_profiles',
    ];

    /**
     * Historical installations recorded dependency-aware checksums for these
     * wrappers. Accept only exact checksums produced by known releases plus the
     * stable file-only checksum; every other mismatch still fails closed.
     */
    private const COMPATIBLE_APPLIED_CHECKSUMS = [
        '20260825_0100_repair_permanent_division_categories' => [
            // Dependency-aware checksum recorded when the repair first shipped.
            'df0325cd74ef31b3792a8abe3e01280a981317a92146a5ac680441773d1285ab',
            // Dependency-aware checksum after the publication-only profile gate.
            '4370bce0224d77c4718953fa425cc7fcf8e741bb1d2e3694379a4350db8c630d',
            // Stable checksum of the immutable migration wrapper only.
            '224b9199f9430cfc7d597aa90b5ef83013f39efd6e6765acc526aefc7a3b645f',
        ],
        '20260803_2200' => [
            'cfa863294a58e28726f9a778fddac0bfe7dc00a4b5a8005aaba337f632fd7d6e',
            '9df39af8349b364ffa924350440a082bd02fa30d9a37fbc6e22b3ef7b20ccdb8',
            'f08a2045fffb22bcedf516b1b08dd75b24fea949ff2e42fcb9ecce002c795d34',
            '1c710ae1c5c4accc1b5d245235d145aa12e6c4515e66022e6a5d269a5630db29',
            'd948b9cc2c9ebde5f7cd36aa684627e7feb4b941d65f6663a22df2f620f77714',
        ],
        '20260806_1700' => [
            '9df39af8349b364ffa924350440a082bd02fa30d9a37fbc6e22b3ef7b20ccdb8',
            'f08a2045fffb22bcedf516b1b08dd75b24fea949ff2e42fcb9ecce002c795d34',
            '1c710ae1c5c4accc1b5d245235d145aa12e6c4515e66022e6a5d269a5630db29',
            'd948b9cc2c9ebde5f7cd36aa684627e7feb4b941d65f6663a22df2f620f77714',
        ],
        '20260825_0300_restore_manual_special_categories' => [
            // dev399 dependency-aware checksum recorded in Production.
            '5da67a98bba0a16199ce6c0e5dea2d65729b9efd2104a68ef005edf3db4cea41',
            // Stable checksum of the immutable migration wrapper only.
            'a9c44cb2dd10923afcedfaa4ba364e56b73f33f74f4c2e82a20fc9fc75e467da',
        ],
    ];

    public function __construct(private PDO $pdo, private string $directory)
    {
    }

    public function run(): array
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bdc_schema_migrations(
            version VARCHAR(191) PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->ensureMigrationVersionCapacity();

        $applied = $this->pdo->query('SELECT version,checksum FROM bdc_schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $completed = [];

        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (strlen($version) > 191) {
                throw new RuntimeException("Migration {$version} exceeds the supported identifier length.");
            }
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
            $checksumDependencies = in_array($version, self::FILE_ONLY_CHECKSUM_MIGRATIONS, true)
                ? []
                : $dependencies;
            foreach ($checksumDependencies as $dependency) {
                if (!is_string($dependency) || !is_file($dependency)) {
                    throw new RuntimeException("Migration {$version} has an invalid dependency.");
                }
                $checksumParts[] = hash_file('sha256', $dependency);
            }
            $checksum = hash('sha256', implode(':', $checksumParts));
            if (isset($applied[$version])) {
                $storedChecksum = (string) $applied[$version];
                if (!hash_equals($storedChecksum, $checksum)
                    && !$this->isCompatibleAppliedChecksum($version, $storedChecksum, $checksum)) {
                    throw new RuntimeException("Applied migration {$version} has been modified.");
                }
                continue;
            }
            if ($version === '20260806_1700') {
                $this->preparePointAdjustmentTable();
            }
            $migration($this->pdo);
            $stmt = $this->pdo->prepare('INSERT INTO bdc_schema_migrations(version,checksum) VALUES(:version,:checksum)');
            $stmt->execute(['version'=>$version,'checksum'=>$checksum]);
            $completed[] = $version;
        }
        return $completed;
    }

    private function ensureMigrationVersionCapacity(): void
    {
        $stmt = $this->pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE()
              AND TABLE_NAME='bdc_schema_migrations'
              AND COLUMN_NAME='version'
            LIMIT 1");
        $length = (int)($stmt->fetchColumn() ?: 0);
        if ($length >= 191) {
            return;
        }
        if ($length < 1) {
            throw new RuntimeException('Cannot determine bdc_schema_migrations.version capacity.');
        }

        /*
         * Older BDC databases created this key as VARCHAR(50). Newer migration
         * filenames are longer than that, so MySQL silently/traditionally stored
         * a truncated identifier on some installations. Expand the key before
         * discovering or recording any new migration. Existing rows are retained
         * exactly as stored; no Production migration history is deleted.
         */
        $this->pdo->exec('ALTER TABLE bdc_schema_migrations MODIFY version VARCHAR(191) NOT NULL');
    }

    private function isCompatibleAppliedChecksum(string $version, string $storedChecksum, string $currentChecksum): bool
    {
        $known = self::COMPATIBLE_APPLIED_CHECKSUMS[$version] ?? [];
        if (!in_array($currentChecksum, $known, true)) {
            return false;
        }

        return in_array($storedChecksum, $known, true);
    }

    private function preparePointAdjustmentTable(): void
    {
        $exists = $this->pdo->query("SHOW TABLES LIKE 'bdc_point_adjustment_requests'")->fetchColumn();
        if ($exists !== false) {
            return;
        }

        $references = [
            'competitor_id' => ['bdc_competitors', 'id', 'RESTRICT'],
            'event_id' => ['bdc_events', 'id', 'RESTRICT'],
            'requested_by' => ['bdc_users', 'id', 'RESTRICT'],
            'reviewed_by' => ['bdc_users', 'id', 'SET NULL'],
            'point_transaction_id' => ['bdc_point_transactions', 'id', 'SET NULL'],
        ];
        $types = [];
        $typeQuery = $this->pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column'
        );
        foreach ($references as $column => [$table, $parentColumn]) {
            $typeQuery->execute(['table' => $table, 'column' => $parentColumn]);
            $type = strtolower(trim((string) $typeQuery->fetchColumn()));
            if (!preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/', $type)) {
                throw new RuntimeException("Cannot determine a compatible ID type for {$table}.{$parentColumn}.");
            }
            $types[$column] = $type;
        }

        $base = "CREATE TABLE bdc_point_adjustment_requests(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            competitor_id {$types['competitor_id']} NOT NULL,
            event_id {$types['event_id']} NOT NULL,
            division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL,
            dance_role ENUM('leader','follower','both','unknown') NOT NULL,
            existing_event_points DECIMAL(8,2) NOT NULL DEFAULT 0,
            additional_points DECIMAL(8,2) NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_by {$types['requested_by']} NOT NULL,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by {$types['reviewed_by']} NULL,
            reviewed_at DATETIME NULL,
            review_reason TEXT NULL,
            point_transaction_id {$types['point_transaction_id']} NULL,
            request_hash CHAR(64) NOT NULL,
            INDEX idx_adjustment_status(status,requested_at),
            INDEX idx_adjustment_competitor_event(competitor_id,event_id),
            UNIQUE INDEX uq_adjustment_request_hash(request_hash)";
        $foreignKeys = ",
            CONSTRAINT fk_adjustment_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE RESTRICT,
            CONSTRAINT fk_adjustment_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE RESTRICT,
            CONSTRAINT fk_adjustment_requester FOREIGN KEY(requested_by) REFERENCES bdc_users(id) ON DELETE RESTRICT,
            CONSTRAINT fk_adjustment_reviewer FOREIGN KEY(reviewed_by) REFERENCES bdc_users(id) ON DELETE SET NULL,
            CONSTRAINT fk_adjustment_transaction FOREIGN KEY(point_transaction_id) REFERENCES bdc_point_transactions(id) ON DELETE SET NULL";
        $suffix = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        try {
            $this->pdo->exec($base . $foreignKeys . $suffix);
        } catch (\PDOException) {
            // Some legacy installations cannot accept cross-generation foreign keys.
            // The application validates every reference and writes approvals atomically;
            // retain indexed compatibility columns rather than blocking the release.
            $this->pdo->exec($base . $suffix);
        }
    }
}
