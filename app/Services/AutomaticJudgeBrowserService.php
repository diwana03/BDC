<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class AutomaticJudgeBrowserService
{
    public static function syncRound(PDO $pdo, int $roundId): array
    {
        self::ensureExpiry($pdo);
        $round = self::round($pdo, $roundId);
        if (($round["scoring_mode"] ?? "manual") !== "automated") {
            return [];
        }
        $judgeStmt = $pdo->prepare(
            "SELECT id,judge_name,judge_order,is_chief,scoring_scope FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order",
        );
        $judgeStmt->execute(["round" => $roundId]);
        $judges = $judgeStmt->fetchAll();
        if (count($judges) < 3) {
            return [];
        }
        $entryStmt = $pdo->prepare(
            "SELECT dance_role,COUNT(*) total FROM bdc_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role",
        );
        $entryStmt->execute(["round" => $roundId]);
        $counts = ["leader" => 0, "follower" => 0];
        foreach ($entryStmt->fetchAll() as $row) {
            $counts[$row["dance_role"]] = (int) $row["total"];
        }
        if (
            ($round["round_type"] ?? "") !== "final" &&
            ($counts["leader"] < 1 || $counts["follower"] < 1)
        ) {
            return [];
        }
        $items = [];
        foreach ($judges as $judge) {
            $session = self::sessionForJudge($pdo, (int) $judge["id"]);
            $plain = "";
            $expired = $session && (empty($session['expires_at']) || strtotime((string) $session['expires_at']) <= time());
            if ($expired) {
                $plain = self::regenerate($pdo, $roundId, (int) $judge['id']);
                $session = self::sessionForJudge($pdo, (int) $judge['id']);
            } elseif (!$session) {
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
        self::ensureExpiry($pdo);
        $round = self::round($pdo, $roundId);
        if (($round["scoring_mode"] ?? "manual") !== "automated") {
            throw new RuntimeException(
                "This is not an Automatic scoring round.",
            );
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM bdc_scoring_judges WHERE id=:judge AND round_id=:round",
        );
        $stmt->execute(["judge" => $judgeId, "round" => $roundId]);
        if (!(int) $stmt->fetchColumn()) {
            throw new RuntimeException("Judge not found for this round.");
        }
        $token = bin2hex(random_bytes(24));
        $hash = hash("sha256", $token);
        $hint = substr($token, 0, 8);
        $pdo->prepare(
            "INSERT INTO bdc_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status,expires_at) VALUES(:round,:judge,:hash,:hint,'not_started',DATE_ADD(NOW(),INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),expires_at=VALUES(expires_at),updated_at=NOW()",
        )->execute([
            "round" => $roundId,
            "judge" => $judgeId,
            "hash" => $hash,
            "hint" => $hint,
        ]);
        return $token;
    }

    public static function byToken(PDO $pdo, string $token): ?array
    {
        self::ensureExpiry($pdo);
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = $pdo->prepare(
            "SELECT s.*,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,r.round_type,r.division,r.scoring_mode,r.status round_status,e.name event_name,e.event_date FROM bdc_scoring_judge_sessions s JOIN bdc_scoring_judges j ON j.id=s.judge_id JOIN bdc_scoring_rounds r ON r.id=s.round_id JOIN bdc_events e ON e.id=r.event_id WHERE s.token_hash=:hash AND s.expires_at>NOW() LIMIT 1",
        );
        $stmt->execute(["hash" => hash("sha256", $token)]);
        return $stmt->fetch() ?: null;
    }

    public static function markOpened(PDO $pdo, int $sessionId): void
    {
        $pdo->prepare(
            "UPDATE bdc_scoring_judge_sessions SET status=CASE WHEN status='not_started' THEN 'scoring' ELSE status END,opened_at=COALESCE(opened_at,NOW()) WHERE id=:id",
        )->execute(["id" => $sessionId]);
    }
    public static function acceptCriteria(PDO $pdo, int $sessionId): void
    {
        self::ensureExpiry($pdo);
        $pdo->prepare("UPDATE bdc_scoring_judge_sessions SET criteria_version=:version,criteria_accepted_at=NOW() WHERE id=:id")
            ->execute(['version'=>JudgingCriteriaService::VERSION,'id'=>$sessionId]);
    }
    public static function markSaved(PDO $pdo, int $sessionId): void
    {
        $pdo->prepare(
            "UPDATE bdc_scoring_judge_sessions SET status='scoring',last_saved_at=NOW() WHERE id=:id AND status<>'submitted'",
        )->execute(["id" => $sessionId]);
    }
    public static function submit(PDO $pdo, int $sessionId): void
    {
        $stmt=$pdo->prepare(
            "UPDATE bdc_scoring_judge_sessions SET status='submitted',last_saved_at=NOW(),submitted_at=NOW() WHERE id=:id",
        );$stmt->execute(["id" => $sessionId]);
        ScoringBackupService::judgeSubmissionCheckpoint($pdo,$sessionId,false);
    }

    public static function unlock(
        PDO $pdo,
        int $roundId,
        int $judgeId,
        int $userId,
        string $reason,
    ): void {
        $reason = trim($reason);
        if ($reason === "") {
            throw new RuntimeException(
                "Enter a reason before unlocking judge scores.",
            );
        }
        $stmt = $pdo->prepare(
            "UPDATE bdc_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlocked_by=:user,unlock_reason=:reason WHERE round_id=:round AND judge_id=:judge AND status='submitted'",
        );
        $stmt->execute([
            "user" => $userId,
            "reason" => $reason,
            "round" => $roundId,
            "judge" => $judgeId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException("Judge scoring session not found.");
        }
    }

    public static function unlockAllSubmitted(
        PDO $pdo,
        int $roundId,
        int $userId,
        string $reason,
    ): array {
        $reason = trim($reason);
        if ($reason === "") {
            throw new RuntimeException("Enter an emergency reason before unlocking all judge scores.");
        }
        $submitted = $pdo->prepare(
            "SELECT s.id,s.judge_id FROM bdc_scoring_judge_sessions s JOIN bdc_scoring_judges j ON j.id=s.judge_id WHERE j.round_id=:round AND s.id=(SELECT MAX(s2.id) FROM bdc_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) AND s.status='submitted' ORDER BY j.judge_order",
        );
        $submitted->execute(["round" => $roundId]);
        $sessions = $submitted->fetchAll();
        if (!$sessions) {
            throw new RuntimeException("There are no locked judge scores to unlock.");
        }
        $ids = array_map(static fn(array $row): int => (int) $row["id"], $sessions);
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $stmt = $pdo->prepare(
            "UPDATE bdc_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlocked_by=?,unlock_reason=? WHERE id IN ($placeholders) AND status='submitted'",
        );
        $stmt->execute(array_merge([$userId, $reason], $ids));
        return [
            "count" => $stmt->rowCount(),
            "judge_ids" => array_map(static fn(array $row): int => (int) $row["judge_id"], $sessions),
        ];
    }

    public static function progress(PDO $pdo, int $roundId): array
    {
        $round = self::round($pdo, $roundId);
        $final = ($round["round_type"] ?? "") === "final";
        if ($final) {
            self::repairIncompleteFinalSubmissions($pdo, $roundId, $round);
        }
        $yesLimit = max(0, (int) ($round["yes_count"] ?? 0));
        $stmt = $pdo->prepare(
            "SELECT j.id judge_id,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,COALESCE(s.status,'not_started') session_status,s.token_hint,s.opened_at,s.last_saved_at,s.submitted_at FROM bdc_scoring_judges j LEFT JOIN bdc_scoring_judge_sessions s ON s.id=(SELECT MAX(s2.id) FROM bdc_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) WHERE j.round_id=:round ORDER BY j.judge_order",
        );
        $stmt->execute(["round" => $roundId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $submitted = (string) $row["session_status"] === "submitted";
            if ($final) {
                $totalStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'",
                );
                $totalStmt->execute(["round" => $roundId]);
                $total = (int) $totalStmt->fetchColumn();
                $total = min($total, max(1, (int) ($round["callback_count"] ?? $total)));
                $doneStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM bdc_scoring_final_marks fm JOIN bdc_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' JOIN bdc_scoring_judges j ON j.id=fm.judge_id AND j.round_id=fm.round_id WHERE fm.round_id=:round AND fm.judge_id=:judge AND fm.rank_value IS NOT NULL",
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
                    if (!$allowed) {
                        $counts[$role] = [0, 0];
                        continue;
                    }
                    $totalStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active'",
                    );
                    $totalStmt->execute(["round" => $roundId, "role" => $role]);
                    $active = (int) $totalStmt->fetchColumn();
                    $target = min($active, $yesLimit + 3);
                    $doneStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM bdc_scoring_marks m JOIN bdc_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.mark_type IN('yes','alt')",
                    );
                    $doneStmt->execute([
                        "round" => $roundId,
                        "judge" => $row["judge_id"],
                        "role" => $role,
                    ]);
                    $done = min((int) $doneStmt->fetchColumn(), $target);
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

    public static function allSubmitted(PDO $pdo, int $roundId): bool
    {
        $round = self::round($pdo, $roundId);
        if (($round["round_type"] ?? "") === "final") {
            self::repairIncompleteFinalSubmissions($pdo, $roundId, $round);
        }
        $judgeCount = $pdo->prepare(
            "SELECT COUNT(*) FROM bdc_scoring_judges WHERE round_id=:round",
        );
        $judgeCount->execute(["round" => $roundId]);
        if ((int) $judgeCount->fetchColumn() < 3) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bdc_scoring_judges j LEFT JOIN bdc_scoring_judge_sessions s ON s.id=(SELECT MAX(s2.id) FROM bdc_scoring_judge_sessions s2 WHERE s2.judge_id=j.id) WHERE j.round_id=:round AND COALESCE(s.status,'not_started')<>'submitted'",
        );
        $stmt->execute(["round" => $roundId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private static function repairIncompleteFinalSubmissions(PDO $pdo, int $roundId, array $round): void
    {
        $cleanup=$pdo->prepare("DELETE fm FROM bdc_scoring_final_marks fm LEFT JOIN bdc_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' LEFT JOIN bdc_scoring_judges j ON j.id=fm.judge_id AND j.round_id=fm.round_id WHERE fm.round_id=:round AND (fp.id IS NULL OR j.id IS NULL)");
        $cleanup->execute(["round"=>$roundId]);
        $pairCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");
        $pairCount->execute(["round"=>$roundId]);
        $required=min((int)$pairCount->fetchColumn(),max(1,(int)($round["callback_count"]??1)));
        if($required<1)return;
        $sessions=$pdo->prepare("SELECT s.id,s.judge_id FROM bdc_scoring_judge_sessions s JOIN bdc_scoring_judges j ON j.id=s.judge_id WHERE j.round_id=:round AND s.status='submitted' AND s.id=(SELECT MAX(s2.id) FROM bdc_scoring_judge_sessions s2 WHERE s2.judge_id=j.id)");
        $sessions->execute(["round"=>$roundId]);
        $valid=$pdo->prepare("SELECT COUNT(*) total,COUNT(DISTINCT fm.rank_value) unique_total,MIN(fm.rank_value) minimum,MAX(fm.rank_value) maximum FROM bdc_scoring_final_marks fm JOIN bdc_scoring_final_pairs fp ON fp.id=fm.pair_id AND fp.round_id=fm.round_id AND fp.pairing_status='confirmed' WHERE fm.round_id=:round AND fm.judge_id=:judge AND fm.rank_value IS NOT NULL");
        $reopen=$pdo->prepare("UPDATE bdc_scoring_judge_sessions SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlock_reason='System reopened an incomplete Final submission after pairing data changed.' WHERE id=:id AND status='submitted'");
        foreach($sessions->fetchAll() as $session){$valid->execute(["round"=>$roundId,"judge"=>$session["judge_id"]]);$state=$valid->fetch()?:[];if((int)($state["total"]??0)!==$required||(int)($state["unique_total"]??0)!==$required||(int)($state["minimum"]??0)!==1||(int)($state["maximum"]??0)!==$required)$reopen->execute(["id"=>$session["id"]]);}
    }

    public static function publicUrl(string $token): string
    {
        $path = url("judge-scoring/?token=" . rawurlencode($token));
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

    private static function createSession(
        PDO $pdo,
        int $roundId,
        int $judgeId,
    ): array {
        $token = bin2hex(random_bytes(24));
        $hash = hash("sha256", $token);
        $hint = substr($token, 0, 8);
        $pdo->prepare(
            "INSERT INTO bdc_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status,expires_at) VALUES(:round,:judge,:hash,:hint,'not_started',DATE_ADD(NOW(),INTERVAL 12 HOUR))",
        )->execute([
            "round" => $roundId,
            "judge" => $judgeId,
            "hash" => $hash,
            "hint" => $hint,
        ]);
        return [
            [
                "id" => (int) $pdo->lastInsertId(),
                "round_id" => $roundId,
                "judge_id" => $judgeId,
                "token_hash" => $hash,
                "token_hint" => $hint,
                "status" => "not_started",
            ],
            $token,
        ];
    }
    private static function ensureExpiry(PDO $pdo):void{try{$pdo->exec("ALTER TABLE bdc_scoring_judge_sessions ADD COLUMN expires_at DATETIME NULL AFTER token_hint");}catch(\Throwable){}foreach(["criteria_version VARCHAR(32) NULL AFTER submitted_at","criteria_accepted_at DATETIME NULL AFTER criteria_version","unlocked_at DATETIME NULL AFTER criteria_accepted_at","unlocked_by BIGINT UNSIGNED NULL AFTER unlocked_at","unlock_reason VARCHAR(500) NULL AFTER unlocked_by"] as $definition){try{$pdo->exec("ALTER TABLE bdc_scoring_judge_sessions ADD COLUMN ".$definition);}catch(\Throwable){}}$pdo->exec("UPDATE bdc_scoring_judge_sessions SET expires_at=DATE_ADD(COALESCE(created_at,NOW()),INTERVAL 12 HOUR) WHERE expires_at IS NULL");}
    private static function sessionForJudge(PDO $pdo, int $judgeId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM bdc_scoring_judge_sessions WHERE judge_id=:judge ORDER BY id DESC LIMIT 1",
        );
        $stmt->execute(["judge" => $judgeId]);
        return $stmt->fetch() ?: null;
    }
    private static function round(PDO $pdo, int $roundId): array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM bdc_scoring_rounds WHERE id=:round LIMIT 1",
        );
        $stmt->execute(["round" => $roundId]);
        $round = $stmt->fetch();
        if (!$round) {
            throw new RuntimeException("Scoring round not found.");
        }
        return $round;
    }
}
