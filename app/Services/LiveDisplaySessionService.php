<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class LiveDisplaySessionService
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bdc_live_display_sessions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,data_mode ENUM('real','test') NOT NULL DEFAULT 'real',token_hash CHAR(64) NOT NULL,token_hint VARCHAR(12) NOT NULL,current_round_id BIGINT UNSIGNED NULL,screen_type VARCHAR(32) NOT NULL DEFAULT 'holding',page_number INT UNSIGNED NOT NULL DEFAULT 1,auto_page TINYINT(1) NOT NULL DEFAULT 1,page_delay_seconds INT UNSIGNED NOT NULL DEFAULT 30,is_enabled TINYINT(1) NOT NULL DEFAULT 1,state_version BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_live_display_event_mode(event_id,data_mode),UNIQUE INDEX uq_live_display_token(token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN results_unlocked TINYINT(1) NOT NULL DEFAULT 0 AFTER page_delay_seconds",
            );
        } catch (\Throwable) {
        }
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN token_value CHAR(48) NULL AFTER token_hint",
            );
        } catch (\Throwable) {
        }
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN reveal_place VARCHAR(8) NULL AFTER screen_type",
            );
        } catch (\Throwable) {
        }
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN loop_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER page_delay_seconds",
            );
        } catch (\Throwable) {
        }
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN loop_screens VARCHAR(255) NULL AFTER loop_enabled",
            );
        } catch (\Throwable) {
        }
        try {
            $pdo->exec(
                "ALTER TABLE bdc_live_display_sessions ADD COLUMN loop_delay_seconds INT UNSIGNED NOT NULL DEFAULT 15 AFTER loop_screens",
            );
        } catch (\Throwable) {
        }
        foreach(["ALTER TABLE bdc_live_display_sessions ADD COLUMN effect_type VARCHAR(24) NULL AFTER screen_type","ALTER TABLE bdc_live_display_sessions ADD COLUMN effect_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER effect_type"] as $sql){try{$pdo->exec($sql);}catch(\Throwable){}}
        foreach([
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN active_event_id BIGINT UNSIGNED NULL AFTER event_id",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN group_name VARCHAR(190) NULL AFTER data_mode",
        ] as $sql){try{$pdo->exec($sql);}catch(\Throwable){}}
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_live_display_session_events(session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(session_id,event_id),INDEX idx_live_display_member_event(event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public static function generate(
        PDO $pdo,
        int $eventId,
        bool $test,
        int $userId,
    ): string {
        self::ensure($pdo);
        $token = bin2hex(random_bytes(24));
        $pdo->prepare(
            "INSERT INTO bdc_live_display_sessions(event_id,active_event_id,data_mode,token_hash,token_hint,token_value,updated_by) VALUES(:e,:e,:m,:h,:hint,:token,:u) ON DUPLICATE KEY UPDATE active_event_id=VALUES(active_event_id),group_name=NULL,token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),token_value=VALUES(token_value),is_enabled=1,results_unlocked=0,screen_type='holding',reveal_place=NULL,loop_enabled=0,loop_screens=NULL,state_version=state_version+1,updated_by=VALUES(updated_by),updated_at=NOW()",
        )->execute([
            "e" => $eventId,
            "m" => $test ? "test" : "real",
            "h" => hash("sha256", $token),
            "hint" => substr($token, 0, 8),
            "token" => $token,
            "u" => $userId ?: null,
        ]);
        $session=self::forEvent($pdo,$eventId,$test);
        if($session){$pdo->prepare("DELETE FROM bdc_live_display_session_events WHERE session_id=:s")->execute(['s'=>$session['id']]);$pdo->prepare("INSERT INTO bdc_live_display_session_events(session_id,event_id,sort_order) VALUES(:s,:e,1)")->execute(['s'=>$session['id'],'e'=>$eventId]);}
        return $token;
    }
    public static function byId(PDO $pdo,int $sessionId,bool $test):?array
    {
        self::ensure($pdo);$s=$pdo->prepare("SELECT * FROM bdc_live_display_sessions WHERE id=:id AND data_mode=:m AND is_enabled=1 LIMIT 1");$s->execute(['id'=>$sessionId,'m'=>$test?'test':'real']);return $s->fetch()?:null;
    }
    public static function members(PDO $pdo,int $sessionId,bool $test):array
    {
        self::ensure($pdo);$eventTable=$test?'bdc_test_events':'bdc_events';$s=$pdo->prepare("SELECT e.id,e.name,e.event_date,se.sort_order FROM bdc_live_display_session_events se JOIN {$eventTable} e ON e.id=se.event_id WHERE se.session_id=:s ORDER BY se.sort_order,e.event_date,e.name");$s->execute(['s'=>$sessionId]);return $s->fetchAll();
    }
    public static function generateFestival(PDO $pdo,array $eventIds,bool $test,int $userId,string $name=''):array
    {
        self::ensure($pdo);$eventIds=array_values(array_unique(array_filter(array_map('intval',$eventIds),fn($id)=>$id>0)));if(count($eventIds)<2)throw new \RuntimeException('Select at least two events for a festival projection.');
        $eventTable=$test?'bdc_test_events':'bdc_events';$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$marks=implode(',',array_fill(0,count($eventIds),'?'));$q=$pdo->prepare("SELECT DISTINCT e.id FROM {$eventTable} e JOIN {$roundTable} r ON r.event_id=e.id WHERE e.id IN ({$marks})");$q->execute($eventIds);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if(count($valid)!==count($eventIds))throw new \RuntimeException('Every selected event must exist and contain at least one scoring round.');
        $token=self::generate($pdo,$eventIds[0],$test,$userId);$session=self::forEvent($pdo,$eventIds[0],$test);if(!$session)throw new \RuntimeException('Festival projection could not be created.');
        $label=trim($name);if($label==='')$label='Festival Projection';$pdo->prepare("UPDATE bdc_live_display_sessions SET group_name=:n,active_event_id=:e,current_round_id=NULL,screen_type='holding',effect_type=NULL,reveal_place=NULL,loop_enabled=0,loop_screens=NULL,state_version=state_version+1,updated_by=:u WHERE id=:s")->execute(['n'=>substr($label,0,190),'e'=>$eventIds[0],'u'=>$userId?:null,'s'=>$session['id']]);
        $pdo->prepare("DELETE FROM bdc_live_display_session_events WHERE session_id=:s")->execute(['s'=>$session['id']]);$add=$pdo->prepare("INSERT INTO bdc_live_display_session_events(session_id,event_id,sort_order) VALUES(:s,:e,:o)");foreach($eventIds as $i=>$id)$add->execute(['s'=>$session['id'],'e'=>$id,'o'=>$i+1]);
        return ['session'=>self::byId($pdo,(int)$session['id'],$test),'token'=>$token];
    }
    public static function byToken(PDO $pdo, string $token): ?array
    {
        self::ensure($pdo);
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $s = $pdo->prepare(
            "SELECT * FROM bdc_live_display_sessions WHERE token_hash=:h AND is_enabled=1 LIMIT 1",
        );
        $s->execute(["h" => hash("sha256", $token)]);
        return $s->fetch() ?: null;
    }
    public static function forEvent(PDO $pdo, int $eventId, bool $test): ?array
    {
        self::ensure($pdo);
        $s = $pdo->prepare(
            "SELECT * FROM bdc_live_display_sessions WHERE event_id=:e AND data_mode=:m AND is_enabled=1 LIMIT 1",
        );
        $s->execute(["e" => $eventId, "m" => $test ? "test" : "real"]);
        return $s->fetch() ?: null;
    }
    public static function setResultsUnlocked(
        PDO $pdo,
        int $eventId,
        bool $test,
        bool $unlocked,
        int $userId,
    ): array {
        self::ensure($pdo);
        $sql = $unlocked
            ? "UPDATE bdc_live_display_sessions SET results_unlocked=1,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1"
            : "UPDATE bdc_live_display_sessions SET results_unlocked=0,screen_type=CASE WHEN screen_type IN('final_results','results','winners') THEN 'holding' ELSE screen_type END,reveal_place=NULL,loop_enabled=0,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1";
        $pdo->prepare($sql)->execute([
            "u" => $userId ?: null,
            "e" => $eventId,
            "m" => $test ? "test" : "real",
        ]);
        return self::forEvent($pdo, $eventId, $test) ?: [];
    }
    public static function update(
        PDO $pdo,
        int $eventId,
        bool $test,
        array $v,
        int $userId,
    ): array {
        self::ensure($pdo);
        $current = self::forEvent($pdo, $eventId, $test);
        if (!$current) {
            throw new \RuntimeException(
                "Live Display link has not been generated.",
            );
        }
        $type = (string) ($v["screen_type"] ?? "holding");
        $allowed = [
            "holding",
            "judges",
            "competitors",
            "scoring",
            "callbacks",
            "finalists",
            "score_matrix",
            "heats_scores",
            "final_results",
            "results",
            "winners",
            "matching",
        ];
        if (!in_array($type, $allowed, true)) {
            $type = "holding";
        }
        if (
            in_array($type, ["final_results", "results", "winners"], true) &&
            empty($current["results_unlocked"])
        ) {
            throw new \RuntimeException(
                "Results Reveal is locked. Unlock it before projecting rankings or winners.",
            );
        }
        $delay = (int) ($v["page_delay_seconds"] ?? 30);
        if (!in_array($delay, [10, 15, 30, 45, 60], true)) {
            $delay = 30;
        }
        $reveal = null;
        if ($type === "winners") {
            $candidate = (string) ($v["reveal_place"] ?? "");
            if (in_array($candidate, ["5", "4", "3", "2", "1", "all"], true)) {
                $reveal = $candidate;
            }
        }
        // Only the explicit Lock action may clear reveal permission. Moving to
        // a non-result loop tab must not silently remove protected loop tabs.
        $relock = (int) ($current["results_unlocked"] ?? 0);
        $loopDelay =
            (int) ($v["loop_delay_seconds"] ??
                ($current["loop_delay_seconds"] ?? 15));
        if (!in_array($loopDelay, [5, 10, 15, 20, 30, 45, 60], true)) {
            $loopDelay = 15;
        }
        $loopAllowed = array_values(
            array_intersect(
                $allowed,
                array_filter(
                    array_map(
                        "trim",
                        explode(
                            ",",
                            (string) ($v["loop_screens"] ??
                                ($current["loop_screens"] ?? "")),
                        ),
                    ),
                ),
            ),
        );
        if (empty($current["results_unlocked"])) {
            $loopAllowed = array_values(
                array_diff($loopAllowed, [
                    "final_results",
                    "results",
                    "winners",
                ]),
            );
        }
        if (!empty($v["loop_enabled"]) && count($loopAllowed) < 2) {
            throw new \RuntimeException(
                "Select at least two available, unlocked tabs for the projector loop.",
            );
        }
        $loopEnabled = !empty($v["loop_enabled"]);
        $pdo->prepare(
            "UPDATE bdc_live_display_sessions SET current_round_id=:r,screen_type=:t,reveal_place=:rp,page_number=:p,auto_page=:a,page_delay_seconds=:d,results_unlocked=:lock,loop_enabled=:le,loop_screens=:ls,loop_delay_seconds=:ld,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1",
        )->execute([
            "r" => (int) ($v["round_id"] ?? 0) ?: null,
            "t" => $type,
            "rp" => $reveal,
            "p" => max(1, (int) ($v["page_number"] ?? 1)),
            "a" => !empty($v["auto_page"]) ? 1 : 0,
            "d" => $delay,
            "lock" => $relock,
            "le" => $loopEnabled ? 1 : 0,
            "ls" => $loopAllowed ? implode(",", $loopAllowed) : null,
            "ld" => $loopDelay,
            "u" => $userId ?: null,
            "e" => $eventId,
            "m" => $test ? "test" : "real",
        ]);
        return self::forEvent($pdo, $eventId, $test) ?: [];
    }
    public static function effect(PDO $pdo,int $eventId,bool $test,string $effect,int $userId):array
    {
        self::ensure($pdo);if(!in_array($effect,['none','countdown','drumroll','drumroll_1','drumroll_2','drumroll_3','drumroll_4','drumroll_5','fireworks','confetti','gold_rain','laser_sweep','champion_impact'],true))throw new \RuntimeException('Invalid presentation effect.');
        // Effects are an overlay channel. Do not increment state_version here:
        // reloading the underlying feed would hide or interrupt the overlay.
        $pdo->prepare("UPDATE bdc_live_display_sessions SET effect_type=:fx,effect_version=effect_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1")->execute(['fx'=>$effect==='none'?null:$effect,'u'=>$userId?:null,'e'=>$eventId,'m'=>$test?'test':'real']);
        return self::forEvent($pdo,$eventId,$test)?:[];
    }
    public static function beginSelection(PDO $pdo,int $eventId,int $roundId,bool $test,int $userId):array
    {
        self::ensure($pdo);
        $session=self::forEvent($pdo,$eventId,$test);if(!$session)throw new \RuntimeException('Live Display link has not been generated.');$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$q=$pdo->prepare("SELECT event_id FROM {$roundTable} WHERE id=:r LIMIT 1");$q->execute(['r'=>$roundId]);$activeEvent=(int)$q->fetchColumn();if($activeEvent<1)throw new \RuntimeException('Selected projection round was not found.');$member=$pdo->prepare("SELECT 1 FROM bdc_live_display_session_events WHERE session_id=:s AND event_id=:e");$member->execute(['s'=>$session['id'],'e'=>$activeEvent]);if(!$member->fetchColumn())throw new \RuntimeException('Selected event is not part of this festival projection.');
        $pdo->prepare("UPDATE bdc_live_display_sessions SET active_event_id=:ae,current_round_id=:r,screen_type='holding',effect_type=NULL,effect_version=effect_version+1,reveal_place=NULL,page_number=1,loop_enabled=0,loop_screens=NULL,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1")
            ->execute(['ae'=>$activeEvent,'r'=>$roundId,'u'=>$userId?:null,'e'=>$eventId,'m'=>$test?'test':'real']);
        return self::forEvent($pdo,$eventId,$test)?:[];
    }
}
