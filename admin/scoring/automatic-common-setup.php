<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SpecialCategoryService;
use App\Services\ScoringJudgeAssignmentService;

function bdcRenderAutomaticCommonSetup(int $roundId):string
{
    $pdo=Database::connection();
    $stmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
    $stmt->execute(['round'=>$roundId]);
    $round=$stmt->fetch();
    if(!$round||($round['scoring_mode']??'')!=='automated'||($round['round_type']??'')==='final')return '';

    $judgeStmt=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order');
    $judgeStmt->execute(['round'=>$roundId]);
    $judges=$judgeStmt->fetchAll();
    $judgeDirectory=[];
    try{$judgeDirectory=ScoringJudgeAssignmentService::directory($pdo);}
    catch(Throwable $directoryError){error_log('BDC automatic scoring judge directory unavailable: '.$directoryError->getMessage());}

    $entryStmt=$pdo->prepare("SELECT se.*,c.bdc_id,c.status competitor_status FROM bdc_scoring_entries se JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:round AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number");
    $entryStmt->execute(['round'=>$roundId]);
    $entries=['leader'=>[],'follower'=>[]];
    foreach($entryStmt->fetchAll() as $entry)$entries[$entry['dance_role']][]=$entry;
    $nextBib=['leader'=>1,'follower'=>1];
    foreach(['leader','follower'] as $role)foreach($entries[$role] as $entry)$nextBib[$role]=max($nextBib[$role],(int)$entry['bib_number']+1);

    $suggestions=$pdo->query("SELECT bdc_id,exact_name,dance_role,status FROM bdc_competitors WHERE status<>'archived' ORDER BY exact_name LIMIT 1500")->fetchAll();
    $csrf=Csrf::token();
    $special=SpecialCategoryService::isSpecial((string)$round['division']);
    $categoryLabel=$special?SpecialCategoryService::label((string)$round['division']):ucfirst((string)$round['division']);

    $linkStmt=$pdo->prepare('SELECT * FROM bdc_registration_desk_links WHERE event_id=:event AND division=:division LIMIT 1');
    $linkStmt->execute(['event'=>$round['event_id'],'division'=>$round['division']]);
    $link=$linkStmt->fetch();
    if(!$link){
        $token=bin2hex(random_bytes(24));
        $insert=$pdo->prepare('INSERT INTO bdc_registration_desk_links(event_id,division,token_hash,token_hint,created_by) VALUES(:event,:division,:hash,:hint,:user)');
        $insert->execute(['event'=>$round['event_id'],'division'=>$round['division'],'hash'=>hash('sha256',$token),'hint'=>substr($token,0,8),'user'=>(int)(Auth::user()['id']??0)?:null]);
        $link=['id'=>(int)$pdo->lastInsertId(),'plain_token'=>$token];
        $_SESSION['registration_desk_tokens'][(int)$link['id']]=$token;
    }

    if(
        $_SERVER['REQUEST_METHOD']==='POST'
        && (string)($_POST['action']??'')==='regenerate_registration_desk_link'
        && (int)($_POST['round_id']??0)===$roundId
        && Csrf::verify($_POST['_csrf']??null)
    ){
        $token=bin2hex(random_bytes(24));
        $pdo->prepare('UPDATE bdc_registration_desk_links SET token_hash=:hash,token_hint=:hint,is_enabled=1 WHERE id=:id')
            ->execute(['hash'=>hash('sha256',$token),'hint'=>substr($token,0,8),'id'=>(int)$link['id']]);
        $_SESSION['registration_desk_tokens'][(int)$link['id']]=$token;
        $link['plain_token']=$token;
    }

    $token=(string)($link['plain_token']??($_SESSION['registration_desk_tokens'][(int)$link['id']]??''));
    $deskUrl='';
    if($token!==''){
        $path=url('registration-desk/?token='.rawurlencode($token).'&round_id='.$roundId);
        $appUrl=rtrim((string)Config::get('app.url',''),'/');
        $parts=parse_url($appUrl);
        if(is_array($parts)&&isset($parts['scheme'],$parts['host']))$deskUrl=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'').$path;
        else $deskUrl=$path;
    }

    $currentTier=(int)$round['yes_count']===5?1:((int)$round['yes_count']===15?3:2);
    $e=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    $html='<div id="automatic-common-setup">';
    $flashError=(string)($_SESSION['automatic_scoring_error']??'');
    $flashNotice=(string)($_SESSION['automatic_scoring_notice']??'');
    unset($_SESSION['automatic_scoring_error'],$_SESSION['automatic_scoring_notice']);
    if($flashError!=='')$html.='<div class="alert alert-danger">'.$e($flashError).'</div>';
    if($flashNotice!=='')$html.='<div class="alert alert-success">'.$e($flashNotice).'</div>';
    $html.='<div class="row g-3 mb-4">';
    $html.='<div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h5">'.($special?'Special Category Settings':$e(ucfirst((string)$round['round_type'])).' Settings').'</h2>';
    if($special){
        $schedule=SpecialCategoryService::schedule((string)$round['division']);
        $points=[];foreach($schedule as $rank=>$point)$points[]=$rank.'='.number_format((float)$point,0);
        $html.='<div class="alert alert-info py-2"><strong>'.$e($categoryLabel).'</strong><br>Fixed points: '.$e(implode(' · ',$points)).'</div>';
        $locked=(int)$round['tier_manual_override']===1;$startedStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_marks WHERE round_id=:round AND (mark_type<>'blank' OR weighted_score>0)");$startedStmt->execute(['round'=>$roundId]);$scoringStarted=(int)$startedStmt->fetchColumn()>0;$largest=max(count($entries['leader']),count($entries['follower']));$selectedYes=$locked?(int)$round['yes_count']:($largest<=15?5:($largest<=30?10:15));
        $html.='<form method="post" action="special-settings.php" class="row g-3"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="return_to" value="automatic-round.php"><div class="col-12"><label class="form-label">YES Tier per Judge</label><select class="form-select" name="special_yes_count" '.($locked?'disabled':'').'><option value="5" '.($selectedYes===5?'selected':'').'>Tier 1 · 5 YES</option><option value="10" '.($selectedYes===10?'selected':'').'>Tier 2 · 10 YES</option><option value="15" '.($selectedYes===15?'selected':'').'>Tier 3 · 15 YES</option></select>'.($locked?'<input type="hidden" name="special_yes_count" value="'.$selectedYes.'">':'').'<div class="form-text">Recommended from the larger Leader or Follower count. You may amend it before locking.</div></div>';
        $html.='<div class="col-12"><div class="border rounded p-3 bg-light"><div class="fw-semibold mb-2">Alternates · Locked</div><div class="row g-2 text-center"><div class="col-4"><small class="text-muted d-block">ALT 1</small><strong>4.5</strong></div><div class="col-4"><small class="text-muted d-block">ALT 2</small><strong>4.3</strong></div><div class="col-4"><small class="text-muted d-block">ALT 3</small><strong>4.2</strong></div></div></div></div>';
        $html.='<div class="col-12">'.($scoringStarted?'<span class="badge text-bg-secondary">Locked because judging has started</span>':($locked?'<button class="btn btn-outline-warning btn-sm" name="action" value="special_settings_unlock">Unlock YES Count</button>':'<button class="btn btn-dark btn-sm" name="action" value="special_settings_lock">Save &amp; Lock YES Count</button>')).'</div></form>';
        $html.='<div class="small text-muted mt-2">All Admin Scorers can configure this before judging. Participant-count tiers do not change special-category points.</div>';
    }else{
        $html.='<form method="post" action="index.php?mode=automated&amp;round_id='.$roundId.'" class="row g-3"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="settings"><input type="hidden" name="round_id" value="'.$roundId.'">';
        $html.='<div class="col-12"><label class="form-label">Competition Tier</label><select class="form-select" name="competition_tier" id="competitionTier" onchange="updateTierSummary()"><option value="1" '.($currentTier===1?'selected':'').'>Tier 1 · 5–15 competitors</option><option value="2" '.($currentTier===2?'selected':'').'>Tier 2 · 16–30 competitors</option><option value="3" '.($currentTier===3?'selected':'').'>Tier 3 · 31+ competitors</option></select></div>';
        $html.='<div class="col-6"><label class="form-label">YES per Judge</label><input class="form-control" id="tierYesCount" value="'.(int)$round['yes_count'].'" readonly></div><div class="col-6"><label class="form-label">Alternates</label><input class="form-control" value="3" readonly></div>';
        $html.='<div class="col-12"><div class="border rounded p-3 bg-light"><div class="fw-semibold mb-2">Official BDC Weights · Locked</div><div class="row g-2 text-center"><div class="col-3"><small class="text-muted d-block">YES</small><strong>10</strong></div><div class="col-3"><small class="text-muted d-block">ALT 1</small><strong>4.5</strong></div><div class="col-3"><small class="text-muted d-block">ALT 2</small><strong>4.3</strong></div><div class="col-3"><small class="text-muted d-block">ALT 3</small><strong>4.2</strong></div></div></div></div>';
        $html.='<div class="col-12"><small class="text-muted">Automatic tier uses the larger individual role count, not Leaders + Followers combined.</small></div><div class="col-12"><button class="btn btn-outline-dark btn-sm">Save Tier Settings</button></div></form>';
    }
    $html.='</div></div></div>';

    $display=$judges?:[['judge_name'=>'','is_chief'=>1,'scoring_scope'=>'all'],['judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all'],['judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all']];
    $html.='<div class="col-lg-8"><div class="card shadow-sm h-100" id="judge-setup"><div class="card-body"><h2 class="h5">Judge Setup</h2><div class="small text-muted mb-3">Search the Judge Database or type a new name. New names automatically receive a Judge ID.</div><form method="post" action="save-judges.php"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="save_judges"><input type="hidden" name="round_id" value="'.$roundId.'"><div id="judgesWrap">';
    foreach($display as $i=>$judge){
        $html.='<div class="row g-2 mb-2 judge-row align-items-center"><div class="col-md-2"><strong>Judge '.($i+1).'</strong><input type="hidden" name="judge_assignment_id[]" value="'.(int)($judge['id']??0).'"><input type="hidden" name="judge_directory_id[]" value="'.(int)($judge['judge_id']??0).'"></div><div class="col-md-5"><input class="form-control" name="judge_name[]" list="judgeDirectorySuggestions" value="'.$e((string)$judge['judge_name']).'" placeholder="Search or type a new judge" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]">';
        foreach(['all'=>'All','leader'=>'Leaders','follower'=>'Followers'] as $value=>$label)$html.='<option value="'.$value.'" '.(($judge['scoring_scope']??'all')===$value?'selected':'').'>'.$label.'</option>';
        $html.='</select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="'.$i.'" '.((int)$judge['is_chief']?'checked':'').'> Chief</label></div></div>';
    }
    $html.='</div><div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="addJudge()">+ Judge</button><button class="btn btn-dark btn-sm">Submit Judges</button></div></form></div></div></div></div>';
    $html.='<datalist id="judgeDirectorySuggestions">';
    foreach($judgeDirectory as $directoryJudge){$name=(string)($directoryJudge['display_name']?:$directoryJudge['full_name']);$html.='<option value="'.$e($name).'">'.$e((string)$directoryJudge['judge_code'].(!empty($directoryJudge['country'])?' · '.$directoryJudge['country']:'')).'</option>';}
    $html.='</datalist>';

    $html.='<datalist id="competitorSuggestions">';
    foreach($suggestions as $suggestion)$html.='<option value="'.$e((string)$suggestion['bdc_id']).'">'.$e((string)$suggestion['exact_name'].' · '.ucfirst((string)$suggestion['dance_role']).((string)$suggestion['status']==='pending'?' · Details pending':'')).'</option>';
    $html.='</datalist><div class="row g-3 mb-4">';
    foreach(['leader'=>['Leaders','primary'],'follower'=>['Followers','danger']] as $role=>$meta){
        $html.='<div class="col-lg-6"><div class="card shadow-sm role-card"><div class="card-header bg-'.$meta[1].'-subtle fw-semibold">'.$meta[0].'</div><div class="card-body"><form method="post" action="index.php?mode=automated&amp;round_id='.$roundId.'" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="dance_role" value="'.$role.'"><div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="'.$nextBib[$role].'" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div><div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestions" placeholder="Type competitor name or BDC ID" required><div class="form-text">Search the BDC database first.</div></div><div class="col-6"><button class="btn btn-'.$meta[1].' w-100" name="entry_mode" value="existing">Add Existing</button></div><div class="col-6"><button class="btn btn-outline-'.$meta[1].' w-100" name="entry_mode" value="create" onclick="return confirm(\'Create a provisional BDC competitor using only this name?\')">Create Name &amp; Add</button></div></form>';
        $html.='<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody>';
        if(!$entries[$role])$html.='<tr><td colspan="4" class="text-muted">No competitors</td></tr>';
        foreach($entries[$role] as $entry){
            $html.='<tr><td><form method="post" action="index.php?mode=automated&amp;round_id='.$roundId.'" class="d-flex gap-1"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="entry_id" value="'.(int)$entry['id'].'"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="'.(int)$entry['bib_number'].'"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td>'.$e((string)$entry['display_name']).((string)$entry['competitor_status']==='pending'?' <span class="badge text-bg-warning">Details pending</span>':'').'</td><td><code>'.$e((string)$entry['bdc_id']).'</code></td><td><form method="post" action="index.php?mode=automated&amp;round_id='.$roundId.'"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="entry_id" value="'.(int)$entry['id'].'"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>';
        }
        $html.='</tbody></table></div></div></div>';
    }
    $html.='</div>';

    $html.='<div class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="h6 mb-1">Registration Desk Link <span class="badge text-bg-secondary">Optional</span></h2><div class="small text-muted">Use only when another person/device will handle check-in. You can add every competitor directly above without opening this link.</div></div>';
    if($deskUrl!==''){
        $html.='<div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(\''.$e($deskUrl).'\')">Copy Link</button><a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="'.$e($deskUrl).'">Open Registration Desk</a></div>';
    }else{
        $html.='<form method="post" class="d-flex align-items-center gap-2"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="regenerate_registration_desk_link"><input type="hidden" name="round_id" value="'.$roundId.'"><span class="small text-muted">Secure desk link needs to be reissued for this admin session.</span><button class="btn btn-warning btn-sm" onclick="return confirm(\'Regenerate the Registration Desk link? The previous desk link will stop working.\')">Regenerate Registration Link</button></form>';
    }
    $html.='</div></div></div></div>';
    $html.='<script>window.addJudge=window.addJudge||function(){const wrap=document.getElementById("judgesWrap");if(!wrap)return;const index=wrap.querySelectorAll(".judge-row").length;const row=document.createElement("div");row.className="row g-2 mb-2 judge-row align-items-center";row.innerHTML=`<div class="col-md-2"><strong>Judge ${index+1}</strong><input type="hidden" name="judge_assignment_id[]" value="0"><input type="hidden" name="judge_directory_id[]" value="0"></div><div class="col-md-5"><input class="form-control" name="judge_name[]" list="judgeDirectorySuggestions" placeholder="Search or type a new judge" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="${index}"> Chief</label></div>`;wrap.appendChild(row);};</script>';
    return $html;
}
