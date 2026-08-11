<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $sourceColumns = $pdo->query(
        "SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_competitors'
         ORDER BY ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC);

    $targetColumns = $pdo->query(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_test_competitors'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $existing = array_fill_keys(array_map('strval', $targetColumns), true);

    foreach ($sourceColumns as $column) {
        $name = (string)$column['COLUMN_NAME'];
        if ($name === 'id' || isset($existing[$name])) {
            continue;
        }

        $type = (string)$column['COLUMN_TYPE'];
        $nullable = (string)$column['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
        $default = '';
        if ($column['COLUMN_DEFAULT'] !== null) {
            $rawDefault = (string)$column['COLUMN_DEFAULT'];
            if (preg_match('/^CURRENT_TIMESTAMP(?:\(\d+\))?$/i', $rawDefault)) {
                $default = ' DEFAULT ' . $rawDefault;
            } else {
                $default = ' DEFAULT ' . $pdo->quote($rawDefault);
            }
        } elseif ((string)$column['IS_NULLABLE'] === 'YES') {
            $default = ' DEFAULT NULL';
        }

        $extra = trim((string)$column['EXTRA']);
        // Never mirror auto_increment/generated behaviour into the disposable test copy.
        if (stripos($extra, 'auto_increment') !== false || stripos($extra, 'generated') !== false) {
            $extra = '';
        }
        $extraSql = $extra !== '' ? ' ' . $extra : '';

        $identifier = '`' . str_replace('`', '``', $name) . '`';
        $pdo->exec(
            "ALTER TABLE bdc_test_competitors ADD COLUMN {$identifier} {$type}{$nullable}{$default}{$extraSql}"
        );
        $existing[$name] = true;
    }
};
