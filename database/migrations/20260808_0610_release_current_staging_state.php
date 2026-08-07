<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    /*
     * Release state is intentionally a VARCHAR rather than a closed ENUM because
     * the Release Manager distinguishes physical installation from validation:
     * current_staging = files are running on Staging, but no worker-backed test
     * proof exists yet. This state must never unlock Production.
     */
    $pdo->exec("ALTER TABLE bdc_release_candidates MODIFY status VARCHAR(40) NOT NULL DEFAULT 'new'");

    // Remove any stale physical-state marker from a candidate that actually has
    // valid worker-backed Staging proof. The deployment pipeline remains the only
    // authority allowed to produce passed/approved.
    $pdo->exec("
        UPDATE bdc_release_candidates r
        JOIN bdc_deployment_jobs j
          ON j.release_id=r.id
         AND j.target_environment='staging'
         AND j.status='success'
         AND j.commit_sha=r.commit_sha
         AND COALESCE(j.output,'') LIKE '%Deployment worker began processing%'
         AND COALESCE(j.output,'') NOT LIKE '%Direct CLI deployment detected%'
        SET r.status='passed',
            r.staging_tested_sha=r.commit_sha,
            r.staged_at=COALESCE(r.staged_at,j.completed_at),
            r.passed_at=COALESCE(r.passed_at,j.completed_at)
        WHERE r.status='current_staging'
    ");
};
