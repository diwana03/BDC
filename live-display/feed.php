<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
use App\Services\ProjectionSettingsService;
use App\Services\ProjectionLayoutService;
use App\Services\CountryFlagService;
use App\Services\JudgeDirectoryService;
use App\Services\ScoringFlightService;
use App\Services\ProjectionNameService;
$pdo = Database::connection();
$token = trim((string) ($_GET["token"] ?? ""));
$session = LiveDisplaySessionService::byToken($pdo, $token);
if (!$session) {
    http_response_code(404);
    exit("Live Display link is invalid or disabled.");
}
$test = $session["data_mode"] === "test";
$roundId = (int) ($session["current_round_id"] ?? 0);
$type = (string) ($session["screen_type"] ?? "holding");
$page = max(1, (int) ($session["page_number"] ?? 1));
$place = (string) ($session["reveal_place"] ?? "");
$eventTable = $test ? "bdc_test_events" : "bdc_events";
$eventStmt = $pdo->prepare(
    "SELECT name FROM {$eventTable} WHERE id=:id LIMIT 1",
);
$activeEventId=(int)($session["active_event_id"]??$session["event_id"]);
$eventStmt->execute(["id" => $activeEventId]);
$eventName = (string) ($eventStmt->fetchColumn() ?: "BACHATA DANCE COUNCIL");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
$holdingBackground=trim((string)($session['holding_background_url']??''));
if (
    $type === "holding" ||
    $roundId < 1
) { ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;width:100%;height:100%;background:#000;color:#fff;font-family:Arial,sans-serif}.holding{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;background-color:#030509;background-image:<?= $holdingBackground!==''?'url('.json_encode($holdingBackground).')':'radial-gradient(circle at top,#4a101e,#111827 55%,#030509)' ?>;background-size:cover;background-position:center;background-repeat:no-repeat;font-size:clamp(30px,5vw,96px);font-weight:900;text-shadow:0 4px 22px rgba(0,0,0,.85)}</style></head><body><div class="holding"><?= e(
    $holdingBackground!==''?'':$eventName,
) ?></div></body></html><?php exit();}
if (
    in_array($type, ["final_results", "results", "winners"], true) &&
    empty($session["results_unlocked"])
) {
    http_response_code(403);
    exit("Results reveal is locked.");
}
$roundTable = $test ? "bdc_test_scoring_rounds" : "bdc_scoring_rounds";
$entryTable = $test ? "bdc_test_scoring_entries" : "bdc_scoring_entries";
$judgeTable = $test ? "bdc_test_scoring_judges" : "bdc_scoring_judges";
$resultTable = $test ? "bdc_test_scoring_results" : "bdc_scoring_results";
$markTable = $test ? "bdc_test_scoring_marks" : "bdc_scoring_marks";
$finalResultTable = $test
    ? "bdc_test_scoring_final_results"
    : "bdc_scoring_final_results";
$finalPairTable = $test
    ? "bdc_test_scoring_final_pairs"
    : "bdc_scoring_final_pairs";
$competitorTable = $test ? "bdc_test_competitors" : "bdc_competitors";
$s = $pdo->prepare(
    "SELECT r.*,e.name event_name,e.status event_status FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id WHERE r.id=:id LIMIT 1",
);
$s->execute(["id" => $roundId]);
$r = $s->fetch();
if (!$r || (int) $r["event_id"] !== $activeEventId) {
    http_response_code(404);
    exit("Selected round is not available for this Live Display.");
}
$settings = ProjectionSettingsService::get($pdo, $roundId, $test);
$items = [];
$title = "";
$finalJudges = [];
$finalMarks = [];
$matrixJudges = [];
$matrixMarks = [];
$scoringSubmittedJudges = [];
$scoringPendingJudges = [];
$scoringTimingJudges = [];
$scoringComplete =
    in_array(
        (string) ($r["event_status"] ?? ""),
        ["completed", "archived", "published"],
        true,
    ) ||
    in_array(
        (string) ($r["status"] ?? ""),
        [
            "awaiting_decision",
            "scores_submitted",
            "pending_approval",
            "completed",
            "archived",
            "published",
        ],
        true,
    );
if ($type === "flights") {
    $flightSummary = ScoringFlightService::summary($pdo, $roundId, $test);
    $flight = max(1, (int)($flightSummary['active_flight'] ?? $page));
    $title = "ROUND {$flight} · NOW DANCING";
    ScoringFlightService::ensure($pdo, $test);
    $assignmentTable = $test ? 'bdc_test_scoring_flight_assignments' : 'bdc_scoring_flight_assignments';
    if ((string)$r['round_type'] === 'final') {
        $q = $pdo->prepare("SELECT fp.pair_number,le.display_name leader_name,le.bib_number leader_bib,lc.country leader_country,lc.photo_url leader_photo,fe.display_name follower_name,fe.bib_number follower_bib,fc.country follower_country,fc.photo_url follower_photo FROM {$assignmentTable} fa JOIN {$finalPairTable} fp ON fp.id=fa.subject_id JOIN {$entryTable} le ON le.id=fp.leader_entry_id LEFT JOIN {$entryTable} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id WHERE fa.round_id=:r AND fa.subject_type='pair' AND fa.flight_number=:flight ORDER BY fa.position_number");
        $q->execute(['r'=>$roundId,'flight'=>$flight]);
        $items = $q->fetchAll();
        $type = 'final_couples';
    } else {
        $q = $pdo->prepare("SELECT se.display_name,se.bib_number,se.dance_role,c.country,c.photo_url FROM {$assignmentTable} fa JOIN {$entryTable} se ON se.id=fa.subject_id LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id WHERE fa.round_id=:r AND fa.subject_type='entry' AND fa.flight_number=:flight ORDER BY CASE se.dance_role WHEN 'leader' THEN 1 ELSE 2 END,fa.position_number");
        $q->execute(['r'=>$roundId,'flight'=>$flight]);
        $items = $q->fetchAll();
        $type = 'flight_competitors';
    }
} elseif ($type === "matching") {
    $title = "RANDOM FINAL MATCH";
    $q = $pdo->prepare("SELECT fp.pair_number,l.bib_number leader_bib,l.display_name leader_name,lc.country leader_country,lc.photo_url leader_photo,f.bib_number follower_bib,f.display_name follower_name,fc.country follower_country,fc.photo_url follower_photo FROM {$finalPairTable} fp JOIN {$entryTable} l ON l.id=fp.leader_entry_id LEFT JOIN {$entryTable} f ON f.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=l.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=f.competitor_id WHERE fp.round_id=:r ORDER BY fp.pair_number");
    $q->execute(["r" => $roundId]);
    $items = $q->fetchAll();
    $type = "matching_couples";
} elseif ($type === "judges") {
    $title = "JUDGES";
    try {
        JudgeDirectoryService::ensure($pdo);
        JudgeDirectoryService::backfillAssignments($pdo);
    } catch (Throwable) {
    }
    $q = $pdo->prepare(
        "SELECT sj.judge_name,sj.judge_order,sj.is_chief,sj.scoring_scope,j.full_name,j.country,j.country_code,j.photo_url FROM {$judgeTable} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:r ORDER BY sj.is_chief DESC,sj.judge_order,sj.id",
    );
    $q->execute(["r" => $roundId]);
    $items = $q->fetchAll();
} elseif ($type === "competitors") {
    $isFinalRound = $r["round_type"] === "final";
    $title = $isFinalRound ? "FINALIST COUPLES" : "COMPETITORS";
    if ($isFinalRound) {
        $q = $pdo->prepare(
            "SELECT fp.pair_number,le.display_name leader_name,le.bib_number leader_bib,lc.country leader_country,lc.photo_url leader_photo,fe.display_name follower_name,fe.bib_number follower_bib,fc.country follower_country,fc.photo_url follower_photo FROM {$finalPairTable} fp JOIN {$entryTable} le ON le.id=fp.leader_entry_id LEFT JOIN {$entryTable} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id WHERE fp.round_id=:r ORDER BY fp.pair_number",
        );
    } else {
        $q = $pdo->prepare(
            "SELECT se.display_name,se.bib_number,se.dance_role,c.country,c.photo_url FROM {$entryTable} se LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number IS NULL,se.bib_number,se.display_name",
        );
    }
    $q->execute(["r" => $roundId]);
    $items = $q->fetchAll();
    if ($isFinalRound) {
        // Use the shared couple renderer, which includes both dancer flags.
        $type = "final_couples";
    }
} elseif (in_array($type, ["callbacks", "finalists"], true)) {
    $title = $type === "callbacks" ? "CALLBACKS" : "FINALISTS";
    $q = $pdo->prepare(
        "SELECT se.display_name,se.bib_number,se.dance_role,c.country,c.photo_url,sr.rank_number FROM {$resultTable} sr JOIN {$entryTable} se ON se.id=sr.entry_id LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id WHERE sr.round_id=:r AND sr.result_status IN('callback','alternate') ORDER BY sr.rank_number,se.display_name",
    );
    $q->execute(["r" => $roundId]);
    $items = $q->fetchAll();
} elseif ($type === "score_matrix") {
    $isFinalMatrix=(string)$r["round_type"]==="final";
    $title=$isFinalMatrix?"LIVE RELATIVE PLACEMENT MATRIX":"LIVE SCORE MATRIX · PROVISIONAL";
    $jq=$pdo->prepare("SELECT id,judge_order,judge_name,is_chief,scoring_scope FROM {$judgeTable} WHERE round_id=:r ORDER BY is_chief DESC,judge_order,id");
    $jq->execute(["r"=>$roundId]);
    $matrixJudges=$jq->fetchAll();
    if($isFinalMatrix){
        $q=$pdo->prepare("SELECT fp.id pair_id,fp.pair_number,le.display_name leader_name,le.bib_number leader_bib,lc.country leader_country,fe.display_name follower_name,fe.bib_number follower_bib,fc.country follower_country,COALESCE(fr.final_rank,0) rank_number,COALESCE(fr.placement_sum,0) total_score FROM {$finalPairTable} fp JOIN {$entryTable} le ON le.id=fp.leader_entry_id LEFT JOIN {$entryTable} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id LEFT JOIN {$finalResultTable} fr ON fr.round_id=fp.round_id AND fr.pair_id=fp.id WHERE fp.round_id=:r AND fp.pairing_status='confirmed' ORDER BY CASE WHEN fr.final_rank IS NULL THEN 1 ELSE 0 END,fr.final_rank,fp.pair_number");
        $q->execute(["r"=>$roundId]);$items=$q->fetchAll();
        $finalMarkTable=$test?"bdc_test_scoring_final_marks":"bdc_scoring_final_marks";
        $mq=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM {$finalMarkTable} WHERE round_id=:r");
        $mq->execute(["r"=>$roundId]);
        foreach($mq->fetchAll() as $mark)$matrixMarks[(int)$mark["pair_id"]][(int)$mark["judge_id"]]=(int)$mark["rank_value"];
    }else{
        $q=$pdo->prepare("SELECT se.id,se.bib_number,se.display_name,se.dance_role,c.country,COALESCE(sr.rank_number,0) official_rank,COALESCE(SUM(m.weighted_score),0) total_score FROM {$entryTable} se LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id LEFT JOIN {$resultTable} sr ON sr.round_id=se.round_id AND sr.entry_id=se.id LEFT JOIN {$markTable} m ON m.round_id=se.round_id AND m.entry_id=se.id WHERE se.round_id=:r AND se.entry_status='active' GROUP BY se.id,se.bib_number,se.display_name,se.dance_role,c.country,sr.rank_number ORDER BY se.dance_role,total_score DESC,se.bib_number,se.display_name");
        $q->execute(["r"=>$roundId]);$items=$q->fetchAll();
        $mq=$pdo->prepare("SELECT entry_id,judge_id,mark_type,alt_rank FROM {$markTable} WHERE round_id=:r");
        $mq->execute(["r"=>$roundId]);
        foreach($mq->fetchAll() as $mark){$label=$mark["mark_type"]==="yes"?"YES":($mark["mark_type"]==="alt"?"A".(int)$mark["alt_rank"]:"—");$matrixMarks[(int)$mark["entry_id"]][(int)$mark["judge_id"]]=$label;}
        $roleRanks=[];foreach($items as &$matrixItem){$role=(string)$matrixItem["dance_role"];$roleRanks[$role]=($roleRanks[$role]??0)+1;$matrixItem["rank_number"]=!empty($matrixItem["official_rank"])?(int)$matrixItem["official_rank"]:$roleRanks[$role];}unset($matrixItem);
    }
} elseif ($type === "heats_scores") {
    $title = "LIVE CONTESTANT SCORES";
    $q = $pdo->prepare(
        "SELECT sr.rank_number AS official_rank,COALESCE(SUM(m.weighted_score),sr.total_score,0) total_score,COALESCE(sr.result_status,'provisional') result_status,se.bib_number,se.display_name,se.dance_role,c.country
         FROM {$entryTable} se
         LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id
         LEFT JOIN {$resultTable} sr ON sr.round_id=se.round_id AND sr.entry_id=se.id
         LEFT JOIN {$markTable} m ON m.round_id=se.round_id AND m.entry_id=se.id
         WHERE se.round_id=:r AND se.entry_status='active'
         GROUP BY sr.rank_number,sr.total_score,sr.result_status,se.id,se.bib_number,se.display_name,se.dance_role,c.country
         ORDER BY se.dance_role,total_score DESC,se.bib_number,se.display_name",
    );
    $q->execute(["r" => $roundId]);
    $items = $q->fetchAll();
    $roleRanks = [];
    foreach ($items as &$item) {
        $role = (string) $item["dance_role"];
        $roleRanks[$role] = ($roleRanks[$role] ?? 0) + 1;
        $item["rank_number"] = !empty($item["official_rank"])
            ? (int) $item["official_rank"]
            : $roleRanks[$role];
    }
    unset($item);
} elseif (in_array($type, ["final_results", "results", "winners"], true)) {
    $title = $type === "winners" ? "WINNER PODIUM" : "FINAL FULL RESULTS";
    try {
        $rankLimit =
            $type === "winners" ? " AND fr.final_rank BETWEEN 1 AND 5" : "";
        $q = $pdo->prepare(
            "SELECT fr.final_rank,fr.placement_sum AS total_score,fp.id pair_id,fp.pair_number,le.display_name leader_name,le.bib_number leader_bib,fe.display_name follower_name,fe.bib_number follower_bib,lc.country leader_country,lc.photo_url leader_photo,fc.country follower_country,fc.photo_url follower_photo FROM {$finalResultTable} fr JOIN {$finalPairTable} fp ON fp.id=fr.pair_id JOIN {$entryTable} le ON le.id=fp.leader_entry_id LEFT JOIN {$entryTable} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id WHERE fr.round_id=:r{$rankLimit} ORDER BY fr.final_rank ASC",
        );
        $q->execute(["r" => $roundId]);
        $items = $q->fetchAll();
    } catch (Throwable) {
        $items = [];
    }
    if ($type === "final_results") {
        $jq = $pdo->prepare(
            "SELECT id,judge_order,judge_name,is_chief FROM {$judgeTable} WHERE round_id=:r ORDER BY judge_order,id",
        );
        $jq->execute(["r" => $roundId]);
        $finalJudges = $jq->fetchAll();
        $finalMarkTable = $test
            ? "bdc_test_scoring_final_marks"
            : "bdc_scoring_final_marks";
        $mq = $pdo->prepare(
            "SELECT pair_id,judge_id,rank_value FROM {$finalMarkTable} WHERE round_id=:r",
        );
        $mq->execute(["r" => $roundId]);
        foreach ($mq->fetchAll() as $mark) {
            $finalMarks[(int) $mark["pair_id"]][(int) $mark["judge_id"]] =
                $mark["rank_value"];
        }
    }
    if ($type === "winners" && $place !== "" && $place !== "all") {
        $reveal = max(1, min(5, (int) $place));
        $items = array_values(
            array_filter($items, fn($x) => (int) $x["final_rank"] >= $reveal),
        );
    }
} else {
    $title = $scoringComplete ? "SCORING COMPLETED" : "SCORING IN PROGRESS";
    try {
        $sessionTable = $test
            ? "bdc_test_scoring_judge_sessions"
            : "bdc_scoring_judge_sessions";
        $q = $pdo->prepare(
            "SELECT j.id,j.judge_order,j.judge_name,j.is_chief,COALESCE(s.status,'not_started') session_status,
                    s.opened_at,s.submitted_at,
                    CASE WHEN s.opened_at IS NOT NULL AND s.submitted_at IS NOT NULL
                         THEN GREATEST(0,TIMESTAMPDIFF(SECOND,s.opened_at,s.submitted_at)) ELSE NULL END elapsed_seconds
             FROM {$judgeTable} j
             LEFT JOIN {$sessionTable} s ON s.round_id=j.round_id AND s.judge_id=j.id
             WHERE j.round_id=:r
             ORDER BY j.is_chief DESC,j.judge_order,j.id",
        );
        $q->execute(["r" => $roundId]);
        $scoringJudges = $q->fetchAll();
        foreach ($scoringJudges as $scoringJudge) {
            if ((string) $scoringJudge["session_status"] === "submitted") {
                $scoringSubmittedJudges[] = $scoringJudge;
            } else {
                $scoringPendingJudges[] = $scoringJudge;
            }
        }
        $items = [[
            "total" => count($scoringJudges),
            "submitted" => count($scoringSubmittedJudges),
        ]];
        if ($scoringJudges && !$scoringPendingJudges) {
            $scoringTimingJudges = array_values(array_filter(
                $scoringSubmittedJudges,
                static fn(array $judge): bool => $judge["elapsed_seconds"] !== null,
            ));
            usort($scoringTimingJudges, static fn(array $a, array $b): int =>
                ((int) $a["elapsed_seconds"] <=> (int) $b["elapsed_seconds"])
                ?: ((int) $a["judge_order"] <=> (int) $b["judge_order"])
            );
        }
    } catch (Throwable) {
        $items = [["total" => 0, "submitted" => 0]];
    }
}

// Audience screens use compact first names. When the same first name appears
// more than once on the current screen, add the surname initial to both names.
$items = ProjectionNameService::abbreviateRows(
    $items,
    ["display_name", "leader_name", "follower_name"],
);
$matrixJudges = ProjectionNameService::abbreviateRows($matrixJudges, ["judge_name"]);
$finalJudges = ProjectionNameService::abbreviateRows($finalJudges, ["judge_name"]);
if ($type === "judges") {
    $judgeProjectionNames = [];
    foreach ($items as $judgeIndex => $judgeItem) {
        $judgeProjectionNames[(string) $judgeIndex] = trim((string) (
            $judgeItem["full_name"] ?: $judgeItem["judge_name"]
        ));
    }
    $judgeProjectionNames = ProjectionNameService::abbreviateNames($judgeProjectionNames);
    foreach ($items as $judgeIndex => &$judgeItem) {
        $judgeItem["full_name"] = $judgeProjectionNames[(string) $judgeIndex] ?? "";
        $judgeItem["judge_name"] = $judgeItem["full_name"];
    }
    unset($judgeItem);
}
$animalPlaceholders = [
    url("public/assets/img/projection-animals/rabbit.png"),
    url("public/assets/img/projection-animals/baby-elephant.png"),
    url("public/assets/img/projection-animals/panda.png"),
];
$animalPhoto = static function (string $key) use ($animalPlaceholders): string {
    $index = abs((int) crc32($key)) % count($animalPlaceholders);
    return $animalPlaceholders[$index];
};
// Projection identity is never optional: every scoring/status view carries the
// contestant bib and country flag in both Test and Live modes.
if ($type === "score_matrix") {
    foreach ($items as &$projectionItem) {
        if ((string) $r["round_type"] === "final") {
            $leaderFlag = CountryFlagService::emoji($projectionItem["leader_country"] ?? null);
            $followerFlag = CountryFlagService::emoji($projectionItem["follower_country"] ?? null);
            $projectionItem["leader_name"] = "BIB " . ($projectionItem["leader_bib"] ?: "—") . ($leaderFlag !== "" ? " " . $leaderFlag : "") . " " . $projectionItem["leader_name"];
            $projectionItem["follower_name"] = "BIB " . ($projectionItem["follower_bib"] ?: "—") . ($followerFlag !== "" ? " " . $followerFlag : "") . " " . $projectionItem["follower_name"];
        } else {
            $flag = CountryFlagService::emoji($projectionItem["country"] ?? null);
            if ($flag !== "") $projectionItem["display_name"] = $flag . " " . $projectionItem["display_name"];
        }
    }
    unset($projectionItem);
} elseif ($type === "heats_scores") {
    foreach ($items as &$projectionItem) {
        $flag = CountryFlagService::emoji($projectionItem["country"] ?? null);
        if ($flag !== "") $projectionItem["display_name"] = $flag . " " . $projectionItem["display_name"];
    }
    unset($projectionItem);
} elseif (in_array($type, ["final_results", "results"], true)) {
    foreach ($items as &$projectionItem) {
        if (isset($projectionItem["pair_number"])) {
            $leaderFlag = CountryFlagService::emoji($projectionItem["leader_country"] ?? null);
            $followerFlag = CountryFlagService::emoji($projectionItem["follower_country"] ?? null);
            $projectionItem["leader_name"] = "P" . (int) $projectionItem["pair_number"] . " · BIB " . ($projectionItem["leader_bib"] ?: "—") . ($leaderFlag !== "" ? " " . $leaderFlag : "") . " " . $projectionItem["leader_name"];
            $projectionItem["follower_name"] = "BIB " . ($projectionItem["follower_bib"] ?: "—") . ($followerFlag !== "" ? " " . $followerFlag : "") . " " . $projectionItem["follower_name"];
        }
    }
    unset($projectionItem);
}

$layout = ProjectionLayoutService::resolve(
    (string) $settings["screen_format"],
    max(1, count($items)),
    (string) $settings["density"],
    $settings["custom_width"] ?: null,
    $settings["custom_height"] ?: null,
);
$ratio = in_array($type, ["score_matrix", "heats_scores", "final_results", "results"], true)
    ? 16 / 9
    : $layout["ratio"];
$cols = $layout["columns"];
$coupleProjection = in_array($type, ["final_couples", "matching_couples"], true);
$coupleColumns = min(5, max(1, count($items)));
$coupleRows = max(1, (int) ceil(count($items) / $coupleColumns));
$competitorRoleItems=["leader"=>[],"follower"=>[]];
$competitorRoleCols=max(1,(int)floor($cols/2));
$competitorRoleCapacity=max(1,(int)$layout["rows"]*$competitorRoleCols);
$splitRoleScreen=in_array($type,["competitors","callbacks","finalists","flight_competitors"],true)&&(string)$r["round_type"]!=="final";
if($splitRoleScreen){
    foreach($items as $competitorItem){
        $role=(string)($competitorItem["dance_role"]??"");
        if(isset($competitorRoleItems[$role]))$competitorRoleItems[$role][]=$competitorItem;
    }
    foreach($competitorRoleItems as $role=>$roleItems){
        $competitorRoleItems[$role]=array_slice($roleItems,($page-1)*$competitorRoleCapacity,$competitorRoleCapacity);
    }
} elseif (
    in_array($type, ["callbacks", "finalists"], true) &&
    !$splitRoleScreen &&
    count($items) > $layout["capacity"]
) {
    $items = array_slice(
        $items,
        ($page - 1) * $layout["capacity"],
        $layout["capacity"],
    );
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(
    $title,
) ?></title><link rel="stylesheet" href="../public/css/projector-responsive-v344.css?v=344"><style>*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;background:#000;color:#fff;font-family:Arial,"Segoe UI Emoji","Apple Color Emoji",sans-serif;overflow:hidden}.viewport{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center}.stage{aspect-ratio:<?= e(
    (string) $ratio,
) ?>;width:min(100vw,calc(100vh * <?= e(
    (string) $ratio,
) ?>));height:min(100vh,calc(100vw / <?= e(
    (string) $ratio,
) ?>));background:radial-gradient(circle at top,#4a101e,#111827 50%,#030509);padding:1.35% 2.1% .8%;text-align:center;overflow:hidden;display:flex;flex-direction:column}.event{font-size:clamp(18px,1.8vw,46px);font-weight:900}.meta{font-size:clamp(12px,.95vw,24px);color:#ffb7c3}.title{font-size:clamp(22px,2.2vw,54px);font-weight:900;margin:.35% 0 .6%}.list{flex:1;display:grid;grid-template-columns:repeat(<?= $cols ?>,minmax(0,1fr));grid-auto-rows:minmax(0,1fr);gap:.7%;min-height:0}.item{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:3%;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;overflow:hidden;font-size:clamp(11px,1.2vw,28px)}.photo{width:min(8vw,11vh);height:min(8vw,11vh);border-radius:50%;object-fit:cover;margin-bottom:.4em}.name{font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.big{font-size:clamp(60px,10vw,220px);font-weight:900}.small{font-size:.72em;color:#cbd5e1}.podium{flex:1;display:flex;align-items:flex-end;justify-content:center;gap:1%;min-height:0}.podium-slot{width:18%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}.podium-person{min-height:27%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;margin-bottom:2%;width:100%}.podium-photos{display:flex;justify-content:center;align-items:center;gap:.45vw;height:min(7vw,10vh)}.podium-photo{width:min(6vw,9vh);height:min(6vw,9vh);border-radius:50%;object-fit:cover;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2)}.podium-photo.blank{display:block}.podium-name{font-weight:900;font-size:clamp(11px,1.15vw,27px);max-width:100%;min-height:2.3em;display:flex;align-items:center;justify-content:center}.podium-country{font-size:clamp(9px,.75vw,18px);color:#ddd;min-height:1.4em}.block{width:100%;border-radius:12px 12px 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:8%;font-size:clamp(30px,5vw,100px);font-weight:900;background:linear-gradient(#7d2638,#35101a);border:2px solid rgba(255,255,255,.18)}.p1{height:58%;background:linear-gradient(#d8aa37,#6e4d00)}.p2{height:46%;background:linear-gradient(#aeb5bf,#505866)}.p3{height:38%;background:linear-gradient(#a86c45,#56311d)}.p4{height:29%}.p5{height:23%}.score-table{width:100%;border-collapse:collapse;font-size:clamp(11px,1.15vw,25px);background:rgba(17,24,39,.82)}.score-table th,.score-table td{border:1px solid rgba(255,255,255,.24);padding:.42em .65em;text-align:left}.score-table th{background:#7d2638;text-transform:uppercase}.score-table .num{text-align:center;font-weight:900}.score-table.relative{font-size:clamp(8px,.82vw,18px);table-layout:fixed}.score-table.relative th:nth-child(2),.score-table.relative td:nth-child(2){text-align:left;width:22%}.score-table.relative th:not(:nth-child(2)),.score-table.relative td:not(:nth-child(2)){padding:.35em .2em}.stage>.score-table{flex:1;height:auto;margin-bottom:.2%}.judge-key{display:flex;flex-wrap:wrap;gap:.3em 1em;margin-top:.7em;font-size:clamp(7px,.65vw,14px);text-align:left}.competitor-split{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1%}.competitor-role-panel{min-width:0;min-height:0;display:flex;flex-direction:column;border:1px solid rgba(255,255,255,.28);border-radius:12px;overflow:hidden;background:rgba(5,10,20,.28)}.competitor-role-title{padding:.38em .7em;background:#111827;border-bottom:1px solid rgba(255,255,255,.35);font-size:clamp(14px,1.2vw,28px);font-weight:900;letter-spacing:.09em;display:flex;justify-content:space-between}.competitor-role-grid{flex:1;min-height:0;display:grid;grid-template-columns:repeat(<?= $competitorRoleCols ?>,minmax(0,1fr));grid-auto-rows:minmax(0,1fr);gap:.7%;padding:.7%}.competitor-role-grid .item{font-size:clamp(9px,.9vw,20px)}.competitor-role-grid .photo{width:min(6vw,9vh);height:min(6vw,9vh)}</style></head><body><div class="viewport"><div class="stage"><div class="event"><?= e(
    $r["event_name"],
) ?></div><div class="meta"><?= e(
    strtoupper(str_replace("_", " ", $r["division"])),
) ?> · <?= e(strtoupper($r["round_type"])) ?></div><div class="title"><?= e(
    $title,
) ?></div><?php if ($splitRoleScreen): ?><div class="competitor-split"><?php foreach(["leader"=>"LEADERS","follower"=>"FOLLOWERS"] as $competitorRole=>$competitorRoleLabel):$roleRows=$competitorRoleItems[$competitorRole];?><section class="competitor-role-panel"><div class="competitor-role-title"><span><?=e($competitorRoleLabel)?> · <?=count($roleRows)?> COMPETITORS</span></div><div class="competitor-role-grid"><?php foreach($roleRows as $x):$fallbackPhoto=$animalPhoto((string)(($x["bib_number"]??"")."|".($x["display_name"]??"")));$displayPhoto=!empty($x["photo_url"])?(string)$x["photo_url"]:$fallbackPhoto;?><div class="item"><img class="photo" src="<?=e($displayPhoto)?>" onerror="this.onerror=null;this.src='<?=e($fallbackPhoto)?>'"><div class="name"><?=e((string)$x["display_name"])?></div><div class="small"><?=!empty($x["bib_number"])?"BIB ".(int)$x["bib_number"]:"BIB UNASSIGNED"?><?php if(!empty($x["country"])):$flagUrl=country_flag_url((string)$x["country"]);?> · <?php if($flagUrl):?><img src="<?=e($flagUrl)?>" alt="<?=e((string)$x["country"])?> flag" style="width:clamp(24px,2.1vw,48px);height:clamp(16px,1.4vw,32px);object-fit:cover;vertical-align:middle;border:1px solid rgba(255,255,255,.65);border-radius:3px"><?php endif;?> <?=e((string)$x["country"])?><?php endif;?></div></div><?php endforeach;?></div></section><?php endforeach;?></div><?php elseif ($type === "competitors" && (string)$r["round_type"]==="final"): ?><div class="list"><?php foreach($items as $x):$leaderFallback=$animalPhoto((string)($x["leader_bib"]??$x["leader_name"]??"leader"));$followerFallback=$animalPhoto((string)($x["follower_bib"]??$x["follower_name"]??"follower"));?><div class="item"><div class="small">COUPLE <?= (int)($x["pair_number"]??0) ?></div><div style="display:flex;gap:.5em;justify-content:center"><img class="photo" src="<?=e(!empty($x["leader_photo"])?$x["leader_photo"]:$leaderFallback)?>" onerror="this.src='<?=e($leaderFallback)?>'"><img class="photo" src="<?=e(!empty($x["follower_photo"])?$x["follower_photo"]:$followerFallback)?>" onerror="this.src='<?=e($followerFallback)?>'"></div><div class="name"><?=e((string)$x["leader_name"])?> &amp; <?=e((string)$x["follower_name"])?></div><div class="small">BIB <?= (int)$x["leader_bib"] ?> + <?= (int)$x["follower_bib"] ?></div></div><?php endforeach;?></div><?php elseif ($type === "matching"): ?><div class="list"><?php foreach ($items as $x): ?><div class="item"><div class="small">COUPLE <?= (int) $x["pair_number"] ?></div><div class="name">BIB <?= (int) $x["leader_bib"] ?> · <?= e(CountryFlagService::emoji($x["leader_country"] ?? null)) ?> <?= e($x["leader_name"]) ?></div><div style="font-size:clamp(24px,3vw,65px);color:#ffcf45">＋</div><div class="name">BIB <?= (int) $x["follower_bib"] ?> · <?= e(CountryFlagService::emoji($x["follower_country"] ?? null)) ?> <?= e($x["follower_name"]) ?></div></div><?php endforeach; ?></div><?php elseif ($type === "score_matrix"): ?><style>.matrix-wrap{flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;padding-bottom:.2%}.matrix-split{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1%}.matrix-panel{min-width:0;min-height:0;overflow:hidden;display:flex;flex-direction:column}.matrix-role{background:#111827;border:1px solid rgba(255,255,255,.35);padding:.3em;font-size:clamp(12px,1vw,24px);font-weight:900;letter-spacing:.08em}.score-table.matrix{font-size:clamp(7px,.68vw,15px);table-layout:auto;height:100%}.matrix-wrap>.score-table.matrix{flex:1;height:auto}.matrix-panel>.score-table.matrix{flex:1;height:auto}.score-table.matrix th,.score-table.matrix td{padding:.25em .32em;text-align:center;white-space:nowrap}.score-table.matrix th.name,.score-table.matrix td.name{text-align:left;max-width:16vw;overflow:hidden;text-overflow:ellipsis}.provisional-label{color:#ffcf45;font-weight:900;letter-spacing:.08em;margin-bottom:.35em}.score-table.matrix td.not-applicable{color:#94a3b8;background:rgba(148,163,184,.08);font-weight:850;letter-spacing:.04em}</style><div class="matrix-wrap"><div class="provisional-label">LIVE · PROVISIONAL</div><?php if((string)$r["round_type"]==="final"):?><table class="score-table matrix"><thead><tr><th>Place</th><th>Couple</th><?php foreach($matrixJudges as $judge):?><th>J<?=(int)$judge["judge_order"]?><?=(int)$judge["is_chief"]?"★":""?><br><small><?=e((string)$judge["judge_name"])?></small></th><?php endforeach;?><th>Sum</th></tr></thead><tbody><?php foreach($items as $x):?><tr><td class="num"><?=!empty($x["rank_number"])?(int)$x["rank_number"]:"—"?></td><td class="name">#<?=(int)$x["pair_number"]?> <?=e((string)$x["leader_name"])?> &amp; <?=e((string)$x["follower_name"])?></td><?php foreach($matrixJudges as $judge):?><td><?=e((string)($matrixMarks[(int)$x["pair_id"]][(int)$judge["id"]]??"—"))?></td><?php endforeach;?><td class="num"><?=e((string)$x["total_score"])?></td></tr><?php endforeach;?></tbody></table><?php else:?><div class="matrix-split"><?php foreach(["leader"=>"LEADERS","follower"=>"FOLLOWERS"] as $role=>$label):?><section class="matrix-panel"><div class="matrix-role"><?=e($label)?></div><table class="score-table matrix"><thead><tr><th>Prov.</th><th class="name">Competitor</th><?php foreach($matrixJudges as $judge):?><th>J<?=(int)$judge["judge_order"]?><?=(int)$judge["is_chief"]?"★":""?><br><small><?=e((string)$judge["judge_name"])?></small></th><?php endforeach;?><th>Score</th></tr></thead><tbody><?php foreach($items as $x):if((string)$x["dance_role"]!==$role)continue;?><tr><td class="num"><?=(int)$x["rank_number"]?></td><td class="name">#<?=(int)$x["bib_number"]?> <?=e((string)$x["display_name"])?></td><?php foreach($matrixJudges as $judge):$judgeScope=(string)($judge["scoring_scope"]??"all");$notApplicable=$judgeScope!=="all"&&$judgeScope!==$role;?><td<?=$notApplicable?' class="not-applicable" title="Judge not assigned to this role"':''?>><?=$notApplicable?"N/A":e((string)($matrixMarks[(int)$x["id"]][(int)$judge["id"]]??"—"))?></td><?php endforeach;?><td class="num"><?=number_format((float)$x["total_score"],1)?></td></tr><?php endforeach;?></tbody></table></section><?php endforeach;?></div><?php endif;?></div><?php elseif ($type === "heats_scores"): ?><style>.score-split{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1%}.score-panel{min-width:0;min-height:0;overflow:hidden;display:flex;flex-direction:column}.score-role{background:#111827;border:1px solid rgba(255,255,255,.35);padding:.35em;font-size:clamp(13px,1.15vw,26px);font-weight:900;letter-spacing:.08em}.score-table.live-role{font-size:clamp(8px,.78vw,17px);table-layout:fixed;flex:1;height:auto}.score-table.live-role th,.score-table.live-role td{padding:.28em .42em}.score-table.live-role th:nth-child(1){width:12%}.score-table.live-role th:nth-child(2){width:15%}.score-table.live-role th:nth-child(4){width:18%}</style><div class="score-split"><?php foreach (["leader" => "LEADERS", "follower" => "FOLLOWERS"] as $role => $roleLabel): ?><section class="score-panel"><div class="score-role"><?= e($roleLabel) ?></div><table class="score-table live-role"><thead><tr><th>Place</th><th>Bib</th><th>Contestant</th><th>Score</th></tr></thead><tbody><?php foreach ($items as $x): if (($x["dance_role"] ?? "") !== $role) continue; ?><tr><td class="num"><?= (int) $x["rank_number"] ?></td><td class="num"><?= !empty($x["bib_number"]) ? (int) $x["bib_number"] : "—" ?></td><td><?= e((string) $x["display_name"]) ?></td><td class="num"><?= e((string) ($x["total_score"] ?? "0.00")) ?></td></tr><?php endforeach; ?></tbody></table></section><?php endforeach; ?></div><?php elseif (
    in_array($type, ["final_results", "results"], true)
): ?><table class="score-table"><thead><tr><th>Place</th><th>Bib</th><th>Competitor / Couple</th><th>Status</th><th>Score</th></tr></thead><tbody><?php foreach (
    $items
    as $x
): ?><tr><td class="num"><?= (int) ($x["rank_number"] ??
    ($x["final_rank"] ?? 0)) ?></td><td class="num"><?= !empty($x["bib_number"])
    ? (int) $x["bib_number"]
    : "—" ?></td><td><?= e(
    (string) ($x["display_name"] ??
        trim(
            (string) ($x["leader_name"] ?? "") .
                " & " .
                (string) ($x["follower_name"] ?? ""),
            " &",
        )),
) ?></td><td><?= e(
    ucwords(
        str_replace(
            "_",
            " ",
            (string) ($x["dance_role"] ?? ($x["result_status"] ?? "Final")),
        ),
    ),
) ?></td><td class="num"><?= e(
    (string) ($x["total_score"] ?? "—"),
) ?></td></tr><?php endforeach; ?></tbody></table><?php elseif (
    $type === "winners"
): ?><div class="podium"><?php foreach ([4, 2, 1, 3, 5] as $rank):

    $x = null;
    foreach ($items as $row) {
        if ((int) $row["final_rank"] === $rank) {
            $x = $row;
            break;
        }
    }
    ?><div class="podium-slot"><?php if ($x):

    $people = [];
    $people[] = [
        "name" => $x["leader_name"] ?? "",
        "photo" => $x["leader_photo"] ?? "",
        "country" => $x["leader_country"] ?? "",
    ];
    if (!empty($x["follower_name"])) {
        $people[] = [
            "name" => $x["follower_name"],
            "photo" => $x["follower_photo"] ?? "",
            "country" => $x["follower_country"] ?? "",
        ];
    }
    ?><div class="podium-person"><div class="podium-photos"><?php foreach (
    $people
    as $person
):
    $fallbackPhoto = $animalPhoto((string) $person["name"]);
    $displayPhoto = !empty($person["photo"])
        ? (string) $person["photo"]
        : $fallbackPhoto;
    ?><img class="podium-photo" src="<?= e(
    $displayPhoto,
) ?>" onerror="this.onerror=null;this.src='<?= e($fallbackPhoto) ?>'"><?php
endforeach; ?></div><div class="podium-name"><?= e(
    implode(" & ", array_column($people, "name")),
) ?></div><div class="podium-country"><?php
foreach ($people as $person) {
    if (!empty($person["country"])) {
        $country = (string) $person["country"];
        echo '<span class="podium-country-entry" style="display:inline-flex;flex-direction:column;align-items:center;justify-content:center;gap:.12em;margin:0 .32em;vertical-align:middle">';
        $flagUrl = country_flag_url($country);
        if ($flagUrl) {
            echo '<img src="' . e($flagUrl) . '" alt="' . e($country) . ' flag" style="width:clamp(52px,4vw,96px);height:clamp(35px,2.67vw,64px);object-fit:cover;border:2px solid rgba(255,255,255,.75);border-radius:5px;box-shadow:0 3px 12px rgba(0,0,0,.55)">';
        }
        echo '<span class="podium-country-name" style="font-size:clamp(8px,.62vw,15px);font-weight:800;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap">' . e($country) . '</span>';
        echo '</span>';
    }
}
?></div></div><div class="block p<?= $rank ?>"><?= $rank ?></div><?php
endif; ?></div><?php
endforeach; ?></div><?php else: ?><div class="list"><?php if (
    $type === "scoring"
):
    $x = $items[0];
    if ($scoringTimingJudges):
        $fastestJudge = $scoringTimingJudges[0];
        $slowestJudge = $scoringTimingJudges[count($scoringTimingJudges) - 1];
        $formatJudgeTime = static function (int $seconds): string {
            if ($seconds < 60) return $seconds . " sec";
            $minutes = intdiv($seconds, 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . " min" . ($remainingSeconds > 0 ? " " . $remainingSeconds . " sec" : "");
        }; ?><style>
.timing-celebration{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,.86fr) minmax(260px,1.28fr) minmax(0,.86fr);gap:1.15%;align-items:stretch}.timing-award,.timing-board{border:1px solid rgba(255,255,255,.2);border-radius:max(8px,min(.8cqw,1.4cqh));overflow:hidden;box-shadow:0 1.2cqh 3cqh rgba(0,0,0,.23)}.timing-award{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4%;background:radial-gradient(circle at 50% 25%,rgba(255,255,255,.13),rgba(8,14,27,.78) 68%)}.timing-award.fastest{border-color:rgba(250,204,21,.68);background:radial-gradient(circle at 50% 28%,rgba(216,170,55,.34),rgba(68,40,7,.6) 50%,rgba(8,14,27,.9))}.timing-award.slowest{border-color:rgba(244,114,182,.5);background:radial-gradient(circle at 50% 28%,rgba(125,38,56,.4),rgba(55,19,38,.64) 52%,rgba(8,14,27,.9))}.timing-award-icon{font-size:max(38px,min(5cqw,8.8cqh));line-height:1}.timing-award-label{margin-top:.45em;color:#fde68a;font-size:max(10px,min(.96cqw,1.7cqh));font-weight:950;letter-spacing:.1em}.timing-award-name{max-width:100%;margin:.35em 0;font-size:max(17px,min(2cqw,3.55cqh));font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.timing-award-time{font-size:max(20px,min(2.7cqw,4.8cqh));font-weight:950}.timing-award-joke{margin-top:.7em;color:#dbe4f3;font-size:max(9px,min(.83cqw,1.48cqh));font-weight:700}.timing-board{display:flex;flex-direction:column;background:rgba(8,14,27,.7)}.timing-board-heading{padding:min(1.2cqh,.68cqw);background:linear-gradient(90deg,rgba(125,38,56,.55),rgba(216,170,55,.24));font-size:max(12px,min(1.25cqw,2.2cqh));font-weight:950;letter-spacing:.06em}.timing-list{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:minmax(0,1fr);gap:min(.8cqh,.45cqw);padding:min(1cqh,.58cqw)}.timing-row{display:flex;align-items:center;justify-content:space-between;gap:.6em;min-width:0;padding:min(.85cqh,.5cqw);border:1px solid rgba(255,255,255,.12);border-radius:max(5px,min(.48cqw,.85cqh));background:rgba(255,255,255,.065);font-size:max(9px,min(.9cqw,1.6cqh));font-weight:850}.timing-row-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.timing-row-time{flex:0 0 auto;color:#fde7b0;font-weight:950}@container (aspect-ratio < 6/5){.timing-celebration{grid-template-columns:1fr 1fr;grid-template-rows:minmax(0,.72fr) minmax(0,1fr)}.timing-board{grid-column:1/-1;grid-row:2}.timing-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style><div class="timing-celebration"><section class="timing-award fastest"><div class="timing-award-icon">⚡🏆</div><div class="timing-award-label">SPEED STAR</div><div class="timing-award-name">J<?=(int)$fastestJudge["judge_order"]?> · <?=e((string)$fastestJudge["judge_name"])?></div><div class="timing-award-time"><?=e($formatJudgeTime((int)$fastestJudge["elapsed_seconds"]))?></div><div class="timing-award-joke">Fast eyes. Sharp scores. No coffee break needed.</div></section><section class="timing-board"><div class="timing-board-heading">ALL JUDGES FINISHED · SCORING TIMES</div><div class="timing-list"><?php foreach($scoringTimingJudges as $position=>$judge):?><div class="timing-row"><span class="timing-row-name"><?=($position+1)?> · J<?=(int)$judge["judge_order"]?> <?=e((string)$judge["judge_name"])?><?=(int)$judge["is_chief"]===1?" ★":""?></span><span class="timing-row-time"><?=e($formatJudgeTime((int)$judge["elapsed_seconds"]))?></span></div><?php endforeach;?></div></section><section class="timing-award slowest"><div class="timing-award-icon">🐢✨</div><div class="timing-award-label">SCENIC ROUTE AWARD</div><div class="timing-award-name">J<?=(int)$slowestJudge["judge_order"]?> · <?=e((string)$slowestJudge["judge_name"])?></div><div class="timing-award-time"><?=e($formatJudgeTime((int)$slowestJudge["elapsed_seconds"]))?></div><div class="timing-award-joke">Great care taken. Next round: same quality, fewer sightseeing stops.</div></section></div><?php elseif (
        $scoringComplete
    ): ?><div class="item"><div class="big">✓</div><div class="small">ROUND FINISHED</div></div><?php else:$x =
            $items[0]; ?><style>
.scoring-progress{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) minmax(150px,.54fr) minmax(0,1fr);gap:1.15%;align-items:stretch}
.judge-status-panel{min-width:0;display:flex;flex-direction:column;border:1px solid rgba(255,255,255,.22);border-radius:max(8px,min(.8cqw,1.4cqh));overflow:hidden;background:rgba(8,14,27,.58);box-shadow:0 1.2cqh 3cqh rgba(0,0,0,.2)}
.judge-status-heading{display:flex;align-items:center;justify-content:space-between;padding:min(1.15cqh,.65cqw) min(1.3cqh,.8cqw);font-size:max(11px,min(1.28cqw,2.25cqh));font-weight:950;letter-spacing:.055em;text-transform:uppercase}
.judge-status-panel.complete{border-color:rgba(52,211,153,.5);background:linear-gradient(145deg,rgba(6,78,59,.42),rgba(8,14,27,.72))}.judge-status-panel.complete .judge-status-heading{background:linear-gradient(90deg,rgba(16,185,129,.32),rgba(16,185,129,.08));color:#a7f3d0}
.judge-status-panel.pending{border-color:rgba(251,191,36,.48);background:linear-gradient(215deg,rgba(120,53,15,.34),rgba(8,14,27,.72))}.judge-status-panel.pending .judge-status-heading{background:linear-gradient(90deg,rgba(245,158,11,.26),rgba(245,158,11,.07));color:#fde68a}
.judge-status-count{display:grid;place-items:center;min-width:1.8em;height:1.8em;border-radius:999px;background:rgba(255,255,255,.12);font-size:.8em}
.judge-status-list{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:minmax(0,1fr);gap:min(1cqh,.55cqw);padding:min(1.25cqh,.7cqw)}
.judge-chip{min-width:0;display:flex;align-items:center;gap:min(1cqh,.55cqw);padding:min(1.05cqh,.58cqw);border-radius:max(6px,min(.56cqw,1cqh));background:rgba(255,255,255,.075);border:1px solid rgba(255,255,255,.13);font-size:max(10px,min(1.05cqw,1.85cqh));font-weight:850;text-align:left}
.judge-chip-icon{display:grid;place-items:center;flex:0 0 auto;width:1.75em;height:1.75em;border-radius:50%;font-weight:950}.complete .judge-chip-icon{background:#10b981;color:#052e22}.pending .judge-chip-icon{background:#f59e0b;color:#451a03}.judge-chip-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.judge-chip-chief{display:block;color:#fbd56a;font-size:.58em;letter-spacing:.07em}
.scoring-progress-total{display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:max(8px,min(.8cqw,1.4cqh));border:1px solid rgba(216,170,55,.48);background:radial-gradient(circle at 50% 35%,rgba(125,38,56,.42),rgba(17,24,39,.92) 68%);box-shadow:inset 0 0 4cqh rgba(216,170,55,.07),0 1.2cqh 3cqh rgba(0,0,0,.25)}
.scoring-progress-total .big{font-size:max(42px,min(6.8cqw,12cqh));line-height:.95}.scoring-progress-total .small{margin-top:.75em;color:#fde7b0;font-weight:850;letter-spacing:.09em}.scoring-progress-bar{width:68%;height:max(5px,min(.42cqw,.72cqh));margin-top:1.1em;border-radius:999px;background:rgba(255,255,255,.13);overflow:hidden}.scoring-progress-bar>span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#d8aa37,#34d399);box-shadow:0 0 1.4cqh rgba(52,211,153,.6)}
@container (aspect-ratio < 6/5){.scoring-progress{grid-template-columns:1fr 1fr;grid-template-rows:auto minmax(0,1fr)}.scoring-progress-total{grid-column:1/-1;grid-row:1;min-height:15cqh}.judge-status-list{grid-template-columns:1fr}}
</style><div class="scoring-progress"><section class="judge-status-panel complete"><div class="judge-status-heading"><span>✓ Completed</span><span class="judge-status-count"><?=count($scoringSubmittedJudges)?></span></div><div class="judge-status-list"><?php foreach($scoringSubmittedJudges as $judge):?><div class="judge-chip"><span class="judge-chip-icon">✓</span><span class="judge-chip-name">J<?=(int)$judge["judge_order"]?> · <?=e((string)$judge["judge_name"])?><?php if((int)$judge["is_chief"]===1):?><small class="judge-chip-chief">★ CHIEF JUDGE</small><?php endif;?></span></div><?php endforeach;?><?php if(!$scoringSubmittedJudges):?><div class="judge-chip"><span class="judge-chip-icon">—</span><span class="judge-chip-name">Waiting for first submission</span></div><?php endif;?></div></section><div class="scoring-progress-total"><div class="big"><?= (int) ($x["submitted"] ?? 0) ?> / <?= (int) ($x["total"] ?? 0) ?></div><div class="small">JUDGES SUBMITTED</div><div class="scoring-progress-bar"><span style="width:<?=((int)($x["total"]??0)>0?round(((int)($x["submitted"]??0)/(int)$x["total"])*100):0)?>%"></span></div></div><section class="judge-status-panel pending"><div class="judge-status-heading"><span>◷ Pending</span><span class="judge-status-count"><?=count($scoringPendingJudges)?></span></div><div class="judge-status-list"><?php foreach($scoringPendingJudges as $judge):?><div class="judge-chip"><span class="judge-chip-icon">…</span><span class="judge-chip-name">J<?=(int)$judge["judge_order"]?> · <?=e((string)$judge["judge_name"])?><?php if((int)$judge["is_chief"]===1):?><small class="judge-chip-chief">★ CHIEF JUDGE</small><?php endif;?></span></div><?php endforeach;?><?php if(!$scoringPendingJudges):?><div class="judge-chip"><span class="judge-chip-icon">✓</span><span class="judge-chip-name">All judges completed</span></div><?php endif;?></div></section></div><?php endif;
elseif ($type === "judges"):
    foreach ($items as $x):
        $country = trim((string) ($x["country"] ?? ""));
        $countryCode = trim((string) ($x["country_code"] ?? "")); ?><div class="item"><?php if (
    !empty($x["photo_url"])
): ?><img class="photo" src="<?= e(
    $x["photo_url"],
) ?>" onerror="this.remove()"><?php endif; ?><div class="name"><?=
e($x["full_name"] ?: $x["judge_name"])
?><?= (int) $x["is_chief"] ? " ★" : ""
?></div><div class="small"><?= (int) $x["is_chief"] ? "CHIEF JUDGE · " : "" ?><?= e(strtoupper((string) ($x["scoring_scope"] ?? "all"))) ?></div><?php if ($country !== "" || $countryCode !== ""): ?><div class="small" style="display:flex;align-items:center;justify-content:center;gap:.45em;min-height:clamp(30px,2.5vw,64px)"><?php if ($flagUrl = country_flag_url($countryCode !== "" ? $countryCode : $country)): ?><img src="<?= e($flagUrl) ?>" alt="<?= e($country ?: $countryCode) ?> flag" style="width:clamp(42px,3.2vw,78px);height:clamp(28px,2.13vw,52px);object-fit:cover;border:2px solid rgba(255,255,255,.7);border-radius:5px;box-shadow:0 3px 10px rgba(0,0,0,.45)"><?php endif; ?><?= $country !== "" ? e($country) : "" ?></div><?php endif; ?></div><?php
    endforeach;
else:
    foreach ($items as $x):
        if (array_key_exists("leader_name", $x)): ?><div class="item"><div class="small" style="font-weight:900;letter-spacing:.08em">COUPLE <?= (int) $x["pair_number"] ?></div><div style="display:flex;align-items:center;justify-content:center;gap:clamp(14px,1.5vw,34px);width:100%;margin:.35em 0"><?php foreach ([["name" => $x["leader_name"], "bib" => $x["leader_bib"], "photo" => $x["leader_photo"], "country" => $x["leader_country"]], ["name" => $x["follower_name"], "bib" => $x["follower_bib"], "photo" => $x["follower_photo"], "country" => $x["follower_country"]]] as $person): $fallbackPhoto = $animalPhoto((string) (($person["bib"] ?? "") . "|" . ($person["name"] ?? ""))); $displayPhoto = !empty($person["photo"]) ? (string) $person["photo"] : $fallbackPhoto; ?><div style="display:flex;flex-direction:column;align-items:center;min-width:0;max-width:44%"><img class="photo" src="<?= e($displayPhoto) ?>" onerror="this.onerror=null;this.src='<?= e($fallbackPhoto) ?>'" style="margin-bottom:.25em"><div class="name" style="font-size:.86em"><?= e($person["name"] ?: "Partner pending") ?></div><div class="small">BIB <?= !empty($person["bib"]) ? (int) $person["bib"] : "—" ?></div><?php if (!empty($person["country"])): ?><div class="small" style="display:flex;align-items:center;justify-content:center;gap:.3em"><?php if ($flagUrl = country_flag_url($person["country"])): ?><img src="<?= e($flagUrl) ?>" alt="<?= e($person["country"]) ?> flag" style="width:clamp(30px,2.25vw,56px);height:clamp(20px,1.5vw,37px);object-fit:cover;border:1px solid rgba(255,255,255,.7);border-radius:4px"><?php endif; ?><?= e($person["country"]) ?></div><?php endif; ?></div><?php endforeach; ?></div></div><?php else:
    $fallbackPhoto = $animalPhoto(
        (string) (($x["bib_number"] ?? "") . "|" . ($x["display_name"] ?? "")),
    );
    $displayPhoto = !empty($x["photo_url"])
        ? (string) $x["photo_url"]
        : $fallbackPhoto;
    ?><div class="item"><img class="photo" src="<?= e(
    $displayPhoto,
) ?>" onerror="this.onerror=null;this.src='<?= e($fallbackPhoto) ?>'"><div class="name"><?= e(
    $x["display_name"],
) ?></div><div class="small"><?=
!empty($x["bib_number"]) ? "BIB " . (int) $x["bib_number"] : "BIB UNASSIGNED"
?><?php if (!empty($x["country"])): ?> · <?php if ($flagUrl = country_flag_url($x["country"])): ?><img src="<?= e($flagUrl) ?>" alt="<?= e($x["country"]) ?> flag" style="width:clamp(42px,3.2vw,78px);height:clamp(28px,2.13vw,52px);object-fit:cover;border:2px solid rgba(255,255,255,.7);border-radius:5px;box-shadow:0 3px 10px rgba(0,0,0,.45);vertical-align:middle;margin:0 .35em"><?php endif; ?><?= e($x["country"]) ?><?php endif;
?></div></div><?php endif; endforeach;
endif; ?></div><?php endif; ?><?php if($coupleProjection):?><style>.list{display:flex;flex-wrap:wrap;align-content:stretch;gap:.65%;padding-bottom:.45%}.list .item{flex:1 1 calc(<?=number_format(100/$coupleColumns,4,'.','')?>% - .65%);height:calc((100% - <?=number_format(max(0,$coupleRows-1)*.65,2,'.','')?>%)/<?=$coupleRows?>);padding:1%;justify-content:space-evenly}.list .item>.small:first-child{font-size:clamp(12px,.9vw,22px);font-weight:900;letter-spacing:.08em}.list .item>div:nth-child(2)>div{max-width:48%}.list .item>div:nth-child(2)>div>.name{font-size:clamp(11px,.92vw,22px)}.list .item>div:nth-child(2)>div>div:nth-of-type(2){font-size:clamp(19px,1.55vw,38px);font-weight:950;line-height:1;color:#fff;margin:.04em 0}</style><?php endif;?><?php if($type==='score_matrix'&&(string)$r['round_type']==='final'):?><style>.matrix-wrap>.score-table.matrix{table-layout:fixed}.matrix-wrap>.score-table.matrix th:first-child,.matrix-wrap>.score-table.matrix td:first-child{text-align:center;font-weight:900}.matrix-wrap>.score-table.matrix th:nth-child(2),.matrix-wrap>.score-table.matrix td:nth-child(2){text-align:left;padding-left:.75em}.matrix-wrap>.score-table.matrix tbody tr{height:auto}</style><script>(function(){document.querySelector('.provisional-label')?.remove();const table=document.querySelector('.score-table.matrix');if(!table)return;const heading=table.querySelector('thead th:first-child');if(heading)heading.textContent='Result';const headers=table.querySelectorAll('thead th');if(headers.length&&headers[headers.length-1].textContent.trim().toLowerCase()==='sum')headers[headers.length-1].remove();const judgeCount=Math.max(1,headers.length-3),colgroup=document.createElement('colgroup');colgroup.innerHTML='<col style="width:14%"><col style="width:42%">'+Array.from({length:judgeCount},()=>'<col style="width:'+(44/judgeCount)+'%">').join('');table.insertBefore(colgroup,table.firstChild);table.querySelectorAll('tbody tr').forEach(row=>{const cells=row.querySelectorAll('td');if(cells.length<2)return;const rank=Number(cells[0].textContent.trim());cells[0].textContent=rank===1?'Champion':rank===2?'1st Runner-Up':rank===3?'2nd Runner-Up':rank>3?ordinal(rank):'—';cells[1].textContent=cells[1].textContent.replace(/^#(\d+)\s*/,'#P$1 · ');const latest=row.querySelectorAll('td');if(latest.length>2&&latest.length===headers.length)latest[latest.length-1].remove()});function ordinal(value){const mod100=value%100;if(mod100>=11&&mod100<=13)return value+'th';return value+(value%10===1?'st':value%10===2?'nd':value%10===3?'rd':'th')}})();</script><?php endif;?></div></div></body></html>
