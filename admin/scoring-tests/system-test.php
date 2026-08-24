<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringCalculationService;
use App\Services\TestAutomaticJudgeService;
use App\Services\TestCompetitorGeneratorService;

Auth::requireAdmin();
if(!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin required.');}

$pdo=Database::connection();
$userId=(int)(Auth::user()['id']??0);
$error='';$report=null;$eventId=(int)($_GET['event_id']??0);

function systemTestCheck(string $label,bool $passed,string $detail=''):array
{
    return ['label'=>$label,'status'=>$passed?'PASS':'FAIL','detail'=>$detail];
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
    $action=(string)($_POST['action']??'');
    try{
        if($action==='archive'){
            $eventId=(int)($_POST['event_id']??0);
            $owned=$pdo->prepare("SELECT id FROM bdc_test_events WHERE id=:id AND name LIKE 'BDC SYSTEM TEST - DO NOT PUBLISH - %'");
            $owned->execute(['id'=>$eventId]);
            if(!(int)$owned->fetchColumn())throw new RuntimeException('System test event not found.');
            $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='archived' WHERE event_id=:event")->execute(['event'=>$eventId]);
            $pdo->prepare("UPDATE bdc_test_events SET status='cancelled' WHERE id=:event")->execute(['event'=>$eventId]);
            header('Location: '.url('admin/scoring-tests/system-test.php?archived=1'),true,303);exit;
        }
        if($action!=='run')throw new RuntimeException('Invalid system test action.');

        $stamp=date('Y-m-d H-i-s');
        $name='BDC SYSTEM TEST - DO NOT PUBLISH - '.$stamp;
        $slug='bdc-system-test-'.date('YmdHis').'-'.random_int(100,999);
        $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,CURDATE(),'draft')")
            ->execute(['name'=>$name,'normalised'=>strtolower($name),'slug'=>$slug]);
        $eventId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by,status) VALUES(:event,'heats','novice',5,5,10.00,4.50,4.30,4.20,:user,'draft')")
            ->execute(['event'=>$eventId,'user'=>$userId?:null]);
        $roundId=(int)$pdo->lastInsertId();

        $generated=TestCompetitorGeneratorService::generate($pdo,$roundId,10,10,$userId);
        if((int)$generated['leaders']<8||(int)$generated['followers']<8)throw new RuntimeException('At least 8 active Leaders and Followers are required in the competitor database for this scenario.');

        $judgeInsert=$pdo->prepare("INSERT INTO bdc_test_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:position,:chief,'all')");
        $chiefId=0;
        foreach(range(1,5) as $position){
            $judgeInsert->execute(['round'=>$roundId,'name'=>'System Test Judge '.$position,'position'=>$position,'chief'=>$position===1?1:0]);
            if($position===1)$chiefId=(int)$pdo->lastInsertId();
        }
        $pdo->prepare('UPDATE bdc_test_scoring_rounds SET chief_judge_id=:chief WHERE id=:round')->execute(['chief'=>$chiefId,'round'=>$roundId]);
        TestAutomaticJudgeService::syncRound($pdo,$roundId);
        TestAutomaticJudgeService::generateAndSubmitAll($pdo,$roundId);
        $calculated=ScoringCalculationService::calculateHeats($pdo,$roundId,ScoringCalculationService::TEST,$userId);

        $count=function(string $sql,array $params)use($pdo):int{$q=$pdo->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();};
        $entryCount=$count("SELECT COUNT(*) FROM bdc_test_scoring_entries WHERE round_id=:round AND entry_status='active'",['round'=>$roundId]);
        $judgeCount=$count('SELECT COUNT(*) FROM bdc_test_scoring_judges WHERE round_id=:round',['round'=>$roundId]);
        $submittedCount=$count("SELECT COUNT(*) FROM bdc_test_scoring_judge_sessions WHERE round_id=:round AND status='submitted'",['round'=>$roundId]);
        $markCount=$count('SELECT COUNT(*) FROM bdc_test_scoring_marks WHERE round_id=:round',['round'=>$roundId]);
        $resultCount=$count('SELECT COUNT(*) FROM bdc_test_scoring_results WHERE round_id=:round',['round'=>$roundId]);
        $callbackLeaders=$count("SELECT COUNT(*) FROM bdc_test_scoring_results r JOIN bdc_test_scoring_entries e ON e.id=r.entry_id WHERE r.round_id=:round AND e.dance_role='leader' AND r.result_status='callback'",['round'=>$roundId]);
        $callbackFollowers=$count("SELECT COUNT(*) FROM bdc_test_scoring_results r JOIN bdc_test_scoring_entries e ON e.id=r.entry_id WHERE r.round_id=:round AND e.dance_role='follower' AND r.result_status='callback'",['round'=>$roundId]);
        $tiePending=$count("SELECT COUNT(*) FROM bdc_test_scoring_results WHERE round_id=:round AND result_status='tie_pending'",['round'=>$roundId]);
        $boundaryStateValid=!empty($calculated['pending_tie'])?$tiePending>0:($callbackLeaders===5&&$callbackFollowers===5);

        $checks=[
            systemTestCheck('Isolated draft event',true,$name),
            systemTestCheck('Isolated scoring roster',$entryCount>=16,$entryCount.' active entries'),
            systemTestCheck('Judge panel',$judgeCount===5,$judgeCount.' judges including one Chief'),
            systemTestCheck('Judge submission locks',$submittedCount===5,$submittedCount.'/5 submitted'),
            systemTestCheck('Score persistence',$markCount>0,$markCount.' saved marks'),
            systemTestCheck('Shared scoring calculation',$resultCount===$entryCount,$resultCount.'/'.$entryCount.' result rows'),
            systemTestCheck('Valid callback or tie state',$boundaryStateValid,!empty($calculated['pending_tie'])?$tiePending.' boundary rows awaiting Chief decision':$callbackLeaders.' Leader and '.$callbackFollowers.' Follower callbacks'),
            systemTestCheck('Calculation version advanced',(int)$calculated['version']>0,'Calculation version '.(int)$calculated['version']),
        ];
        $report=['event_id'=>$eventId,'round_id'=>$roundId,'name'=>$name,'checks'=>$checks,'passed'=>!array_filter($checks,static fn(array $check):bool=>$check['status']!=='PASS')];
        $pdo->prepare("INSERT INTO bdc_test_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,'production_hosted_system_test_completed',:details)")
            ->execute(['round'=>$roundId,'user'=>$userId?:null,'details'=>json_encode($report,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    }catch(Throwable $e){$error=$e->getMessage();}
}

$recent=$pdo->query("SELECT e.id,e.name,e.status,e.created_at,COUNT(r.id) rounds FROM bdc_test_events e LEFT JOIN bdc_test_scoring_rounds r ON r.event_id=e.id WHERE e.name LIKE 'BDC SYSTEM TEST - DO NOT PUBLISH - %' GROUP BY e.id,e.name,e.status,e.created_at ORDER BY e.id DESC LIMIT 10")->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDC System Test Runner</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=391" rel="stylesheet"></head><body class="bg-light"><main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><div class="text-danger fw-bold">SUPER ADMIN · ISOLATED TEST DATA</div><h1 class="h3 mb-1">BDC System Test Runner</h1><p class="text-muted mb-0">Runs on the Production application using only <code>bdc_test_*</code> tables. It never publishes results or writes to live scoring tables.</p></div><a class="btn btn-outline-dark" href="dashboard.php?test_mode=automated">Back</a></div>
<?php if(isset($_GET['archived'])):?><div class="alert alert-success">The system test event was archived.</div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><strong>Test stopped:</strong> <?=e($error)?></div><?php endif;?>
<section class="card shadow-sm border-danger mb-4"><div class="card-body"><h2 class="h5">One-click Heats smoke test</h2><p>Creates a hidden draft event, 10 Leaders, 10 Followers, five judges, submitted judge scores and calculated callbacks. It then verifies persistence and scoring invariants.</p><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="run"><button class="btn btn-danger fw-bold">Run Isolated System Test</button></form></div></section>
<?php if($report):?><section class="card shadow-sm mb-4"><div class="card-header d-flex justify-content-between"><strong><?=e($report['name'])?></strong><span class="badge <?=$report['passed']?'text-bg-success':'text-bg-danger'?>"><?=$report['passed']?'PASS':'FAIL'?></span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Check</th><th>Status</th><th>Evidence</th></tr></thead><tbody><?php foreach($report['checks'] as $check):?><tr><td><?=e($check['label'])?></td><td><span class="badge <?=$check['status']==='PASS'?'text-bg-success':'text-bg-danger'?>"><?=e($check['status'])?></span></td><td><?=e($check['detail'])?></td></tr><?php endforeach;?></tbody></table></div><div class="card-body d-flex flex-wrap gap-2"><a class="btn btn-primary" href="dashboard.php?legacy=1&amp;test_mode=automated&amp;round_id=<?=(int)$report['round_id']?>">Open Generated Round</a><a class="btn btn-outline-danger" target="_blank" rel="noopener" href="../live-screen/test-control.php?round_id=<?=(int)$report['round_id']?>">Open Test Projector Control</a><form method="post" onsubmit="return confirm('Archive this isolated system test event?');"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="event_id" value="<?=(int)$report['event_id']?>"><button class="btn btn-outline-secondary">Archive Test Event</button></form></div></section><?php endif;?>
<section class="card shadow-sm"><div class="card-header"><strong>Recent system tests</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Event</th><th>Status</th><th>Rounds</th><th>Created</th></tr></thead><tbody><?php foreach($recent as $item):?><tr><td><?=e($item['name'])?></td><td><?=e($item['status'])?></td><td><?=(int)$item['rounds']?></td><td><?=e($item['created_at'])?></td></tr><?php endforeach;?><?php if(!$recent):?><tr><td colspan="4" class="text-muted">No system tests have been run yet.</td></tr><?php endif;?></tbody></table></div></section></main></body></html>
