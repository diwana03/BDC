<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
use App\Services\ProjectionSettingsService;
use App\Services\ProjectionLayoutService;
$pdo = Database::connection();
$token = trim((string) ($_GET["token"] ?? ""));
$s = LiveDisplaySessionService::byToken($pdo, $token);
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (!$s) {
    http_response_code(404);
    echo json_encode(["ok" => false]);
    exit();
}
if (!empty($s["loop_enabled"])) {
    $clock = $pdo->prepare(
        "SELECT TIMESTAMPDIFF(SECOND,updated_at,NOW()) FROM bdc_live_display_sessions WHERE id=:id",
    );
    $clock->execute(["id" => $s["id"]]);
    $elapsed = max(0, (int) $clock->fetchColumn());
    $delay = max(5, (int) ($s["loop_delay_seconds"] ?? 15));
    if ($elapsed >= $delay) {
        $allowed = [
            "holding",
            "judges",
            "competitors",
            "scoring",
            "callbacks",
            "finalists",
            "heats_scores",
            "final_results",
            "results",
            "winners",
        ];
        $screens = array_values(
            array_intersect(
                $allowed,
                array_filter(
                    array_map(
                        "trim",
                        explode(",", (string) ($s["loop_screens"] ?? "")),
                    ),
                ),
            ),
        );
        if (empty($s["results_unlocked"])) {
            $screens = array_values(
                array_diff($screens, ["final_results", "results", "winners"]),
            );
        }
        if (count($screens) >= 2) {
            $at = array_search((string) $s["screen_type"], $screens, true);
            $next = $screens[$at === false ? 0 : ($at + 1) % count($screens)];
            $pdo->prepare(
                "UPDATE bdc_live_display_sessions SET screen_type=:t,reveal_place=:rp,page_number=1,state_version=state_version+1,updated_at=NOW() WHERE id=:id AND loop_enabled=1",
            )->execute([
                "t" => $next,
                "rp" => $next === "winners" ? "all" : null,
                "id" => $s["id"],
            ]);
            $s = LiveDisplaySessionService::byToken($pdo, $token) ?: $s;
        }
    }
}
$test = $s["data_mode"] === "test";
$eventTable = $test ? "bdc_test_events" : "bdc_events";
$entryTable = $test ? "bdc_test_scoring_entries" : "bdc_scoring_entries";
$e = $pdo->prepare("SELECT name FROM {$eventTable} WHERE id=:id");
$e->execute(["id" => $s["event_id"]]);
$eventName = (string) ($e->fetchColumn() ?: "BDC Event");
$roundId = (int) ($s["current_round_id"] ?? 0);
$total = 1;
if (
    $roundId &&
    in_array($s["screen_type"], ["competitors", "callbacks", "finalists"], true)
) {
    $count = 0;
    if ($s["screen_type"] === "competitors") {
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM {$entryTable} WHERE round_id=:r AND entry_status='active'",
        );
        $q->execute(["r" => $roundId]);
        $count = (int) $q->fetchColumn();
    } else {
        $resultTable = $test
            ? "bdc_test_scoring_results"
            : "bdc_scoring_results";
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM {$resultTable} WHERE round_id=:r AND result_status IN('callback','alternate')",
        );
        $q->execute(["r" => $roundId]);
        $count = (int) $q->fetchColumn();
    }
    $set = ProjectionSettingsService::get($pdo, $roundId, $test);
    $layout = ProjectionLayoutService::resolve(
        (string) $set["screen_format"],
        max(1, $count),
        (string) $set["density"],
        $set["custom_width"] ?: null,
        $set["custom_height"] ?: null,
    );
    $total = max(1, (int) $layout["pages"]);
}
$dataVersion = "0";
if ($roundId && ($s["screen_type"] ?? "") === "matching") {
    try {
        $pairTable = $test ? "bdc_test_scoring_final_pairs" : "bdc_scoring_final_pairs";
        $q = $pdo->prepare("SELECT CONCAT(COALESCE(UNIX_TIMESTAMP(MAX(updated_at)),0),'-',COUNT(*)) FROM {$pairTable} WHERE round_id=:r");
        $q->execute(["r" => $roundId]);
        $dataVersion = (string) $q->fetchColumn();
    } catch (Throwable) { $dataVersion = (string) time(); }
} elseif (
    $roundId &&
    in_array(($s["screen_type"] ?? ""), ["scoring", "heats_scores"], true)
) {
    try {
        $sessionTable = $test
            ? "bdc_test_scoring_judge_sessions"
            : "bdc_scoring_judge_sessions";
        $markTable = $test ? "bdc_test_scoring_marks" : "bdc_scoring_marks";
        $finalMarkTable = $test
            ? "bdc_test_scoring_final_marks"
            : "bdc_scoring_final_marks";
        $parts = [];
        foreach ([$sessionTable, $markTable, $finalMarkTable] as $table) {
            $q = $pdo->prepare(
                "SELECT CONCAT(COALESCE(UNIX_TIMESTAMP(MAX(updated_at)),0),'-',COUNT(*)) FROM {$table} WHERE round_id=:r",
            );
            $q->execute(["r" => $roundId]);
            $parts[] = (string) $q->fetchColumn();
        }
        if (($s["screen_type"] ?? "") === "heats_scores") {
            $resultTable = $test
                ? "bdc_test_scoring_results"
                : "bdc_scoring_results";
            $q = $pdo->prepare(
                "SELECT CONCAT(COALESCE(UNIX_TIMESTAMP(MAX(updated_at)),0),'-',COUNT(*)) FROM {$resultTable} WHERE round_id=:r",
            );
            $q->execute(["r" => $roundId]);
            $parts[] = (string) $q->fetchColumn();
        }
        $dataVersion = implode(":", $parts);
    } catch (Throwable) {
        $dataVersion = (string) time();
    }
}
echo json_encode(
    [
        "ok" => true,
        "event_name" => $eventName,
        "round_id" => $roundId,
        "screen_type" => $s["screen_type"],
        "reveal_place" => $s["reveal_place"] ?? null,
        "page_number" => (int) $s["page_number"],
        "total_pages" => $total,
        "auto_page" => (bool) $s["auto_page"],
        "page_delay_seconds" => (int) $s["page_delay_seconds"],
        "loop_enabled" => (bool) ($s["loop_enabled"] ?? false),
        "loop_delay_seconds" => (int) ($s["loop_delay_seconds"] ?? 15),
        "state_version" => (int) $s["state_version"],
        "data_version" => $dataVersion,
        "effect_type" => $s["effect_type"] ?? null,
        "effect_version" => (int) ($s["effect_version"] ?? 0),
    ],
    JSON_UNESCAPED_SLASHES,
);
