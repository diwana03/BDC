<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupScoringService
{
    /** @return array{competitions:string,criteria:string,events:string} */
    public static function tables(bool $test = false): array
    {
        return $test
            ? ['competitions' => 'bdc_test_dance_cup_competitions', 'criteria' => 'bdc_test_dance_cup_criteria', 'events' => 'bdc_test_dance_cup_events']
            : ['competitions' => 'bdc_dance_cup_competitions', 'criteria' => 'bdc_dance_cup_criteria', 'events' => 'bdc_dance_cup_events'];
    }

    /**
     * Keep the Dance Cup workspace recoverable when code is updated outside the
     * release manager and the matching migration has not been run yet.
     */
    public static function ensureWorkspaceTables(PDO $pdo, bool $test = false): void
    {
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_entries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NULL,bib_number INT UNSIGNED NOT NULL,display_name VARCHAR(190) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_entry_bib(competition_id,bib_number),UNIQUE KEY uq_dc_entry_competitor(competition_id,competitor_id),INDEX idx_dc_entry_comp(competition_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_judges(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,judge_id BIGINT UNSIGNED NULL,judge_name VARCHAR(190) NOT NULL,judge_order INT UNSIGNED NOT NULL DEFAULT 1,is_chief TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_judge_name(competition_id,judge_name),INDEX idx_dc_judge_comp(competition_id,judge_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_marks(competition_id BIGINT UNSIGNED NOT NULL,entry_id BIGINT UNSIGNED NOT NULL,judge_id BIGINT UNSIGNED NOT NULL,criterion_id BIGINT UNSIGNED NOT NULL,points DECIMAL(8,2) NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(competition_id,entry_id,judge_id,criterion_id),INDEX idx_dc_marks_comp(competition_id,judge_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // `_results` is a legacy public-history table in Live. Scoring results
        // intentionally use a distinct name so neither schema can overwrite it.
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_scoring_results(competition_id BIGINT UNSIGNED NOT NULL,entry_id BIGINT UNSIGNED NOT NULL,total_score DECIMAL(12,2) NOT NULL DEFAULT 0,placement INT UNSIGNED NOT NULL,calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(competition_id,entry_id),INDEX idx_dc_scoring_result_place(competition_id,placement)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_checkpoints(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,label VARCHAR(190) NOT NULL,snapshot_json LONGTEXT NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_dc_checkpoint_comp(competition_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_judge_sessions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,judge_assignment_id BIGINT UNSIGNED NOT NULL,access_token CHAR(64) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'not_started',started_at DATETIME NULL,submitted_at DATETIME NULL,last_seen_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_session_judge(competition_id,judge_assignment_id),UNIQUE KEY uq_dc_session_token(access_token),INDEX idx_dc_session_status(competition_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_projection(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,access_token CHAR(64) NOT NULL,screen_type VARCHAR(30) NOT NULL DEFAULT 'holding',theme VARCHAR(30) NOT NULL DEFAULT 'midnight_wine',state_version BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_projection_competition(competition_id),UNIQUE KEY uq_dc_projection_token(access_token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_event_projection(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,active_competition_id BIGINT UNSIGNED NOT NULL,active_entry_id BIGINT UNSIGNED NULL,access_token CHAR(64) NOT NULL,screen_type VARCHAR(30) NOT NULL DEFAULT 'holding',theme VARCHAR(30) NOT NULL DEFAULT 'midnight_wine',holding_title VARCHAR(190) NOT NULL DEFAULT 'Dance Cup',holding_message VARCHAR(255) NOT NULL DEFAULT 'Next contestant preparing',contestant_seconds INT UNSIGNED NOT NULL DEFAULT 12,holding_seconds INT UNSIGNED NOT NULL DEFAULT 8,auto_cycle TINYINT(1) NOT NULL DEFAULT 0,state_version BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_event_projection(event_id),UNIQUE KEY uq_dc_event_projection_token(access_token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            "page_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER auto_cycle",
            "auto_page TINYINT(1) NOT NULL DEFAULT 1 AFTER page_number",
            "page_delay INT UNSIGNED NOT NULL DEFAULT 10 AFTER auto_page",
        ] as $definition) {
            try { $pdo->exec("ALTER TABLE {$prefix}_event_projection ADD COLUMN ".$definition); } catch (\Throwable) {}
        }
    }

    public static function ensureAutomation(PDO $pdo, int $competitionId, bool $test = false): void
    {
        self::ensureWorkspaceTables($pdo, $test);
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        $judges = $pdo->prepare("SELECT id FROM {$prefix}_judges WHERE competition_id=:competition ORDER BY judge_order,id");
        $judges->execute(['competition' => $competitionId]);
        $session = $pdo->prepare("INSERT IGNORE INTO {$prefix}_judge_sessions(competition_id,judge_assignment_id,access_token) VALUES(:competition,:judge,:token)");
        foreach ($judges->fetchAll(PDO::FETCH_COLUMN) as $judgeId) {
            $session->execute(['competition' => $competitionId, 'judge' => (int) $judgeId, 'token' => bin2hex(random_bytes(32))]);
        }
        $event = $pdo->prepare("SELECT event_id FROM ".self::tables($test)['competitions']." WHERE id=:competition");
        $event->execute(['competition' => $competitionId]);
        $eventId = (int) $event->fetchColumn();
        if ($eventId > 0) {
            $projection = $pdo->prepare("INSERT IGNORE INTO {$prefix}_event_projection(event_id,active_competition_id,access_token) VALUES(:event,:competition,:token)");
            $projection->execute(['event' => $eventId, 'competition' => $competitionId, 'token' => bin2hex(random_bytes(32))]);
        }
    }

    /** @return array<string,array<int,array{name:string,max:float}>> */
    public static function defaultCriteriaTemplates(): array
    {
        $technique = 'Dance Style Technique / Authenticity';
        $presentation = 'Overall Presentation (Costume & Showmanship)';
        return [
            'solo' => [
                ['name' => 'Timing', 'max' => 20.0],
                ['name' => 'Musicality & Choreography', 'max' => 20.0],
                ['name' => 'Difficulty', 'max' => 20.0],
                ['name' => $technique, 'max' => 20.0],
                ['name' => $presentation, 'max' => 20.0],
            ],
            'couple' => [
                ['name' => 'Timing', 'max' => 20.0],
                ['name' => 'Musicality & Choreography', 'max' => 20.0],
                ['name' => 'Connection & Partnering', 'max' => 20.0],
                ['name' => $technique, 'max' => 20.0],
                ['name' => 'Difficulty', 'max' => 10.0],
                ['name' => $presentation, 'max' => 10.0],
            ],
            'duo' => [
                ['name' => 'Timing', 'max' => 20.0],
                ['name' => 'Musicality & Choreography', 'max' => 20.0],
                ['name' => 'Connection & Partnering', 'max' => 20.0],
                ['name' => $technique, 'max' => 20.0],
                ['name' => 'Difficulty', 'max' => 10.0],
                ['name' => $presentation, 'max' => 10.0],
            ],
            'team' => [
                ['name' => 'Timing', 'max' => 20.0],
                ['name' => 'Musicality & Choreography', 'max' => 20.0],
                ['name' => 'Synchronization & Teamwork', 'max' => 20.0],
                ['name' => $technique, 'max' => 20.0],
                ['name' => 'Difficulty', 'max' => 10.0],
                ['name' => $presentation, 'max' => 10.0],
            ],
        ];
    }

    /** @return array<int,array{name:string,max:float}> */
    public static function defaultCriteria(string $entryType): array
    {
        $templates = self::defaultCriteriaTemplates();
        return $templates[$entryType] ?? $templates['solo'];
    }

    /** @param array<int,array{name:string,max:float}> $criteria */
    public static function createCompetition(PDO $pdo, array $data, array $criteria, ?int $userId, bool $test = false): int
    {
        $tables = self::tables($test);
        $eventId = (int) ($data['event_id'] ?? 0);
        $category = trim((string) ($data['category_name'] ?? ''));
        $entryType = (string) ($data['entry_type'] ?? 'solo');
        $roundName = (string) ($data['round_name'] ?? 'final');
        $danceStyle = (string) ($data['dance_style'] ?? 'bachata');
        $level = (string) ($data['competition_level'] ?? 'open');
        $performanceType = (string) ($data['performance_type'] ?? 'showcase');
        if ($eventId < 1 || $category === '') throw new RuntimeException('Event and category name are required.');
        if (!in_array($entryType, ['solo', 'couple', 'duo', 'team'], true)) throw new RuntimeException('Invalid entry type.');
        if (!in_array($roundName, ['qualifier', 'quarterfinal', 'semifinal', 'final'], true)) throw new RuntimeException('Invalid Dance Cup round.');
        if (!in_array($danceStyle, ['salsa', 'bachata', 'cha_cha', 'other'], true)) throw new RuntimeException('Invalid dance style.');
        if (!in_array($level, ['amateur', 'intermediate', 'pro_am', 'professional', 'open'], true)) throw new RuntimeException('Invalid competition level.');
        if (!in_array($performanceType, ['showcase', 'classic', 'cabaret', 'shines', 'just_dance'], true)) throw new RuntimeException('Invalid performance type.');
        if (!$criteria) throw new RuntimeException('Add at least one scoring criterion.');
        $maximum = 0.0;
        $seen = [];
        foreach ($criteria as $criterion) {
            $name = trim($criterion['name']);
            $max = (float) $criterion['max'];
            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if ($name === '' || $max <= 0) throw new RuntimeException('Every criterion needs a name and a maximum above zero.');
            if (isset($seen[$key])) throw new RuntimeException('Criterion names must be unique.');
            $seen[$key] = true;
            $maximum += $max;
        }
        if ($maximum > 1000) throw new RuntimeException('The combined maximum score cannot exceed 1000.');

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO {$tables['competitions']}(event_id,category_name,entry_type,dance_style,competition_level,performance_type,round_name,maximum_score,created_by) VALUES(:event,:category,:entry_type,:dance_style,:level,:performance_type,:round_name,:maximum,:user)");
            $insert->execute([
                'event' => $eventId,
                'category' => $category,
                'entry_type' => $entryType,
                'dance_style' => $danceStyle,
                'level' => $level,
                'performance_type' => $performanceType,
                'round_name' => $roundName,
                'maximum' => $maximum,
                'user' => $userId ?: null,
            ]);
            $competitionId = (int) $pdo->lastInsertId();
            $criterionInsert = $pdo->prepare("INSERT INTO {$tables['criteria']}(competition_id,criterion_name,maximum_points,sort_order) VALUES(:competition,:name,:maximum,:sort)");
            foreach ($criteria as $index => $criterion) {
                $criterionInsert->execute(['competition' => $competitionId, 'name' => trim($criterion['name']), 'maximum' => (float) $criterion['max'], 'sort' => $index + 1]);
            }
            $pdo->commit();
            return $competitionId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
