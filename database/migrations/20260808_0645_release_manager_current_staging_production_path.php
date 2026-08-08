<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    /*
     * dev41's exact-worker trigger was too strict for BDC's existing automatic
     * GitHub -> Staging workflow. A release can be physically current on Staging
     * without having a Release Manager worker job, which made the UI offer the
     * same version for Staging again and hid the Production action.
     *
     * ReleaseManagerService now reconciles only the version actually running in
     * the current Staging application root. Production still requires the release
     * candidate's staging_tested_sha to equal its commit_sha.
     */
    $pdo->exec('DROP TRIGGER IF EXISTS bdc_release_exact_staging_guard');

    // Clear the obsolete intermediate state. The current Staging release will be
    // reconciled to passed by ReleaseManagerService on the next dashboard load.
    $pdo->exec("
        UPDATE bdc_release_candidates
        SET status='new',
            staging_tested_sha=NULL,
            passed_at=NULL,
            approved_at=NULL,
            approved_by=NULL
        WHERE status='current_staging'
    ");
};
