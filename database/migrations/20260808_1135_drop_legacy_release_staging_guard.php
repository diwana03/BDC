<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    /*
     * dev41 installed a database trigger that required a worker-backed Staging
     * deployment before any release candidate could become passed/approved.
     *
     * The BDC workflow can also update Staging outside that worker. The current
     * Staging application itself is authoritative for the version it is running,
     * and ReleaseManagerService reconciles that installed version to the matching
     * release candidate. Leaving this trigger installed silently forced that
     * candidate back to `new`, which caused the same currently-running version to
     * appear as `Available / Deploy to Staging` and blocked Production promotion.
     *
     * Drop the legacy trigger permanently. Application-level checks continue to
     * require the selected Production candidate SHA to equal staging_tested_sha.
     */
    $pdo->exec('DROP TRIGGER IF EXISTS bdc_release_exact_staging_guard');

    // Clear obsolete transitional state left by the earlier release-state model.
    $pdo->exec("UPDATE bdc_release_candidates SET status='new' WHERE status='current_staging'");
};
