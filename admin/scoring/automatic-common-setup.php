<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SpecialCategoryService;
use App\Services\ScoringJudgeAssignmentService;
use App\Services\AutomaticJudgeBrowserService;
use App\Services\ScoringRulesService;
use App\Services\ScoringRosterCheckpointService;
use App\Services\JackJillCompetitorEligibilityService;

function bdcRenderAutomaticCommonSetup(int $roundId):string
{
    $pdo=Database::connection();
    $stmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
    $stmt->execute(['round'=>$roundId]);
    $round=$stmt->fetch();
    if(!$round||($round['scoring_mode']??'')!=='automated'||($round['round_type']??'')==='final')return '';

    $judgeStmt=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:round ORDER BY is_chief DESC,judge_order,id');
    $judgeStmt->execute(['round'=>$roundId]);
    $judges=$judgeStmt->fetchAll();
    $judgeDirectory=[];
    try{$judgeDirectory=ScoringJudgeAssignmentService::directory($pdo);}
    catch(Throwable $directoryError){error_log('BDC automatic scoring judge directory unavailable: '.$directoryError->getMessage());}

    $entryStmt=$pdo->prepare("SELECT se.*,c.bdc_id,c.status competitor_status FROM bdc_scoring_entries se JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:round AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number");
    $entryStmt->execute(['round'=>$roundId]);
    $entries=['leader'=>[],'follower'=>[]];
    foreach($entryStmt->fetchAll() as $entry)$entries[$entry['dance_role']][]=$entry;
    $activeCompetitorIds=[];foreach(array_merge($entries['leader'],$entries['follower']) as $entry)$activeCompetitorIds[(int)$entry['competitor_id']]=true;
    $rosterState=ScoringRosterCheckpointService::state($pdo,$roundId);
    $rosterSubmitted=(string)$rosterState['status']==='submitted';
    $nextBib=['leader'=>1,'follower'=>1];
    foreach(['leader','follower'] as $role)foreach($entries[$role] as $entry)$nextBib[$role]=max($nextBib[$role],(int)$entry['bib_number']+1);

    $suggestions=JackJillCompetitorEligibilityService::directory($pdo,(string)$round['dance_style'],null,1500);
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

    if(!$special){
        $tierInfo=ScoringRulesService::normalizeNormalRoundTier(
            count($entries['leader']),
            count($entries['follower']),
            (int)$round['yes_count'],
            (int)$round['callback_count'],
            (int)$round['tier_manual_override']===1
        );
        if($tierInfo['corrected']){
            $pdo->prepare('UPDATE bdc_scoring_rounds SET yes_count=:yes,callback_count=:callbacks WHERE id=:round')
                ->execute(['yes'=>$tierInfo['yes_count'],'callbacks'=>$tierInfo['callback_count'],'round'=>$roundId]);
            $round['yes_count']=$tierInfo['yes_count'];
            $round['callback_count']=$tierInfo['callback_count'];
        }
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
        $html.='<form method="post" action="automatic-setup-action.php" class="row g-3"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="settings"><input type="hidden" name="round_id" value="'.$roundId.'">';
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
    foreach($judgeDirectory as $directoryJudge){$name=(string)($directoryJudge['display_name']?:$directoryJudge['full_name']);$code=(string)($directoryJudge['judge_code']??'');$country=(string)($directoryJudge['country']??'');$label=trim($code.($country!==''?' · '.$country:''));foreach(array_values(array_unique(array_filter([$name,$code,$country!==''?$country.' · '.$name:'']))) as $alias)$html.='<option value="'.$e($alias).'" data-judge-name="'.$e($name).'" data-judge-id="'.(int)$directoryJudge['id'].'">'.$e($label.($alias!==$name?' · '.$name:'')).'</option>';}
    $html.='</datalist>';
    $html.='<script>(function(){const form=document.querySelector("#judge-setup form"),wrap=document.getElementById("judgesWrap"),list=document.getElementById("judgeDirectorySuggestions");if(!form||!wrap||!list)return;const master=[...list.options].map(option=>({value:option.value,name:option.dataset.judgeName||option.value,label:option.label||option.textContent||"",id:option.dataset.judgeId||"0"})),normalise=value=>value.trim().replace(/\\s+/g," ").toLocaleLowerCase(),message=document.createElement("div");message.className="alert alert-danger py-2 mt-2 d-none";message.setAttribute("role","alert");message.textContent="The same judge cannot be selected more than once.";wrap.after(message);function refresh(){const inputs=[...wrap.querySelectorAll("input[name=\\"judge_name[]\\"]")],counts=new Map();inputs.forEach(input=>{const typed=normalise(input.value),item=master.find(candidate=>normalise(candidate.value)===typed||normalise(candidate.name)===typed),key=normalise(item?item.name:input.value);if(key)counts.set(key,(counts.get(key)||0)+1)});let duplicate=false;inputs.forEach(input=>{let key=normalise(input.value);const match=master.find(item=>normalise(item.value)===key||normalise(item.name)===key);if(match&&input.value!==match.name){input.value=match.name;key=normalise(match.name)}const repeated=key!==""&&(counts.get(key)||0)>1;input.setCustomValidity(repeated?"The same judge cannot be selected more than once.":"");input.classList.toggle("is-invalid",repeated);duplicate=duplicate||repeated;const row=input.closest(".judge-row"),directory=row&&row.querySelector("input[name=\\"judge_directory_id[]\\"]");if(directory)directory.value=match?match.id:"0"});message.classList.toggle("d-none",!duplicate);const selected=new Set(inputs.map(input=>normalise(input.value)).filter(Boolean));list.replaceChildren();master.forEach(item=>{if(selected.has(normalise(item.value)))return;const option=document.createElement("option");option.value=item.value;option.label=item.label;option.dataset.judgeName=item.name;option.dataset.judgeId=item.id;list.appendChild(option)});const submit=form.querySelector("button:not([type=\\"button\\"])");if(submit)submit.disabled=duplicate}wrap.addEventListener("input",refresh);wrap.addEventListener("change",refresh);new MutationObserver(refresh).observe(wrap,{childList:true});form.addEventListener("submit",event=>{refresh();if(!form.checkValidity()){event.preventDefault();form.reportValidity()}});refresh()})();</script>';

    foreach(['leader'=>'Leader','follower'=>'Follower'] as $suggestionRole=>$suggestionLabel){
        $html.='<datalist id="competitorSuggestions'.ucfirst($suggestionRole).'">';
        foreach($suggestions as $suggestion)if(in_array((string)$suggestion['dance_role'],[$suggestionRole,'both'],true)&&!isset($activeCompetitorIds[(int)$suggestion['id']]))$html.='<option value="'.$e((string)$suggestion['identity_code']).'">'.$e((string)$suggestion['exact_name'].' · '.$suggestionLabel).'</option>';
        $html.='</datalist>';
    }

    $html.='<div class="card shadow-sm mb-4 border-warning border-2 bg-warning-subtle"><div class="card-body"><div class="d-flex justify-content-between align-items-center flex-wrap gap-3"><div><h2 class="h5 mb-1">Registration Desk <span class="badge text-bg-warning">Optional</span></h2><div class="small text-body-secondary">Use this when another person or device will handle competitor check-in. You can also add competitors directly in the Leader and Follower boards below.</div></div>';
    if($deskUrl!==''){
        $html.='<div class="d-flex gap-2"><button type="button" class="btn btn-outline-dark btn-sm" onclick="navigator.clipboard.writeText(\''.$e($deskUrl).'\')">Copy Link</button><a class="btn btn-warning btn-sm fw-semibold" target="_blank" rel="noopener" href="'.$e($deskUrl).'">Open Registration Desk</a></div>';
    }else{
        $html.='<form method="post" class="d-flex align-items-center gap-2 flex-wrap"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="regenerate_registration_desk_link"><input type="hidden" name="round_id" value="'.$roundId.'"><span class="small text-body-secondary">Secure desk link needs to be reissued for this admin session.</span><button class="btn btn-warning btn-sm fw-semibold" onclick="return confirm(\'Regenerate the Registration Desk link? The previous desk link will stop working.\')">Regenerate Registration Link</button></form>';
    }
    $html.='</div></div></div>';
    $html.='<div class="card shadow-sm mb-4 border-primary border-2 bg-primary-subtle"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1 text-primary-emphasis">Flights <span class="badge text-bg-primary">Optional</span></h2><div class="small text-body-secondary">Divide this round into unlimited bib-ordered Flights without changing its scoring rules.</div></div><a class="btn btn-primary fw-semibold" href="flights.php?round_id='.$roundId.'&amp;data_mode=real">Manage Flights</a></div></div>';

    $html.='<fieldset '.($rosterSubmitted?'disabled':'').'><div class="row g-3 mb-4">';
    foreach(['leader'=>['Leaders','primary'],'follower'=>['Followers','danger']] as $role=>$meta){
        $council=JackJillCompetitorEligibilityService::council((string)$round['dance_style']);
        $html.='<div class="col-lg-6"><div class="card shadow-sm role-card border-'.$meta[1].' border-2"><div class="card-header bg-'.$meta[1].' text-white fw-semibold d-flex justify-content-between align-items-center"><span>'.$meta[0].'</span><span class="badge rounded-pill bg-white text-'.$meta[1].' fs-6">'.count($entries[$role]).'</span></div><div class="card-body"><form method="post" action="automatic-setup-action.php" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="dance_role" value="'.$role.'"><div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="'.$nextBib[$role].'" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div><div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestions'.ucfirst($role).'" placeholder="Type '.$role.' name or '.$council.' ID" required><div class="form-text">Only active '.$council.' '.ucfirst($role).' profiles are shown.</div></div><div class="col-12"><button class="btn btn-'.$meta[1].' w-100" name="entry_mode" value="existing">Add Existing '.$council.' Competitor</button></div></form>';
        $html.='<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody>';
        if(!$entries[$role])$html.='<tr><td colspan="4" class="text-muted">No competitors</td></tr>';
        foreach($entries[$role] as $entry){
            $html.='<tr><td><form method="post" action="automatic-setup-action.php" class="d-flex gap-1"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="entry_id" value="'.(int)$entry['id'].'"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="'.(int)$entry['bib_number'].'"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td>'.$e((string)$entry['display_name']).((string)$entry['competitor_status']==='pending'?' <span class="badge text-bg-warning">Details pending</span>':'').'</td><td><code>'.$e((string)$entry['bdc_id']).'</code></td><td><form method="post" action="automatic-setup-action.php"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="'.$roundId.'"><input type="hidden" name="entry_id" value="'.(int)$entry['id'].'"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>';
        }
        $html.='</tbody></table></div></div></div>';
    }
    $html.='</div></fieldset>';

    $html.='<div class="card shadow-sm mb-4 '.($rosterSubmitted?'border-success bg-success-subtle':'border-warning bg-warning-subtle').'"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1">Competitor Checkpoint</h2><div class="small text-body-secondary">'.($rosterSubmitted?'Competitors are submitted and locked. Bib changes, additions and removals are blocked.':'Save the current roster as a draft, then submit it to lock competitors before judging.').'</div>'.(!empty($rosterState['saved_at'])?'<div class="small mt-1">Last saved: '.$e((string)$rosterState['saved_at']).'</div>':'').'</div><div class="d-flex gap-2 flex-wrap">';
    if(!$rosterSubmitted){
        $html.='<form method="post" action="automatic-setup-action.php"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="round_id" value="'.$roundId.'"><button class="btn btn-outline-dark" name="action" value="save_competitors">Save Competitors</button><button class="btn btn-success" name="action" value="submit_competitors" onclick="return confirm(\'Submit and lock the current competitor roster? Bib changes, additions and removals will be blocked.\')">Submit Competitors</button></form>';
    }elseif(Auth::isSuperAdmin()){
        $html.='<form method="post" action="automatic-setup-action.php" class="d-flex gap-2 flex-wrap"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="round_id" value="'.$roundId.'"><input class="form-control" name="reopen_reason" placeholder="Required reopen reason" required><button class="btn btn-warning" name="action" value="reopen_competitors" onclick="return confirm(\'Reopen submitted competitors for correction?\')">Reopen Competitors</button></form>';
    }
    $html.='</div></div></div>';

    if($rosterSubmitted){
        try{
            foreach(AutomaticJudgeBrowserService::syncRound($pdo,$roundId) as $syncedJudge){
                if((string)($syncedJudge['plain_token']??'')!=='')$_SESSION['automatic_judge_tokens'][(int)$syncedJudge['id']]=(string)$syncedJudge['plain_token'];
            }
        }catch(Throwable $judgeLinkError){error_log('BDC automatic judge-link sync unavailable: '.$judgeLinkError->getMessage());}
        $judgeControlGateway='index.php?mode=automated&amp;judge_panel=1&amp;round_id='.$roundId;
        $progress=AutomaticJudgeBrowserService::progress($pdo,$roundId);
        $lockedJudgeCount=count(array_filter($progress,static fn(array $judge):bool=>(string)($judge['session_status']??'')==='submitted'));
        $html.='<div class="card shadow-sm mb-4 border-primary"><div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong>Automatic '.ucfirst((string)$round['round_type']).' Judge Scoring</strong><div class="small text-muted">Secure judge links, sharing, submission progress and rescore controls.</div></div><div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById(\'automaticJudgeFrame\').contentWindow.location.reload()">Refresh Status</button><button type="button" class="btn btn-outline-dark btn-sm" onclick="const panel=document.getElementById(\'scoring-backups\');if(panel){panel.open=true;panel.scrollIntoView({behavior:\'smooth\',block:\'start\'})}">Backups</button><a class="btn btn-outline-primary btn-sm" href="'.$judgeControlGateway.'" target="_blank" rel="noopener">Open Judge Links</a></div></div><div class="card-body p-0"><iframe id="automaticJudgeFrame" title="Automatic Judge Live Links" src="'.$judgeControlGateway.'" style="display:block;width:100%;height:620px;border:0" loading="eager"></iframe></div></div>';
        if($lockedJudgeCount>0&&Auth::canOverrideCompletedScores()){
            $html.='<section class="border border-danger rounded p-3 mb-4"><div class="fw-bold text-danger">Emergency Scoring Control</div><div class="small text-muted mb-2">Reopens all '.$lockedJudgeCount.' submitted judge columns together. Existing scores are preserved and every affected judge must resubmit.</div><form method="post" action="automatic-setup-action.php" class="row g-2 align-items-end" onsubmit="return confirm(\'Emergency unlock all '.$lockedJudgeCount.' submitted judge score columns?\')"><input type="hidden" name="_csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="unlock_all_judges"><input type="hidden" name="round_id" value="'.$roundId.'"><div class="col-lg-6"><label class="form-label small fw-semibold">Required emergency reason</label><input class="form-control" name="unlock_all_reason" maxlength="500" required></div><div class="col-lg-3"><label class="form-label small fw-semibold">Type UNLOCK ALL</label><input class="form-control" name="unlock_all_confirmation" autocomplete="off" required></div><div class="col-lg-3"><button class="btn btn-danger w-100">Unlock All Locked Scores ('.$lockedJudgeCount.')</button></div></form></section>';
        }
        ob_start();$backupTestMode=false;$backupAction='index.php?mode=automated&round_id='.$roundId;require __DIR__.'/backup-panel.php';$html.=(string)ob_get_clean();
    }else{
        $html.='<div class="card shadow-sm mb-4 border-secondary"><div class="card-body"><h2 class="h5">Judge Live Links</h2><div class="alert alert-secondary mb-0">Save and submit the competitor roster above before judge links are enabled.</div></div></div>';
    }
    $html.='<script>window.addJudge=window.addJudge||function(){const wrap=document.getElementById("judgesWrap");if(!wrap)return;const index=wrap.querySelectorAll(".judge-row").length;const row=document.createElement("div");row.className="row g-2 mb-2 judge-row align-items-center";row.innerHTML=`<div class="col-md-2"><strong>Judge ${index+1}</strong><input type="hidden" name="judge_assignment_id[]" value="0"><input type="hidden" name="judge_directory_id[]" value="0"></div><div class="col-md-5"><input class="form-control" name="judge_name[]" list="judgeDirectorySuggestions" placeholder="Search or type a new judge" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="${index}"> Chief</label></div>`;wrap.appendChild(row);};</script>';
    $html.='<script src="../../public/js/judge-order-controls.js?v=348"></script>';
    return $html;
}
