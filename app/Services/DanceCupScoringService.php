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
        $tables = self::tables($test);
        foreach ([
            "ALTER TABLE {$tables['events']} ADD COLUMN scoring_mode VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER country",
            "ALTER TABLE {$tables['competitions']} ADD COLUMN scoring_mode VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER round_name",
        ] as $definition) {
            try { $pdo->exec($definition); } catch (\Throwable) {}
        }
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
        $official = [
                ['name' => 'Timing', 'max' => 20.0],
                ['name' => 'Musicality & Choreography', 'max' => 20.0],
                ['name' => 'Difficulty', 'max' => 20.0],
                ['name' => $technique, 'max' => 20.0],
                ['name' => $presentation, 'max' => 20.0],
        ];
        return ['solo' => $official, 'couple' => $official, 'duo' => $official, 'team' => $official];
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
        $genderEligibility = (string) ($data['gender_eligibility'] ?? 'mixed');
        $performanceType = (string) ($data['performance_type'] ?? 'showcase');
        $scoringMode = (string) ($data['scoring_mode'] ?? 'manual');
        if ($eventId < 1 || $category === '') throw new RuntimeException('Event and category name are required.');
        if (!in_array($entryType, ['solo', 'couple', 'duo', 'pro_am', 'team'], true)) throw new RuntimeException('Invalid entry type.');
        if (!in_array($roundName, ['qualifier', 'quarterfinal', 'semifinal', 'final'], true)) throw new RuntimeException('Invalid Dance Cup round.');
        if (!in_array($danceStyle, ['salsa', 'bachata', 'cha_cha', 'other'], true)) throw new RuntimeException('Invalid dance style.');
        if (!in_array($level, ['amateur', 'intermediate', 'pro_am', 'professional', 'open'], true)) throw new RuntimeException('Invalid competition level.');
        if (!in_array($genderEligibility, ['mixed', 'female_only', 'male_only'], true)) throw new RuntimeException('Invalid category gender eligibility.');
        if (!in_array($performanceType, ['showcase', 'classic', 'cabaret', 'shines', 'just_dance'], true)) throw new RuntimeException('Invalid performance type.');
        if (!in_array($scoringMode, ['manual', 'automatic'], true)) throw new RuntimeException('Invalid scoring workflow.');
        $eventMode = $pdo->prepare("SELECT scoring_mode FROM {$tables['events']} WHERE id=:event");
        $eventMode->execute(['event' => $eventId]);
        if ((string) $eventMode->fetchColumn() !== $scoringMode) throw new RuntimeException('Choose an event created for this scoring workflow.');
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
            $insert = $pdo->prepare("INSERT INTO {$tables['competitions']}(event_id,category_name,entry_type,dance_style,competition_level,gender_eligibility,performance_type,round_name,scoring_mode,maximum_score,created_by) VALUES(:event,:category,:entry_type,:dance_style,:level,:gender_eligibility,:performance_type,:round_name,:scoring_mode,:maximum,:user)");
            $insert->execute([
                'event' => $eventId,
                'category' => $category,
                'entry_type' => $entryType,
                'dance_style' => $danceStyle,
                'level' => $level,
                'gender_eligibility' => $genderEligibility,
                'performance_type' => $performanceType,
                'round_name' => $roundName,
                'scoring_mode' => $scoringMode,
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

    public static function assertDanceCupEligibility(PDO $pdo,int $competitorId,int $competitionId,bool $test=false):void
    {
        $table=self::tables($test)['competitions'];$q=$pdo->prepare("SELECT dance_style,entry_type,competition_level,gender_eligibility FROM {$table} WHERE id=:id");$q->execute(['id'=>$competitionId]);$category=$q->fetch();if(!$category)throw new RuntimeException('Dance Cup category not found.');
        $q=$pdo->prepare("SELECT gender FROM bdc_competitors WHERE id=:id AND status<>'archived'");$q->execute(['id'=>$competitorId]);$gender=$q->fetchColumn();if($gender===false)throw new RuntimeException('Competitor profile not found.');
        if($category['gender_eligibility']==='female_only'&&$gender!=='female')throw new RuntimeException('This category is Female Only.');
        if($category['gender_eligibility']==='male_only'&&$gender!=='male')throw new RuntimeException('This category is Male Only.');
        // Reusable registration categories are organiser reference data, not a scoring-roster gate.
        // An authorised scorer may assign any active BDC competitor to an event category.
    }

    public static function assertScoringMode(PDO $pdo, int $competitionId, string $mode, bool $test = false): void
    {
        if (!in_array($mode, ['manual', 'automatic'], true)) throw new RuntimeException('Invalid scoring workflow.');
        self::ensureWorkspaceTables($pdo, $test);
        $table = self::tables($test)['competitions'];
        $query = $pdo->prepare("SELECT scoring_mode FROM {$table} WHERE id=:competition");
        $query->execute(['competition' => $competitionId]);
        $saved = $query->fetchColumn();
        if ($saved === false) throw new RuntimeException('Dance Cup category not found.');
        if (!hash_equals($mode, (string) $saved)) throw new RuntimeException('This category belongs to the '.ucfirst((string) $saved).' Scoring workflow.');
    }
    /** @return array<int,array<string,mixed>> */
    public static function calculateResults(PDO $pdo, int $competitionId, bool $test = false): array
    {
        self::ensureWorkspaceTables($pdo, $test);
        $tables = self::tables($test);
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        $count = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition");
        $count->execute(['competition' => $competitionId]);
        if ((int) $count->fetchColumn() < 1) throw new RuntimeException('Save at least one score before calculating results.');

        $query = $pdo->prepare("SELECT e.id,COALESCE(SUM(m.points),0) total FROM {$prefix}_entries e LEFT JOIN {$prefix}_marks m ON m.entry_id=e.id AND m.competition_id=e.competition_id WHERE e.competition_id=:competition AND e.status='active' GROUP BY e.id ORDER BY total DESC,e.bib_number,e.id");
        $query->execute(['competition' => $competitionId]);
        $rows = $query->fetchAll();
        if (!$rows) throw new RuntimeException('Add competitors before calculating results.');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM {$prefix}_scoring_results WHERE competition_id=:competition")->execute(['competition' => $competitionId]);
            $insert = $pdo->prepare("INSERT INTO {$prefix}_scoring_results(competition_id,entry_id,total_score,placement) VALUES(:competition,:entry,:total,:placement)");
            $placement = 0;
            $lastTotal = null;
            foreach ($rows as $index => $row) {
                $total = (float) $row['total'];
                if ($lastTotal === null || $total < $lastTotal) $placement = $index + 1;
                $insert->execute(['competition' => $competitionId, 'entry' => (int) $row['id'], 'total' => $total, 'placement' => $placement]);
                $lastTotal = $total;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return self::results($pdo, $competitionId, $test);
    }

    /** @return array<int,array<string,mixed>> */
    public static function results(PDO $pdo, int $competitionId, bool $test = false): array
    {
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        $query = $pdo->prepare("SELECT r.*,e.bib_number,e.display_name FROM {$prefix}_scoring_results r JOIN {$prefix}_entries e ON e.id=r.entry_id WHERE r.competition_id=:competition ORDER BY r.placement,e.bib_number,e.id");
        $query->execute(['competition' => $competitionId]);
        return $query->fetchAll();
    }

    /** Super Admin publication is the only path from locked Live scoring into permanent Dance Cup history. */
    public static function approveResults(PDO $pdo,int $competitionId,int $userId,bool $test=false,?string $notes=null):void
    {
        if($test)throw new RuntimeException('Test Dance Cup results cannot be published to permanent history.');
        $tables=self::tables(false);$prefix='bdc_dance_cup';
        $competition=$pdo->prepare("SELECT * FROM {$tables['competitions']} WHERE id=:competition FOR UPDATE");
        $competition->execute(['competition'=>$competitionId]);$row=$competition->fetch();
        if(!$row)throw new RuntimeException('Dance Cup category not found.');
        if((string)$row['status']!=='pending_approval')throw new RuntimeException('Only a submitted Dance Cup result awaiting approval can be published.');
        $results=$pdo->prepare("SELECT r.entry_id,r.total_score,r.placement,e.competitor_id,e.display_name FROM {$prefix}_scoring_results r JOIN {$prefix}_entries e ON e.id=r.entry_id AND e.competition_id=r.competition_id WHERE r.competition_id=:competition AND e.status='active' ORDER BY r.placement,e.id");
        $results->execute(['competition'=>$competitionId]);$rows=$results->fetchAll();
        if(!$rows)throw new RuntimeException('No calculated Dance Cup results are available for approval.');
        $active=$pdo->prepare("SELECT COUNT(*) FROM {$prefix}_entries WHERE competition_id=:competition AND status='active'");$active->execute(['competition'=>$competitionId]);
        if(count($rows)!==(int)$active->fetchColumn())throw new RuntimeException('Recalculate every active contestant before approval.');
        $save=$pdo->prepare("INSERT INTO bdc_dance_cup_result_history(competition_id,event_id,entry_id,competitor_id,display_name,dance_style,entry_type,competition_level,gender_eligibility,placement,total_score,approved_by,approved_at) VALUES(:competition,:event,:entry,:competitor,:name,:style,:entry_type,:level,:gender,:placement,:total,:user,NOW()) ON DUPLICATE KEY UPDATE competitor_id=VALUES(competitor_id),display_name=VALUES(display_name),placement=VALUES(placement),total_score=VALUES(total_score),approved_by=VALUES(approved_by),approved_at=NOW()");
        foreach($rows as $result)$save->execute(['competition'=>$competitionId,'event'=>$row['event_id'],'entry'=>$result['entry_id'],'competitor'=>$result['competitor_id']?:null,'name'=>$result['display_name'],'style'=>$row['dance_style'],'entry_type'=>$row['entry_type'],'level'=>$row['competition_level'],'gender'=>$row['gender_eligibility']??'mixed','placement'=>$result['placement'],'total'=>$result['total_score'],'user'=>$userId]);
        $pdo->prepare("UPDATE {$tables['competitions']} SET status='approved',approved_by=:user,approved_at=NOW(),approval_notes=:notes WHERE id=:competition")->execute(['user'=>$userId,'notes'=>$notes,'competition'=>$competitionId]);
    }

    /** @return array<string,mixed> */
    public static function workflowState(PDO $pdo, int $competitionId, bool $test = false): array
    {
        self::ensureAutomation($pdo, $competitionId, $test);
        $tables = self::tables($test);
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        $competition = $pdo->prepare("SELECT id,status FROM {$tables['competitions']} WHERE id=:competition");
        $competition->execute(['competition' => $competitionId]);
        $competitionRow = $competition->fetch();
        if (!$competitionRow) throw new RuntimeException('Dance Cup category not found.');

        $entryCount = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_entries WHERE competition_id=:competition AND status='active'");
        $entryCount->execute(['competition' => $competitionId]);
        $entries = (int) $entryCount->fetchColumn();
        $criterionQuery = $pdo->prepare("SELECT id,criterion_name,maximum_points,sort_order FROM {$tables['criteria']} WHERE competition_id=:competition ORDER BY sort_order,id");
        $criterionQuery->execute(['competition' => $competitionId]);
        $criterionRows = $criterionQuery->fetchAll();
        $criteria = count($criterionRows);
        $judgeCount = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=:competition");
        $judgeCount->execute(['competition' => $competitionId]);
        $judges = (int) $judgeCount->fetchColumn();
        $markCount = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition");
        $markCount->execute(['competition' => $competitionId]);
        $marks = (int) $markCount->fetchColumn();

        $sessions = $pdo->prepare("SELECT s.id,s.status,s.started_at,s.submitted_at,s.last_seen_at,j.id judge_assignment_id,j.judge_name,j.judge_order,j.is_chief,(SELECT COUNT(*) FROM {$prefix}_marks m WHERE m.competition_id=s.competition_id AND m.judge_id=s.judge_assignment_id) mark_count FROM {$prefix}_judge_sessions s JOIN {$prefix}_judges j ON j.id=s.judge_assignment_id WHERE s.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id");
        $sessions->execute(['competition' => $competitionId]);
        $sessionRows = $sessions->fetchAll();
        $perJudgeRequired = $entries * $criteria;
        $submitted = 0;
        $completed = 0;
        foreach ($sessionRows as &$session) {
            $session['required_count'] = $perJudgeRequired;
            $session['completed_count'] = min($perJudgeRequired, (int) $session['mark_count']);
            if ((int) $session['completed_count'] >= $perJudgeRequired && $perJudgeRequired > 0) $completed++;
            if ($session['status'] === 'submitted' && (int) $session['completed_count'] >= $perJudgeRequired && $perJudgeRequired > 0) $submitted++;
        }
        unset($session);

        $totals = $pdo->prepare("SELECT entry_id,judge_id,SUM(points) total FROM {$prefix}_marks WHERE competition_id=:competition GROUP BY entry_id,judge_id");
        $totals->execute(['competition' => $competitionId]);
        $rowTotals = [];
        foreach ($totals->fetchAll() as $row) $rowTotals[(int) $row['entry_id']][(int) $row['judge_id']] = (float) $row['total'];

        $markQuery = $pdo->prepare("SELECT entry_id,judge_id,criterion_id,points FROM {$prefix}_marks WHERE competition_id=:competition");
        $markQuery->execute(['competition' => $competitionId]);
        $markMatrix = [];
        foreach ($markQuery->fetchAll() as $row) $markMatrix[(int) $row['entry_id']][(int) $row['judge_id']][(int) $row['criterion_id']] = (float) $row['points'];

        $results = self::results($pdo, $competitionId, $test);
        $requiredMarks = $entries * $judges * $criteria;
        return [
            'competition_status' => (string) $competitionRow['status'],
            'entry_count' => $entries,
            'judge_count' => $judges,
            'criterion_count' => $criteria,
            'required_marks' => $requiredMarks,
            'mark_count' => $marks,
            'all_marks_complete' => $requiredMarks > 0 && $marks >= $requiredMarks,
            'completed_judges' => $completed,
            'submitted_judges' => $submitted,
            'all_judges_submitted' => $judges > 0 && $submitted === $judges,
            'sessions' => $sessionRows,
            'criteria' => $criterionRows,
            'mark_matrix' => $markMatrix,
            'row_totals' => $rowTotals,
            'results' => $results,
            'results_current' => $entries > 0 && count($results) === $entries,
        ];
    }

}
