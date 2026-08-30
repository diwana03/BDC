<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\DanceCupScoringService;
Auth::requireAdmin();$pdo=Database::connection();$test=(string)($_GET['data_mode']??$_POST['data_mode']??'')==='test';if($test&&!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin required.');}$id=(int)($_GET['id']??$_POST['id']??0);$t=DanceCupScoringService::tables($test);$p=$test?'bdc_test_dance_cup':'bdc_dance_cup';DanceCupScoringService::ensureAutomation($pdo,$id,$test);$q=$pdo->prepare("SELECT c.*,e.name event_name FROM {$t['competitions']} c JOIN {$t['events']} e ON e.id=c.event_id WHERE c.id=:id");$q->execute(['id'=>$id]);$c=$q->fetch();if(!$c){http_response_code(404);exit('Dance Cup category not found.');}$eventId=(int)$c['event_id'];$error='';
try{
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
    $action=(string)($_POST['action']??'update');
    $screen=(string)($_POST['screen_type']??'holding');
    $theme=(string)($_POST['theme']??'midnight_wine');
    $user=(int)(Auth::user()['id']??0)?:null;
    if($action==='switch_category'){
        $next=(int)($_POST['competition_id']??0);
        $valid=$pdo->prepare("SELECT COUNT(*) FROM {$t['competitions']} WHERE id=:competition AND event_id=:event");
        $valid->execute(['competition'=>$next,'event'=>$eventId]);
        if(!(int)$valid->fetchColumn())throw new RuntimeException('Category does not belong to this Dance Cup event.');
        $pdo->prepare("UPDATE {$p}_event_projection SET active_competition_id=:competition,active_entry_id=NULL,screen_type='holding',auto_cycle=0,page_number=1,results_unlocked=0,reveal_place=NULL,effect_type=NULL,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['competition'=>$next,'user'=>$user,'event'=>$eventId]);
        header('Location: ?id='.$next.($test?'&data_mode=test':''),true,303);exit;
    }
    if($action==='show_entry'){
        $entry=(int)($_POST['entry_id']??0);
        $valid=$pdo->prepare("SELECT COUNT(*) FROM {$p}_entries WHERE id=:entry AND competition_id=:competition AND status='active'");
        $valid->execute(['entry'=>$entry,'competition'=>$id]);
        if(!(int)$valid->fetchColumn())throw new RuntimeException('Contestant is not in this category.');
        $pdo->prepare("UPDATE {$p}_event_projection SET active_competition_id=:competition,active_entry_id=:entry,screen_type='contestant',auto_cycle=0,state_version=state_version+1 WHERE event_id=:event")->execute(['competition'=>$id,'entry'=>$entry,'event'=>$eventId]);
    }elseif($action==='start_cycle'){
        $first=$pdo->prepare("SELECT id FROM {$p}_entries WHERE competition_id=:competition AND status='active' ORDER BY bib_number,id LIMIT 1");
        $first->execute(['competition'=>$id]);$entry=(int)$first->fetchColumn();
        if(!$entry)throw new RuntimeException('Add contestants before starting Run of Show.');
        $pdo->prepare("UPDATE {$p}_event_projection SET active_competition_id=:competition,active_entry_id=:entry,screen_type='contestant',auto_cycle=1,contestant_seconds=:contestant,holding_seconds=:holding,state_version=state_version+1 WHERE event_id=:event")->execute(['competition'=>$id,'entry'=>$entry,'contestant'=>max(5,min(120,(int)($_POST['contestant_seconds']??12))),'holding'=>max(3,min(120,(int)($_POST['holding_seconds']??8))),'event'=>$eventId]);
    }elseif($action==='stop_cycle'){
        $pdo->prepare("UPDATE {$p}_event_projection SET auto_cycle=0,screen_type='holding',active_entry_id=NULL,page_number=1,state_version=state_version+1 WHERE event_id=:event")->execute(['event'=>$eventId]);
    }elseif($action==='update_page'){
        $page=max(1,(int)($_POST['page_number']??1));$delay=max(5,min(120,(int)($_POST['page_delay']??10)));$auto=!empty($_POST['auto_page'])?1:0;
        $pdo->prepare("UPDATE {$p}_event_projection SET page_number=:page,auto_page=:auto,page_delay=:delay,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['page'=>$page,'auto'=>$auto,'delay'=>$delay,'user'=>$user,'event'=>$eventId]);
    }elseif($action==='unlock_results'){
        $pdo->prepare("UPDATE {$p}_event_projection SET results_unlocked=1,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['user'=>$user,'event'=>$eventId]);
    }elseif($action==='lock_results'){
        $pdo->prepare("UPDATE {$p}_event_projection SET results_unlocked=0,screen_type=CASE WHEN screen_type IN('results','podium') THEN 'holding' ELSE screen_type END,reveal_place=NULL,effect_type=NULL,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['user'=>$user,'event'=>$eventId]);
    }elseif($action==='reveal_podium'){
        $place=(string)($_POST['reveal_place']??'');
        if(!in_array($place,['3','2','1','all'],true))throw new RuntimeException('Invalid winner reveal.');
        $lock=$pdo->prepare("SELECT results_unlocked FROM {$p}_event_projection WHERE event_id=:event");$lock->execute(['event'=>$eventId]);
        if(!(int)$lock->fetchColumn())throw new RuntimeException('Unlock official results before revealing winners.');
        $pdo->prepare("UPDATE {$p}_event_projection SET active_competition_id=:competition,screen_type='podium',reveal_place=:place,auto_cycle=0,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['competition'=>$id,'place'=>$place,'user'=>$user,'event'=>$eventId]);
    }elseif($action==='effect'){
        $effect=(string)($_POST['effect_type']??'none');
        if(!in_array($effect,['none','hearts','balloons','heart_smiles','finger_hearts','gold_rain','champion_impact'],true))throw new RuntimeException('Invalid presentation effect.');
        $pdo->prepare("UPDATE {$p}_event_projection SET effect_type=:effect,effect_version=effect_version+1,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['effect'=>$effect==='none'?null:$effect,'user'=>$user,'event'=>$eventId]);
    }elseif($action==='theme'){
        if(!in_array($theme,['midnight_wine','obsidian_gold','ivory_wine','pearl_navy'],true))throw new RuntimeException('Invalid projection theme.');
        $pdo->prepare("UPDATE {$p}_event_projection SET theme=:theme,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['theme'=>$theme,'user'=>$user,'event'=>$eventId]);
    }else{
        $allowed=['holding','contestant','judges','contestants','scoring','results'];$themes=['midnight_wine','obsidian_gold','ivory_wine','pearl_navy'];
        if(!in_array($screen,$allowed,true)||!in_array($theme,$themes,true))throw new RuntimeException('Invalid projection setting.');
        if($screen==='results'){$lock=$pdo->prepare("SELECT results_unlocked FROM {$p}_event_projection WHERE event_id=:event");$lock->execute(['event'=>$eventId]);if(!(int)$lock->fetchColumn())throw new RuntimeException('Unlock official results before sending scores live.');}
        $pdo->prepare("UPDATE {$p}_event_projection SET active_competition_id=:competition,screen_type=:screen,theme=:theme,auto_cycle=0,page_number=1,reveal_place=NULL,state_version=state_version+1,updated_by=:user WHERE event_id=:event")->execute(['competition'=>$id,'screen'=>$screen,'theme'=>$theme,'user'=>$user,'event'=>$eventId]);
    }
    header('Location: ?id='.$id.($test?'&data_mode=test':''),true,303);exit;
}
}catch(Throwable $e){$error=$e->getMessage();}
$q=$pdo->prepare("SELECT * FROM {$p}_event_projection WHERE event_id=:event");$q->execute(['event'=>$eventId]);$state=$q->fetch();$categories=$pdo->prepare("SELECT id,category_name,round_name,status FROM {$t['competitions']} WHERE event_id=:event ORDER BY category_name,id");$categories->execute(['event'=>$eventId]);$categories=$categories->fetchAll();$entries=$pdo->prepare("SELECT id,bib_number,display_name FROM {$p}_entries WHERE competition_id=:competition AND status='active' ORDER BY bib_number,id");$entries->execute(['competition'=>$id]);$entries=$entries->fetchAll();$suffix=$test?'&data_mode=test':'';$projector=url('admin/dance-cup/projector-launch.php?token='.rawurlencode($state['access_token']).($test?'&data_mode=test':''));$screens=['holding'=>'Holding Screen','judges'=>'Judges','contestants'=>'All Contestants','scoring'=>'Scoring Progress','results'=>'Live Scoreboard'];$themes=['midnight_wine'=>['Midnight Wine','Dark'],'obsidian_gold'=>['Obsidian Gold','Dark'],'ivory_wine'=>['Ivory Wine','Light'],'pearl_navy'=>['Pearl Navy','Light']];$csrf=Csrf::token();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dance Cup Projection Control</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../../public/css/scoring-premium.css?v=354" rel="stylesheet">
<script defer src="../../public/assets/js/bdc-theme.js?v=505">
</script>
<style>.screen-btn{min-height:80px}.theme-preview{height:58px;border-radius:12px}.midnight_wine{background:linear-gradient(135deg,#08111f,#5b1833)}.obsidian_gold{background:linear-gradient(135deg,#050608,#44371e);border:2px solid #c8a95b}.ivory_wine{background:linear-gradient(135deg,#fffaf0,#f1e2e6)}.pearl_navy{background:linear-gradient(135deg,#f8fbff,#dce8f6)}.contestant-call{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.65rem}.reveal-safety{border-left:5px solid #d4a72c}.effect-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}.effect-grid form,.effect-grid .btn{width:100%}.presentation-sidebar{position:sticky;top:1rem;display:flex;flex-direction:column}.presentation-effects{order:-3;border-top:4px solid #a51d45!important}.premium-background{order:-2;border-top:4px solid #d4a72c!important}.quick-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}.quick-nav a{font-weight:700}@media(max-width:991px){.presentation-sidebar{position:static}.effect-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:575px){.contestant-call{grid-template-columns:1fr}.contestant-call .btn{width:100%}.screen-btn{min-height:68px}.effect-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}</style>
</head>
<body class="bg-light">
<main class="container-fluid py-4" style="max-width:1450px">
<div class="d-flex gap-3 mb-3">
<a href="automation.php?id=<?=$id?><?=$suffix?>">← Dance Cup Automation</a>
<a href="category.php?id=<?=$id?><?=$suffix?>">Category Workspace</a>
</div>
<span class="badge text-bg-danger">SEPARATE DANCE CUP PROJECTOR</span>
<h1 class="h2 mt-2 mb-1">
<?=e($c['event_name'])?>
</h1>
<p class="text-muted">Active category: <strong>
<?=e($c['category_name'])?>
</strong>
</p>
<div class="alert alert-info py-2">
<strong>Live state:</strong> <?=e($screens[$state['screen_type']]??ucfirst((string)$state['screen_type']))?> · Page <?=(int)($state['page_number']??1)?> · Auto Page <?=!empty($state['auto_page'])?'On':'Off'?>
</div>
<nav class="quick-nav" aria-label="Projection controls">
<a class="btn btn-sm btn-outline-dark" href="#sendScreenLive">Screens</a>
<a class="btn btn-sm btn-outline-warning" href="#officialResultReveal">Result Reveal</a>
<a class="btn btn-sm btn-outline-danger" href="#presentationEffects">Effects</a>
<a class="btn btn-sm btn-outline-primary" href="#premiumBackground">Premium Background</a>
</nav>
<?php if($error):?>
<div class="alert alert-danger">
<?=e($error)?>
</div>
<?php endif;?>
<div class="row g-4">
<div class="col-lg-8">
<section class="card border-0 shadow-sm mb-4">
<div class="card-body">
<h2 class="h4">Projector Live Feed</h2>
<p class="text-muted">Every copied or opened link first returns the audience display to the Holding Screen.</p>
<div class="input-group">
<input id="projectorUrl" class="form-control" readonly value="<?=e($projector)?>">
<button id="copyProjector" class="btn btn-outline-primary">Copy</button>
<a class="btn btn-danger" target="_blank" rel="noopener" href="<?=e($projector)?>">Open Projector</a>
</div>
</div>
</section>
<section class="card border-0 shadow-sm mb-4">
<div class="card-body">
<h2 class="h4">Run of Show</h2>
<p class="text-muted">Automatically alternates each contestant with the event-name Holding Screen.</p>
<form method="post" class="row g-3">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<div class="col-6 col-md-3">
<label class="form-label">Contestant display</label>
<input class="form-control" type="number" min="5" max="120" name="contestant_seconds" value="<?=(int)$state['contestant_seconds']?>">
</div>
<div class="col-6 col-md-3">
<label class="form-label">Holding display</label>
<input class="form-control" type="number" min="3" max="120" name="holding_seconds" value="<?=(int)$state['holding_seconds']?>">
</div>
<div class="col-12 d-flex gap-2">
<button class="btn btn-success" name="action" value="start_cycle">Start from Contestant 1</button>
<button class="btn btn-outline-danger" name="action" value="stop_cycle">Stop &amp; Hold</button>
<span class="badge align-self-center text-bg-<?=$state['auto_cycle']?'success':'secondary'?>">
<?=$state['auto_cycle']?'RUNNING':'STOPPED'?>
</span>
</div>
</form>
<hr>
<h3 class="h6">Call one contestant now</h3>
<form method="post" class="contestant-call">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<select class="form-select" name="entry_id" aria-label="Choose contestant">
<?php foreach($entries as $entry):?>
<option value="<?=$entry['id']?>" <?=((int)($state['active_entry_id']??0)===(int)$entry['id'])?'selected':''?>>No. <?=(int)$entry['bib_number']?> · <?=e($entry['display_name'])?>
</option>
<?php endforeach;?>
</select>
<button class="btn btn-primary px-4" name="action" value="show_entry">Call Contestant</button>
</form>
</div>
</section>
<section id="sendScreenLive" class="card border-0 shadow-sm">
<div class="card-body">
<h2 class="h4">Send Screen Live</h2>
<div class="row g-3">
<?php foreach($screens as $key=>$label):?>
<div class="col-6 col-lg-4">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="theme" value="<?=e($state['theme'])?>">
<button class="btn screen-btn w-100 <?=$state['screen_type']===$key?'btn-danger':'btn-outline-dark'?>" name="screen_type" value="<?=e($key)?>" <?=$key==='results'&&empty($state['results_unlocked'])?'disabled':''?>>
<strong>
<?=$key==='results'&&empty($state['results_unlocked'])?'🔒 ':''?>
<?=e($label)?>
</strong>
<small class="d-block">
<?=$state['screen_type']===$key?'LIVE NOW':($key==='results'&&empty($state['results_unlocked'])?'Unlock first':'Send to projector')?>
</small>
</button>
</form>
</div>
<?php endforeach;?>
</div>
</div>
</section>
<section id="officialResultReveal" class="card border-0 shadow-sm mt-4 reveal-safety">
<div class="card-body">
<div class="d-flex flex-wrap justify-content-between gap-2">
<div>
<h2 class="h4 mb-1">Official Results Reveal</h2>
<p class="text-muted mb-2">Scores and winners remain locked until you deliberately unlock them.</p>
</div>
<span class="badge text-bg-<?=!empty($state['results_unlocked'])?'success':'secondary'?> align-self-start">
<?=!empty($state['results_unlocked'])?'UNLOCKED':'LOCKED'?>
</span>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<button class="btn <?=!empty($state['results_unlocked'])?'btn-success':'btn-warning'?>" name="action" value="unlock_results" <?=!empty($state['results_unlocked'])?'disabled':''?>>
<?=!empty($state['results_unlocked'])?'Results Unlocked':'Unlock Results Reveal'?>
</button>
</form>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<button class="btn btn-outline-secondary" name="action" value="lock_results" <?=empty($state['results_unlocked'])?'disabled':''?>>Lock and Hold</button>
</form>
</div>
<div class="fw-bold mb-2">Winner Podium Reveal</div>
<div class="d-flex flex-wrap gap-2">
<?php foreach(['3'=>'Reveal 3rd','2'=>'Reveal 2nd','1'=>'Reveal Champion','all'=>'Show Full Podium'] as $place=>$label):?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="reveal_place" value="<?=e($place)?>">
<button class="btn <?=$place==='1'?'btn-warning':'btn-outline-dark'?>" name="action" value="reveal_podium" <?=empty($state['results_unlocked'])?'disabled':''?>>
<?=e($label)?>
</button>
</form>
<?php endforeach;?>
</div>
</div>
</section>
</div>
<div class="col-lg-4 presentation-sidebar">
<section id="presentationEffects" class="card border-0 shadow-sm mb-4 presentation-effects">
<div class="card-body">
<h2 class="h4 mb-1">Presentation Effects</h2>
<p class="text-muted">Send lightweight live effects to the projector at any time.</p>
<div class="effect-grid">
<?php foreach(['hearts'=>'💖 Hearts','balloons'=>'🎈 Balloons','heart_smiles'=>'🥰 Smiling Hearts','finger_hearts'=>'🫰 Finger Hearts','gold_rain'=>'✨ Gold Celebration','champion_impact'=>'🏆 Champion Impact','none'=>'Clear Effect'] as $effect=>$label):?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="effect_type" value="<?=e($effect)?>">
<button class="btn btn-sm <?=$effect==='none'?'btn-outline-secondary':'btn-outline-danger'?>" name="action" value="effect"><?=e($label)?></button>
</form>
<?php endforeach;?>
</div>
</div>
</section>
<section class="card border-0 shadow-sm mb-4">
<div class="card-body">
<h2 class="h4">Screen Paging</h2>
<p class="text-muted">Controls Judges, All Contestants and Live Scoreboard pages.</p>
<form method="post" class="row g-2">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="action" value="update_page">
<div class="col-5">
<label class="form-label">Page</label>
<input class="form-control" type="number" min="1" name="page_number" value="<?=max(1,(int)($state['page_number']??1))?>">
</div>
<div class="col-7">
<label class="form-label">Page delay</label>
<select class="form-select" name="page_delay">
<?php foreach([5,10,15,20,30] as $seconds):?>
<option value="<?=$seconds?>" <?=(int)($state['page_delay']??10)===$seconds?'selected':''?>>
<?=$seconds?> seconds</option>
<?php endforeach;?>
</select>
</div>
<div class="col-12 form-check ms-2">
<input class="form-check-input" type="checkbox" name="auto_page" value="1" id="autoPage" <?=!empty($state['auto_page'])?'checked':''?>>
<label class="form-check-label" for="autoPage">Automatically rotate through every page</label>
</div>
<div class="col-12">
<button class="btn btn-primary w-100">Apply Paging</button>
</div>
</form>
</div>
</section>
<section class="card border-0 shadow-sm mb-4">
<div class="card-body">
<h2 class="h4">Switch Category</h2>
<p class="text-muted">Same event and same projector link.</p>
<div class="d-grid gap-2">
<?php foreach($categories as $category):?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="competition_id" value="<?=$category['id']?>">
<button class="btn w-100 text-start <?=$category['id']==$id?'btn-primary':'btn-outline-primary'?>" name="action" value="switch_category">
<strong>
<?=e($category['category_name'])?>
</strong>
<small class="d-block">
<?=e(ucfirst($category['round_name']))?> · <?=e(ucfirst($category['status']))?>
</small>
</button>
</form>
<?php endforeach;?>
</div>
</div>
</section>
<section id="premiumBackground" class="card border-0 shadow-sm premium-background">
<div class="card-body">
<h2 class="h4">Premium Background</h2>
<p class="text-muted">Two dark and two light adaptive presets.</p>
<div class="row g-2">
<?php foreach($themes as $key=>$theme):?>
<div class="col-6">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="id" value="<?=$id?>">
<input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>">
<input type="hidden" name="action" value="theme">
<input type="hidden" name="holding_title" value="<?=e($state['holding_title'])?>">
<input type="hidden" name="holding_message" value="<?=e($state['holding_message'])?>">
<button class="btn w-100 text-start <?=$state['theme']===$key?'btn-warning':'btn-outline-secondary'?>" name="theme" value="<?=e($key)?>">
<span class="theme-preview <?=e($key)?> d-block mb-2">
</span>
<strong>
<?=e($theme[0])?>
</strong>
<small class="d-block">
<?=e($theme[1])?>
</small>
</button>
</form>
</div>
<?php endforeach;?>
</div>
</div>
</section>
</div>
</div>
</main>
<script>document.getElementById('copyProjector').onclick=async e=>{const x=document.getElementById('projectorUrl');try{await navigator.clipboard.writeText(x.value)}catch{x.select();document.execCommand('copy')}e.currentTarget.textContent='Copied'}</script>
</body>
</html>
