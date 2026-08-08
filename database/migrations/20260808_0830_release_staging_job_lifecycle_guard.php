<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    /*
     * A real web-triggered Staging deployment owns the release state while its
     * job is queued/running.  The Release Manager's legacy direct-deployment
     * reconciliation must not be able to reinterpret that candidate mid-flight.
     *
     * `deploying` is deliberately outside the reconciliation set
     * (new/failed/queued/testing).  The terminal job trigger below moves the
     * candidate to passed/failed when the actual Staging job finishes.
     */
    $pdo->exec('DROP TRIGGER IF EXISTS trg_bdc_release_staging_active_state');
    $pdo->exec("
        CREATE TRIGGER trg_bdc_release_staging_active_state
        BEFORE UPDATE ON bdc_release_candidates
        FOR EACH ROW
        BEGIN
            IF NEW.status IN ('queued','testing')
               AND EXISTS (
                   SELECT 1
                   FROM bdc_deployment_jobs j
                   WHERE j.release_id=NEW.id
                     AND j.target_environment='staging'
                     AND j.status IN ('queued','running')
               )
            THEN
                SET NEW.status='deploying';
            END IF;
        END
    ");

    /*
     * Keep the release candidate synchronized with the real terminal result.
     * This also repairs the candidate correctly when recoverStaleJobs() closes
     * a genuinely abandoned Staging job.
     */
    $pdo->exec('DROP TRIGGER IF EXISTS trg_bdc_release_staging_terminal_state');
    $pdo->exec("
        CREATE TRIGGER trg_bdc_release_staging_terminal_state
        AFTER UPDATE ON bdc_deployment_jobs
        FOR EACH ROW
        BEGIN
            IF NEW.target_environment='staging'
               AND OLD.status IN ('queued','running')
               AND NEW.status='success'
            THEN
                UPDATE bdc_release_candidates
                SET status='passed',
                    staging_tested_sha=NEW.commit_sha,
                    staged_at=COALESCE(staged_at,NEW.completed_at,NOW()),
                    passed_at=COALESCE(NEW.completed_at,NOW())
                WHERE id=NEW.release_id;
            ELSEIF NEW.target_environment='staging'
               AND OLD.status IN ('queued','running')
               AND NEW.status='failed'
            THEN
                UPDATE bdc_release_candidates
                SET status='failed'
                WHERE id=NEW.release_id
                  AND status='deploying';
            END IF;
        END
    ");
};
