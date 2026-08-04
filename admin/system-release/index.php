<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ReleaseManagerService;
use App\Services\SchemaUpdater;

Auth::requireSuperAdmin();
$pdo=Database::connection();


$message='';
$error='';
$userId=(int)(Auth::user()['id']??0);

try{
 ReleaseManagerService::recordCurrentRelease($userId?:null);
}catch(Throwable $e){
 $error=$e->getMessage();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'');

  if($action==='register_release'){
   $version=trim((string)($_POST['version']??''));
   $status=(string)($_POST['release_status']??'development');
   $notes=trim((string)($_POST['release_notes']??''));
   if(!preg_match('/^\d+\.\d+\.\d+(?:-(?:dev|rc)\d+)?$/',$version))throw new RuntimeException('Enter a valid version such as 2.1.0-dev1, 2.1.0-rc1 or 2.1.0.');
   if(!in_array($status,['development','testing','qa_approved','production_candidate','production','archived'],true))throw new RuntimeException('Invalid release status.');

   $stmt=$pdo->prepare("
    INSERT INTO bdc_releases(version,release_status,release_notes,created_by)
    VALUES(:version,:status,:notes,:user_id)
    ON DUPLICATE KEY UPDATE release_status=VALUES(release_status),release_notes=VALUES(release_notes),updated_at=NOW()
   ");
   $stmt->execute(['version'=>$version,'status'=>$status,'notes'=>$notes?:null,'user_id'=>$userId?:null]);
   $message='Release '.$version.' saved.';
  }elseif($action==='health_check'){
   $checks=ReleaseManagerService::health($pdo);
   $passed=count(array_filter($checks,fn($check)=>$check['status']))===count($checks);
   $pdo->prepare("INSERT INTO bdc_deployment_history(version,environment,action,status,performed_by,details) VALUES(:version,:environment,'health_check',:status,:user_id,:details)")
       ->execute([
        'version'=>ReleaseManagerService::VERSION,
        'environment'=>ReleaseManagerService::environment(),
        'status'=>$passed?'success':'failed',
        'user_id'=>$userId?:null,
        'details'=>json_encode($checks,JSON_UNESCAPED_SLASHES),
       ]);
   $message=$passed?'All health checks passed.':'Health check completed with warnings.';
  }else{
   throw new RuntimeException('Unknown action.');
  }
 }catch(Throwable $e){
  $error=$e->getMessage();
 }
}

$current=ReleaseManagerService::versionInfo();
$environment=ReleaseManagerService::environment();
$checks=ReleaseManagerService::health($pdo);
$releases=$pdo->query("SELECT * FROM bdc_releases ORDER BY created_at DESC,id DESC LIMIT 50")->fetchAll();
$history=$pdo->query("SELECT * FROM bdc_release_installations ORDER BY installed_at DESC,id DESC LIMIT 50")->fetchAll();
$csrf=Csrf::token();

function releaseStatusClass(string $status):string{
 return match($status){
  'production'=>'success',
  'production_candidate','qa_approved'=>'primary',
  'testing'=>'warning',
  'archived'=>'secondary',
  default=>'dark',
 };
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Release Manager | BDC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f3f5f7}.environment-banner{font-weight:800;letter-spacing:.08em}.card{border:0;border-radius:14px}.health-dot{width:12px;height:12px;border-radius:50%;display:inline-block}.release-version{font-size:2rem;font-weight:800}
</style>
</head>
<body>
<div class="environment-banner text-center py-2 <?=$environment==='staging'?'bg-warning text-dark':'bg-success text-white'?>">
 <?=e(ReleaseManagerService::environmentLabel())?> ENVIRONMENT
</div>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><span class="text-white">Release Manager</span></div></nav>

<main class="container py-4">
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>

<div class="row g-4 mb-4">
 <div class="col-lg-7">
  <div class="card shadow-sm h-100"><div class="card-body">
   <div class="text-muted text-uppercase small">Current Installed Release</div>
   <div class="release-version"><?=e((string)($current['version']??ReleaseManagerService::VERSION))?></div>
   <div class="mt-2"><span class="badge text-bg-<?=$environment==='staging'?'warning':'success'?>"><?=e(ReleaseManagerService::environmentLabel())?></span></div>
   <hr>
   <div><strong>Status:</strong> <?=e((string)($current['status']??'production-ready'))?></div>
   <div><strong>Release date:</strong> <?=e((string)($current['release_date']??'2026-08-03'))?></div>
   <div><strong>Base path:</strong> <?=e((string)\App\Core\Config::get('app.base_path',''))?></div>
   <div><strong>Database:</strong> <?=e((string)\App\Core\Config::get('database.name',''))?></div>
  </div></div>
 </div>
 <div class="col-lg-5">
  <div class="card shadow-sm h-100"><div class="card-body">
   <div class="d-flex justify-content-between align-items-center"><h2 class="h5">Environment Health</h2>
    <form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="health_check"><button class="btn btn-sm btn-outline-dark">Run &amp; Record</button></form>
   </div>
   <?php foreach($checks as $check):?>
    <div class="d-flex gap-2 align-items-start py-2 border-bottom">
     <span class="health-dot mt-1 <?=$check['status']?'bg-success':'bg-danger'?>"></span>
     <div><strong><?=e($check['name'])?></strong><div class="small text-muted text-break"><?=e($check['detail'])?></div></div>
    </div>
   <?php endforeach;?>
  </div></div>
 </div>
</div>

<div class="card shadow-sm mb-4"><div class="card-body">
 <h2 class="h5">Register or Update Release</h2>
 <p class="text-muted">Use this to record development, staging and production candidates. This release does not deploy files automatically.</p>
 <form method="post" class="row g-3">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
  <input type="hidden" name="action" value="register_release">
  <div class="col-md-3"><label class="form-label">Version</label><input class="form-control" name="version" placeholder="2.1.0-dev1" required></div>
  <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="release_status"><?php foreach(['development','testing','qa_approved','production_candidate','production','archived'] as $status):?><option value="<?=$status?>"><?=e(ucwords(str_replace('_',' ',$status)))?></option><?php endforeach;?></select></div>
  <div class="col-md-6"><label class="form-label">Release notes</label><input class="form-control" name="release_notes" placeholder="What changed in this build"></div>
  <div class="col-12"><button class="btn btn-primary">Save Release</button></div>
 </form>
</div></div>

<div class="card shadow-sm mb-4"><div class="card-body">
 <h2 class="h5">Release Pipeline</h2>
 <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Version</th><th>Status</th><th>Notes</th><th>Created</th></tr></thead><tbody>
 <?php foreach($releases as $release):?><tr>
  <td><strong><?=e($release['version'])?></strong></td>
  <td><span class="badge text-bg-<?=releaseStatusClass($release['release_status'])?>"><?=e(ucwords(str_replace('_',' ',$release['release_status'])))?></span></td>
  <td><?=e((string)$release['release_notes'])?></td>
  <td><?=e((string)$release['created_at'])?></td>
 </tr><?php endforeach;?>
 <?php if(!$releases):?><tr><td colspan="4" class="text-muted">No planned releases recorded yet.</td></tr><?php endif;?>
 </tbody></table></div>
</div></div>

<div class="card shadow-sm"><div class="card-body">
 <h2 class="h5">Installation History</h2>
 <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Version</th><th>Environment</th><th>Status</th><th>Installed</th></tr></thead><tbody>
 <?php foreach($history as $item):?><tr>
  <td><?=e($item['version'])?></td><td><?=e(strtoupper($item['environment']))?></td><td><?=e($item['status'])?></td><td><?=e((string)$item['installed_at'])?></td>
 </tr><?php endforeach;?>
 </tbody></table></div>
</div></div>
</main>
</body>
</html>
