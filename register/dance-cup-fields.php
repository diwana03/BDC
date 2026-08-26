<?php
declare(strict_types=1);

$danceCupCategories=[];
try{
    $danceCupCategories=$pdo->query("SELECT c.id,c.category_name,c.entry_type,c.dance_style,c.competition_level,c.performance_type,e.id event_id,e.name event_name,e.event_date FROM bdc_dance_cup_competitions c JOIN bdc_dance_cup_events e ON e.id=c.event_id WHERE e.status<>'archived' AND c.status<>'submitted' AND c.round_name='final' ORDER BY COALESCE(e.event_date,'9999-12-31'),e.name,c.category_name")->fetchAll();
}catch(Throwable $ignored){}

$selectedDanceCupIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['dance_cup_categories']??[])))));
$selectedDanceCupRows=[];
$danceCupPartnerOrTeam=trim((string)($_POST['dance_cup_partner_team']??''));
$danceCupTeamSize=max(0,(int)($_POST['dance_cup_team_size']??0));
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['competition_type']??'')==='dance_cup'){
    if(!$selectedDanceCupIds)$error='Choose at least one BDC Dance Cup category.';
    else{
        $placeholders=implode(',',array_fill(0,count($selectedDanceCupIds),'?'));
        $categoryCheck=$pdo->prepare("SELECT c.id,c.category_name,c.entry_type,c.dance_style,c.competition_level,c.performance_type,e.id event_id,e.name event_name,e.event_date FROM bdc_dance_cup_competitions c JOIN bdc_dance_cup_events e ON e.id=c.event_id WHERE c.id IN ($placeholders) AND e.status<>'archived' AND c.status<>'submitted' AND c.round_name='final'");
        $categoryCheck->execute($selectedDanceCupIds);$selectedDanceCupRows=$categoryCheck->fetchAll();
        if(count($selectedDanceCupRows)!==count($selectedDanceCupIds))$error='A selected Dance Cup category is no longer open.';
        elseif(count(array_unique(array_map(static fn(array $row):int=>(int)$row['event_id'],$selectedDanceCupRows)))!==1)$error='Choose categories from one Dance Cup event per registration.';
        else{
            $needsPartner=false;$needsTeam=false;
            foreach($selectedDanceCupRows as $category){$needsPartner=$needsPartner||in_array($category['entry_type'],['couple','duo'],true);$needsTeam=$needsTeam||$category['entry_type']==='team';}
            if(($needsPartner||$needsTeam)&&$danceCupPartnerOrTeam==='')$error=$needsTeam?'Enter the team name.':'Enter the partner or routine name.';
            elseif($needsTeam&&$danceCupTeamSize<4)$error='Team Choreography requires at least 4 dancers.';
        }
    }
}

$eventGroups=[];foreach($danceCupCategories as $category){$eventGroups[(int)$category['event_id']]['event']=$category;$eventGroups[(int)$category['event_id']]['categories'][]=$category;}
$categoryMarkup='<div id="danceCupNote" class="col-12 d-none"><div class="border rounded p-3 bg-light"><h3 class="h6 mb-1">BDC Dance Cup event and categories</h3><p class="small text-muted">Choose every category you are registering for. Only categories configured by the organiser are available.</p>';
if(!$eventGroups)$categoryMarkup.='<div class="alert alert-warning mb-0">No BDC Dance Cup categories are currently open.</div>';
foreach($eventGroups as $group){$event=$group['event'];$categoryMarkup.='<fieldset class="mb-3"><legend class="h6">'.e((string)$event['event_name']).($event['event_date']?' · '.e((string)$event['event_date']):'').'</legend><div class="row g-2">';foreach($group['categories'] as $category){$checked=in_array((int)$category['id'],$selectedDanceCupIds,true)?' checked':'';$meta=ucwords(str_replace('_',' ',(string)$category['dance_style'])).' · '.ucwords(str_replace('_',' ',(string)$category['entry_type'])).' · '.ucwords(str_replace('_',' ',(string)$category['competition_level']));$categoryMarkup.='<div class="col-md-6"><label class="border rounded bg-white p-3 d-flex gap-2 h-100"><input class="form-check-input dc-cup-category" type="checkbox" name="dance_cup_categories[]" value="'.(int)$category['id'].'" data-entry="'.e((string)$category['entry_type']).'"'.$checked.'><span><strong>'.e((string)$category['category_name']).'</strong><small class="d-block text-muted">'.e($meta).'</small></span></label></div>';}$categoryMarkup.='</div></fieldset>';}
$categoryMarkup.='<div class="row g-3" id="dcCupGroupDetails"><div class="col-md-8"><label class="form-label">Partner, routine or team name</label><input class="form-control" name="dance_cup_partner_team" value="'.e($danceCupPartnerOrTeam).'" placeholder="Shown when Partner, Duo or Team is selected"></div><div class="col-md-4"><label class="form-label">Number of team dancers</label><input class="form-control" type="number" min="4" name="dance_cup_team_size" value="'.($danceCupTeamSize?:'').'" placeholder="Team only"></div></div></div></div>';

ob_start(static function(string $html)use(&$success,&$error,$pdo,&$selectedDanceCupRows,$selectedDanceCupIds,$danceCupPartnerOrTeam,$danceCupTeamSize,$categoryMarkup):string{
    if($_SERVER['REQUEST_METHOD']==='POST'&&$success!==''&&($_POST['competition_type']??'')==='dance_cup'){
        $requestId=(int)$pdo->lastInsertId();
        try{
            $snapshots=[];$insert=$pdo->prepare("INSERT INTO bdc_profile_request_dance_cup_categories(request_id,competition_id,event_name,category_name,dance_style,entry_type,competition_level,partner_or_team_name,team_size) VALUES(:request,:competition,:event_name,:category_name,:dance_style,:entry_type,:level,:partner_team,:team_size)");
            foreach($selectedDanceCupRows as $category){$insert->execute(['request'=>$requestId,'competition'=>(int)$category['id'],'event_name'=>$category['event_name'],'category_name'=>$category['category_name'],'dance_style'=>$category['dance_style'],'entry_type'=>$category['entry_type'],'level'=>$category['competition_level'],'partner_team'=>$danceCupPartnerOrTeam?:null,'team_size'=>$danceCupTeamSize?:null]);$snapshots[]=['competition_id'=>(int)$category['id'],'event_id'=>(int)$category['event_id'],'event_name'=>$category['event_name'],'category_name'=>$category['category_name'],'dance_style'=>$category['dance_style'],'entry_type'=>$category['entry_type'],'competition_level'=>$category['competition_level'],'performance_type'=>$category['performance_type']];}
            $pdo->prepare('UPDATE bdc_profile_requests SET payload_json=:payload WHERE id=:id')->execute(['payload'=>json_encode(['submitted_ip'=>$_SERVER['REMOTE_ADDR']??null,'competition_type'=>'dance_cup','dance_cup_categories'=>$snapshots,'partner_or_team_name'=>$danceCupPartnerOrTeam?:null,'team_size'=>$danceCupTeamSize?:null,'permanent_division_change_requested'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$requestId]);
        }catch(Throwable $exception){$pdo->prepare('DELETE FROM bdc_profile_requests WHERE id=:id')->execute(['id'=>$requestId]);$success='';$error='Dance Cup registration could not be saved. Please try again after the portal update is complete.';$html=preg_replace('#<div class="alert alert-success">.*?</div>#s','',$html,1)??$html;$html=str_replace('<div class="btn-group mb-4">','<div class="alert alert-danger">'.e($error).'</div><div class="btn-group mb-4">',$html);}
    }
    $html=str_replace('<div id="danceCupNote" class="col-12 d-none"></div>',$categoryMarkup,$html);
    $script="const cupChecks=[...document.querySelectorAll('.dc-cup-category')],cupDetails=document.getElementById('dcCupGroupDetails');function syncCupDetails(){if(!cupDetails)return;const types=cupChecks.filter(x=>x.checked).map(x=>x.dataset.entry);cupDetails.classList.toggle('d-none',!types.some(x=>['couple','duo','team'].includes(x)));}cupChecks.forEach(x=>x.addEventListener('change',syncCupDetails));syncCupDetails();";
    return str_replace('</script></body>',$script.'</script></body>',$html);
});
