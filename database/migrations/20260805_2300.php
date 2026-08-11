<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_release_candidates(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        commit_sha CHAR(40) NOT NULL,
        version VARCHAR(50) NOT NULL,
        source_branch VARCHAR(120) NOT NULL DEFAULT 'develop',
        subject VARCHAR(500) NULL,
        release_notes LONGTEXT NULL,
        status ENUM('new','queued','testing','failed','passed','approved','production','rolled_back') NOT NULL DEFAULT 'new',
        staging_tested_sha CHAR(40) NULL,
        discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        staged_at DATETIME NULL,
        passed_at DATETIME NULL,
        approved_at DATETIME NULL,
        approved_by BIGINT UNSIGNED NULL,
        production_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_release_candidate_sha(commit_sha),
        INDEX idx_release_candidate_status(status,discovered_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_deployment_jobs(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        release_id BIGINT UNSIGNED NOT NULL,
        action ENUM('deploy_staging','approve','deploy_production','rollback_production') NOT NULL,
        target_environment ENUM('staging','production') NOT NULL,
        commit_sha CHAR(40) NOT NULL,
        status ENUM('queued','running','success','failed','cancelled') NOT NULL DEFAULT 'queued',
        requested_by BIGINT UNSIGNED NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        output LONGTEXT NULL,
        INDEX idx_deployment_jobs_queue(status,requested_at),
        INDEX idx_deployment_jobs_release(release_id,requested_at),
        CONSTRAINT fk_deployment_job_release FOREIGN KEY(release_id) REFERENCES bdc_release_candidates(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
