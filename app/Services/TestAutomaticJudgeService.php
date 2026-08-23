<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class TestAutomaticJudgeService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_test_scoring_judge_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id BIGINT UNSIGNED NOT NULL,
            judge_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_hint VARCHAR(12) NOT NULL,
            status ENUM('not_started','scoring','submitted') NOT NULL DEFAULT 'not_started',
            opened_at DATETIME NULL,
            last_saved_at DATETIME NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uq_test_judge_session_judge(judge_id),
            UNIQUE KEY uq_test_judge_session_token(token_hash),
            KEY idx_test_judge_session_round(round_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try{$pdo->exec("ALTER TABLE bdc_test_scoring_judge_sessions ADD COLUMN expires_at DATETIME NULL AFTER token_hint");}catch(\Throwable){}
        foreach(["criteria_version VARCHAR(32) NULL AFTER submitted_at","criteria_accepted_at DATETIME NULL AFTER criteria_version","unlocked_at DATETIME NULL AFTER criteria_accepted_at","unlocked_by BIGINT UNSIGNED NULL AFTER unlocked_at","unlock_reason VARCHAR(500) NULL AFTER unlocked_by"] as $definition){try{$pdo->exec("ALTER TABLE bdc_test_scoring_judge_sessions ADD COLUMN ".$definition);}catch(\Throwable){}}
        $pdo->exec("UPDATE bdc_test_scoring_judge_sessions SET expires_at=DATE_ADD(COALESCE(created_at,NOW()),INTERVAL 12 HOUR) WHERE expires_at IS NULL");
    }

    public static function syncRound(PDO $pdo, int $roundId): array
    {
        self::ensureSchema($pdo);
        $judges = self::judges($pdo, $roundId);
        $items = [];
        foreach ($judges as $judge) {
            $session = self::sessionForJudge($pdo, (int) $judge["id"]);
            $plain = "";
            if (!$session) {
                [$session, $plain] = self::createSession(
                    $pdo,
                    $roundId,
                    (int) $judge["id"],
                );
            }
            $items[] = $judge + [
                "session" => $session,
                "plain_token" => $plain,
            ];
        }
        return $items;
    }

    public static function regenerate(
        PDO $pdo,
        int $roundId,
        int $judgeId,
    ): string {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            "SELECT id FROM bdc_test_scoring_judges WHERE id=:judge AND round_id=:round",
        );
        $stmt->execute(["judge" => $judgeId, "round" => $roundId]);
        if (!(int) $stmt->fetchColumn()) {
            throw new RuntimeException("Test judge not found.");
        }
        $token = bin2hex(random_bytes(24));
        $pdo->prepare(
            "INSERT INTO bdc_test_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status,expires_at) VALUES(:round,:judge,:hash,:hint,'not_started',DATE_ADD(NOW(),INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),expires_at=VALUES(expires_at),updated_at=NOW()",
        )->execute([
            "round" => $roundId,
            "judge" => $judgeId,
            "hash" => hash("sha256", $token),
            "hint" => substr($token, 0, 8),
        ]);
        return $token;
    }

    public static function byToken(PDO $pdo, string $token): ?array
    {
        self::ensureSchema($pdo);
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = $pdo->prepare(
            "SELECT s.*,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,r.round_type,r.division,e.name event_name FROM bdc_test_scoring_judge_sessions s JOIN bdc_test_scoring_judges j ON j.id=s.judge_id JOIN bdc_test_scoring_rounds r ON r.id=s.round_id JOIN bdc_test_events e ON e.id=r.event_id WHERE s.token_hash=:hash AND s.expires_at>NOW() LIMIT 1",
        );
        $stmt->execute(["hash" => hash("sha256", $token)]);
        return $stmt->fetch() ?: null;
    }

    public static function markOpened(PDO $pdo, int $sessionId): void
    {
        $pdo->prepare(
            "UPDATE bdc_test_scoring_judge_sessions SET status=CASE WHEN status='not_started' THEN 'scoring' ELSE status END,opened_at=COALESCE(opened_at,NOW()) WHERE id=:id",
        )->execute(["id" => $sessionId]);
    }
    public static function markSaved(PDO $pdo, int $sessionId): void
    {
        $pdo->prepare(
            "UPDATE bdc_test_scoring_judge_sessions SET status='scoring',last_saved_at=NOW() WHERE id=:id AND status<>'submitted'",
        )->execute(["id" => $sessionId]);
    }
    public static function submit(PDO $pdo, int $sessionId): void
    {
        $stmt=$pdo->prepare(
            "UPDATE bdc_test_scoring_judge_sessions SET status='submitted',last_saved_at=NOW(),submitted_at=NOW() WHERE id=:id AND status<>'submitted'",
        );$stmt->execute(["id" => $sessionId]);
        if($stmt->rowCount()>0)ScoringBackupService::judgeSubmissionCheckpoint($pdo,$sessionId,true);
    }

    public static function acceptCriteria(PDO $pdo, int $sessionId): void
    {
        $pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET criteria_version=:version,criteria_accepted_at=NOW() WHERE id=:id")
            ->execute(['version'=>JudgingCriteriaService::VERSION,'id'=>$sessionId]);
    }

    public static function unlock(PDO $pdo,int $roundId,int $judgeId,int $userId,string $reason):void
    {
        $reason=trim($reason);
        if($reason==='')throw new RuntimeException('Enter a reason before reopening judge scoring.');
        $stmt=$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlocked_by=:user,unlock_reason=:reason WHERE round_id=:round AND judge_id=:judge AND status='submitted'");
        $stmt->execute(['user'=>$userId?:null,'reason'=>$reason,'round'=>$roundId,'judge'=>$judgeId]);
        if($stmt->rowCount()!==1)throw new RuntimeException('Submitted test judge session not found.');
    }

    public static function unlockAllSubmitted(PDO $pdo,int $roundId,int $userId,string $reason):array
    {
        $reason=trim($reason);
        if($reason==='')throw new RuntimeException('Enter an emergency reason before unlocking all test judge scores.');
        $submitted=$pdo->prepare("SELECT s.id,s.judge_id FROM bdc_test_scoring_judge_sessions s JOIN bdc_test_scoring_judges j ON j.id=s.judge_id WHERE j.round_id=:round AND s.id=(SELECT MAX(s2.id) FROM bdc_test_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) AND s.status='submitted' ORDER BY j.judge_order");
        $submitted->execute(['round'=>$roundId]);
        $sessions=$submitted->fetchAll();
        if(!$sessions)throw new RuntimeException('There are no locked test judge scores to unlock.');
        $ids=array_map(static fn(array $row):int=>(int)$row['id'],$sessions);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlocked_by=?,unlock_reason=? WHERE id IN ($placeholders) AND status='submitted'");
        $stmt->execute(array_merge([$userId?:null,$reason],$ids));
        return ['count'=>$stmt->rowCount(),'judge_ids'=>array_map(static fn(array $row):int=>(int)$row['judge_id'],$sessions)];
    }

    public static function submitJudge(
        PDO $pdo,
        int $roundId,
        int $judgeId,
    ): void {
        $stmt = $pdo->prepare(
            "UPDATE bdc_test_scoring_judge_sessions SET status='submitted',opened_at=COALESCE(opened_at,NOW()),last_saved_at=NOW(),submitted_at=NOW() WHERE round_id=:round AND judge_id=:judge",
        );
        $stmt->execute(["round" => $roundId, "judge" => $judgeId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException("Test judge session not found.");
        }
    }

    public static function allSubmitted(PDO $pdo, int $roundId): bool
    {
        self::syncRound($pdo, $roundId);
        self::repairIncompleteFinalSubmissions($pdo,$roundId);
        $roundStmt=$pdo->prepare("SELECT round_type FROM bdc_test_scoring_rounds WHERE id=:round");$roundStmt->execute(['round'=>$roundId]);
        if((string)$roundStmt->fetchColumn()!=='final'){
            $required=array_values(array_filter(self::progress($pdo,$roundId),static fn(array $row):bool=>(int)($row['total']??0)>0));
            if(count($required)<3)return false;
            foreach($required as $row)if((string)($row['session_status']??'not_started')!=='submitted')return false;
            return true;
        }
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM bdc_test_scoring_judges WHERE round_id=:round",
        );
        $count->execute(["round" => $roundId]);
        if ((int) $count->fetchColumn() < 3) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bdc_test_scoring_judges j LEFT JOIN bdc_test_scoring_judge_sessions s ON s.id=(SELECT MAX(s2.id) FROM bdc_test_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) WHERE j.round_id=:round AND COALESCE(s.status,'not_started')<>'submitted'",
        );
        $stmt->execute(["round" => $roundId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private static function repairIncompleteFinalSubmissions(PDO $pdo,int $roundId):void
    {
        $roundStmt=$pdo->prepare("SELECT round_type,callback_count FROM bdc_test_scoring_rounds WHERE id=:round");$roundStmt->execute(["round"=>$roundId]);$round=$roundStmt->fetch();if(!$round||$round["round_type"]!=="final")return;
        $cleanup=$pdo->prepare("DELETE fm FROM bdc_test_scoring_final_marks fm LEFT JOIN bdc_test_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' LEFT JOIN bdc_test_scoring_judges j ON j.id=fm.judge_id AND j.round_id=fm.round_id WHERE fm.round_id=:round AND (fp.id IS NULL OR j.id IS NULL)");$cleanup->execute(["round"=>$roundId]);
        $pairCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");$pairCount->execute(["round"=>$roundId]);$required=min((int)$pairCount->fetchColumn(),max(1,(int)($round["callback_count"]??1)));if($required<1)return;
        $sessions=$pdo->prepare("SELECT s.id,s.judge_id FROM bdc_test_scoring_judge_sessions s JOIN bdc_test_scoring_judges j ON j.id=s.judge_id WHERE j.round_id=:round AND s.status='submitted' AND s.id=(SELECT MAX(s2.id) FROM bdc_test_scoring_judge_sessions s2 WHERE s2.judge_id=j.id)");$sessions->execute(["round"=>$roundId]);
        $valid=$pdo->prepare("SELECT COUNT(*) total,COUNT(DISTINCT fm.rank_value) unique_total,MIN(fm.rank_value) minimum,MAX(fm.rank_value) maximum FROM bdc_test_scoring_final_marks fm JOIN bdc_test_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' WHERE fm.round_id=:round AND fm.judge_id=:judge AND fm.rank_value IS NOT NULL");
        $reopen=$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlock_reason='System reopened an incomplete Final submission after pairing data changed.' WHERE id=:id AND status='submitted'");
        foreach($sessions->fetchAll() as $session){$valid->execute(["round"=>$roundId,"judge"=>$session["judge_id"]]);$state=$valid->fetch()?:[];if((int)($state["total"]??0)!==$required||(int)($state["unique_total"]??0)!==$required||(int)($state["minimum"]??0)!==1||(int)($state["maximum"]??0)!==$required)$reopen->execute(["id"=>$session["id"]]);}
    }

    public static function generateAndSubmitAll(PDO $pdo, int $roundId): void
    {
        self::generateAll($pdo, $roundId, true);
    }

    public static function generateAllDraft(PDO $pdo, int $roundId): void
    {
        self::generateAll($pdo, $roundId, false);
    }

    private static function generateAll(
        PDO $pdo,
        int $roundId,
        bool $submit,
    ): void {
        $roundStmt = $pdo->prepare(
            "SELECT * FROM bdc_test_scoring_rounds WHERE id=:round",
        );
        $roundStmt->execute(["round" => $roundId]);
        $round = $roundStmt->fetch();
        if (!$round) {
            throw new RuntimeException("Test round not found.");
        }
        $judges = self::judges($pdo, $roundId);
        if (count($judges) < 3) {
            throw new RuntimeException("Save at least 3 judges first.");
        }
        foreach ($judges as $judge) {
            $judgeId = (int) $judge["id"];
            if ((string) $round["round_type"] === "final") {
                $q = $pdo->prepare(
                    "SELECT id FROM bdc_test_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed' ORDER BY pair_number",
                );
                $q->execute(["round" => $roundId]);
                $pairs = array_map("intval", $q->fetchAll(PDO::FETCH_COLUMN));
                if (!$pairs) {
                    throw new RuntimeException("Confirm Final pairing first.");
                }
                shuffle($pairs);
                $rankLimit = min(count($pairs), max(1, (int) ($round["callback_count"] ?? count($pairs))));
                $pairs = array_slice($pairs, 0, $rankLimit);
                $ranks = range(1, $rankLimit);
                shuffle($ranks);
                $pdo->prepare(
                    "DELETE FROM bdc_test_scoring_final_marks WHERE round_id=:round AND judge_id=:judge",
                )->execute(["round" => $roundId, "judge" => $judgeId]);
                $insert = $pdo->prepare(
                    "INSERT INTO bdc_test_scoring_final_marks(round_id,pair_id,judge_id,rank_value) VALUES(:round,:pair,:judge,:rank)",
                );
                foreach ($pairs as $i => $pair) {
                    $insert->execute([
                        "round" => $roundId,
                        "pair" => $pair,
                        "judge" => $judgeId,
                        "rank" => $ranks[$i],
                    ]);
                }
            } else {
                $pdo->prepare(
                    "DELETE FROM bdc_test_scoring_marks WHERE round_id=:round AND judge_id=:judge",
                )->execute(["round" => $roundId, "judge" => $judgeId]);
                $insert = $pdo->prepare(
                    "INSERT INTO bdc_test_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score) VALUES(:round,:entry,:judge,:type,:alt,:weight)",
                );
                foreach (["leader", "follower"] as $role) {
                    if (
                        !in_array(
                            (string) $judge["scoring_scope"],
                            ["all", $role],
                            true,
                        )
                    ) {
                        continue;
                    }
                    $q = $pdo->prepare(
                        "SELECT id FROM bdc_test_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active' ORDER BY id",
                    );
                    $q->execute(["round" => $roundId, "role" => $role]);
                    $ids = array_map("intval", $q->fetchAll(PDO::FETCH_COLUMN));
                    shuffle($ids);
                    $yes = min((int) $round["yes_count"], count($ids));
                    for ($i = 0; $i < $yes; $i++) {
                        $insert->execute([
                            "round" => $roundId,
                            "entry" => $ids[$i],
                            "judge" => $judgeId,
                            "type" => "yes",
                            "alt" => null,
                            "weight" => $round["yes_weight"],
                        ]);
                    }
                    $cursor = $yes;
                    foreach (
                        [
                            1 => "alt1_weight",
                            2 => "alt2_weight",
                            3 => "alt3_weight",
                        ]
                        as $alt => $weight
                    ) {
                        if (isset($ids[$cursor])) {
                            $insert->execute([
                                "round" => $roundId,
                                "entry" => $ids[$cursor++],
                                "judge" => $judgeId,
                                "type" => "alt",
                                "alt" => $alt,
                                "weight" => $round[$weight],
                            ]);
                        }
                    }
                }
            }
            if ($submit) {
                self::submitJudge($pdo, $roundId, $judgeId);
            } else {
                $session = $pdo->prepare(
                    "UPDATE bdc_test_scoring_judge_sessions SET status='scoring',opened_at=COALESCE(opened_at,NOW()),last_saved_at=NOW(),submitted_at=NULL WHERE round_id=:round AND judge_id=:judge",
                );
                $session->execute(["round" => $roundId, "judge" => $judgeId]);
            }
        }
    }

    public static function progress(PDO $pdo, int $roundId): array
    {
        self::ensureSchema($pdo);
        $roundStmt = $pdo->prepare(
            "SELECT round_type,yes_count,callback_count FROM bdc_test_scoring_rounds WHERE id=:round",
        );
        $roundStmt->execute(["round" => $roundId]);
        $round = $roundStmt->fetch() ?: [
            "round_type" => "heats",
            "yes_count" => 10,
        ];
        $final = (string) $round["round_type"] === "final";
        if ($final) {
            self::repairIncompleteFinalSubmissions($pdo, $roundId);
        }
        $yesLimit = max(0, (int) $round["yes_count"]);
        $roleCountStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
        $roleCountStmt->execute(['round'=>$roundId]);$roleCounts=['leader'=>0,'follower'=>0];
        foreach($roleCountStmt->fetchAll() as $countRow){$countRole=(string)$countRow['dance_role'];if(isset($roleCounts[$countRole]))$roleCounts[$countRole]=(int)$countRow['total'];}
        $rolePlan=RoleAdvancementService::roundPlan($roleCounts['leader'],$roleCounts['follower'],$yesLimit);
        $stmt = $pdo->prepare(
            "SELECT j.id judge_id,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,COALESCE(s.status,'not_started') session_status,s.token_hint,s.opened_at,s.last_saved_at,s.submitted_at FROM bdc_test_scoring_judges j LEFT JOIN bdc_test_scoring_judge_sessions s ON s.id=(SELECT MAX(s2.id) FROM bdc_test_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) WHERE j.round_id=:round ORDER BY j.is_chief DESC,j.judge_order,j.id",
        );
        $stmt->execute(["round" => $roundId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $submitted = (string) $row["session_status"] === "submitted";
            if ($final) {
                $totalStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM bdc_test_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'",
                );
                $totalStmt->execute(["round" => $roundId]);
                $total = (int) $totalStmt->fetchColumn();
                $total = min($total, max(1, (int) ($round["callback_count"] ?? $total)));
                $doneStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM bdc_test_scoring_final_marks fm JOIN bdc_test_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' JOIN bdc_test_scoring_judges j ON j.id=fm.judge_id AND j.round_id=fm.round_id WHERE fm.round_id=:round AND fm.judge_id=:judge AND fm.rank_value IS NOT NULL",
                );
                $doneStmt->execute([
                    "round" => $roundId,
                    "judge" => $row["judge_id"],
                ]);
                $done = (int) $doneStmt->fetchColumn();
                $row["leaders_done"] = $done;
                $row["leaders_total"] = $total;
                $row["followers_done"] = 0;
                $row["followers_total"] = 0;
                $row["done"] = $done;
                $row["total"] = $total;
            } else {
                $scope = (string) $row["scoring_scope"];
                $counts = [];
                foreach (["leader", "follower"] as $role) {
                    $allowed = $scope === "all" || $scope === $role;
                    if (!$allowed || !($rolePlan[$role]['requires_judging']??false)) {
                        $counts[$role] = [0, 0];
                        continue;
                    }
                    $q = $pdo->prepare(
                        "SELECT COUNT(*) FROM bdc_test_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active'",
                    );
                    $q->execute(["round" => $roundId, "role" => $role]);
                    $active = (int) $q->fetchColumn();
                    $target = min($active, $yesLimit + 3);
                    $q = $pdo->prepare(
                        "SELECT COUNT(*) FROM bdc_test_scoring_marks m JOIN bdc_test_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.mark_type IN('yes','alt')",
                    );
                    $q->execute([
                        "round" => $roundId,
                        "judge" => $row["judge_id"],
                        "role" => $role,
                    ]);
                    $done = min((int) $q->fetchColumn(), $target);
                    if ($submitted) {
                        $done = $target;
                    }
                    $counts[$role] = [$done, $target];
                }
                [$ld, $lt] = $counts["leader"];
                [$fd, $ft] = $counts["follower"];
                $row["leaders_done"] = $ld;
                $row["leaders_total"] = $lt;
                $row["followers_done"] = $fd;
                $row["followers_total"] = $ft;
                $row["done"] = $ld + $fd;
                $row["total"] = $lt + $ft;
            }
            $row["percent"] = $submitted
                ? 100
                : ($row["total"] > 0
                    ? (int) round(($row["done"] * 100) / $row["total"])
                    : 0);
        }
        unset($row);
        return $rows;
    }

    public static function publicUrl(string $token): string
    {
        $path = url("test-judge-scoring/?token=" . rawurlencode($token));
        $appUrl = rtrim((string) Config::get("app.url", ""), "/");
        if ($appUrl === "") {
            return $path;
        }
        $parts = parse_url($appUrl);
        if (!is_array($parts) || !isset($parts["scheme"], $parts["host"])) {
            return $path;
        }
        return $parts["scheme"] .
            "://" .
            $parts["host"] .
            (isset($parts["port"]) ? ":" . (int) $parts["port"] : "") .
            $path;
    }

    public static function clearJudgeSessions(PDO $pdo, int $roundId): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare(
            "DELETE FROM bdc_test_scoring_judge_sessions WHERE round_id=:round",
        )->execute(["round" => $roundId]);
    }
    private static function judges(PDO $pdo, int $roundId): array
    {
        $stmt = $pdo->prepare(
            "SELECT id,judge_name,judge_order,is_chief,scoring_scope FROM bdc_test_scoring_judges WHERE round_id=:round ORDER BY is_chief DESC,judge_order,id",
        );
        $stmt->execute(["round" => $roundId]);
        return $stmt->fetchAll();
    }
    private static function createSession(
        PDO $pdo,
        int $roundId,
        int $judgeId,
    ): array {
        $token = bin2hex(random_bytes(24));
        $session = [
            "round_id" => $roundId,
            "judge_id" => $judgeId,
            "token_hash" => hash("sha256", $token),
            "token_hint" => substr($token, 0, 8),
            "status" => "not_started",
        ];
        $pdo->prepare(
            "INSERT INTO bdc_test_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status,expires_at) VALUES(:round_id,:judge_id,:token_hash,:token_hint,:status,DATE_ADD(NOW(),INTERVAL 12 HOUR))",
        )->execute($session);
        $session["id"] = (int) $pdo->lastInsertId();
        return [$session, $token];
    }
    private static function sessionForJudge(PDO $pdo, int $judgeId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM bdc_test_scoring_judge_sessions WHERE judge_id=:judge ORDER BY id DESC LIMIT 1",
        );
        $stmt->execute(["judge" => $judgeId]);
        return $stmt->fetch() ?: null;
    }
}
