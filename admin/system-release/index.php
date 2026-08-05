<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DeploymentPipelineService;
use App\Services\ReleaseManagerService;

if(!ReleaseManagerService::isReleaseManagerAvailable()){
    http_response_code(404);
    exit('Not found.');
}

Auth::requireSuperAdmin();
$pdo=Database::connection();
$message='';
$error='';
$userId=(int)(Auth::user()['id']??0);
$recoveredJobs=DeploymentPipelineService::recoverStaleJobs($pdo);

if(isset($_GET['status'])){
    DeploymentPipelineService::recoverStaleJobs($pdo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $statusJobs=$pdo->query("SELECT j.id,j.status,j.target_environment,j.output,r.version
        FROM bdc_deployment_jobs j JOIN bdc_release_candidates r ON r.id=j.release_id
        ORDER BY j.id DESC LIMIT 15")->fetchAll();
    echo json_encode([
        'active'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_deployment_jobs WHERE status IN ('queued','running')")->fetchColumn(),
        'jobs'=>$statusJobs,
    ],JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Your session expired. Please refresh and try again.');
        $action=(string)($_POST['action']??'');
        if($action==='discover'){
            DeploymentPipelineService::discover($pdo);
            $message='Available releases have been refreshed.';
        }elseif($action==='deploy_staging'){
            $jobId=DeploymentPipelineService::queue($pdo,(int)($_POST['release_id']??0),$action,$userId);
            DeploymentPipelineService::startWorker($pdo,$jobId);
            $message='Staging deployment started. This page will update automatically.';
        }elseif($action==='deploy_production'){
            $preflight=ReleaseManagerService::health($pdo);
            if(count(array_filter($preflight,fn($check)=>(bool)$check['status']))!==count($preflight)){
                throw new RuntimeException('Production deployment is blocked until every system check is ready.');
            }
            $releaseId=(int)($_POST['release_id']??0);
            $statusStmt=$pdo->prepare('SELECT status FROM bdc_release_candidates WHERE id=:id');
            $statusStmt->execute(['id'=>$releaseId]);
            if($statusStmt->fetchColumn()==='passed'){
                DeploymentPipelineService::queue($pdo,$releaseId,'approve',$userId);
            }
            $jobId=DeploymentPipelineService::queue($pdo,$releaseId,$action,$userId);
            DeploymentPipelineService::startWorker($pdo,$jobId);
            $message='Production deployment started. This page will update automatically.';
        }elseif($action==='validate_production'){
            $validated=DeploymentPipelineService::validateProduction($pdo,(int)($_POST['release_id']??0));
            $message='Production deployment validated successfully: '.$validated['version'].' at commit '.substr($validated['commit_sha'],0,12).'.';
        }elseif($action==='rollback_production'){
            $rolledBack=DeploymentPipelineService::rollbackProduction($pdo,(int)($_POST['deployment_job_id']??0),$userId);
            $message='Production rolled back successfully to '.$rolledBack['version'].' at commit '.substr($rolledBack['commit_sha'],0,12).'.';
        }elseif($action==='refresh_status'){
            $started=DeploymentPipelineService::startWorker($pdo);
            if($started>0){
                $message='Waiting deployment restarted. This page will update automatically.';
            }else{
                $message='Deployment status refreshed. No waiting job was found.';
            }
        }elseif($action==='health_check'){
            $checks=ReleaseManagerService::health($pdo);
            $message=count(array_filter($checks,fn($c)=>$c['status']))===count($checks)
                ?'Everything is ready for deployment.'
                :'System check completed. One or more items need attention.';
        }else{
            throw new RuntimeException('Please choose a valid action.');
        }
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}

$settings=DeploymentPipelineService::settings();
$current=ReleaseManagerService::installedVersion(dirname(__DIR__,2))??ReleaseManagerService::versionInfo();
$latestSourceSha='';
try{$latestSourceSha=DeploymentPipelineService::latestSourceSha();}catch(Throwable){}

/*
 * A direct CLI deployment bypasses the queue worker. Reconcile the database
 * only when the installed version matches the exact latest release candidate.
 */
if($latestSourceSha!==''&&!empty($current['version'])){
    $reconcileStmt=$pdo->prepare('SELECT id,version,status FROM bdc_release_candidates WHERE commit_sha=:sha LIMIT 1');
    $reconcileStmt->execute(['sha'=>$latestSourceSha]);
    $installedRelease=$reconcileStmt->fetch();
    if($installedRelease
        &&hash_equals((string)$installedRelease['version'],(string)$current['version'])
        &&in_array((string)$installedRelease['status'],['new','failed','queued','testing'],true)
    ){
        $pdo->beginTransaction();
        try{
            $pdo->prepare("UPDATE bdc_deployment_jobs
                SET status='failed',completed_at=NOW(),
                    output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),
                    'Superseded by a verified direct CLI Staging deployment.')
                WHERE target_environment='staging' AND status IN ('queued','running') AND release_id<>:id")
                ->execute(['id'=>$installedRelease['id']]);
            $pdo->prepare("UPDATE bdc_release_candidates
                SET status='failed'
                WHERE status IN ('queued','testing') AND id<>:id")
                ->execute(['id'=>$installedRelease['id']]);
            $pdo->prepare("UPDATE bdc_deployment_jobs
                SET status='success',completed_at=NOW(),
                    output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),
                    'Direct CLI deployment detected and reconciled from the installed Staging version.')
                WHERE release_id=:id AND target_environment='staging' AND status IN ('queued','running')")
                ->execute(['id'=>$installedRelease['id']]);
            $pdo->prepare("UPDATE bdc_release_candidates
                SET status='passed',staging_tested_sha=commit_sha,staged_at=COALESCE(staged_at,NOW()),passed_at=NOW()
                WHERE id=:id")
                ->execute(['id'=>$installedRelease['id']]);
            $pdo->commit();
            $message=$message?:'Direct Staging deployment detected. Release Manager records were synchronized.';
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $error=$error?:'The installed release is healthy, but its deployment history could not be synchronized: '.$e->getMessage();
        }
    }
}
$releaseStmt=$pdo->prepare('SELECT * FROM bdc_release_candidates ORDER BY (commit_sha=:latest_sha) DESC,discovered_at DESC,id DESC LIMIT 8');
$releaseStmt->execute(['latest_sha'=>$latestSourceSha]);
$releases=$releaseStmt->fetchAll();
$jobs=$pdo->query('SELECT j.*,r.version,r.subject FROM bdc_deployment_jobs j JOIN bdc_release_candidates r ON r.id=j.release_id ORDER BY j.id DESC LIMIT 15')->fetchAll();
$activeJobs=(int)$pdo->query("SELECT COUNT(*) FROM bdc_deployment_jobs WHERE status IN ('queued','running')")->fetchColumn();
$latestProductionJob=null;
foreach($jobs as $candidateJob){
    if((string)$candidateJob['action']==='deploy_production'
        &&(string)$candidateJob['target_environment']==='production'
        &&(string)$candidateJob['status']==='success'){
        $latestProductionJob=$candidateJob;
        break;
    }
}
$productionValidatedReleaseIds=[];
foreach($jobs as $job){
    if((string)$job['target_environment']==='production'
        &&(string)$job['status']==='success'
        &&str_contains((string)($job['output']??''),'[PRODUCTION_VALIDATED]')
    ){
        $productionValidatedReleaseIds[(int)$job['release_id']]=true;
    }
}
$checks=ReleaseManagerService::health($pdo);
$csrf=Csrf::token();
$production=ReleaseManagerService::installedVersion($settings['production_path']);
if(!$production){
    $production=$pdo->query("SELECT r.version,r.commit_sha,j.completed_at AS deployed_at FROM bdc_deployment_jobs j JOIN bdc_release_candidates r ON r.id=j.release_id WHERE j.target_environment='production' AND j.status='success' ORDER BY j.completed_at DESC,j.id DESC LIMIT 1")->fetch()?:null;
}
$allHealthy=count(array_filter($checks,fn($c)=>$c['status']))===count($checks);

function statusClass(string $status):string
{
    return match($status){
        'production','passed','success'=>'success',
        'approved'=>'primary',
        'rolled_back','cancelled'=>'secondary',
        'failed'=>'danger',
        'testing','running'=>'warning',
        'queued'=>'info',
        default=>'secondary'
    };
}

function friendlyStatus(string $status):string
{
    return match($status){
        'new'=>'Available',
        'passed'=>'Tested on Staging',
        'approved'=>'Ready for Production',
        'production'=>'Live in Production',
        'rolled_back'=>'Rolled Back',
        'cancelled'=>'Cancelled',
        'failed'=>'Needs Retry',
        'testing','running'=>'Deploying',
        'queued'=>'Waiting',
        default=>ucwords(str_replace('_',' ',$status))
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
body{background:#f4f6f9;color:#172033}.navbar{background:#111827}.card{border:0;border-radius:18px}.release-card{transition:.18s ease}.release-card:hover{transform:translateY(-2px)}.version{font-size:2rem;font-weight:800}.sha{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.section-title{font-weight:800}.soft-success{background:#eaf8ef;color:#176b36}.soft-primary{background:#eaf1ff;color:#174ea6}.btn{border-radius:10px;font-weight:700}.btn-production{background:#111827;color:#fff}.btn-production:hover{background:#000;color:#fff}.status-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
</style>
</head>
<body>
<nav class="navbar navbar-dark"><div class="container py-2"><a class="navbar-brand fw-bold" href="<?=e(url('admin/'))?>">BDC Release Manager</a><span class="text-white-50">Simple and safe updates</span></div></nav>
<main class="container py-4 py-lg-5">
<?php if($message):?><div class="alert alert-success shadow-sm border-0"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger shadow-sm border-0"><strong>Deployment could not be completed.</strong><br><?=e($error)?></div><?php endif;?>
<?php if($recoveredJobs>0):?><div class="alert alert-warning shadow-sm border-0">An old deployment that stopped responding was cleared automatically. You can deploy again now.</div><?php endif;?>
<?php if(!$settings['enabled']):?><div class="alert alert-warning"><strong>Release Manager is not ready.</strong> Deployment settings must be enabled first.</div><?php endif;?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
<div><h1 class="section-title h3 mb-1">Current Releases</h1><p class="text-muted mb-0">See what is installed before choosing an update.</p></div>
<div class="d-flex flex-wrap gap-2"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="refresh_status"><button class="btn btn-primary" <?=$settings['enabled']?'':'disabled'?>>Refresh Status</button></form><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="discover"><button class="btn btn-outline-primary" <?=$settings['enabled']?'':'disabled'?>>Refresh Available Releases</button></form></div>
</div>

<div class="row g-4 mb-5">
<div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-body p-4">
<div class="text-uppercase small fw-bold text-muted mb-2">Current Staging Release</div>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2"><div class="version"><?=e((string)($current['version']??'Unknown'))?></div><span class="badge rounded-pill soft-primary px-3 py-2">Staging</span></div>
<p class="text-muted mb-0">This is the version currently running on this Staging dashboard.</p>
<?php if(!empty($current['commit_sha'])):?><div class="small text-muted mt-2">Commit <span class="sha"><?=e(substr((string)$current['commit_sha'],0,12))?></span></div><?php endif;?>
</div></div></div>
<div class="col-lg-5"><div class="card shadow-sm h-100"><div class="card-body p-4">
<div class="text-uppercase small fw-bold text-muted mb-2">Current Production Release</div>
<?php if($production):?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2"><div class="version"><?=e($production['version'])?></div><span class="badge rounded-pill soft-success px-3 py-2">Live</span></div>
<p class="text-muted mb-0">Deployed <?=e((string)($production['deployed_at']??'time not recorded'))?></p>
<?php if(!empty($production['commit_sha'])):?><div class="small text-muted mt-2">Commit <span class="sha"><?=e(substr((string)$production['commit_sha'],0,12))?></span></div><?php endif;?>
<?php else:?><div class="h4 mb-2">Not recorded yet</div><p class="text-muted mb-0">The first successful Production deployment will appear here.</p><?php endif;?>
</div></div></div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
<div><h2 class="section-title h3 mb-1">Available Releases</h2><p class="text-muted mb-0">Deploy to Staging first. After testing, the Production button becomes available.</p></div>
<div class="small fw-bold <?=$allHealthy?'text-success':'text-danger'?>"><span class="status-dot <?=$allHealthy?'bg-success':'bg-danger'?> me-2"></span><?=$allHealthy?'System ready':'System needs attention'?></div>
</div>

<?php if(!$releases):?>
<div class="card shadow-sm mb-5"><div class="card-body p-4 text-center text-muted">No releases found. Click “Refresh Available Releases”.</div></div>
<?php else:?>
<div class="row g-3 mb-5">
<?php foreach($releases as $release):$status=(string)$release['status'];$isLatest=$latestSourceSha!==''&&hash_equals($latestSourceSha,(string)$release['commit_sha']);?>
<div class="col-12"><div class="card release-card shadow-sm"><div class="card-body p-4">
<div class="row align-items-center g-3">
<div class="col-lg-7">
<div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h3 class="h5 mb-0"><?=e($release['version'])?></h3><?php if($isLatest):?><span class="badge text-bg-primary">Latest, recommended</span><?php endif;?><span class="badge text-bg-<?=statusClass($status)?>"><?=e(friendlyStatus($status))?></span></div>
<div class="text-muted mb-2"><?=e($release['subject'])?></div>
<div class="small text-muted">Release code <span class="sha"><?=e(substr($release['commit_sha'],0,12))?></span></div>
</div>
<div class="col-lg-5"><div class="d-flex flex-wrap justify-content-lg-end gap-2">
<?php if($isLatest&&in_array($status,['new','failed','passed'],true)):?>
<form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="deploy_staging"><input type="hidden" name="release_id" value="<?=(int)$release['id']?>"><button class="btn btn-primary" <?=$settings['enabled']?'':'disabled'?>><?=$status==='passed'?'Redeploy to Staging':'Deploy to Staging'?></button></form>
<?php endif;?>
<?php if($isLatest&&in_array($status,['passed','approved'],true)):?>
<form method="post" onsubmit="return confirm('Deploy the exact Staging-tested release <?=e($release['version'])?> to LIVE Production? Production files and the database will be backed up first.')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="deploy_production"><input type="hidden" name="release_id" value="<?=(int)$release['id']?>"><button class="btn btn-production" <?=($settings['enabled']&&$allHealthy)?'':'disabled'?>>Deploy <?=e($release['version'])?> to Production</button></form>
<?php elseif(in_array($status,['queued','testing','running'],true)):?><span class="text-muted align-self-center">Deployment in progress…</span>
<?php elseif($status==='production'):?>
<form method="post" onsubmit="return confirm('Validate that the live Production version, commit and health check all match this release?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="validate_production"><input type="hidden" name="release_id" value="<?=(int)$release['id']?>"><button class="btn btn-outline-success" <?=$settings['enabled']?'':'disabled'?>>Validate Production Deployment</button></form>
<?php if(isset($productionValidatedReleaseIds[(int)$release['id']])):?><span class="text-success fw-bold align-self-center">✓ Production Validated</span><?php else:?><span class="text-success fw-bold align-self-center">✓ Live</span><?php endif;?>
<?php endif;?>
<?php if(!$isLatest&&!in_array($status,['queued','testing','running','production'],true)):?><span class="text-muted small align-self-center">Previous release</span><?php endif;?>
</div></div>
</div></div></div></div>
<?php endforeach;?>
</div>
<?php endif;?>

<div class="card shadow-sm mb-4"><div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 mb-1">Production Deployment Plan</h2><p class="text-muted mb-0">Only the exact release tested on Staging can be promoted.</p></div><span class="badge text-bg-<?=$allHealthy?'success':'danger'?> px-3 py-2"><?=$allHealthy?'Preflight ready':'Deployment blocked'?></span></div>
<div class="row g-3">
<div class="col-md-6"><div class="border rounded-3 p-3 h-100"><strong>Deploy to Production</strong><ul class="small text-muted mb-0 mt-2"><li>Application code from the tested commit</li><li>Database migrations included in that release</li><li>VERSION.json and the installed release manifest</li><li>Release Manager remains unavailable on Production</li></ul></div></div>
<div class="col-md-6"><div class="border rounded-3 p-3 h-100"><strong>Preserve and protect</strong><ul class="small text-muted mb-0 mt-2"><li>config/config.php and config/config.local.php</li><li>storage, uploads and public/results data</li><li>Full Production file and database backup first</li><li>Automatic file rollback if the health check fails</li></ul></div></div>
</div>
</div></div>

<?php if($latestProductionJob):?>
<div class="card shadow-sm mb-4 border border-danger-subtle"><div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
<div><h2 class="h5 mb-1">Production Recovery</h2><p class="text-muted mb-0">Restore the verified file backup created immediately before the latest Production deployment. Configuration, storage, uploads and published results remain protected. Database data is not rolled back.</p></div>
<form method="post" onsubmit="return confirm('ROLL BACK LIVE PRODUCTION to the release installed before deployment job #<?=(int)$latestProductionJob['id']?>? This restores application files only and keeps Production data.')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="rollback_production"><input type="hidden" name="deployment_job_id" value="<?=(int)$latestProductionJob['id']?>"><button class="btn btn-outline-danger" <?=$activeJobs===0?'':'disabled'?>>Rollback Latest Production Deployment</button></form>
</div></div></div>
<?php endif;?>

<div class="card shadow-sm mb-4"><div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h2 class="h5 mb-1">System Check</h2><p class="text-muted mb-0">A quick readiness check in plain language.</p></div><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="health_check"><button class="btn btn-outline-secondary">Check Again</button></form></div>
<div class="row g-2 mt-2"><?php foreach($checks as $check):?><div class="col-md-6 col-lg-4"><div class="border rounded-3 p-3"><span class="status-dot <?=$check['status']?'bg-success':'bg-danger'?> me-2"></span><strong><?=e($check['name'])?></strong><div class="small text-muted ms-4"><?=$check['status']?'Ready':'Needs attention'?></div></div></div><?php endforeach;?></div>
</div></div>

<div class="card shadow-sm"><div class="card-body p-4"><h2 class="h5 mb-3">Recent Activity</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Release</th><th>Destination</th><th>Result</th><th>Time</th><th>Details</th></tr></thead><tbody>
<?php foreach($jobs as $job):?><tr><td><strong><?=e($job['version'])?></strong></td><td><?=e(ucfirst($job['target_environment']))?></td><td><span class="badge text-bg-<?=statusClass($job['status'])?>"><?=e(friendlyStatus($job['status']))?></span></td><td><?=e($job['requested_at'])?></td><td><details><summary class="text-primary">View details</summary><pre class="small text-wrap mt-2 mb-0"><?=e($job['output'])?></pre></details></td></tr><?php endforeach;?>
<?php if(!$jobs):?><tr><td colspan="5" class="text-center text-muted py-4">No deployment activity yet.</td></tr><?php endif;?>
</tbody></table></div></div></div>
</main>
<script>
document.querySelectorAll('form').forEach(function(form){
  form.addEventListener('submit',function(){
    var action=form.querySelector('input[name="action"]');
    if(!action||!['deploy_staging','deploy_production','validate_production','rollback_production','refresh_status'].includes(action.value))return;
    var button=form.querySelector('button[type="submit"],button:not([type])');
    if(button){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent=action.value==='refresh_status'?'Refreshing…':(action.value==='validate_production'?'Validating Production…':(action.value==='rollback_production'?'Rolling Back Production…':'Starting Deployment…'));}
  });
});
<?php if($activeJobs>0):?>
(function pollDeployment(){
  window.setTimeout(function(){
    fetch('?status=1',{headers:{'Accept':'application/json'},cache:'no-store'})
      .then(function(response){if(!response.ok)throw new Error('Status request failed');return response.json();})
      .then(function(data){if(Number(data.active)===0){window.location.reload();return;}pollDeployment();})
      .catch(function(){pollDeployment();});
  },3000);
})();
<?php endif;?>
</script>
</body></html>
