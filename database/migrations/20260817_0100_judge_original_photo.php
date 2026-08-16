<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM bdc_judges')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('original_photo_url', $columns, true)) {
        $pdo->exec('ALTER TABLE bdc_judges ADD COLUMN original_photo_url VARCHAR(500) NULL AFTER photo_url');
    }
};
