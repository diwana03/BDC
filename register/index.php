<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';use App\Core\Csrf;use App\Core\Database;$pdo=Database::connection();$mode=($_GET['mode']??$_POST['mode']??'new')==='update'?'update':'new';$error='';$success='';$matches=[];$q=trim((string)($_GET['q']??''));
$countryOptions=[];$countriesJson=@file_get_contents(dirname(__DIR__).'/public/assets/flags/countries.json');foreach((json_decode((string)$countriesJson,true)?:[]) as $countryRow)if(!empty($countryRow['name']))$countryOptions[]=(string)$countryRow['name'];sort($countryOptions,SORT_NATURAL|SORT_FLAG_CASE);
$dialCodes=['Argentina'=>'+54','Australia'=>'+61','Austria'=>'+43','Belgium'=>'+32','Brazil'=>'+55','Bulgaria'=>'+359','Cambodia'=>'+855','Canada'=>'+1','Chile'=>'+56','China'=>'+86','Colombia'=>'+57','Costa Rica'=>'+506','Croatia'=>'+385','Cuba'=>'+53','Cyprus'=>'+357','Czech Republic'=>'+420','Denmark'=>'+45','Dominican Republic'=>'+1','Ecuador'=>'+593','Estonia'=>'+372','Finland'=>'+358','France'=>'+33','Germany'=>'+49','Greece'=>'+30','Hong Kong'=>'+852','Hungary'=>'+36','India'=>'+91','Indonesia'=>'+62','Ireland'=>'+353','Israel'=>'+972','Italy'=>'+39','Japan'=>'+81','Kazakhstan'=>'+7','Latvia'=>'+371','Lithuania'=>'+370','Luxembourg'=>'+352','Malaysia'=>'+60','Mexico'=>'+52','Morocco'=>'+212','Netherlands'=>'+31','New Zealand'=>'+64','Norway'=>'+47','Peru'=>'+51','Philippines'=>'+63','Poland'=>'+48','Portugal'=>'+351','Romania'=>'+40','Russia'=>'+7','Singapore'=>'+65','Slovakia'=>'+421','Slovenia'=>'+386','South Africa'=>'+27','South Korea'=>'+82','Spain'=>'+34','Sweden'=>'+46','Switzerland'=>'+41','Taiwan'=>'+886','Thailand'=>'+66','Turkey'=>'+90','Ukraine'=>'+380','United Arab Emirates'=>'+971','United Kingdom'=>'+44','United States'=>'+1','Uruguay'=>'+598','Venezuela'=>'+58','Vietnam'=>'+84'];
$normaliseCountry=static function(string $country)use($countryOptions):string{foreach($countryOptions as $option)if(strcasecmp(trim($country),$option)===0)return $option;return trim($country);};
if($mode==='update'&&$q!==''){$n=mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u',' ',$q)??$q));$s=$pdo->prepare("SELECT id,bdc_id,exact_name,country,current_division,dance_role,photo_url FROM bdc_competitors WHERE exact_name LIKE :q OR normalised_name LIKE :n OR bdc_id=:id OR instagram=:ig ORDER BY exact_name LIMIT 20");$s->execute(['q'=>'%'.$q.'%','n'=>'%'.$n.'%','id'=>$q,'ig'=>ltrim($q,'@')]);$matches=$s->fetchAll();}
$bachataDivisions=['novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','semi_pro','pro','all_star'];$salsaDivisions=['novice','intermediate','advanced','salsa_rising','salsa_open'];
$formCompetitionType=in_array((string)($_POST['competition_type']??''),['jack_jill','dance_cup'],true)?(string)$_POST['competition_type']:'jack_jill';$formDanceStyle=in_array((string)($_POST['dance_style']??''),['bachata','salsa'],true)?(string)$_POST['dance_style']:'bachata';$formRole=in_array((string)($_POST['dance_role']??''),['unknown','leader','follower','both'],true)?(string)$_POST['dance_role']:'unknown';$formDivision=(string)($_POST['current_division']??'unknown');$formCountry=$normaliseCountry((string)($_POST['country']??''));$formDial=preg_replace('/[^0-9+]/','',(string)($_POST['phone_dial_code']??''))??'';$formValue=static fn(string $key):string=>trim((string)($_POST[$key]??''));
require __DIR__.'/dance-cup-fields.php';
$storeOriginalPhoto=static function(?array $file):?string{
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    $uploadError=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($uploadError!==UPLOAD_ERR_OK){
        $message=match($uploadError){
            UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'Photo is larger than the server upload limit.',
            UPLOAD_ERR_PARTIAL=>'Photo upload was interrupted. Please choose the photo again.',
            UPLOAD_ERR_NO_TMP_DIR=>'Photo upload storage is temporarily unavailable.',
            UPLOAD_ERR_CANT_WRITE=>'The server could not save the uploaded photo.',
            UPLOAD_ERR_EXTENSION=>'The server rejected this photo upload.',
            default=>'Photo upload failed.',
        };
        throw new RuntimeException($message);
    }
    $tmp=(string)($file['tmp_name']??'');
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('The selected photo could not be received. Please choose it again.');
    $size=(int)($file['size']??0);
    if($size<1||$size>15*1024*1024)throw new RuntimeException('Photo must be no larger than 15 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime])||getimagesize($tmp)===false)throw new RuntimeException('Photo must be a valid JPG, PNG or WebP image.');
    $dir=dirname(__DIR__).'/uploads/competitors';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Photo upload folder is unavailable.');
    $filename='request-original-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
    if(!move_uploaded_file($tmp,$dir.'/'.$filename))throw new RuntimeException('Photo upload failed while saving the original image.');
    return url('uploads/competitors/'.$filename);
};
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token. Please refresh and try again.';
    else{
        $name=trim((string)($_POST['full_name']??''));
        $email=strtolower(trim((string)($_POST['email']??'')));
        $submittedCountry=$normaliseCountry((string)($_POST['country']??''));
        $localPhone=trim((string)($_POST['phone_local']??''));
        $dialCode=preg_replace('/[^0-9+]/','',(string)($_POST['phone_dial_code']??''))??'';
        $submittedPhone=$localPhone===''?null:(str_starts_with($localPhone,'+')?$localPhone:trim($dialCode.' '.$localPhone));
        $cid=(int)($_POST['competitor_id']??0);
        $competitionType=(string)($_POST['competition_type']??'jack_jill');
        $danceStyle=(string)($_POST['dance_style']??'bachata');
        if(!in_array($competitionType,['jack_jill','dance_cup'],true))$competitionType='jack_jill';
        if(!in_array($danceStyle,['bachata','salsa'],true))$danceStyle='bachata';
        $division=(string)($_POST['current_division']??'unknown');
        $allowed=$danceStyle==='salsa'?$salsaDivisions:$bachataDivisions;
        if($competitionType==='jack_jill'&&$division!=='unknown'&&!in_array($division,$allowed,true))$error='Invalid division for '.ucfirst($danceStyle).'.';
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Please enter a valid email or leave it blank.';
        elseif($mode==='update'&&$cid<1)$error='Please select your existing BDC profile.';
        else{
            $photo=null;
            try{$photo=$storeOriginalPhoto($_FILES['photo']??null);}
            catch(Throwable $uploadFailure){$error=$uploadFailure->getMessage();}
            if($error===''){
                $type=$mode==='update'?'profile_update':'new_registration';
                $role=$competitionType==='jack_jill'?($_POST['dance_role']??'unknown'):'unknown';
                $division=$competitionType==='jack_jill'?$division:'unknown';
                $payload=json_encode([
                    'submitted_ip'=>$_SERVER['REMOTE_ADDR']??null,
                    'competition_type'=>$competitionType,
                    'dance_style'=>$competitionType==='jack_jill'?$danceStyle:null,
                    'requested_competition_category'=>$competitionType==='jack_jill'?$division:null,
                    'permanent_division_change_requested'=>false,
                    'photo_processing'=>'original_unchanged',
                ],JSON_UNESCAPED_UNICODE);
                $s=$pdo->prepare("INSERT INTO bdc_profile_requests(request_type,competitor_id,full_name,email,phone,instagram,country,dance_role,current_division,photo_url,notes,payload_json,status) VALUES(:t,:cid,:n,:e,:p,:ig,:c,:r,:d,:photo,:notes,:payload,'pending')");
                $s->execute([
                    't'=>$type,
                    'cid'=>$cid?:null,
                    'n'=>$name,
                    'e'=>$email,
                    'p'=>$submittedPhone,
                    'ig'=>ltrim(trim((string)($_POST['instagram']??'')),'@')?:null,
                    'c'=>$submittedCountry?:null,
                    'r'=>$role,
                    'd'=>$division,
                    'photo'=>$photo,
                    'notes'=>trim((string)($_POST['notes']??''))?:null,
                    'payload'=>$payload,
                ]);
                $success=$mode==='update'?'Your profile update request was submitted for admin approval.':'Your registration was submitted for admin approval.';
            }
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDC Competitor Registration</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=e(url('public/assets/css/app.css'))?>" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:900px"><h1 class="mb-2">BDC Competitor Portal</h1><div class="btn-group mb-4"><a class="btn <?=$mode==='new'?'btn-dark':'btn-outline-dark'?>" href="?mode=new">New competitor</a><a class="btn <?=$mode==='update'?'btn-dark':'btn-outline-dark'?>" href="?mode=update">Update existing profile</a></div><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($mode==='update'):?><div class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h5">Find your existing BDC ID</h2><form class="row g-2"><input type="hidden" name="mode" value="update"><div class="col-md-9"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search exact name, BDC ID or Instagram"></div><div class="col-md-3 d-grid"><button class="btn btn-outline-dark">Search</button></div></form></div></div><?php endif;?><form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="mode" value="<?=e($mode)?>"><?php if($mode==='update'):?><label class="form-label fw-semibold">Select your BDC profile</label><div class="row g-2 mb-3"><?php foreach($matches as $m):?><div class="col-md-6"><label class="border rounded p-3 d-flex gap-3 align-items-center w-100 bg-white"><input class="form-check-input" type="radio" name="competitor_id" value="<?=$m['id']?>" required><span><strong><?=e($m['exact_name'])?></strong><br><small><?=e($m['bdc_id'])?> · <?=e((string)$m['country'])?></small></span></label></div><?php endforeach;?></div><?php endif;?><div class="row g-3"><div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="full_name" value="<?=e($formValue('full_name'))?>" placeholder="Optional"></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e($formValue('email'))?>" placeholder="Optional"></div><div class="col-md-6"><label class="form-label">Phone</label><div class="input-group"><select class="form-select" name="phone_dial_code" id="portalDial" style="max-width:125px"><option value="">Code</option><?php foreach($dialCodes as $dialCountry=>$dial):?><option value="<?=e($dial)?>" data-country="<?=e($dialCountry)?>" <?=(($formCountry===$dialCountry)||($formCountry===''&&$formDial===$dial))?'selected':''?>><?=e($dial.' · '.$dialCountry)?></option><?php endforeach;?></select><input class="form-control" name="phone_local" value="<?=e($formValue('phone_local'))?>" inputmode="tel" autocomplete="tel-national" placeholder="Phone number"></div></div><div class="col-md-6"><label class="form-label">Instagram</label><input class="form-control" name="instagram" value="<?=e($formValue('instagram'))?>"></div><div class="col-md-6"><label class="form-label">Country</label><select class="form-select" name="country" id="portalCountry"><option value="">Select country</option><?php foreach($countryOptions as $option):?><option value="<?=e($option)?>" <?=$formCountry===$option?'selected':''?>><?=e($option)?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label">Profile photo</label><input class="form-control" id="profilePhoto" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><div class="form-text">Upload the exact original photo. No crop or adjustment is applied. JPG, PNG or WebP, maximum 15 MB.</div></div><div class="col-12"><hr><h2 class="h5">Competition Profile</h2></div><div class="col-md-6"><label class="form-label fw-semibold">Competition Type</label><select id="competitionType" class="form-select" name="competition_type"><option value="jack_jill" <?=$formCompetitionType==='jack_jill'?'selected':''?>>Jack & Jill</option><option value="dance_cup" <?=$formCompetitionType==='dance_cup'?'selected':''?>>BDC Dance Cup</option></select></div><div class="col-md-6" id="danceStyleWrap"><label class="form-label fw-semibold">Dance Style</label><select class="form-select" name="dance_style" id="danceStyle"><option value="bachata" <?=$formDanceStyle==='bachata'?'selected':''?>>Bachata</option><option value="salsa" <?=$formDanceStyle==='salsa'?'selected':''?>>Salsa</option></select></div><div id="jackJillFields" class="col-12"><div class="row g-3"><div class="col-md-6"><label class="form-label">Role</label><select class="form-select" name="dance_role"><option value="unknown" <?=$formRole==='unknown'?'selected':''?>>Select</option><option value="leader" <?=$formRole==='leader'?'selected':''?>>Lead</option><option value="follower" <?=$formRole==='follower'?'selected':''?>>Follow</option><option value="both" <?=$formRole==='both'?'selected':''?>>Both</option></select></div><div class="col-md-6"><label class="form-label">Competition category</label><select class="form-select" name="current_division" id="currentDivision"><option value="unknown" <?=$formDivision==='unknown'?'selected':''?>>Select</option><optgroup label="BDC Divisions"><option value="novice" <?=$formDivision==='novice'?'selected':''?>>Novice</option><option value="intermediate" <?=$formDivision==='intermediate'?'selected':''?>>Intermediate</option><option value="advanced" <?=$formDivision==='advanced'?'selected':''?>>Advanced</option><option value="semi_pro" data-dance="bachata" <?=$formDivision==='semi_pro'?'selected':''?>>Semi Pro</option><option value="pro" data-dance="bachata" <?=$formDivision==='pro'?'selected':''?>>Pro</option><option value="all_star" data-dance="bachata" <?=$formDivision==='all_star'?'selected':''?>>Bachata All Star</option></optgroup><optgroup label="Special Categories"><option value="bachata_rising" data-dance="bachata" <?=$formDivision==='bachata_rising'?'selected':''?>>Bachata Rising</option><option value="bachata_open" data-dance="bachata" <?=$formDivision==='bachata_open'?'selected':''?>>Bachata Open</option><option value="bachata_invitational" data-dance="bachata" <?=$formDivision==='bachata_invitational'?'selected':''?>>Bachata Invitational</option><option value="salsa_rising" data-dance="salsa" <?=$formDivision==='salsa_rising'?'selected':''?>>Salsa Rising</option><option value="salsa_open" data-dance="salsa" <?=$formDivision==='salsa_open'?'selected':''?>>Salsa Open</option></optgroup></select><div class="form-text">Choose the category you may enter. This does not change your permanent BDC division. Permanent progression is recorded only after an actual competition result is approved by a Super Admin.</div><div class="d-none" id="salsaDivisionNote"></div></div></div></div><div id="danceCupNote" class="col-12 d-none"></div><div class="col-12"><label class="form-label">Notes for admin</label><textarea class="form-control" name="notes" rows="3"><?=e($formValue('notes'))?></textarea></div></div><div class="mt-4"><button class="btn btn-dark btn-lg"><?=$mode==='update'?'Submit update request':'Submit registration'?></button></div></div></form></main><script>const portalCountry=document.getElementById('portalCountry'),portalDial=document.getElementById('portalDial'),portalDialCodes=<?=json_encode($dialCodes,JSON_UNESCAPED_SLASHES)?>;portalCountry?.addEventListener('change',()=>{const country=portalCountry.value,dial=portalDialCodes[country]||'',option=[...portalDial.options].find(item=>item.dataset.country===country);if(option)portalDial.selectedIndex=option.index;else if(dial)portalDial.value=dial});const type=document.getElementById('competitionType'),danceWrap=document.getElementById('danceStyleWrap'),dance=document.getElementById('danceStyle'),jj=document.getElementById('jackJillFields'),cup=document.getElementById('danceCupNote'),division=document.getElementById('currentDivision'),salsaNote=document.getElementById('salsaDivisionNote');function syncDivision(){[...division.options].forEach(o=>{const only=o.dataset.dance;o.hidden=!!only&&only!==dance.value;o.disabled=!!only&&only!==dance.value});const allowed=dance.value==='salsa'?['unknown','novice','intermediate','advanced','salsa_rising','salsa_open']:['unknown','novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','semi_pro','pro','all_star'];if(!allowed.includes(division.value))division.value='unknown';salsaNote.classList.toggle('d-none',dance.value!=='salsa')}function syncCompetition(){const isCup=type.value==='dance_cup';danceWrap.classList.toggle('d-none',isCup);jj.classList.toggle('d-none',isCup);cup.classList.toggle('d-none',!isCup);syncDivision()}type.addEventListener('change',syncCompetition);dance.addEventListener('change',syncDivision);syncCompetition();</script></body></html>
