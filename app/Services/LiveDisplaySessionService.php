<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class LiveDisplaySessionService
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS bdc_live_display_sessions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,data_mode ENUM('real','test') NOT NULL DEFAULT 'real',token_hash CHAR(64) NOT NULL,token_hint VARCHAR(12) NOT NULL,current_round_id BIGINT UNSIGNED NULL,screen_type VARCHAR(32) NOT NULL DEFAULT 'holding',page_number INT UNSIGNED NOT NULL DEFAULT 1,auto_page TINYINT(1) NOT NULL DEFAULT 1,page_delay_seconds INT UNSIGNED NOT NULL DEFAULT 15,is_enabled TINYINT(1) NOT NULL DEFAULT 1,state_version BIGINT UNSIGNED NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_live_display_event_mode(event_id,data_mode),UNIQUE INDEX uq_live_display_token(token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
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
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN holding_background_url VARCHAR(500) NULL AFTER group_name",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN playlist_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER loop_delay_seconds",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN playlist_position INT UNSIGNED NOT NULL DEFAULT 0 AFTER playlist_enabled",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN screen_theme VARCHAR(32) NOT NULL DEFAULT 'midnight_burgundy' AFTER holding_background_url",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN music_url VARCHAR(500) NULL AFTER screen_theme",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN music_name VARCHAR(190) NULL AFTER music_url",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN music_status VARCHAR(16) NOT NULL DEFAULT 'stopped' AFTER music_name",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN music_volume INT UNSIGNED NOT NULL DEFAULT 60 AFTER music_status",
            "ALTER TABLE bdc_live_display_sessions ADD COLUMN music_version BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER music_volume",
        ] as $sql){try{$pdo->exec($sql);}catch(\Throwable){}}
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_live_display_session_events(session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(session_id,event_id),INDEX idx_live_display_member_event(event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_live_display_playlist_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,round_id BIGINT UNSIGNED NOT NULL,screen_type ENUM('winners','final_results') NOT NULL,sort_order INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_live_playlist_order(session_id,sort_order),INDEX idx_live_playlist_session(session_id),INDEX idx_live_playlist_round(round_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
            "INSERT INTO bdc_live_display_sessions(event_id,active_event_id,data_mode,token_hash,token_hint,token_value,updated_by) VALUES(:e,:ae,:m,:h,:hint,:token,:u) ON DUPLICATE KEY UPDATE active_event_id=VALUES(active_event_id),group_name=NULL,token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),token_value=VALUES(token_value),is_enabled=1,results_unlocked=0,screen_type='holding',reveal_place=NULL,loop_enabled=0,loop_screens=NULL,playlist_enabled=0,playlist_position=0,state_version=state_version+1,updated_by=VALUES(updated_by),updated_at=NOW()",
        )->execute([
            "e" => $eventId,
            "ae" => $eventId,
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
        $label=trim($name);if($label==='')$label='Festival Projection';$pdo->prepare("UPDATE bdc_live_display_sessions SET group_name=:n,active_event_id=:e,current_round_id=NULL,screen_type='holding',effect_type=NULL,reveal_place=NULL,loop_enabled=0,loop_screens=NULL,playlist_enabled=0,playlist_position=0,state_version=state_version+1,updated_by=:u WHERE id=:s")->execute(['n'=>substr($label,0,190),'e'=>$eventIds[0],'u'=>$userId?:null,'s'=>$session['id']]);
        $pdo->prepare("DELETE FROM bdc_live_display_session_events WHERE session_id=:s")->execute(['s'=>$session['id']]);$add=$pdo->prepare("INSERT INTO bdc_live_display_session_events(session_id,event_id,sort_order) VALUES(:s,:e,:o)");foreach($eventIds as $i=>$id)$add->execute(['s'=>$session['id'],'e'=>$id,'o'=>$i+1]);
        return ['session'=>self::byId($pdo,(int)$session['id'],$test),'token'=>$token];
    }
    public static function byToken(PDO $pdo, string $token): ?array
    {
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
            "SELECT s.* FROM bdc_live_display_sessions s WHERE s.data_mode=:m AND s.is_enabled=1 AND (s.event_id=:e OR EXISTS(SELECT 1 FROM bdc_live_display_session_events se WHERE se.session_id=s.id AND se.event_id=:member)) ORDER BY (s.active_event_id=:active) DESC,(s.group_name IS NOT NULL) DESC,(s.event_id=:primary) DESC,s.id DESC LIMIT 1",
        );
        $s->execute(["e"=>$eventId,"member"=>$eventId,"active"=>$eventId,"primary"=>$eventId,"m"=>$test ? "test" : "real"]);
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
            : "UPDATE bdc_live_display_sessions SET results_unlocked=0,screen_type=CASE WHEN screen_type IN('final_results','results','winners') THEN 'holding' ELSE screen_type END,reveal_place=NULL,loop_enabled=0,playlist_enabled=0,playlist_position=0,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1";
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
        int $sessionId=0,
    ): array {
        self::ensure($pdo);
        $current = $sessionId>0 ? self::byId($pdo,$sessionId,$test) : self::forEvent($pdo, $eventId, $test);
        if (!$current) {
            throw new \RuntimeException(
                "Live Display link has not been generated.",
            );
        }
        $requestedRoundId = (int) ($v["round_id"] ?? 0);
        $activeEventId = $eventId;
        if ($requestedRoundId > 0) {
            $roundTable = $test ? "bdc_test_scoring_rounds" : "bdc_scoring_rounds";
            $roundEventQuery = $pdo->prepare("SELECT event_id FROM {$roundTable} WHERE id=:r LIMIT 1");
            $roundEventQuery->execute(["r" => $requestedRoundId]);
            $roundEventId = (int) $roundEventQuery->fetchColumn();
            if ($roundEventId < 1) {
                throw new \RuntimeException("Selected projection round was not found.");
            }
            $isMember = $roundEventId === (int) $current["event_id"];
            if (!$isMember) {
                $memberQuery = $pdo->prepare("SELECT 1 FROM bdc_live_display_session_events WHERE session_id=:s AND event_id=:e LIMIT 1");
                $memberQuery->execute(["s" => (int) $current["id"], "e" => $roundEventId]);
                $isMember = (bool) $memberQuery->fetchColumn();
            }
            if (!$isMember) {
                throw new \RuntimeException("Selected event is not part of this Live Display.");
            }
            $activeEventId = $roundEventId;
        }
        $type = (string) ($v["screen_type"] ?? "holding");
        $allowed = [
            "holding",
            "judges",
            "judge_call",
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
            "flights",
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
        $delay = (int) ($v["page_delay_seconds"] ?? 15);
        if (!in_array($delay, [5, 10, 15, 20, 30, 45, 60], true)) {
            $delay = 15;
        }
        $reveal = null;
        if ($type === "winners") {
            $candidate = (string) ($v["reveal_place"] ?? "");
            if (in_array($candidate, ["5", "4", "3", "2", "1", "all"], true)) {
                $reveal = $candidate;
            }
        }
        $page = max(1, (int) ($v["page_number"] ?? 1));
        if ($type === "judge_call") {
            $roundId = (int) ($v["round_id"] ?? 0);
            $judgeTable = $test ? "bdc_test_scoring_judges" : "bdc_scoring_judges";
            $judgeCount = 1;
            if ($roundId > 0) {
                $judgeCountQuery = $pdo->prepare("SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r");
                $judgeCountQuery->execute(["r" => $roundId]);
                $judgeCount = max(1, (int) $judgeCountQuery->fetchColumn());
            }
            $page = min($page, $judgeCount);
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
        $effect = (string) ($v["effect_type"] ?? "");
        $setEffect = $effect !== "";
        if ($setEffect && !in_array($effect, ['countdown'], true)) {
            throw new \RuntimeException("Invalid screen transition effect.");
        }
        $pdo->prepare(
            "UPDATE bdc_live_display_sessions SET active_event_id=:ae,current_round_id=:r,screen_type=:t,effect_type=CASE WHEN :set_fx=1 THEN :fx ELSE effect_type END,effect_version=effect_version+:fx_bump,reveal_place=:rp,page_number=:p,auto_page=:a,page_delay_seconds=:d,results_unlocked=:lock,loop_enabled=:le,loop_screens=:ls,loop_delay_seconds=:ld,playlist_enabled=0,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE id=:id AND data_mode=:m AND is_enabled=1",
        )->execute([
            "ae" => $activeEventId,
            "r" => $requestedRoundId ?: null,
            "t" => $type,
            "set_fx" => $setEffect ? 1 : 0,
            "fx" => $setEffect ? $effect : null,
            "fx_bump" => $setEffect ? 1 : 0,
            "rp" => $reveal,
            "p" => $page,
            "a" => !empty($v["auto_page"]) ? 1 : 0,
            "d" => $delay,
            "lock" => $relock,
            "le" => $loopEnabled ? 1 : 0,
            "ls" => $loopAllowed ? implode(",", $loopAllowed) : null,
            "ld" => $loopDelay,
            "u" => $userId ?: null,
            "id" => (int) $current["id"],
            "m" => $test ? "test" : "real",
        ]);
        return $sessionId>0 ? (self::byId($pdo,$sessionId,$test)?:[]) : (self::forEvent($pdo, $eventId, $test) ?: []);
    }
    public static function effect(PDO $pdo,int $eventId,bool $test,string $effect,int $userId,int $sessionId=0):array
    {
        self::ensure($pdo);if(!in_array($effect,['none','countdown','drumroll','drumroll_1','drumroll_2','drumroll_3','drumroll_4','drumroll_5','fireworks','confetti','hearts','balloons','heart_smiles','finger_hearts','gold_rain','laser_sweep','champion_impact'],true))throw new \RuntimeException('Invalid presentation effect.');
        $session=$sessionId>0?self::byId($pdo,$sessionId,$test):self::forEvent($pdo,$eventId,$test);if(!$session)throw new \RuntimeException('Generate the Live Display link first.');
        // Effects are an overlay channel. Do not increment state_version here:
        // reloading the underlying feed would hide or interrupt the overlay.
        $pdo->prepare("UPDATE bdc_live_display_sessions SET effect_type=:fx,effect_version=effect_version+1,updated_by=:u,updated_at=NOW() WHERE id=:id AND data_mode=:m AND is_enabled=1")->execute(['fx'=>$effect==='none'?null:$effect,'u'=>$userId?:null,'id'=>(int)$session['id'],'m'=>$test?'test':'real']);
        return self::byId($pdo,(int)$session['id'],$test)?:[];
    }
    public static function setTheme(PDO $pdo,int $eventId,bool $test,string $theme,int $userId):array
    {
        self::ensure($pdo);$allowed=['midnight_burgundy','obsidian_gold','ivory_burgundy','pearl_sapphire'];
        if(!in_array($theme,$allowed,true))throw new \RuntimeException('Choose one of the four projector screen styles.');
        $pdo->prepare("UPDATE bdc_live_display_sessions SET screen_theme=:theme,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1")->execute(['theme'=>$theme,'u'=>$userId?:null,'e'=>$eventId,'m'=>$test?'test':'real']);
        return self::forEvent($pdo,$eventId,$test)?:[];
    }
    public static function musicControl(PDO $pdo,int $eventId,bool $test,string $action,int $volume,int $userId):array
    {
        self::ensure($pdo);$session=self::forEvent($pdo,$eventId,$test);if(!$session)throw new \RuntimeException('Live Display link has not been generated.');
        $volume=max(0,min(100,$volume));$status=(string)($session['music_status']??'stopped');$url=(string)($session['music_url']??'');
        if($action==='music_play'){if($url==='')throw new \RuntimeException('Upload a music track first.');$status='playing';}
        elseif($action==='music_pause'){$status='paused';}
        elseif($action==='music_clear'){$status='stopped';$url='';}
        elseif($action!=='music_volume')throw new \RuntimeException('Invalid music command.');
        $clear=$action==='music_clear';$pdo->prepare("UPDATE bdc_live_display_sessions SET music_url=:url,music_name=CASE WHEN :clear=1 THEN NULL ELSE music_name END,music_status=:status,music_volume=:volume,music_version=music_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1")->execute(['url'=>$clear?null:$url,'clear'=>$clear?1:0,'status'=>$status,'volume'=>$volume,'u'=>$userId?:null,'e'=>$eventId,'m'=>$test?'test':'real']);
        return self::forEvent($pdo,$eventId,$test)?:[];
    }
    public static function beginSelection(PDO $pdo,int $eventId,int $roundId,bool $test,int $userId):array
    {
        self::ensure($pdo);
        $session=self::forEvent($pdo,$eventId,$test);if(!$session)throw new \RuntimeException('Live Display link has not been generated.');$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$q=$pdo->prepare("SELECT event_id FROM {$roundTable} WHERE id=:r LIMIT 1");$q->execute(['r'=>$roundId]);$activeEvent=(int)$q->fetchColumn();if($activeEvent<1)throw new \RuntimeException('Selected projection round was not found.');$member=$pdo->prepare("SELECT 1 FROM bdc_live_display_session_events WHERE session_id=:s AND event_id=:e");$member->execute(['s'=>$session['id'],'e'=>$activeEvent]);if(!$member->fetchColumn())throw new \RuntimeException('Selected event is not part of this festival projection.');
        $pdo->prepare("UPDATE bdc_live_display_sessions SET active_event_id=:ae,current_round_id=:r,screen_type='holding',effect_type=NULL,effect_version=effect_version+1,reveal_place=NULL,page_number=1,loop_enabled=0,loop_screens=NULL,playlist_enabled=0,playlist_position=0,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE event_id=:e AND data_mode=:m AND is_enabled=1")
            ->execute(['ae'=>$activeEvent,'r'=>$roundId,'u'=>$userId?:null,'e'=>$eventId,'m'=>$test?'test':'real']);
        return self::forEvent($pdo,$eventId,$test)?:[];
    }
    public static function saveFestivalPlaylist(PDO $pdo,int $sessionId,bool $test,array $values,int $delay,int $userId):array
    {
        self::ensure($pdo);$session=self::byId($pdo,$sessionId,$test);if(!$session||empty($session['group_name']))throw new \RuntimeException('Open a festival projection before creating its playlist.');$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$items=[];
        foreach($values as $value){if(!preg_match('/^(\d+):(winners|final_results)$/',(string)$value,$m))continue;$roundId=(int)$m[1];$q=$pdo->prepare("SELECT r.event_id FROM {$roundTable} r JOIN bdc_live_display_session_events se ON se.event_id=r.event_id AND se.session_id=:s WHERE r.id=:r AND r.round_type='final' LIMIT 1");$q->execute(['s'=>$sessionId,'r'=>$roundId]);$eventId=(int)$q->fetchColumn();if($eventId>0)$items[]=['event_id'=>$eventId,'round_id'=>$roundId,'screen_type'=>$m[2]];}
        if(count($items)<2)throw new \RuntimeException('Select at least two podium or Final score slides.');if(!in_array($delay,[5,10,15,20,30,45,60],true))$delay=15;
        $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM bdc_live_display_playlist_items WHERE session_id=:s')->execute(['s'=>$sessionId]);$add=$pdo->prepare('INSERT INTO bdc_live_display_playlist_items(session_id,event_id,round_id,screen_type,sort_order) VALUES(:s,:e,:r,:t,:o)');foreach($items as $i=>$item)$add->execute(['s'=>$sessionId,'e'=>$item['event_id'],'r'=>$item['round_id'],'t'=>$item['screen_type'],'o'=>$i+1]);$first=$items[0];$pdo->prepare("UPDATE bdc_live_display_sessions SET active_event_id=:e,current_round_id=:r,screen_type=:t,reveal_place=:rp,results_unlocked=1,loop_enabled=0,playlist_enabled=1,playlist_position=1,loop_delay_seconds=:d,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE id=:s")->execute(['e'=>$first['event_id'],'r'=>$first['round_id'],'t'=>$first['screen_type'],'rp'=>$first['screen_type']==='winners'?'all':null,'d'=>$delay,'u'=>$userId?:null,'s'=>$sessionId]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}return self::byId($pdo,$sessionId,$test)?:[];
    }
    public static function stopFestivalPlaylist(PDO $pdo,int $sessionId,bool $test,int $userId):array
    {
        self::ensure($pdo);$session=self::byId($pdo,$sessionId,$test);if(!$session)throw new \RuntimeException('Festival projection was not found.');$pdo->prepare("UPDATE bdc_live_display_sessions SET playlist_enabled=0,playlist_position=0,screen_type='holding',reveal_place=NULL,state_version=state_version+1,updated_by=:u,updated_at=NOW() WHERE id=:s")->execute(['u'=>$userId?:null,'s'=>$sessionId]);return self::byId($pdo,$sessionId,$test)?:[];
    }
    public static function advanceFestivalPlaylist(PDO $pdo,array $session):array
    {
        if(empty($session['playlist_enabled']))return $session;self::ensure($pdo);$q=$pdo->prepare('SELECT event_id,round_id,screen_type,sort_order FROM bdc_live_display_playlist_items WHERE session_id=:s ORDER BY sort_order');$q->execute(['s'=>$session['id']]);$items=$q->fetchAll();if(count($items)<2){$pdo->prepare("UPDATE bdc_live_display_sessions SET playlist_enabled=0,screen_type='holding',state_version=state_version+1 WHERE id=:s")->execute(['s'=>$session['id']]);return self::byId($pdo,(int)$session['id'],$session['data_mode']==='test')?:$session;}$position=max(1,(int)($session['playlist_position']??1));$next=$position>=count($items)?1:$position+1;$item=$items[$next-1];$pdo->prepare('UPDATE bdc_live_display_sessions SET active_event_id=:e,current_round_id=:r,screen_type=:t,reveal_place=:rp,playlist_position=:p,page_number=1,state_version=state_version+1,updated_at=NOW() WHERE id=:s AND playlist_enabled=1')->execute(['e'=>$item['event_id'],'r'=>$item['round_id'],'t'=>$item['screen_type'],'rp'=>$item['screen_type']==='winners'?'all':null,'p'=>$next,'s'=>$session['id']]);return self::byId($pdo,(int)$session['id'],$session['data_mode']==='test')?:$session;
    }
}
