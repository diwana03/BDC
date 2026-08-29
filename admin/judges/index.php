<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\JudgeDirectoryService;use App\Services\CountryFlagService;use App\Services\JudgeProfileUpdateLinkService;use App\Services\JudgeRegistrationLinkService;
Auth::requirePermission('judges.view');$canEdit=Auth::can('judges.edit');
Auth::requireAdmin();$pdo=Database::connection();JudgeDirectoryService::ensure($pdo);JudgeDirectoryService::ensureProfileRequests($pdo);JudgeProfileUpdateLinkService::ensure($pdo);JudgeRegistrationLinkService::ensure($pdo);$notice='';$error='';$generatedUpdateLink='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$canEdit){http_response_code(403);exit('You do not have permission to edit judge profiles.');}
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token. Refresh and try again.';
 else try{
  $action=(string)($_POST['action']??'create');
  if($action==='create'){$judge=JudgeDirectoryService::create($pdo,$_POST);$notice='Judge created: '.$judge['full_name'].' · '.$judge['judge_code'];}
  elseif($action==='generate_registration_link'){JudgeRegistrationLinkService::generate($pdo,(int)(Auth::user()['id']??0)?:null);$notice='New 12-hour private judge registration link created. The previous link is now invalid.';Auth::audit((int)(Auth::user()['id']??0),'judge_registration_link_generated',['expires_hours'=>12]);}
  elseif($action==='generate_profile_update_link'){$judgeId=(int)($_POST['judge_id']??0);$q=$pdo->prepare('SELECT id,full_name FROM bdc_judges WHERE id=:id');$q->execute(['id'=>$judgeId]);$target=$q->fetch();if(!$target)throw new RuntimeException('Judge not found.');$token=JudgeProfileUpdateLinkService::generate($pdo,$judgeId,(int)(Auth::user()['id']??0)?:null);$generatedUpdateLink=JudgeProfileUpdateLinkService::url($token);$notice='Six-hour profile update link created for '.$target['full_name'].'. Regenerating replaces the previous link.';Auth::audit((int)(Auth::user()['id']??0),'judge_profile_update_link_generated',['expires_hours'=>6],'judge',$judgeId);}
  elseif(in_array($action,['approve_request','reject_request'],true)){
   $id=(int)($_POST['request_id']??0);$s=$pdo->prepare("SELECT * FROM bdc_judge_profile_requests WHERE id=:id AND status='pending'");$s->execute(['id'=>$id]);$request=$s->fetch();if(!$request)throw new RuntimeException('Pending judge profile request not found.');
   if($action==='approve_request'){$judge=JudgeDirectoryService::findOrCreate($pdo,(string)$request['full_name'],$request);$judge=JudgeDirectoryService::mergeProfile($pdo,(int)$judge['id'],$request);$status='approved';$notice='Judge approved: '.$judge['full_name'].' · '.$judge['judge_code'];}else{$status='rejected';$notice='Judge profile request rejected.';}
   $pdo->prepare('UPDATE bdc_judge_profile_requests SET status=:status,admin_notes=:notes,reviewed_by=:uid,reviewed_at=NOW() WHERE id=:id')->execute(['status'=>$status,'notes'=>trim((string)($_POST['admin_notes']??''))?:null,'uid'=>(int)(Auth::user()['id']??0),'id'=>$id]);
  }else throw new RuntimeException('Invalid judge action.');
 }catch(Throwable $e){$error=$e->getMessage();}
}
$pending=$pdo->query("SELECT * FROM bdc_judge_profile_requests WHERE status='pending' ORDER BY created_at,id")->fetchAll();$rows=$pdo->query("SELECT * FROM bdc_judges ORDER BY status='active' DESC,full_name")->fetchAll();$registrationLink=JudgeRegistrationLinkService::active($pdo);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Judge Directory | BDC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?=e(url('public/css/scoring-premium.css?v=428'))?>">
<script defer src="<?=e(url('public/assets/js/bdc-theme.js?v=420'))?>"></script>
<script defer src="<?=e(url('public/assets/js/admin-mobile-v428.js?v=428'))?>"></script>
</head>
<body class="bg-light bdc-mobile-admin">
<nav class="navbar navbar-dark bg-dark">
<div class="container-fluid">
<a class="navbar-brand" href="../">BDC Admin</a>
<a class="btn btn-light btn-sm" href="../scoring/">Scoring</a>
</div>
</nav>
<main class="container-fluid py-4 px-lg-4">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
<div>
<h1 class="h2 mb-1">Judge Directory</h1>
<p class="text-muted mb-0">Reusable judge profiles for scoring, contact and projection.</p>
</div>
<div class="card shadow-sm" style="min-width:min(100%,430px)">
<div class="card-body">
<label class="form-label fw-semibold">Private judge registration link</label>
<?php if($registrationLink):?>
<div class="input-group"><input class="form-control" id="judgeRegistrationLink" readonly value="<?=e((string)$registrationLink['url'])?>"><button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('judgeRegistrationLink').value);this.textContent='Copied'">Copy Full Link</button></div>
<div class="form-text">Token protected. Expires <?=e((string)$registrationLink['expires_at'])?>. Send the complete link to invited judges.</div>
<?php else:?><div class="alert alert-secondary py-2">No active registration link.</div><?php endif;?>
<form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="generate_registration_link"><button class="btn btn-primary btn-sm"><?=$registrationLink?'Regenerate':'Generate'?> 12-hour Full Link</button></form>
</div>
</div>
</div>
<?php if($notice):?>
<div class="alert alert-success">
<?=e($notice)?>
</div>
<?php endif;?>
<?php if($error):?>
<div class="alert alert-danger">
<?=e($error)?>
</div>
<?php endif;?>
<?php if($generatedUpdateLink):?><div class="card border-primary shadow-sm mb-4"><div class="card-body"><label class="form-label fw-semibold">Secure judge profile update link · expires in 6 hours</label><div class="input-group"><input class="form-control" id="generatedJudgeUpdateLink" readonly value="<?=e($generatedUpdateLink)?>"><button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('generatedJudgeUpdateLink').value);this.textContent='Copied'">Copy Link</button><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?=e($generatedUpdateLink)?>">Open</a></div></div></div><?php endif;?>
<?php if($pending):?>
<section class="card shadow-sm border-warning mb-4">
<div class="card-header bg-warning-subtle">
<strong>Pending Judge Profiles · <?=count($pending)?>
</strong>
</div>
<div class="card-body">
<div class="row g-3">
<?php foreach($pending as $r):?>
<div class="col-xl-6">
<div class="border rounded p-3 h-100">
<div class="d-flex gap-3">
<img src="<?=e($r['photo_url']?:url('public/assets/img/default-competitor.svg'))?>" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover">
<div>
<h2 class="h5 mb-1">
<?=e($r['full_name'])?>
</h2>
<div class="small text-muted">
<?=e(CountryFlagService::label($r['country']))?>
<?=!empty($r['city'])?' · '.e($r['city']):''?>
</div>
<div class="small">
<?=e(ucwords(str_replace(',',' / ',(string)$r['dance_styles'])))?> · <?=e(ucwords((string)$r['judge_role']))?>
</div>
<div class="small mt-2">
<strong>Email:</strong> <?=e((string)($r['email']?:'—'))?> · <strong>WhatsApp:</strong> <?=e((string)($r['whatsapp']?:'—'))?>
</div>
</div>
</div>
<form method="post" class="mt-3">
<input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>">
<input type="hidden" name="request_id" value="<?=(int)$r['id']?>">
<textarea class="form-control form-control-sm mb-2" name="admin_notes" rows="2" placeholder="Internal admin notes">
</textarea>
<div class="d-flex gap-2">
<button class="btn btn-success btn-sm" name="action" value="approve_request">Approve &amp; Create Judge</button>
<button class="btn btn-outline-danger btn-sm" name="action" value="reject_request" onclick="return confirm('Reject this judge profile request?')">Reject</button>
</div>
</form>
</div>
</div>
<?php endforeach;?>
</div>
</div>
</section>
<?php endif;?>
<section class="card shadow-sm mb-4">
<div class="card-header bg-white">
<strong>Add Judge Internally</strong>
</div>
<div class="card-body">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>">
<input type="hidden" name="action" value="create">
<div class="row g-2">
<div class="col-md-3">
<input name="full_name" class="form-control" placeholder="Judge full name *" required>
</div>
<div class="col-md-2">
<input name="country" class="form-control" placeholder="Country">
</div>
<div class="col-md-2">
<input name="instagram" class="form-control" placeholder="Instagram">
</div>
<div class="col-md-2">
<input name="email" type="email" class="form-control" placeholder="Email optional">
</div>
<div class="col-md-2">
<input name="whatsapp" class="form-control" placeholder="WhatsApp optional">
</div>
<div class="col-md-1 d-grid">
<button class="btn btn-dark">Add</button>
</div>
</div>
</form>
</div>
</section>
<section class="card shadow-sm">
<div class="table-responsive">
<table class="table align-middle mb-0" data-mobile-cards>
<thead>
<tr>
<th>Judge</th>
<th>Location</th>
<th>Dance / Role</th>
<th>Private Contact</th>
<th>Instagram</th>
<th>Status</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $j):?>
<tr>
<td>
<div class="d-flex gap-2 align-items-center">
<img src="<?=e($j['photo_url']?:url('public/assets/img/default-competitor.svg'))?>" alt="" style="width:46px;height:46px;border-radius:50%;object-fit:cover">
<div>
<strong>
<?=e(CountryFlagService::emoji($j['country_code']?:$j['country']))?>
<?=e($j['display_name']?:$j['full_name'])?>
</strong>
<div class="small text-muted">
<?=e($j['judge_code'])?> · <?=e($j['full_name'])?>
</div>
</div>
</div>
</td>
<td>
<?=e(CountryFlagService::label($j['country']))?>
<?=!empty($j['city'])?'<div class="small text-muted">'.e($j['city']).'</div>':''?>
</td>
<td>
<?=e(ucwords(str_replace(',',' / ',(string)($j['dance_styles']?:'Not set'))))?>
<div class="small text-muted">
<?=e(ucwords((string)$j['judge_role']))?>
</div>
</td>
<td>
<div class="small">
<strong>Email:</strong> <?=e((string)($j['email']?:'—'))?>
</div>
<div class="small">
<strong>Phone:</strong> <?=e((string)($j['phone']?:'—'))?>
</div>
<div class="small">
<strong>WhatsApp:</strong> <?=e((string)($j['whatsapp']?:'—'))?>
</div>
</td>
<td>
<?=e((string)($j['instagram']?'@'.$j['instagram']:'—'))?>
</td>
<td>
<span class="badge text-bg-<?=$j['status']==='active'?'success':'secondary'?>">
<?=e(ucfirst($j['status']))?>
</span>
</td>
<td>
<?php if($canEdit): ?>
<div class="d-flex flex-wrap gap-1">
<a class="btn btn-sm btn-primary" href="edit.php?id=<?=(int)$j['id']?>">Edit Profile &amp; Photo</a>
<form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="generate_profile_update_link"><input type="hidden" name="judge_id" value="<?=(int)$j['id']?>"><button class="btn btn-sm btn-outline-primary">Create 6-hour Update Link</button></form>
<?php if(!empty($j['photo_url'])): ?>
<a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?=(int)$j['id']?>#judge-photo">Edit &amp; Adjust Photo</a>
<?php endif; ?>
</div>
<?php else: ?>
<span class="text-muted small">View only</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
