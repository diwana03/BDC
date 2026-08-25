<?php
declare(strict_types=1);

return static function(PDO $pdo):void {
    foreach ([
        ['bdc_dance_cup_events', 'bdc_dance_cup_competitions', 'bdc_dance_cup_judge_sessions'],
        ['bdc_test_dance_cup_events', 'bdc_test_dance_cup_competitions', 'bdc_test_dance_cup_judge_sessions'],
    ] as [$events, $competitions, $sessions]) {
        foreach ([
            "ALTER TABLE {$events} ADD COLUMN scoring_mode VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER country",
            "ALTER TABLE {$competitions} ADD COLUMN scoring_mode VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER round_name",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (Throwable) {}
        }
        $pdo->exec("UPDATE {$events} SET scoring_mode='manual' WHERE scoring_mode NOT IN ('manual','automatic') OR scoring_mode IS NULL");
        $pdo->exec("UPDATE {$competitions} SET scoring_mode='manual' WHERE scoring_mode NOT IN ('manual','automatic') OR scoring_mode IS NULL");
        // Old Manual status polling accidentally created untouched `not_started`
        // sessions. Only a session a judge actually opened or submitted proves
        // that the category was already operating as Automatic Scoring.
        try {
            $pdo->exec("UPDATE {$competitions} c SET c.scoring_mode='automatic' WHERE EXISTS (SELECT 1 FROM {$sessions} s WHERE s.competition_id=c.id AND (s.status<>'not_started' OR s.started_at IS NOT NULL OR s.submitted_at IS NOT NULL OR s.last_seen_at IS NOT NULL))");
            $pdo->exec("UPDATE {$events} e SET e.scoring_mode='automatic' WHERE EXISTS (SELECT 1 FROM {$competitions} c WHERE c.event_id=e.id AND c.scoring_mode='automatic')");
        } catch (Throwable) {}
    }
};
