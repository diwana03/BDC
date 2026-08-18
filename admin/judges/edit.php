<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\JudgeDirectoryService;use App\Services\CountryFlagService;
Auth::requirePermission('judges.edit');
Auth::requireAdmin();$pdo=Database::connection();JudgeDirectoryService::ensure($pdo);$id=(int)($_GET['id']??$_POST['id']??0);$s=$pdo->prepare('SELECT * FROM bdc_judges WHERE id=:id');$s->execute(['id'=>$id]);$judge=$s->fetch();if(!$judge){http_response_code(404);exit('Judge not found.');}$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token.';else{
  $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));if($name==='')$error='Full name is required.';elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email or leave it blank.';
  $photo=(string)($judge['photo_url']??'');$original=(string)($judge['original_photo_url']??'');if(isset($_POST['remove_photo'])){$photo='';$original='';}
  if($error===''&&!empty($_FILES['photo']['tmp_name'])){$f=$_FILES['photo'];$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($types[$mime])||(int)$f['size']>5*1024*1024)$error='Photo must be JPG, PNG or WebP and under 5 MB.';else{$dir=dirname(__DIR__,2).'/uploads/judges';if(!is_dir($dir)&&!mkdir($dir,0755,true))$error='Photo upload is temporarily unavailable.';else{$filename='judge-'.$id.'-'.bin2hex(random_bytes(6)).'.'.$types[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$filename))$error='Photo upload failed.';else$photo=$original=url('uploads/judges/'.$filename);}}}
  if($error===''){$list=static fn(string $key,array $allowed):?string=>($v=implode(',',array_values(array_intersect($allowed,array_map('strval',(array)($_POST[$key]??[]))))))!==''?$v:null;$preferred=in_array(($_POST['preferred_contact']??''),['email','whatsapp','either','none'],true)?$_POST['preferred_contact']:'none';$role=in_array(($_POST['judge_role']??''),['regular','chief','both'],true)?$_POST['judge_role']:'regular';$status=in_array(($_POST['status']??''),['active','inactive'],true)?$_POST['status']:'active';$pdo->prepare('UPDATE bdc_judges SET full_name=:name,display_name=:display,country=:country,country_code=:code,city=:city,photo_url=:photo,original_photo_url=:original,instagram=:instagram,email=:email,phone=:phone,whatsapp=:whatsapp,preferred_contact=:preferred,dance_styles=:styles,judge_role=:role,qualified_divisions=:divisions,qualified_rounds=:rounds,languages=:languages,biography=:bio,experience=:experience,certification=:certification,status=:status,notes=:notes WHERE id=:id')->execute(['name'=>$name,'display'=>trim((string)($_POST['display_name']??''))?:null,'country'=>trim((string)($_POST['country']??''))?:null,'code'=>CountryFlagService::code($_POST['country']??null),'city'=>trim((string)($_POST['city']??''))?:null,'photo'=>$photo?:null,'original'=>$original?:null,'instagram'=>ltrim(trim((string)($_POST['instagram']??'')),'@')?:null,'email'=>$email?:null,'phone'=>trim((string)($_POST['phone']??''))?:null,'whatsapp'=>trim((string)($_POST['whatsapp']??''))?:null,'preferred'=>$preferred,'styles'=>$list('dance_styles',['bachata','salsa']),'role'=>$role,'divisions'=>$list('qualified_divisions',['novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','all_star']),'rounds'=>$list('qualified_rounds',['heats','semifinal','final']),'languages'=>trim((string)($_POST['languages']??''))?:null,'bio'=>trim((string)($_POST['biography']??''))?:null,'experience'=>trim((string)($_POST['experience']??''))?:null,'certification'=>trim((string)($_POST['certification']??''))?:null,'status'=>$status,'notes'=>trim((string)($_POST['notes']??''))?:null,'id'=>$id]);Auth::audit((int)(Auth::user()['id']??0),'judge_updated',[],'judge',$id);$success='Judge profile updated.';$s->execute(['id'=>$id]);$judge=$s->fetch();}
 }
}
$has=static fn(string $field,string $value):bool=>in_array($value,explode(',',(string)($judge[$field]??'')),true);$photoSrc=$judge['photo_url']?:url('public/assets/img/default-competitor.svg');$countries=['Singapore','Thailand','China','Hong Kong','Taiwan','Japan','South Korea','Indonesia','Malaysia','Vietnam','Philippines','India','Australia','New Zealand','United States','United Kingdom','Spain','France','Italy','Germany','Netherlands','Portugal','Morocco','Kazakhstan','Peru','Colombia','Costa Rica','Mexico','Canada','Brazil','Argentina','Cuba'];$judgeFlag=CountryFlagService::emoji($judge['country_code']?:$judge['country']);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Judge | BDC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4" style="max-width:950px">
<div class="d-flex justify-content-between align-items-center mb-3">
<div>
<h1 class="h3 mb-1">Edit Judge Profile</h1>
<div class="text-muted">
<?=e($judge['judge_code'])?>
</div>
</div>
<a class="btn btn-outline-dark" href="./">Back to Judge Database</a>
</div>
<?php if($success):?>
<div class="alert alert-success">
<?=e($success)?>
</div>
<?php endif;?>
<?php if($error):?>
<div class="alert alert-danger">
<?=e($error)?>
</div>
<?php endif;?>
<form method="post" enctype="multipart/form-data" class="card shadow-sm">
<div class="card-body p-4">
<input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>">
<input type="hidden" name="id" value="<?=$id?>">
<div class="row g-4">
<div class="col-md-3">
<img src="<?=e($photoSrc)?>" alt="Judge photo" style="width:150px;height:188px;object-fit:cover;border-radius:14px;border:1px solid #dee2e6">
<label class="form-label d-block mt-3">Judge photo</label>
<input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
<div class="form-text">JPG, PNG or WebP, maximum 5 MB.</div>
<?php if(!empty($judge['photo_url'])):?>
<div class="d-grid gap-2 mt-3">
<a class="btn btn-outline-primary" href="photo-adjust.php?id=<?=$id?>">Adjust photo</a>
<label class="form-check">
<input class="form-check-input" type="checkbox" name="remove_photo"> Remove current photo</label>
</div>
<?php endif;?>
</div>
<div class="col-md-9">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Full name *</label>
<input class="form-control" name="full_name" value="<?=e($judge['full_name'])?>" required>
</div>
<div class="col-md-6">
<label class="form-label">Display name</label>
<input class="form-control" name="display_name" value="<?=e((string)$judge['display_name'])?>">
</div>
<div class="col-md-6">
<label class="form-label">Country</label>
<div class="input-group">
<span class="input-group-text" title="Judge flag"><?=$judgeFlag!==''?e($judgeFlag):'🏳️'?></span>
<input class="form-control" name="country" list="judgeCountries" value="<?=e((string)$judge['country'])?>" placeholder="Select or type country">
</div>
<datalist id="judgeCountries"><?php foreach($countries as $country):?><option value="<?=e($country)?>"><?php endforeach;?></datalist>
<div class="form-text">The country automatically controls the flag shown for this judge.</div>
</div>
<div class="col-md-6">
<label class="form-label">City</label>
<input class="form-control" name="city" value="<?=e((string)$judge['city'])?>">
</div>
<div class="col-md-6">
<label class="form-label">Instagram</label>
<input class="form-control" name="instagram" value="<?=e((string)$judge['instagram'])?>">
</div>
<div class="col-md-6">
<label class="form-label">Languages</label>
<input class="form-control" name="languages" value="<?=e((string)$judge['languages'])?>">
</div>
</div>
</div>
</div>
<hr>
<h2 class="h5">Private contact details</h2>
<div class="row g-3">
<div class="col-md-4">
<label class="form-label">Email</label>
<input class="form-control" type="email" name="email" value="<?=e((string)$judge['email'])?>">
</div>
<div class="col-md-4">
<label class="form-label">Phone</label>
<input class="form-control" name="phone" value="<?=e((string)$judge['phone'])?>">
</div>
<div class="col-md-4">
<label class="form-label">WhatsApp</label>
<input class="form-control" name="whatsapp" value="<?=e((string)$judge['whatsapp'])?>">
</div>
<div class="col-md-6">
<label class="form-label">Preferred contact</label>
<select class="form-select" name="preferred_contact">
<?php foreach(['none'=>'No preference','email'=>'Email','whatsapp'=>'WhatsApp','either'=>'Either'] as $v=>$label):?>
<option value="<?=$v?>" <?=$judge['preferred_contact']===$v?'selected':''?>>
<?=$label?>
</option>
<?php endforeach;?>
</select>
</div>
</div>
<hr>
<h2 class="h5">Judging qualifications</h2>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label d-block">Dance styles</label>
<?php foreach(['bachata'=>'Bachata','salsa'=>'Salsa'] as $v=>$label):?>
<label class="form-check form-check-inline">
<input class="form-check-input" type="checkbox" name="dance_styles[]" value="<?=$v?>" <?=$has('dance_styles',$v)?'checked':''?>> <?=$label?>
</label>
<?php endforeach;?>
</div>
<div class="col-md-6">
<label class="form-label">Judge role</label>
<select class="form-select" name="judge_role">
<?php foreach(['regular'=>'Regular Judge','chief'=>'Chief Judge','both'=>'Regular and Chief Judge'] as $v=>$label):?>
<option value="<?=$v?>" <?=$judge['judge_role']===$v?'selected':''?>>
<?=$label?>
</option>
<?php endforeach;?>
</select>
</div>
<div class="col-12">
<label class="form-label d-block">Qualified rounds</label>
<?php foreach(['heats'=>'Heats','semifinal'=>'Semifinal','final'=>'Final Relative Placement'] as $v=>$label):?>
<label class="form-check form-check-inline">
<input class="form-check-input" type="checkbox" name="qualified_rounds[]" value="<?=$v?>" <?=$has('qualified_rounds',$v)?'checked':''?>> <?=$label?>
</label>
<?php endforeach;?>
</div>
<div class="col-12">
<label class="form-label d-block">Qualified divisions</label>
<div class="row g-2">
<?php foreach(['novice'=>'Novice','intermediate'=>'Intermediate','advanced'=>'Advanced','bachata_rising'=>'Bachata Rising','bachata_open'=>'BDC Open','bachata_invitational'=>'Bachata Invitational','salsa_rising'=>'Salsa Rising','salsa_open'=>'Salsa Open','semi_pro'=>'Semi Pro','pro'=>'Pro','all_star'=>'All Star'] as $v=>$label):?>
<div class="col-6 col-md-4">
<label class="form-check">
<input class="form-check-input" type="checkbox" name="qualified_divisions[]" value="<?=$v?>" <?=$has('qualified_divisions',$v)?'checked':''?>> <?=$label?>
</label>
</div>
<?php endforeach;?>
</div>
</div>
<div class="col-12">
<label class="form-label">Short biography</label>
<textarea class="form-control" name="biography" rows="3">
<?=e((string)$judge['biography'])?>
</textarea>
</div>
<div class="col-md-6">
<label class="form-label">Judging experience</label>
<textarea class="form-control" name="experience" rows="3">
<?=e((string)$judge['experience'])?>
</textarea>
</div>
<div class="col-md-6">
<label class="form-label">Certification or training</label>
<textarea class="form-control" name="certification" rows="3">
<?=e((string)$judge['certification'])?>
</textarea>
</div>
<div class="col-md-6">
<label class="form-label">Status</label>
<select class="form-select" name="status">
<option value="active" <?=$judge['status']==='active'?'selected':''?>>Active</option>
<option value="inactive" <?=$judge['status']==='inactive'?'selected':''?>>Inactive</option>
</select>
</div>
<div class="col-12">
<label class="form-label">Internal admin notes</label>
<textarea class="form-control" name="notes" rows="3">
<?=e((string)$judge['notes'])?>
</textarea>
</div>
</div>
</div>
<div class="card-footer bg-white p-3">
<button class="btn btn-dark">Save Judge Profile</button>
</div>
</form>
</main>
</body>
</html>
