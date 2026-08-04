<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DeploymentPipelineService;
use App\Services\ReleaseManagerService;

Auth::requireSuperAdmin();
$pdo=Database::connection();
$message='';$error='';$userId=(int)(Auth::user()['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');
        if($action==='discover'){
            DeploymentPipelineService::discover($pdo);
            $message='GitHub develop checked. The release list is current.';
        }elseif($action==='deploy_staging'){
            $jobId=DeploymentPipelineService::queue($pdo,(int)($_POST['release_id']??0),$action,$userId);
            $message='Deployment job #'.$jobId.' queued for Staging. It will run within one minute.';
        }elseif($action==='health_check'){
            $checks=ReleaseManagerService::health($pdo);
            $message=count(array_filter($checks,fn($c)=>$c['status']))===count($checks)?'All dashboard health checks passed.':'Health check completed with warnings.';
        }else throw new RuntimeException('Unknown action.');
    }catch(Throwable $e){$error=$e->getMessage();}
}

$settings=DeploymentPipelineService::settings();
$releases=$pdo->query('SELECT * FROM bdc_release_candidates ORDER BY discovered_at DESC,id DESC LIMIT 50')->fetchAll();
$jobs=$pdo->query('SELECT j.*,r.version,r.subject FROM bdc_deployment_jobs j JOIN bdc_release_candidates r ON r.id=j.release_id ORDER BY j.id DESC LIMIT 30')->fetchAll();
$checks=ReleaseManagerService::health($pdo);$csrf=Csrf::token();
$selectedReleaseId=(int)($_GET['release_id']??$_POST['release_id']??($releases[0]['id']??0));
$selectedRelease=null;
foreach($releases as $release){
    if((int)$release['id']===$selectedReleaseId){$selectedRelease=$release;break;}
}
if($selectedRelease===null&&$releases){$selectedRelease=$releases[0];$selectedReleaseId=(int)$selectedRelease['id'];}

function statusClass(string $status):string{return match($status){'production','passed','success'=>'success','approved'=>'primary','failed'=>'danger','testing','running'=>'warning','queued'=>'info','rolled_back'=>'secondary',default=>'dark'};}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Deployment Dashboard | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f3f5f7}.card{border:0;border-radius:14px}.sha{font-family:monospace}.pipeline{font-weight:700}.health-dot{width:11px;height:11px;border-radius:50%;display:inline-block}</style></head><body>
<div class="text-center py-2 bg-dark text-white fw-bold">SECURE DEVELOPMENT DEPLOYMENT DASHBOARD</div>
<nav class="navbar navbar-dark bg-primary"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><span class="text-white">Exact Commit Release Control</span></div></nav>
<main class="container py-4">
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if(!$settings['enabled']):?><div class="alert alert-warning"><strong>Dashboard not enabled.</strong> Add the deployment settings from <code>config/config.example.php</code> to this environment's protected <code>config/config.php</code>.</div><?php endif;?>
<div class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h1 class="h4 mb-1">Development Release Dashboard</h1><div class="pipeline text-muted">Choose exact release → Deploy to Staging → Test</div></div><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="discover"><button class="btn btn-primary" <?=$settings['enabled']?'':'disabled'?>>Refresh Release List</button></form></div></div></div>
<div class="alert alert-info"><strong>Staging only.</strong> Production deployment is not available from this dashboard yet.</div>
<div class="row g-4 mb-4"><div class="col-lg-8"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h5">Select a Release</h2>
<?php if($releases):?>
<form method="get" class="mb-4"><label class="form-label" for="release_id">Development releases</label><select class="form-select" id="release_id" name="release_id" size="10" onchange="this.form.submit()">
<?php foreach($releases as $r):?><option value="<?=(int)$r['id']?>" <?=$selectedReleaseId===(int)$r['id']?'selected':''?>><?=e($r['version'].' | '.substr($r['commit_sha'],0,12).' | '.ucwords(str_replace('_',' ',$r['status'])).' | '.$r['subject'])?></option><?php endforeach;?>
</select><noscript><button class="btn btn-outline-primary mt-2">View Selected Release</button></noscript></form>
<?php if($selectedRelease):?><div class="border rounded-3 p-3 bg-light"><div class="d-flex flex-wrap justify-content-between gap-2"><div><div class="text-muted small">SELECTED RELEASE</div><h3 class="h5 mb-1"><?=e($selectedRelease['version'])?></h3></div><span class="badge text-bg-<?=statusClass($selectedRelease['status'])?> align-self-start"><?=e(ucwords(str_replace('_',' ',$selectedRelease['status'])))?></span></div><dl class="row mt-3 mb-3"><dt class="col-sm-3">Commit</dt><dd class="col-sm-9 sha text-break"><?=e($selectedRelease['commit_sha'])?></dd><dt class="col-sm-3">Change</dt><dd class="col-sm-9"><?=e($selectedRelease['subject'])?></dd><dt class="col-sm-3">Discovered</dt><dd class="col-sm-9"><?=e($selectedRelease['discovered_at'])?></dd></dl>
<?php if(in_array($selectedRelease['status'],['new','failed','passed'],true)):?><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="deploy_staging"><input type="hidden" name="release_id" value="<?=(int)$selectedRelease['id']?>"><button class="btn btn-primary" <?=$settings['enabled']?'':'disabled'?>><?=($selectedRelease['status']==='passed')?'Retest on Staging':'Deploy Selected Release to Staging'?></button></form><?php else:?><div class="text-muted">This release is currently <?=e(str_replace('_',' ',$selectedRelease['status']))?> and cannot be queued again.</div><?php endif;?></div><?php endif;?>
<?php else:?><p class="text-muted mb-0">Click Refresh Release List to load Development releases.</p><?php endif;?></div></div></div>
<div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h5">Staging Safety</h2><ul class="mb-0"><li>Super Admin and CSRF protected</li><li>One deployment job at a time</li><li>Exact 40 character commit SHA</li><li>Configuration, uploads, storage and results preserved</li><li>Health check required for a passing release</li><li>No Production deployment control</li></ul><hr><?php foreach($checks as $c):?><div class="d-flex gap-2 py-1"><span class="health-dot mt-1 <?=$c['status']?'bg-success':'bg-danger'?>"></span><small><?=e($c['name'])?></small></div><?php endforeach;?></div></div></div></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5">Deployment History</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Job</th><th>Release</th><th>Commit</th><th>Target</th><th>Status</th><th>Requested</th><th>Log</th></tr></thead><tbody><?php foreach($jobs as $j):?><tr><td>#<?=(int)$j['id']?></td><td><?=e($j['version'])?></td><td class="sha"><?=e(substr($j['commit_sha'],0,12))?></td><td><?=e(strtoupper($j['target_environment']))?></td><td><span class="badge text-bg-<?=statusClass($j['status'])?>"><?=e($j['status'])?></span></td><td><?=e($j['requested_at'])?></td><td><details><summary>View</summary><pre class="small text-wrap"><?=e($j['output'])?></pre></details></td></tr><?php endforeach;?></tbody></table></div></div></div>
</main></body></html>
