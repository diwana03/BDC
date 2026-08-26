<?php
declare(strict_types=1);

$danceCupGender=(string)($_POST['competitor_gender']??'');
$danceCupStyles=array_values(array_unique(array_intersect((array)($_POST['dance_cup_styles']??[]),['salsa','bachata','cha_cha','other'])));
$danceCupEntries=array_values(array_unique(array_intersect((array)($_POST['dance_cup_entry_types']??[]),['solo','couple','pro_am','team'])));
$danceCupLevels=array_values(array_unique(array_intersect((array)($_POST['dance_cup_levels']??[]),['open','amateur','intermediate','professional'])));
$danceCupPartnerOrTeam=trim((string)($_POST['dance_cup_partner_team']??''));
$danceCupTeamSize=max(0,(int)($_POST['dance_cup_team_size']??0));
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['competition_type']??'')==='dance_cup'){
    if(!in_array($danceCupGender,['male','female','non_binary','prefer_not_to_say'],true))$error='Choose the competitor gender.';
    elseif(!$danceCupStyles)$error='Choose at least one Dance Cup style.';
    elseif(!$danceCupEntries)$error='Choose at least one Dance Cup entry format.';
    elseif(!$danceCupLevels)$error='Choose at least one Dance Cup level.';
    elseif(array_intersect($danceCupEntries,['couple','pro_am','team'])&&$danceCupPartnerOrTeam==='')$error=in_array('team',$danceCupEntries,true)?'Enter the team name.':'Enter the partner or Pro-Am name.';
    elseif(in_array('team',$danceCupEntries,true)&&$danceCupTeamSize<4)$error='Team Choreography requires at least 4 dancers.';
}

$checked=static fn(string $name,array $selected):string=>in_array($name,$selected,true)?' checked':'';
$genderOptions=['male'=>'Male','female'=>'Female','non_binary'=>'Non-binary','prefer_not_to_say'=>'Prefer not to say'];
$markup='<div id="danceCupNote" class="col-12 d-none"><div class="border rounded p-3 bg-light"><h3 class="h6 mb-1">BDC Dance Cup registration profile</h3><p class="small text-muted">Register the styles, formats and levels you can compete in. The organiser assigns the event and its Mixed Gender, Female Only or Male Only rule later.</p><div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Competitor gender</label><select class="form-select" name="competitor_gender"><option value="">Choose gender</option>';
foreach($genderOptions as $value=>$label)$markup.='<option value="'.e($value).'"'.($danceCupGender===$value?' selected':'').'>'.e($label).'</option>';
$markup.='</select></div><div class="col-12"><fieldset><legend class="h6">Dance style</legend><div class="d-flex gap-3 flex-wrap">';
foreach(['salsa'=>'Salsa','bachata'=>'Bachata','cha_cha'=>'Cha Cha','other'=>'Other / Custom'] as $value=>$label)$markup.='<label class="form-check"><input class="form-check-input" type="checkbox" name="dance_cup_styles[]" value="'.$value.'"'.$checked($value,$danceCupStyles).'> '.$label.'</label>';
$markup.='</div></fieldset></div><div class="col-12"><fieldset><legend class="h6">Entry format</legend><div class="d-flex gap-3 flex-wrap">';
foreach(['solo'=>'Solo','couple'=>'Couple','pro_am'=>'Pro-Am','team'=>'Team'] as $value=>$label)$markup.='<label class="form-check"><input class="form-check-input dc-cup-entry" type="checkbox" name="dance_cup_entry_types[]" value="'.$value.'"'.$checked($value,$danceCupEntries).'> '.$label.'</label>';
$markup.='</div></fieldset></div><div class="col-12"><fieldset><legend class="h6">Competition level</legend><div class="d-flex gap-3 flex-wrap">';
foreach(['open'=>'Open','amateur'=>'Amateur','intermediate'=>'Intermediate','professional'=>'Professional'] as $value=>$label)$markup.='<label class="form-check"><input class="form-check-input" type="checkbox" name="dance_cup_levels[]" value="'.$value.'"'.$checked($value,$danceCupLevels).'> '.$label.'</label>';
$markup.='</div></fieldset></div><div class="col-12"><div class="row g-3 d-none" id="dcCupGroupDetails"><div class="col-md-8"><label class="form-label">Partner, Pro-Am or team name</label><input class="form-control" name="dance_cup_partner_team" value="'.e($danceCupPartnerOrTeam).'" placeholder="Required for Couple, Pro-Am or Team"></div><div class="col-md-4"><label class="form-label">Number of team dancers</label><input class="form-control" type="number" min="4" name="dance_cup_team_size" value="'.($danceCupTeamSize?:'').'" placeholder="Team only"></div></div></div></div></div></div>';

ob_start(static function(string $html)use(&$success,&$error,$pdo,$danceCupGender,$danceCupStyles,$danceCupEntries,$danceCupLevels,$danceCupPartnerOrTeam,$danceCupTeamSize,$markup):string{
    if($_SERVER['REQUEST_METHOD']==='POST'&&$success!==''&&($_POST['competition_type']??'')==='dance_cup'){
        $requestId=(int)$pdo->lastInsertId();
        try{
            $insert=$pdo->prepare("INSERT INTO bdc_profile_request_dance_cup_categories(request_id,competition_id,event_name,category_name,dance_style,entry_type,competition_level,partner_or_team_name,team_size,competitor_gender) VALUES(:request,NULL,NULL,NULL,:dance_style,:entry_type,:level,:partner_team,:team_size,:gender)");
            $profiles=[];foreach($danceCupStyles as $style)foreach($danceCupEntries as $entry)foreach($danceCupLevels as $level){$insert->execute(['request'=>$requestId,'dance_style'=>$style,'entry_type'=>$entry,'level'=>$level,'partner_team'=>$danceCupPartnerOrTeam?:null,'team_size'=>$danceCupTeamSize?:null,'gender'=>$danceCupGender]);$profiles[]=['dance_style'=>$style,'entry_type'=>$entry,'competition_level'=>$level];}
            $pdo->prepare('UPDATE bdc_profile_requests SET payload_json=:payload WHERE id=:id')->execute(['payload'=>json_encode(['submitted_ip'=>$_SERVER['REMOTE_ADDR']??null,'competition_type'=>'dance_cup','competitor_gender'=>$danceCupGender,'dance_cup_profiles'=>$profiles,'partner_or_team_name'=>$danceCupPartnerOrTeam?:null,'team_size'=>$danceCupTeamSize?:null,'event_assigned_during_scoring'=>true,'permanent_division_change_requested'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$requestId]);
        }catch(Throwable $exception){$pdo->prepare('DELETE FROM bdc_profile_requests WHERE id=:id')->execute(['id'=>$requestId]);$success='';$error='Dance Cup registration could not be saved. Please try again after the portal update is complete.';$html=preg_replace('#<div class="alert alert-success">.*?</div>#s','',$html,1)??$html;$html=str_replace('<div class="btn-group mb-4">','<div class="alert alert-danger">'.e($error).'</div><div class="btn-group mb-4">',$html);}
    }
    $html=str_replace('<div id="danceCupNote" class="col-12 d-none"></div>',$markup,$html);
    $script="const dcEntries=[...document.querySelectorAll('.dc-cup-entry')],dcDetails=document.getElementById('dcCupGroupDetails');function syncDcDetails(){if(!dcDetails)return;dcDetails.classList.toggle('d-none',!dcEntries.some(x=>x.checked&&['couple','pro_am','team'].includes(x.value)));}dcEntries.forEach(x=>x.addEventListener('change',syncDcDetails));syncDcDetails();";
    return str_replace('</script></body>',$script.'</script></body>',$html);
});
