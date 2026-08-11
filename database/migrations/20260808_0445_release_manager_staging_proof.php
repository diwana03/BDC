<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    /*
     * A Git push/discovery must never become "Tested on Staging".
     * Only a successful web/worker Staging deployment for the exact candidate
     * and exact commit SHA counts as Staging proof. The historical direct-CLI
     * reconciliation path is deliberately excluded because it can infer a
     * deployment from version state without proving the deployment actually ran.
     */
    $pdo->exec("
        UPDATE bdc_release_candidates r
        LEFT JOIN bdc_deployment_jobs j
          ON j.release_id=r.id
         AND j.target_environment='staging'
         AND j.status='success'
         AND j.commit_sha=r.commit_sha
         AND COALESCE(j.output,'') NOT LIKE '%Direct CLI deployment detected%'
         AND COALESCE(j.output,'') LIKE '%Deployment worker began processing%'
        SET r.status=CASE WHEN r.status IN ('passed','approved') THEN 'new' ELSE r.status END,
            r.staging_tested_sha=NULL,
            r.staged_at=NULL,
            r.passed_at=NULL,
            r.approved_at=NULL,
            r.approved_by=NULL
        WHERE r.status IN ('passed','approved')
          AND j.id IS NULL
    ");

    $pdo->exec('DROP TRIGGER IF EXISTS bdc_release_exact_staging_guard');

    $pdo->exec("
        CREATE TRIGGER bdc_release_exact_staging_guard
        BEFORE UPDATE ON bdc_release_candidates
        FOR EACH ROW
        BEGIN
            IF (
                (NEW.status IN ('passed','approved') OR NEW.staging_tested_sha IS NOT NULL)
                AND NOT EXISTS (
                    SELECT 1
                    FROM bdc_deployment_jobs j
                    WHERE j.release_id=NEW.id
                      AND j.target_environment='staging'
                      AND j.status='success'
                      AND j.commit_sha=NEW.commit_sha
                      AND COALESCE(j.output,'') NOT LIKE '%Direct CLI deployment detected%'
                      AND COALESCE(j.output,'') LIKE '%Deployment worker began processing%'
                    LIMIT 1
                )
            ) THEN
                SET NEW.status='new';
                SET NEW.staging_tested_sha=NULL;
                SET NEW.staged_at=NULL;
                SET NEW.passed_at=NULL;
                SET NEW.approved_at=NULL;
                SET NEW.approved_by=NULL;
            END IF;
        END
    ");
};
