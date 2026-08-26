<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DanceCupScoringService;

Auth::requireAdmin();
$pdo=Database::connection();
$test=(string)($_GET['data_mode']??$_POST['data_mode']??'')==='test';
if($test&&!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin required.');}
$id=(int)($_GET['id']??$_POST['id']??0);
$suffix=$test?'&data_mode=test':'';
$tables=DanceCupScoringService::tables($test);
$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';
$error='';$notice='';
$directoryCompetitorId=(int)($_POST['competitor_id']??0);
$directoryJudgeId=(int)($_POST['judge_id']??0);
try{
    DanceCupScoringService::assertScoringMode($pdo,$id,'automatic',$test);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');
        if($action==='add_competitor'){
            $name=trim((string)($_POST['display_name']??''));$number=(int)($_POST['bib_number']??0);
            if($directoryCompetitorId>0){
                $directory=$pdo->prepare("SELECT exact_name FROM bdc_competitors WHERE id=:id AND status<>'archived' LIMIT 1");
                $directory->execute(['id'=>$directoryCompetitorId]);
                $directoryName=trim((string)$directory->fetchColumn());
                if($directoryName==='')throw new RuntimeException('The selected BDC competitor is no longer available. Search and choose again.');
                $duplicate=$pdo->prepare("SELECT COUNT(*) FROM {$prefix}_entries WHERE competition_id=:competition AND competitor_id=:directory AND status='active'");
                $duplicate->execute(['competition'=>$id,'directory'=>$directoryCompetitorId]);
                if((int)$duplicate->fetchColumn()>0)throw new RuntimeException('This BDC competitor is already assigned to this category.');
                $name=$directoryName;
            }
            if($name===''||$number<1)throw new RuntimeException('Contestant name and number are required.');
            $q=$pdo->prepare("INSERT INTO {$prefix}_entries(competition_id,competitor_id,bib_number,display_name) VALUES(:competition,:directory,:number,:name)");
            $q->execute(['competition'=>$id,'directory'=>$directoryCompetitorId?:null,'number'=>$number,'name'=>$name]);$notice='Contestant added to Automatic Scoring.';
        }elseif($action==='add_judge'){
            $name=trim((string)($_POST['judge_name']??''));
            if($directoryJudgeId>0){
                $directory=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),full_name) FROM bdc_judges WHERE id=:id AND status='active' LIMIT 1");
                $directory->execute(['id'=>$directoryJudgeId]);
                $directoryName=trim((string)$directory->fetchColumn());
                if($directoryName==='')throw new RuntimeException('The selected Judge Database profile is no longer active. Search and choose again.');
                $duplicate=$pdo->prepare("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=:competition AND judge_id=:directory");
                $duplicate->execute(['competition'=>$id,'directory'=>$directoryJudgeId]);
                if((int)$duplicate->fetchColumn()>0)throw new RuntimeException('This Judge Database profile is already assigned to this category.');
                $name=$directoryName;
            }
            if($name==='')throw new RuntimeException('Judge name is required.');
            $duplicate=$pdo->prepare("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=:competition AND LOWER(TRIM(judge_name))=LOWER(:name)");
            $duplicate->execute(['competition'=>$id,'name'=>$name]);if((int)$duplicate->fetchColumn()>0)throw new RuntimeException('This judge is already assigned.');
            $q=$pdo->prepare("INSERT INTO {$prefix}_judges(competition_id,judge_id,judge_name,judge_order,is_chief) VALUES(:competition,:directory,:name,(SELECT COALESCE(MAX(j.judge_order),0)+1 FROM {$prefix}_judges j WHERE j.competition_id=:same),:chief)");
            $q->execute(['competition'=>$id,'directory'=>$directoryJudgeId?:null,'name'=>$name,'same'=>$id,'chief'=>!empty($_POST['is_chief'])?1:0]);$notice='Judge added to Automatic Scoring.';
        }else{throw new RuntimeException('Unsupported setup action.');}
        header('Location: automatic-setup.php?id='.$id.$suffix.'&saved=1',true,303);exit;
    }
}catch(Throwable $e){$error=$e->getMessage();}
if(isset($_GET['saved']))$notice='Automatic setup saved.';
$q=$pdo->prepare("SELECT c.*,e.name event_name,e.event_date FROM {$tables['competitions']} c JOIN {$tables['events']} e ON e.id=c.event_id WHERE c.id=:id AND c.scoring_mode='automatic'");$q->execute(['id'=>$id]);$competition=$q->fetch();
if(!$competition){http_response_code(404);exit($error?:'Automatic Dance Cup category not found.');}
$q=$pdo->prepare("SELECT * FROM {$prefix}_entries WHERE competition_id=:id AND status='active' ORDER BY bib_number,id");$q->execute(['id'=>$id]);$entries=$q->fetchAll();
$q=$pdo->prepare("SELECT * FROM {$prefix}_judges WHERE competition_id=:id ORDER BY judge_order,id");$q->execute(['id'=>$id]);$judges=$q->fetchAll();
$csrf=Csrf::token();
ob_start(static function(string $html)use($test):string{
    $dashboardHref=$test?'../?data_mode=test':'../';
    $navbar='<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="'.e($dashboardHref).'">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="'.e($dashboardHref).'">Back to Dashboard</a></div></nav>';
    $html=str_replace('<body class="bg-light"><main class="container py-4"','<body class="bg-light">'.$navbar.'<main class="container py-4"',$html);
    $html=str_replace(
        '<input class="form-control" name="display_name" placeholder="Contestant or team name" required>',
        '<div class="dc-directory-field"><input class="form-control" name="display_name" placeholder="Type competitor name or BDC ID" data-directory-type="competitor" data-directory-target="dcAutomaticCompetitorId" autocomplete="off" required><input id="dcAutomaticCompetitorId" type="hidden" name="competitor_id" value=""><span class="dc-directory-hint">Choose a suggestion to link the existing BDC profile.</span></div>',
        $html
    );
    $html=str_replace(
        '<input class="form-control" name="judge_name" placeholder="Judge name" required>',
        '<div class="dc-directory-field"><input class="form-control" name="judge_name" placeholder="Type judge name or Judge ID" data-directory-type="judge" data-directory-target="dcAutomaticJudgeId" autocomplete="off" required><input id="dcAutomaticJudgeId" type="hidden" name="judge_id" value=""><span class="dc-directory-hint">Choose a suggestion to link the existing Judge Database profile.</span></div>',
        $html
    );
    return str_replace('</head>','<script defer src="../../public/js/dance-cup-directory.js?v=421"></script></head>',$html);
});
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Setup | Dance Cup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=398" rel="stylesheet"><script defer src="../../public/assets/js/bdc-theme.js?v=420"></script></head><body class="bg-light"><main class="container py-4" style="max-width:1300px"><div class="d-flex gap-3 mb-3"><a href="workflow.php?workflow=automatic<?=$suffix?>">← Automatic Categories</a><a href="./<?=$test?'?data_mode=test':''?>">Scoring Options</a></div><section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><span class="badge text-bg-primary"><?=$test?'TEST · ':''?>AUTOMATIC SCORING</span><h1 class="h3 mt-2"><?=e($competition['category_name'])?></h1><p class="text-muted mb-0"><?=e($competition['event_name'])?> · Set up this Automatic category before issuing judge links.</p></div></section><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><div class="row g-4"><div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Contestants</h2><form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="add_competitor"><div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" placeholder="No." required></div><div class="col"><input class="form-control" name="display_name" placeholder="Contestant or team name" required></div><div class="col-auto"><button class="btn btn-primary">Add</button></div></form><div class="list-group"><?php foreach($entries as $entry):?><div class="list-group-item"><strong>#<?=(int)$entry['bib_number']?></strong> · <?=e($entry['display_name'])?></div><?php endforeach;?><?php if(!$entries):?><div class="text-muted">No contestants assigned.</div><?php endif;?></div></div></section></div><div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Judges</h2><form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="add_judge"><div class="col"><input class="form-control" name="judge_name" placeholder="Judge name" required></div><div class="col-auto form-check pt-2"><input class="form-check-input" type="checkbox" name="is_chief" id="chief"><label class="form-check-label" for="chief">Chief</label></div><div class="col-auto"><button class="btn btn-dark">Add</button></div></form><div class="list-group"><?php foreach($judges as $judge):?><div class="list-group-item"><strong>J<?=(int)$judge['judge_order']?></strong> · <?=e($judge['judge_name'])?><?=$judge['is_chief']?' ★ Chief':''?></div><?php endforeach;?><?php if(!$judges):?><div class="text-muted">No judges assigned.</div><?php endif;?></div></div></section></div></div><section class="card border-0 shadow-sm mt-4"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1">Automatic Judge Scoring</h2><p class="text-muted mb-0">Generate secure judge links only for this Automatic category.</p></div><a class="btn btn-primary btn-lg <?=(!$entries||!$judges)?'disabled':''?>" href="automation.php?id=<?=$id?><?=$suffix?>">Open Judge Links &amp; Progress</a></div></section></main></body></html>
