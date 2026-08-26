<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DanceCupRosterService;
use App\Services\DanceCupScoringService;

function dcAutomaticWorkspaceSnapshot(PDO $pdo,string $prefix,int $competition):array{
    $snapshot=[];
    foreach(['entries','judges','marks','scoring_results'] as $name){$query=$pdo->prepare("SELECT * FROM {$prefix}_{$name} WHERE competition_id=:competition");$query->execute(['competition'=>$competition]);$snapshot[$name]=$query->fetchAll();}
    return $snapshot;
}

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
    DanceCupScoringService::ensureAutomation($pdo,$id,$test);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');
        $statusQuery=$pdo->prepare("SELECT status FROM {$tables['competitions']} WHERE id=:competition");$statusQuery->execute(['competition'=>$id]);$currentStatus=(string)$statusQuery->fetchColumn();
        if(in_array($currentStatus,['submitted','pending_approval','approved'],true)&&!in_array($action,['checkpoint','reset_projection'],true))throw new RuntimeException('This Automatic round is submitted and locked.');
        if($action==='add_competitor'){
            $name=trim((string)($_POST['display_name']??''));$number=(int)($_POST['bib_number']??0);
            if($directoryCompetitorId>0){
                DanceCupScoringService::assertDanceCupEligibility($pdo,$directoryCompetitorId,$id,$test);
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
            $q->execute(['competition'=>$id,'directory'=>$directoryJudgeId?:null,'name'=>$name,'same'=>$id,'chief'=>0]);
            $addedJudgeId=(int)$pdo->lastInsertId();
            if(!empty($_POST['is_chief']))DanceCupRosterService::makeAddedJudgeChief($pdo,$prefix,$id,$addedJudgeId);
            $notice='Judge added to Automatic Scoring.';
        }elseif(in_array($action,['remove_competitor','move_competitor','remove_judge','move_judge','set_chief_judge'],true)){
            $notice=DanceCupRosterService::apply($pdo,$id,$action,$_POST,$test);
        }elseif($action==='regenerate'){
            $pdo->prepare("UPDATE {$prefix}_judge_sessions SET access_token=:token,status='not_started',started_at=NULL,submitted_at=NULL WHERE id=:session AND competition_id=:competition")->execute(['token'=>bin2hex(random_bytes(32)),'session'=>(int)($_POST['session_id']??0),'competition'=>$id]);$notice='Judge link regenerated.';
        }elseif($action==='reopen'){
            $pdo->prepare("UPDATE {$prefix}_judge_sessions SET status='scoring',submitted_at=NULL WHERE id=:session AND competition_id=:competition")->execute(['session'=>(int)($_POST['session_id']??0),'competition'=>$id]);$notice='Judge scores reopened; existing marks were preserved.';
        }elseif($action==='checkpoint'){
            $label=trim((string)($_POST['checkpoint_label']??''))?:'Automatic checkpoint '.date('Y-m-d H:i');$query=$pdo->prepare("INSERT INTO {$prefix}_checkpoints(competition_id,label,snapshot_json,created_by) VALUES(:competition,:label,:snapshot,:user)");$query->execute(['competition'=>$id,'label'=>$label,'snapshot'=>json_encode(dcAutomaticWorkspaceSnapshot($pdo,$prefix,$id),JSON_UNESCAPED_SLASHES),'user'=>(int)(Auth::user()['id']??0)?:null]);$notice='Automatic scoring checkpoint saved.';
        }elseif($action==='confirm_roster'){
            $entryCount=(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}_entries WHERE competition_id=".$id." AND status='active'")->fetchColumn();
            $judgeCount=(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=".$id)->fetchColumn();
            $chiefCount=(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=".$id." AND is_chief=1")->fetchColumn();
            if($entryCount<1)throw new RuntimeException('Add at least one contestant before opening scoring.');
            if($judgeCount<1)throw new RuntimeException('Add at least one judge before opening scoring.');
            if($chiefCount!==1)throw new RuntimeException('Select exactly one Chief Judge before opening scoring.');
            DanceCupScoringService::ensureAutomation($pdo,$id,$test);$notice='Roster confirmed. Judge scoring is ready.';
        }elseif($action==='reset_projection'){
            $event=$pdo->prepare("SELECT event_id FROM {$tables['competitions']} WHERE id=:competition");$event->execute(['competition'=>$id]);$pdo->prepare("UPDATE {$prefix}_event_projection SET access_token=:token,screen_type='holding',auto_cycle=0,state_version=state_version+1 WHERE event_id=:event")->execute(['token'=>bin2hex(random_bytes(32)),'event'=>(int)$event->fetchColumn()]);$notice='Projector link regenerated.';
        }else{throw new RuntimeException('Unsupported setup action.');}
        header('Location: automatic-setup.php?id='.$id.$suffix.'&saved=1',true,303);exit;
    }
}catch(Throwable $e){$error=$e->getMessage();}
if(isset($_GET['saved']))$notice='Automatic setup saved.';
$q=$pdo->prepare("SELECT c.*,e.name event_name,e.event_date FROM {$tables['competitions']} c JOIN {$tables['events']} e ON e.id=c.event_id WHERE c.id=:id AND c.scoring_mode='automatic'");$q->execute(['id'=>$id]);$competition=$q->fetch();
if(!$competition){http_response_code(404);exit($error?:'Automatic Dance Cup category not found.');}
$q=$pdo->prepare("SELECT * FROM {$prefix}_entries WHERE competition_id=:id AND status='active' ORDER BY bib_number,id");$q->execute(['id'=>$id]);$entries=$q->fetchAll();
$q=$pdo->prepare("SELECT * FROM {$prefix}_judges WHERE competition_id=:id ORDER BY judge_order,id");$q->execute(['id'=>$id]);$judges=$q->fetchAll();$chiefCount=count(array_filter($judges,static fn(array $judge):bool=>(int)$judge['is_chief']===1));
$csrf=Csrf::token();
DanceCupScoringService::ensureAutomation($pdo,$id,$test);
$q=$pdo->prepare("SELECT s.*,j.judge_name,j.judge_order,j.is_chief,(SELECT COUNT(*) FROM {$prefix}_marks m WHERE m.competition_id=s.competition_id AND m.judge_id=s.judge_assignment_id) mark_count,(SELECT COUNT(*) FROM {$prefix}_entries e WHERE e.competition_id=s.competition_id AND e.status='active') entry_count,(SELECT COUNT(*) FROM {$tables['criteria']} x WHERE x.competition_id=s.competition_id) criterion_count FROM {$prefix}_judge_sessions s JOIN {$prefix}_judges j ON j.id=s.judge_assignment_id WHERE s.competition_id=:id ORDER BY j.is_chief DESC,j.judge_order,j.id");$q->execute(['id'=>$id]);$sessions=$q->fetchAll();
$state=DanceCupScoringService::workflowState($pdo,$id,$test);
$q=$pdo->prepare("SELECT * FROM {$prefix}_checkpoints WHERE competition_id=:id ORDER BY id DESC LIMIT 10");$q->execute(['id'=>$id]);$automaticCheckpoints=$q->fetchAll();
$q=$pdo->prepare("SELECT * FROM {$prefix}_event_projection WHERE event_id=:event");$q->execute(['event'=>$competition['event_id']]);$projection=$q->fetch();$projectorUrl=url('admin/dance-cup/projector-launch.php?token='.rawurlencode((string)($projection['access_token']??'')).($test?'&data_mode=test':''));
require dirname(__DIR__,2).'/app/Views/admin/dance-cup-automatic-page.php';
exit;
ob_start();require dirname(__DIR__,2).'/app/Views/admin/dance-cup-automatic-workspace.php';$automaticWorkspace=ob_get_clean();
ob_start(static function(string $html)use($test,$automaticWorkspace,$id,$suffix):string{
    $html=str_replace('scoring-premium.css?v=398','scoring-premium.css?v=434',$html);
    $dashboardHref=$test?'../?data_mode=test':'../';
    $workflowHref='workflow.php?workflow=automatic'.($test?'&data_mode=test':'');
    $scoringHref='./'.($test?'?data_mode=test':'');
    $navbar='<nav class="navbar navbar-dark bg-dark" data-bdc-nav-compact="1"><div class="container-fluid"><a class="navbar-brand" href="'.e($dashboardHref).'">BDC Admin</a></div></nav>';
    $html=str_replace('<body class="bg-light"><main class="container py-4"','<body class="bg-light dc-auto-setup">'.$navbar.'<main class="container py-4"',$html);
    $html=str_replace(
        '<div class="d-flex gap-3 mb-3"><a href="'.e($workflowHref).'">← Automatic Categories</a><a href="'.e($scoringHref).'">Scoring Options</a></div>',
        '<nav class="dc-auto-subnav mb-3" aria-label="Dance Cup setup navigation"><a href="'.e($workflowHref).'">← Automatic Categories</a><a href="'.e($scoringHref).'">Scoring Options</a></nav>',
        $html
    );
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
    $style='<style>.dc-auto-setup main{max-width:1180px!important}.dc-auto-subnav{display:flex;gap:8px;flex-wrap:wrap}.dc-auto-subnav a{display:inline-flex;align-items:center;min-height:38px;padding:7px 12px;border:1px solid #cfd6e1;border-radius:10px;background:#fff;color:#263653;font-size:.84rem;font-weight:750;text-decoration:none}.dc-auto-subnav a:hover{border-color:#7a2948;color:#7a2948}.dc-auto-setup main>section:first-of-type{border-radius:18px!important;border-left:5px solid #2563eb!important}.dc-auto-setup main>.row>.col-lg-6>section{overflow:visible;border-radius:18px!important}.dc-auto-setup main>.row>.col-lg-6>section>.card-body{padding:22px!important}.dc-auto-setup main>.row>.col-lg-6 h2{margin-bottom:16px;font-weight:850}.dc-auto-setup main>.row>.col-lg-6 form.row{display:grid;align-items:end;gap:12px;margin:0 0 18px!important}.dc-auto-setup main>.row>.col-lg-6 form.row>[class*=col]{width:auto;max-width:none;padding:0}.dc-auto-setup main>.row>.col-lg-6:first-child form.row{grid-template-columns:92px minmax(250px,1fr) auto}.dc-auto-setup main>.row>.col-lg-6:last-child form.row{grid-template-columns:minmax(250px,1fr) auto auto}.dc-auto-setup main>.row>.col-lg-6 form.row .btn{min-height:38px;padding-inline:16px;font-weight:800}.dc-auto-setup .form-check{display:flex;align-items:center;gap:6px;padding:0 4px 8px 26px!important;white-space:nowrap}.dc-auto-setup .dc-directory-menu{top:42px;z-index:1090}.dc-auto-setup .dc-directory-hint{min-height:16px}.dc-auto-setup .dc-auto-directory-grid + section{border-radius:18px!important}.dc-auto-setup .dc-auto-directory-grid + section .btn-lg{min-height:46px;border-radius:11px;font-size:.95rem;font-weight:850}@media(max-width:767px){.dc-auto-setup main{padding:18px 12px!important}.dc-auto-setup main>.row>.col-lg-6:first-child form.row,.dc-auto-setup main>.row>.col-lg-6:last-child form.row{grid-template-columns:1fr}.dc-auto-setup main>.row>.col-lg-6 form.row .btn{width:100%}.dc-auto-setup .form-check{padding:4px 0 4px 26px!important}.dc-auto-setup .dc-auto-directory-grid + section .btn-lg{width:100%}}</style>';
    $rosterStyle='.dc-auto-directory-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin:0!important}.dc-auto-directory-grid>[class*=col]{width:100%!important;max-width:none!important;padding:0!important}.dc-roster-actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.dc-roster-actions form{display:flex!important;grid-template-columns:none!important;gap:4px!important;margin:0!important}.dc-roster-list .list-group-item{min-width:0}.dc-roster-list .list-group-item>span{min-width:0;overflow-wrap:anywhere}@media(max-width:1199px){.dc-auto-directory-grid{grid-template-columns:1fr}.dc-auto-setup main>.dc-auto-directory-grid>.col-lg-6 form.row{grid-template-columns:92px minmax(0,1fr) auto}}';
    $buttonStyle='.dc-auto-setup .dc-auto-directory-grid form.row{align-items:start!important}.dc-auto-setup .dc-auto-directory-grid form.row>.col-auto:last-child{align-self:start!important}.dc-auto-setup .dc-auto-directory-grid form.row>.col-auto:last-child>.btn{height:38px!important;display:inline-flex;align-items:center;justify-content:center}.dc-auto-setup .dc-auto-directory-grid .form-check{height:38px!important;min-height:38px!important;padding-top:0!important;padding-bottom:0!important;align-items:center!important}.dc-auto-setup .dc-roster-actions .btn{height:32px!important;min-height:32px!important;padding:4px 9px!important;display:inline-flex;align-items:center;justify-content:center;line-height:1!important}.dc-auto-setup .dc-auto-directory-grid + section .d-flex.gap-2{align-items:stretch!important}.dc-auto-setup .dc-auto-directory-grid + section .btn-lg{height:46px!important;display:inline-flex;align-items:center;justify-content:center;margin:0!important}';
    $style=str_replace('</style>',$rosterStyle.$buttonStyle.'</style>',$style);
    $html=str_replace('<div class="row g-4 dc-auto-directory-grid">','<div id="automatic-roster" class="row g-4 dc-auto-directory-grid">',$html);
    $html=str_replace('href="automation.php?id='.$id.$suffix.'"','href="#automatic-workspace"',$html);
    $html=str_replace('</main>',$automaticWorkspace.'</main>',$html);
    return str_replace('</head>',$style.'<script defer src="../../public/js/dance-cup-directory.js?v=426"></script></head>',$html);
});
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Setup | Dance Cup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=398" rel="stylesheet"><script defer src="../../public/assets/js/bdc-theme.js?v=420"></script></head><body class="bg-light"><main class="container py-4" style="max-width:1300px"><div class="d-flex gap-3 mb-3"><a href="workflow.php?workflow=automatic<?=$suffix?>">← Automatic Categories</a><a href="./<?=$test?'?data_mode=test':''?>">Scoring Options</a></div><section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><span class="badge text-bg-primary"><?=$test?'TEST · ':''?>AUTOMATIC SCORING</span><h1 class="h3 mt-2"><?=e($competition['category_name'])?></h1><p class="text-muted mb-0"><?=e($competition['event_name'])?> · Set up this Automatic category before issuing judge links.</p></div></section><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><div class="row g-4 dc-auto-directory-grid"><div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Contestants</h2><form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="add_competitor"><div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" placeholder="No." required></div><div class="col"><input class="form-control" name="display_name" placeholder="Contestant or team name" required></div><div class="col-auto"><button class="btn btn-primary">Add</button></div></form><div class="list-group dc-roster-list"><?php foreach($entries as $entry):?><div class="list-group-item d-flex align-items-center justify-content-between gap-2"><span><strong>#<?=(int)$entry['bib_number']?></strong> · <?=e($entry['display_name'])?></span><div class="dc-roster-actions"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="move_competitor"><input type="hidden" name="entry_id" value="<?=(int)$entry['id']?>"><button class="btn btn-sm btn-outline-secondary" name="direction" value="up" title="Move up">↑</button><button class="btn btn-sm btn-outline-secondary" name="direction" value="down" title="Move down">↓</button></form><form method="post" onsubmit="return confirm('Remove this contestant from the category?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="remove_competitor"><input type="hidden" name="entry_id" value="<?=(int)$entry['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></div></div><?php endforeach;?><?php if(!$entries):?><div class="text-muted">No contestants assigned.</div><?php endif;?></div></div></section></div><div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-body"><h2 class="h5">Judges</h2><form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="add_judge"><div class="col"><input class="form-control" name="judge_name" placeholder="Judge name" required></div><div class="col-auto form-check pt-2"><input class="form-check-input" type="checkbox" name="is_chief" id="chief"><label class="form-check-label" for="chief">Chief</label></div><div class="col-auto"><button class="btn btn-dark">Add</button></div></form><div class="list-group dc-roster-list"><?php foreach($judges as $judge):?><div class="list-group-item d-flex align-items-center justify-content-between gap-2"><span><strong>J<?=(int)$judge['judge_order']?></strong> · <?=e($judge['judge_name'])?><?=$judge['is_chief']?' ★ Chief':''?></span><div class="dc-roster-actions"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="move_judge"><input type="hidden" name="judge_assignment_id" value="<?=(int)$judge['id']?>"><button class="btn btn-sm btn-outline-secondary" name="direction" value="up" title="Move up">↑</button><button class="btn btn-sm btn-outline-secondary" name="direction" value="down" title="Move down">↓</button></form><?php if(!$judge['is_chief']):?><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="set_chief_judge"><input type="hidden" name="judge_assignment_id" value="<?=(int)$judge['id']?>"><button class="btn btn-sm btn-outline-warning">Make Chief</button></form><?php endif;?><form method="post" onsubmit="return confirm('Remove this judge from the panel?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="remove_judge"><input type="hidden" name="judge_assignment_id" value="<?=(int)$judge['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></div></div><?php endforeach;?><?php if(!$judges):?><div class="text-muted">No judges assigned.</div><?php endif;?></div></div></section></div></div><section class="card border-0 shadow-sm mt-4"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1">Automatic Judge Scoring</h2><p class="text-muted mb-0">Generate secure judge links only for this Automatic category.</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary btn-lg <?=(!$entries||!$judges||$chiefCount!==1)?'disabled':''?>" href="automation.php?id=<?=$id?><?=$suffix?>">Open Judge Links &amp; Progress</a><a class="btn btn-outline-danger btn-lg dc-projection-action" href="projection-control.php?id=<?=$id?><?=$suffix?>">Live Projection</a></div></div></section></main></body></html>
