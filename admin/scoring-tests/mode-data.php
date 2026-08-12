<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
use App\Services\SpecialCategoryService;
Auth::requireAdmin();
header('Content-Type: application/json; charset=UTF-8');
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??0);
$judges=[];$division='';$yesCount=10;$yesLocked=false;$scoringStarted=false;
if($roundId>0){
    $s=$pdo->prepare('SELECT id,judge_name,is_chief FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');
    $s->execute(['r'=>$roundId]);$judges=$s->fetchAll();
    $s=$pdo->prepare('SELECT division,yes_count,tier_manual_override FROM bdc_test_scoring_rounds WHERE id=:r');
    $s->execute(['r'=>$roundId]);$round=$s->fetch()?:[];$division=(string)($round['division']??'');$yesCount=(int)($round['yes_count']??10);$yesLocked=(int)($round['tier_manual_override']??0)===1;
    $s=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_marks WHERE round_id=:r AND (mark_type<>'blank' OR weighted_score>0)");$s->execute(['r'=>$roundId]);$scoringStarted=(int)$s->fetchColumn()>0;
}
$categories=SpecialCategoryService::categories();$schedules=[];
foreach(array_keys($categories) as $category)$schedules[$category]=SpecialCategoryService::schedule($category);
echo json_encode(['ok'=>true,'round_id'=>$roundId,'judges'=>$judges,'division'=>$division,'yes_count'=>$yesCount,'yes_locked'=>$yesLocked,'scoring_started'=>$scoringStarted,'special_categories'=>$categories,'special_schedules'=>$schedules],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
