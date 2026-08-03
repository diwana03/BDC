<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\RelativePlacementCalculator;
use App\Services\SchemaUpdater;
use App\Services\ScoringTestEngine;

Auth::requireSuperAdmin();
$pdo=Database::connection();
SchemaUpdater::run($pdo);

if(!isset($_SESSION['bdc_scoring_test'])){
    $_SESSION['bdc_scoring_test']=[
        'judges'=>[],
        'competitors'=>[],
        'pairs'=>[],
        'heats_marks'=>[],
        'final_marks'=>[],
        'heats_results'=>[],
        'final_results'=>[],
        'callback_count'=>5,
    ];
}
$state=&$_SESSION['bdc_scoring_test'];
$message='';
$error='';

function testId(array $items):int{
    $ids=array_map(fn(array $item)=>(int)$item['test_id'],$items);
    return $ids?max($ids)+1:1;
}

function testNames(PDO $pdo,string $role,int $count):array{
    $where=$role==='leader'?"dance_role IN ('leader','both','unknown')":"dance_role IN ('follower','both','unknown')";
    $stmt=$pdo->query("
        SELECT id,exact_name,country
        FROM bdc_competitors
        WHERE status='active' AND {$where}
        ORDER BY RAND()
        LIMIT ".max(1,min(100,$count))
    );
    return $stmt->fetchAll();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');

        if($action==='reset'){
            $_SESSION['bdc_scoring_test']=[
                'judges'=>[],'competitors'=>[],'pairs'=>[],
                'heats_marks'=>[],'final_marks'=>[],
                'heats_results'=>[],'final_results'=>[],
                'callback_count'=>5,
            ];
            $state=&$_SESSION['bdc_scoring_test'];
            $message='Test session reset. Nothing was written to the database.';
        }

        elseif($action==='generate_setup'){
            $judgeCount=max(3,min(101,(int)($_POST['judge_count']??5)));
            $leaderCount=max(2,min(60,(int)($_POST['leader_count']??10)));
            $followerCount=max(2,min(60,(int)($_POST['follower_count']??10)));
            $callback=max(1,min($leaderCount,$followerCount,(int)($_POST['callback_count']??5)));

            $allCount=max(0,(int)($_POST['all_judge_count']??$judgeCount));$leaderOnly=max(0,(int)($_POST['leader_judge_count']??0));$followerOnly=max(0,(int)($_POST['follower_judge_count']??0));
            if($allCount+$leaderOnly+$followerOnly!==$judgeCount)throw new RuntimeException('All + Leader-only + Follower-only judges must equal Total judges.');
            if($allCount+$leaderOnly<3)throw new RuntimeException('Leader panel must contain at least 3 judges.');if($allCount+$followerOnly<3)throw new RuntimeException('Follower panel must contain at least 3 judges.');
            $scopes=array_merge(array_fill(0,$allCount,'all'),array_fill(0,$leaderOnly,'leader'),array_fill(0,$followerOnly,'follower'));shuffle($scopes);$chiefIndex=random_int(0,$judgeCount-1);
            $state['judges']=[];for($i=1;$i<=$judgeCount;$i++)$state['judges'][]=['test_id'=>$i,'name'=>'J'.$i,'is_chief'=>($i-1)===$chiefIndex,'scoring_scope'=>$scopes[$i-1]];

            $state['competitors']=[];
            foreach(testNames($pdo,'leader',$leaderCount) as $row){
                $state['competitors'][]=[
                    'test_id'=>testId($state['competitors']),
                    'database_id'=>(int)$row['id'],
                    'name'=>$row['exact_name'],
                    'country'=>$row['country']??'',
                    'role'=>'leader',
                ];
            }
            foreach(testNames($pdo,'follower',$followerCount) as $row){
                $state['competitors'][]=[
                    'test_id'=>testId($state['competitors']),
                    'database_id'=>(int)$row['id'],
                    'name'=>$row['exact_name'],
                    'country'=>$row['country']??'',
                    'role'=>'follower',
                ];
            }

            $state['callback_count']=$callback;
            $state['pairs']=[];
            $state['heats_marks']=ScoringTestEngine::randomHeatsMarks($state['competitors'],$state['judges']);
            $state['final_marks']=[];
            $state['heats_results']=[];
            $state['final_results']=[];
            $message='Random judges, competitors and Heats marks generated in this session only.';
        }

        elseif($action==='save_setup'){
            foreach($state['judges'] as &$judge){
                $id=(int)$judge['test_id'];
                $judge['name']=trim((string)($_POST['judge_name'][$id]??$judge['name']))?:$judge['name'];$judge['is_chief']=(int)($_POST['chief_judge']??0)===$id;$scope=(string)($_POST['judge_scope'][$id]??($judge['scoring_scope']??'all'));$judge['scoring_scope']=in_array($scope,['all','leader','follower'],true)?$scope:'all';
            }
            unset($judge);
            if(!array_filter($state['judges'],fn(array $j):bool=>$j['is_chief'])){
                $state['judges'][0]['is_chief']=true;
            }
            $state['callback_count']=max(1,(int)($_POST['callback_count']??$state['callback_count']));
            $message='Test setup updated in memory.';
        }

        elseif($action==='add_competitor'){
            $name=trim((string)($_POST['competitor_name']??''));
            $role=(string)($_POST['competitor_role']??'');
            if($name===''||!in_array($role,['leader','follower'],true))throw new RuntimeException('Enter a name and role.');
            $state['competitors'][]=[
                'test_id'=>testId($state['competitors']),
                'database_id'=>null,
                'name'=>$name,
                'country'=>'',
                'role'=>$role,
            ];
            $state['heats_marks']=ScoringTestEngine::randomHeatsMarks($state['competitors'],$state['judges']);
            $message='Manual test competitor added. It was not added to the database.';
        }

        elseif($action==='remove_competitor'){
            $id=(int)($_POST['test_id']??0);
            $state['competitors']=array_values(array_filter($state['competitors'],fn(array $c):bool=>(int)$c['test_id']!==$id));
            unset($state['heats_marks'][$id]);
            $state['pairs']=[];
            $state['final_marks']=[];
            $state['final_results']=[];
            $message='Test competitor removed.';
        }

        elseif($action==='random_heats'){
            $state['heats_marks']=ScoringTestEngine::randomHeatsMarks($state['competitors'],$state['judges']);
            $state['heats_results']=[];
            $message='New random Heats marks generated.';
        }

        elseif($action==='calculate_heats'){
            $state['heats_marks']=[];
            foreach(($_POST['heat_mark']??[]) as $competitorId=>$judgeMarks){
                foreach($judgeMarks as $judgeId=>$value){
                    $state['heats_marks'][(int)$competitorId][(int)$judgeId]=strtoupper(trim((string)$value));
                }
            }
            $state['heats_results']=ScoringTestEngine::calculateHeats(
                $state['competitors'],$state['judges'],$state['heats_marks'],(int)$state['callback_count']
            );
            $message='Heats calculated and sorted. Test data remains session-only.';
        }

        elseif($action==='create_final'){
            if(!$state['heats_results'])throw new RuntimeException('Calculate Heats first.');
            $leaders=array_values(array_filter($state['heats_results'],fn(array $r):bool=>$r['role']==='leader'&&$r['status']==='Callback'));
            $followers=array_values(array_filter($state['heats_results'],fn(array $r):bool=>$r['role']==='follower'&&$r['status']==='Callback'));
            $pairCount=min(count($leaders),count($followers));
            if($pairCount<2)throw new RuntimeException('At least two callback Leaders and Followers are required.');

            shuffle($followers);
            $state['pairs']=[];
            for($i=0;$i<$pairCount;$i++){
                $state['pairs'][]=[
                    'test_id'=>$i+1,
                    'leader_name'=>$leaders[$i]['name'],
                    'follower_name'=>$followers[$i]['name'],
                ];
            }
            $state['final_marks']=ScoringTestEngine::randomFinalMarks($state['pairs'],$state['judges']);
            $state['final_results']=[];
            $message='Finalists paired randomly and Final rankings generated.';
        }

        elseif($action==='add_manual_pair'){
            $leader=trim((string)($_POST['leader_name']??''));
            $follower=trim((string)($_POST['follower_name']??''));
            if($leader===''||$follower==='')throw new RuntimeException('Enter both Leader and Follower names.');
            $state['pairs'][]=[
                'test_id'=>testId($state['pairs']),
                'leader_name'=>$leader,
                'follower_name'=>$follower,
            ];
            $state['final_marks']=ScoringTestEngine::randomFinalMarks($state['pairs'],$state['judges']);
            $state['final_results']=[];
            $message='Manual test pair added.';
        }

        elseif($action==='random_final'){
            $state['final_marks']=ScoringTestEngine::randomFinalMarks($state['pairs'],$state['judges']);
            $state['final_results']=[];
            $message='New random Final rankings generated.';
        }

        elseif($action==='calculate_final'){
            $state['final_marks']=[];
            foreach(($_POST['final_mark']??[]) as $pairId=>$judgeMarks){
                foreach($judgeMarks as $judgeId=>$rank){
                    $state['final_marks'][(int)$pairId][(int)$judgeId]=(int)$rank;
                }
            }
            ScoringTestEngine::validateFinalMarks($state['pairs'],$state['judges'],$state['final_marks']);
            $pairIds=array_map(fn(array $p)=>(int)$p['test_id'],$state['pairs']);
            $judgeIds=array_map(fn(array $j)=>(int)$j['test_id'],$state['judges']);
            $chiefId=(int)(array_values(array_filter($state['judges'],fn(array $j):bool=>$j['is_chief']))[0]['test_id']??0);
            $state['final_results']=RelativePlacementCalculator::calculate($pairIds,$judgeIds,$chiefId,$state['final_marks']);
            $message='Relative Placement calculated using the production calculator.';
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$csrf=Csrf::token();
$competitors=$state['competitors'];
$judges=$state['judges'];
$pairs=$state['pairs'];
$heatsResults=$state['heats_results'];
$finalResults=$state['final_results'];
$finalByPair=[];
foreach($finalResults as $result)$finalByPair[(int)$result['pair_id']]=$result;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scoring Test Engine | BDC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f5f7}.card{border:0;border-radius:14px}.test-banner{background:#7c2d12;color:#fff;font-weight:800;text-align:center;padding:10px}.sticky-actions{position:sticky;bottom:0;background:#fff;padding:12px;border-top:1px solid #ddd}.score-select{min-width:72px}.name-cell{min-width:210px}.rank-box{display:inline-flex;width:38px;height:38px;border-radius:50%;align-items:center;justify-content:center;background:#111827;color:#fff;font-weight:800}
</style>
</head>
<body>
<div class="test-banner">TEST ENGINE · SESSION ONLY · NOTHING IS COMMITTED TO THE DATABASE</div>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><span class="text-white">Scoring Test Engine</span></div></nav>
<main class="container-fluid px-3 px-lg-4 py-4">
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>

<div class="row g-4">
<div class="col-xl-3">
 <div class="card shadow-sm mb-4"><div class="card-body">
  <h2 class="h5">1. Generate Test Setup</h2>
  <form method="post" class="row g-3">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="generate_setup">
   <div class="col-6"><label class="form-label">Judges</label><input class="form-control" type="number" name="judge_count" min="3" max="101" value="<?=count($judges)?:5?>"></div>
   <div class="col-6"><label class="form-label">Callbacks</label><input class="form-control" type="number" name="callback_count" min="1" value="<?=(int)$state['callback_count']?>"></div><div class="col-4"><label class="form-label">All judges</label><input class="form-control" type="number" name="all_judge_count" min="0" value="<?=count($judges)?:5?>"></div><div class="col-4"><label class="form-label">Leader only</label><input class="form-control" type="number" name="leader_judge_count" min="0" value="0"></div><div class="col-4"><label class="form-label">Follower only</label><input class="form-control" type="number" name="follower_judge_count" min="0" value="0"></div><div class="col-12"><div class="form-text">All + Leader only + Follower only must equal Total. Chief Judge is random and editable.</div></div>
   <div class="col-6"><label class="form-label">Leaders</label><input class="form-control" type="number" name="leader_count" min="2" max="60" value="10"></div>
   <div class="col-6"><label class="form-label">Followers</label><input class="form-control" type="number" name="follower_count" min="2" max="60" value="10"></div>
   <div class="col-12"><button class="btn btn-primary w-100">Generate Random Setup</button></div>
  </form>
 </div></div>

 <?php if($judges):?>
 <div class="card shadow-sm mb-4"><div class="card-body">
  <h2 class="h5">Judges</h2>
  <form method="post">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_setup">
   <?php foreach($judges as $judge):?>
    <div class="row g-2 mb-2 align-items-center"><div class="col-2"><label><input type="radio" name="chief_judge" value="<?=(int)$judge['test_id']?>" <?=$judge['is_chief']?'checked':''?>> Chief</label></div><div class="col-5"><input class="form-control" name="judge_name[<?=(int)$judge['test_id']?>]" value="<?=e($judge['name'])?>"></div><div class="col-5"><select class="form-select" name="judge_scope[<?=(int)$judge['test_id']?>]"><?php foreach(['all'=>'All','leader'=>'Leaders','follower'=>'Followers'] as $scopeValue=>$scopeLabel):?><option value="<?=$scopeValue?>" <?=($judge['scoring_scope']??'all')===$scopeValue?'selected':''?>><?=$scopeLabel?></option><?php endforeach;?></select></div></div>
   <?php endforeach;?>
   <label class="form-label mt-2">Callback count per role</label>
   <input class="form-control mb-2" type="number" name="callback_count" value="<?=(int)$state['callback_count']?>" min="1">
   <button class="btn btn-outline-dark w-100">Save Judges</button>
  </form>
 </div></div>
 <?php endif;?>

 <div class="card shadow-sm mb-4"><div class="card-body">
  <h2 class="h5">Add Manual Competitor</h2>
  <form method="post" class="row g-2">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_competitor">
   <div class="col-12"><input class="form-control" name="competitor_name" placeholder="Test competitor name" required></div>
   <div class="col-12"><select class="form-select" name="competitor_role"><option value="leader">Leader</option><option value="follower">Follower</option></select></div>
   <div class="col-12"><button class="btn btn-outline-primary w-100">Add to Test Only</button></div>
  </form>
 </div></div>

 <form method="post" onsubmit="return confirm('Reset the complete test session?');">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="reset">
  <button class="btn btn-outline-danger w-100">Reset Test Session</button>
 </form>
</div>

<div class="col-xl-9">
 <?php if($competitors&&$judges):?>
 <div class="card shadow-sm mb-4"><div class="card-body p-0">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
   <div><h2 class="h5 mb-1">2. Heats Test</h2><div class="text-muted small">YES = 10, A1 = 4.5, A2 = 4.3, A3 = 4.2</div></div>
   <form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="random_heats"><button class="btn btn-outline-primary">Generate New Random Heats Scores</button></form>
  </div>
  <form method="post">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="calculate_heats">
   <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th class="name-cell">Competitor</th><th>Role</th><?php foreach($judges as $judge):?><th><?=e($judge['name'])?><?=$judge['is_chief']?' ★':''?></th><?php endforeach;?><th></th></tr></thead><tbody>
   <?php foreach($competitors as $competitor):?><tr>
    <td><strong><?=e($competitor['name'])?></strong><?php if($competitor['database_id']):?><div class="small text-muted">Database sample</div><?php else:?><div class="small text-warning">Manual test entry</div><?php endif;?></td>
    <td><?=e(ucfirst($competitor['role']))?></td>
    <?php foreach($judges as $judge):$value=$state['heats_marks'][(int)$competitor['test_id']][(int)$judge['test_id']]??'';?>
     <td><select class="form-select form-select-sm score-select" name="heat_mark[<?=(int)$competitor['test_id']?>][<?=(int)$judge['test_id']?>]"><?php foreach([''=>'—','YES'=>'YES','A1'=>'A1','A2'=>'A2','A3'=>'A3'] as $v=>$label):?><option value="<?=$v?>" <?=$value===$v?'selected':''?>><?=$label?></option><?php endforeach;?></select></td>
    <?php endforeach;?>
    <td><button class="btn btn-sm btn-outline-danger" formmethod="post" name="action" value="remove_competitor" formaction=""><input type="hidden" name="test_id" value="<?=(int)$competitor['test_id']?>">Remove</button></td>
   </tr><?php endforeach;?>
   </tbody></table></div>
   <div class="sticky-actions"><button class="btn btn-success">Calculate &amp; Sort Heats</button></div>
  </form>
 </div></div>

 <?php if($heatsResults):?>
 <div class="card shadow-sm mb-4"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center"><h2 class="h5">Heats Result</h2><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create_final"><button class="btn btn-primary">Create Random Final Pairing</button></form></div>
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Role Rank</th><th>Competitor</th><th>Role</th><th>Total</th><th>Chief</th><th>YES</th><th>Status</th></tr></thead><tbody>
  <?php foreach($heatsResults as $row):?><tr><td><span class="rank-box"><?=(int)$row['rank']?></span></td><td><?=e($row['name'])?></td><td><?=e(ucfirst($row['role']))?></td><td><?=number_format((float)$row['total_score'],1)?></td><td><?=number_format((float)$row['chief_score'],1)?></td><td><?=(int)$row['yes_count']?></td><td><span class="badge text-bg-<?=$row['status']==='Callback'?'success':($row['status']==='Alternate'?'warning':'secondary')?>"><?=e($row['status'])?></span></td></tr><?php endforeach;?>
  </tbody></table></div>
 </div></div>
 <?php endif;?>

 <?php if($pairs):?>
 <div class="card shadow-sm mb-4"><div class="card-body p-0">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
   <div><h2 class="h5 mb-1">3. Final Relative Placement Test</h2><div class="text-muted small">Every judge must use each rank exactly once.</div></div>
   <form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="random_final"><button class="btn btn-outline-primary">Generate New Random Final Rankings</button></form>
  </div>
  <form method="post">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="calculate_final">
   <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pair</th><th class="name-cell">Leader</th><th class="name-cell">Follower</th><?php foreach($judges as $judge):?><th><?=e($judge['name'])?><?=$judge['is_chief']?' ★':''?></th><?php endforeach;?></tr></thead><tbody>
   <?php foreach($pairs as $pair):?><tr>
    <td>#<?=(int)$pair['test_id']?></td><td><?=e($pair['leader_name'])?></td><td><?=e($pair['follower_name'])?></td>
    <?php foreach($judges as $judge):?><td><input class="form-control form-control-sm score-select" type="number" min="1" max="<?=count($pairs)?>" name="final_mark[<?=(int)$pair['test_id']?>][<?=(int)$judge['test_id']?>]" value="<?=(int)($state['final_marks'][(int)$pair['test_id']][(int)$judge['test_id']]??0)?>"></td><?php endforeach;?>
   </tr><?php endforeach;?>
   </tbody></table></div>
   <div class="sticky-actions"><button class="btn btn-success">Calculate Relative Placement</button></div>
  </form>
 </div></div>

 <div class="card shadow-sm mb-4"><div class="card-body">
  <h2 class="h5">Add Manual Final Pair</h2>
  <form method="post" class="row g-2">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_manual_pair">
   <div class="col-md-5"><input class="form-control" name="leader_name" placeholder="Leader name" required></div>
   <div class="col-md-5"><input class="form-control" name="follower_name" placeholder="Follower name" required></div>
   <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add Pair</button></div>
  </form>
 </div></div>
 <?php endif;?>

 <?php if($finalResults):?>
 <div class="card shadow-sm"><div class="card-body">
  <h2 class="h5">Final Result</h2>
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Rank</th><th>Couple</th><th>Majority Reached</th><th>Count</th><th>Sum</th><th>Decision</th></tr></thead><tbody>
  <?php foreach($finalResults as $result):$pair=current(array_filter($pairs,fn(array $p):bool=>(int)$p['test_id']===(int)$result['pair_id']));?><tr>
   <td><span class="rank-box"><?=(int)$result['final_rank']?></span></td>
   <td><strong><?=e($pair['leader_name']??'')?> &amp; <?=e($pair['follower_name']??'')?></strong></td>
   <td>Top <?=(int)$result['level']?></td><td><?=(int)$result['count']?></td><td><?=(int)$result['sum']?></td><td><?=e(ucwords(str_replace('_',' ',$result['deciding_step'])))?></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
 </div></div>
 <?php endif;?>
 <?php else:?>
 <div class="alert alert-info">Generate a test setup to begin.</div>
 <?php endif;?>
</div>
</div>
</main>
</body>
</html>
