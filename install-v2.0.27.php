<?php
declare(strict_types=1);

/**
 * BDC v2.0.27
 * Fixes missing Super Admin approval controls after Final scores are submitted.
 */

$root=__DIR__;
$publishFile=$root.'/admin/scoring/publish.php';
$indexFile=$root.'/admin/scoring/index.php';

function v227Fail(string $message): never{
 http_response_code(500);
 echo '<!doctype html><meta charset="utf-8"><h1>BDC v2.0.27 installation failed</h1><pre>'
  .htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</pre>';
 exit;
}

function v227Backup(string $root,string $file,string $label):string{
 $dir=$root.'/storage/patch-backups';
 if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)){
  v227Fail('Unable to create storage/patch-backups.');
 }
 $backup=$dir.'/'.$label.'-before-v227-'.date('Ymd-His').'.php';
 if(!copy($file,$backup))v227Fail('Unable to back up '.$file);
 return $backup;
}

if(!is_file($publishFile))v227Fail('admin/scoring/publish.php was not found.');
if(!is_file($indexFile))v227Fail('admin/scoring/index.php was not found.');

$publish=file_get_contents($publishFile);
$index=file_get_contents($indexFile);
if($publish===false||$index===false)v227Fail('Unable to read scoring files.');

if(str_contains($publish,'BDC_V227_SUPER_ADMIN_FIX')){
 echo '<h1>BDC v2.0.27 is already installed</h1>';
 exit;
}

$publishBackup=v227Backup($root,$publishFile,'publish');
$indexBackup=v227Backup($root,$indexFile,'scoring-index');

$helper=<<<'PHP'

/* BDC_V227_SUPER_ADMIN_FIX */
function bdcV227IsSuperAdmin():bool{
 try{
  if(method_exists(\App\Core\Auth::class,'isSuperAdmin')&&\App\Core\Auth::isSuperAdmin())return true;
 }catch(\Throwable $e){}

 $user=\App\Core\Auth::user();
 if(!is_array($user))return false;

 $role=strtolower(trim((string)($user['role']??$user['user_role']??$user['type']??'')));
 $role=str_replace(['-',' '],'_',$role);

 if(in_array($role,['super_admin','superadmin','system_admin','owner','root'],true))return true;

 foreach(['is_super_admin','super_admin','is_owner','is_root'] as $flag){
  if(array_key_exists($flag,$user)&&in_array($user[$flag],[1,'1',true,'true','yes','YES'],true)){
   return true;
  }
 }

 return false;
}
PHP;

if(!preg_match('/SchemaUpdater::run\(\$pdo\);\s*/',$publish)){
 v227Fail('Could not find SchemaUpdater::run($pdo) in publish.php.');
}

$publish=preg_replace(
 '/SchemaUpdater::run\(\$pdo\);\s*/',
 "SchemaUpdater::run(\$pdo);\n".$helper."\n",
 $publish,
 1
);

$replaced=0;
$publish=preg_replace(
 '/\$isSuperAdmin\s*=\s*Auth::isSuperAdmin\(\)\s*;/',
 '$isSuperAdmin=bdcV227IsSuperAdmin();',
 $publish,
 1,
 $replaced
);

if($replaced===0){
 $publish=preg_replace(
  '/\$userId\s*=\s*\(int\)\(Auth::user\(\)\[\'id\'\]\?\?0\);/',
  "$0\n\$isSuperAdmin=bdcV227IsSuperAdmin();",
  $publish,
  1,
  $replaced
 );
}

if($replaced===0)v227Fail('Unable to install the Super Admin role resolver.');

$panel=<<<'PHP'

<?php if(($publication['status']??'')==='pending_approval' && $isSuperAdmin):?>
<section class="card review-card approval-card mb-4" id="v227-super-admin-panel">
 <div class="card-body">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
   <div>
    <h2 class="h5 text-success mb-1">Super Admin Approval Required</h2>
    <p class="mb-0">The competition is submitted. No points or repository result have been created yet.</p>
   </div>
   <span class="badge text-bg-warning fs-6">Pending Approval</span>
  </div>

  <div class="d-flex flex-wrap gap-2 mt-3">
   <a class="btn btn-outline-primary" target="_blank" href="final-result.php?round_id=<?=$roundId?>">
    Review Final Scores
   </a>
   <a class="btn btn-outline-dark" target="_blank" href="publication-report.php?round_id=<?=$roundId?>">
    Review Points Report
   </a>
   <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#approvalModal">
    Approve, Publish &amp; Update Points
   </button>
  </div>

  <div class="border-top mt-3 pt-3">
   <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#v227-reject-panel">
    Reject &amp; Reopen Scoring
   </button>

   <div class="collapse mt-3" id="v227-reject-panel">
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="reject_approval">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <label class="form-label">Rejection reason</label>
     <textarea class="form-control mb-2" name="rejection_reason" required></textarea>
     <button class="btn btn-danger">Confirm Rejection and Reopen</button>
    </form>
   </div>
  </div>
 </div>
</section>
<?php endif;?>
PHP;

if(stripos($publish,'<div class="modal fade" id="submitModal"')!==false){
 $publish=str_ireplace(
  '<div class="modal fade" id="submitModal"',
  $panel."\n<div class=\"modal fade\" id=\"submitModal\"",
  $publish
 );
}elseif(stripos($publish,'</main>')!==false){
 $publish=str_ireplace('</main>',$panel."\n</main>",$publish);
}else{
 v227Fail('Could not insert the approval panel into publish.php.');
}

$dedupe=<<<'HTML'
<script>
document.addEventListener('DOMContentLoaded',function(){
 const fallback=document.getElementById('v227-super-admin-panel');
 if(!fallback)return;

 const otherApprovalButtons=[...document.querySelectorAll('button')].filter(function(button){
  return !fallback.contains(button)
   && button.textContent.trim().includes('Approve, Publish');
 });

 if(otherApprovalButtons.length)fallback.remove();
});
</script>
HTML;

if(stripos($publish,'</body>')!==false){
 $publish=preg_replace('/<\/body>/i',$dedupe."\n</body>",$publish,1);
}else{
 $publish.=$dedupe;
}

if(file_put_contents($publishFile,$publish)===false){
 v227Fail('Unable to write publish.php.');
}

/*
 * Dashboard repair:
 * - Scores Submitted: clearly route to approval page.
 * - Pending Approval: show the same approval-page shortcut.
 */
$dashboardBanner=<<<'PHP'

<!-- BDC_V227_APPROVAL_SHORTCUT -->
<?php if($round && in_array((string)($round['status']??''),['scores_submitted','pending_approval'],true)):?>
<div class="alert <?=($round['status']==='pending_approval'?'alert-warning':'alert-info')?> d-flex justify-content-between align-items-center gap-3 flex-wrap">
 <div>
  <strong>
   <?=($round['status']==='pending_approval'
      ? 'Pending Super Admin Approval'
      : 'Final Scores Submitted')?>
  </strong><br>
  <span class="small">
   <?=($round['status']==='pending_approval'
      ? 'Open the approval screen to approve, publish and update BDC points.'
      : 'Open the approval screen to submit this competition for Super Admin approval.')?>
  </span>
 </div>
 <a class="btn <?=($round['status']==='pending_approval'?'btn-warning':'btn-primary')?>" href="publish.php?round_id=<?=$roundId?>">
  <?=($round['status']==='pending_approval'
     ? 'Open Super Admin Approval'
     : 'Submit for Super Admin Approval')?>
 </a>
</div>
<?php endif;?>
PHP;

if(!str_contains($index,'BDC_V227_APPROVAL_SHORTCUT')){
 if(preg_match('/(<main\b[^>]*>)/i',$index)){
  $index=preg_replace('/(<main\b[^>]*>)/i',"$1\n".$dashboardBanner,$index,1);
 }elseif(preg_match('/(<body\b[^>]*>)/i',$index)){
  $index=preg_replace('/(<body\b[^>]*>)/i',"$1\n".$dashboardBanner,$index,1);
 }else{
  $index.=$dashboardBanner;
 }
}

if(file_put_contents($indexFile,$index)===false){
 v227Fail('Unable to write scoring dashboard.');
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>BDC v2.0.27 Installed</title>';
echo '<style>body{font-family:Arial;padding:40px;background:#f5f6f8}.box{max-width:800px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 5px 20px #0001}.ok{color:#087830}</style>';
echo '</head><body><div class="box">';
echo '<h1 class="ok">BDC v2.0.27 installed</h1>';
echo '<p>The missing Super Admin approval controls are repaired.</p>';
echo '<ul>';
echo '<li>Scores Submitted now shows Submit for Super Admin Approval.</li>';
echo '<li>Pending Approval now shows Open Super Admin Approval.</li>';
echo '<li>Super Admin sees Approve, Publish &amp; Update Points.</li>';
echo '<li>Super Admin also sees Reject &amp; Reopen Scoring.</li>';
echo '<li>Common Super Admin role names and flags are recognized.</li>';
echo '</ul>';
echo '<p><strong>Publish backup:</strong> '.htmlspecialchars($publishBackup,ENT_QUOTES,'UTF-8').'</p>';
echo '<p><strong>Dashboard backup:</strong> '.htmlspecialchars($indexBackup,ENT_QUOTES,'UTF-8').'</p>';
echo '<p><a href="admin/scoring/">Open Scoring Dashboard</a></p>';
echo '</div></body></html>';
