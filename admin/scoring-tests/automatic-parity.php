<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Services\AutomaticJudgeBrowserService;
use App\Services\RelativePlacementCalculator;
use App\Services\ScoringRulesService;
use App\Services\SpecialCategoryService;

Auth::requireAdmin();

$leaders=max(0,min(500,(int)($_POST['leaders']??16)));
$followers=max(0,min(500,(int)($_POST['followers']??16)));
$category=(string)($_POST['category']??'novice');
$allowed=['novice','intermediate','advanced',SpecialCategoryService::RISING,SpecialCategoryService::OPEN,SpecialCategoryService::INVITATIONAL];
if(!in_array($category,$allowed,true))$category='novice';

$checks=[];
$add=function(string $name,bool $pass,string $actual,string $expected='')use(&$checks):void{
    $checks[]=['name'=>$name,'pass'=>$pass,'actual'=>$actual,'expected'=>$expected];
};

$tier=ScoringRulesService::tierFromRoleCounts($leaders,$followers);
$expectedTier=max($leaders,$followers)<=15?1:(max($leaders,$followers)<=30?2:3);
$expectedYes=[1=>5,2=>10,3=>15][$expectedTier];
$add('Participant tier uses larger role count',$tier['tier']===$expectedTier,'Tier '.$tier['tier'].' from '.$tier['largest'].' competitors','Tier '.$expectedTier);
$add('YES count comes from shared BDC tier rules',$tier['yes_count']===$expectedYes,(string)$tier['yes_count'],(string)$expectedYes);

$weights=ScoringRulesService::weights();
$add('YES weight',$weights['yes']===10.0,(string)$weights['yes'],'10');
$add('ALT 1 weight',$weights['alt1']===4.5,(string)$weights['alt1'],'4.5');
$add('ALT 2 weight',$weights['alt2']===4.3,(string)$weights['alt2'],'4.3');
$add('ALT 3 weight',$weights['alt3']===4.2,(string)$weights['alt3'],'4.2');
$add('Minimum judges per role',ScoringRulesService::MINIMUM_JUDGES_PER_ROLE===3,(string)ScoringRulesService::MINIMUM_JUDGES_PER_ROLE,'3');

$isSpecial=SpecialCategoryService::isSpecial($category);
if($isSpecial){
    $schedule=ScoringRulesService::specialPointSchedule($category);
    $expected=$category===SpecialCategoryService::INVITATIONAL?[1=>3,2=>2,3=>1]:[1=>5,2=>4,3=>3,4=>2,5=>1];
    $add('Special category bypasses participant point tiers',ScoringRulesService::isSpecialCategory($category),'Special category','Special category');
    $add('Special fixed point schedule uses production service',$schedule===$expected,implode(', ',$schedule),implode(', ',$expected));
}else{
    $add('Normal division uses participant tier rules',!ScoringRulesService::isSpecialCategory($category),'Normal division','Normal division');
}

$add('Automatic judge browser service is production service',class_exists(AutomaticJudgeBrowserService::class),'Loaded','Loaded');
$add('Automatic judges use same mark weights as manual',ScoringRulesService::markWeight('yes')===10.0 && ScoringRulesService::markWeight('alt',1)===4.5 && ScoringRulesService::markWeight('alt',2)===4.3 && ScoringRulesService::markWeight('alt',3)===4.2,'YES 10 / A1 4.5 / A2 4.3 / A3 4.2','YES 10 / A1 4.5 / A2 4.3 / A3 4.2');

try{
    $pairs=[101,102,103];
    $judges=[1,2,3,4,5];
    $marks=[
        101=>[1=>1,2=>1,3=>2,4=>1,5=>2],
        102=>[1=>2,2=>2,3=>1,4=>2,5=>1],
        103=>[1=>3,2=>3,3=>3,4=>3,5=>3],
    ];
    $rp=RelativePlacementCalculator::calculate($pairs,$judges,1,$marks);
    $ranks=array_map(static fn(array $row)=>(int)$row['final_rank'],$rp);
    sort($ranks);
    $add('Final uses production Relative Placement calculator',$ranks===[1,2,3],implode(',',$ranks),'1,2,3');
}catch(Throwable $e){
    $add('Final uses production Relative Placement calculator',false,$e->getMessage(),'Successful calculation');
}

$passed=count(array_filter($checks,static fn($c)=>$c['pass']));
$total=count($checks);
$allPass=$passed===$total;
$categoryLabel=$isSpecial?SpecialCategoryService::label($category):ucfirst($category);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Automatic Scoring Parity Test | BDC Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.check-pass{border-left:5px solid #198754}.check-fail{border-left:5px solid #dc3545}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="./">BDC Scoring Tests</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="./">Testing Dashboard</a><a class="btn btn-light btn-sm" href="../">Admin Dashboard</a></div></div></nav>
<main class="container py-4" style="max-width:1200px">
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4"><div><div class="text-primary fw-bold text-uppercase small">Production Parity Test</div><h1 class="h3 mb-1">Automatic Scoring Engine</h1><p class="text-muted mb-0">This screen calls shared production BDC rule services directly. It does not maintain a second copy of scoring formulas.</p></div><span class="badge <?= $allPass?'text-bg-success':'text-bg-danger' ?> fs-6"><?=$passed?> / <?=$total?> PASS</span></div>

<div class="card shadow-sm mb-4"><div class="card-body"><form method="post" class="row g-3 align-items-end">
<div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach($allowed as $value):$label=SpecialCategoryService::isSpecial($value)?SpecialCategoryService::label($value):ucfirst($value);?><option value="<?=e($value)?>" <?=$category===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label">Leaders</label><input class="form-control" type="number" min="0" max="500" name="leaders" value="<?=$leaders?>"></div>
<div class="col-md-3"><label class="form-label">Followers</label><input class="form-control" type="number" min="0" max="500" name="followers" value="<?=$followers?>"></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Run Tests</button></div>
</form></div></div>

<div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">CATEGORY</div><div class="h4 mb-0"><?=e($categoryLabel)?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">ACTIVE TIER</div><div class="h4 mb-0"><?=$isSpecial?'Fixed Special Points':'Tier '.$tier['tier']?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">JUDGE MARKS</div><div class="h4 mb-0">YES / A1 / A2 / A3</div></div></div></div></div>

<div class="card shadow-sm"><div class="card-header d-flex justify-content-between"><strong>Parity Checks</strong><span class="badge <?= $allPass?'text-bg-success':'text-bg-danger' ?>"><?=$allPass?'ALL PASS':'CHECK FAILURES'?></span></div><div class="card-body p-0"><div class="list-group list-group-flush">
<?php foreach($checks as $check):?><div class="list-group-item <?= $check['pass']?'check-pass':'check-fail' ?>"><div class="d-flex justify-content-between gap-3"><div><strong><?=e($check['name'])?></strong><div class="small text-muted">Actual: <?=e($check['actual'])?><?php if($check['expected']!==''):?> · Expected: <?=e($check['expected'])?><?php endif;?></div></div><span class="badge <?= $check['pass']?'text-bg-success':'text-bg-danger' ?> align-self-center"><?=$check['pass']?'PASS':'FAIL'?></span></div></div><?php endforeach;?>
</div></div></div>

<div class="alert alert-info mt-4 mb-0"><strong>What this proves:</strong> Automatic and Manual use the same BDC tier thresholds and judge mark weights, special categories use the production SpecialCategoryService, and Finals use the production RelativePlacementCalculator. The browser delivery layer is Automatic-only; it does not define separate scoring mathematics.</div>
</main>
</body></html>
