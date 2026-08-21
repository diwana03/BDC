<?php

use App\Core\Auth;
use App\Services\ScoringBackupService;

$backupTestMode=(bool)($backupTestMode??false);
$backupAction=(string)($backupAction??'');
$backupActionAttribute=$backupAction!==''?' action="'.e($backupAction).'"':'';
$scoringBackups=ScoringBackupService::list($pdo,$roundId,$backupTestMode,25);
$auditTable=$backupTestMode?'bdc_test_scoring_audit':'bdc_scoring_audit';
$transactionStmt=$pdo->prepare("SELECT a.action,a.details_json,a.created_at,u.full_name user_name FROM {$auditTable} a LEFT JOIN bdc_users u ON u.id=a.user_id WHERE a.round_id=:round ORDER BY a.id DESC LIMIT 30");
$transactionStmt->execute(['round'=>$roundId]);$scoreTransactions=$transactionStmt->fetchAll();
$formatTransaction=static function(string $json):string{
 $data=json_decode($json,true);if(!is_array($data)||!$data)return 'No additional details';
 $labels=['pairs'=>'Pairs','judges'=>'Judges','judge_id'=>'Judge record','reason'=>'Reason','rank_count'=>'Required placements','role'=>'Role','competitor_id'=>'Competitor record','source_rank'=>'Source rank','source_status'=>'Previous status','previous_role_count'=>'Previous role count','new_role_count'=>'New role count','pairing_reset'=>'Pairing reset','algorithm_version'=>'Algorithm','backup_id'=>'Backup'];
 $parts=[];foreach($data as $key=>$value){if(is_array($value))continue;$label=$labels[$key]??ucwords(str_replace('_',' ',(string)$key));if(is_bool($value))$value=$value?'Yes':'No';$parts[]=$label.': '.$value;}return $parts?implode(' · ',$parts):'No additional details';
};
?>
<details class="card shadow-sm border-primary mt-3 mb-3" id="scoring-backups">
 <summary class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2" style="cursor:pointer;list-style:none">
  <span><strong>Backups &amp; Score History</strong> <span class="small text-muted">· <?=count($scoringBackups)?> checkpoints · latest <?=e((string)($scoringBackups[0]['created_at']??'none'))?></span></span>
  <span class="badge text-bg-<?=$backupTestMode?'warning':'primary'?>"><?=$backupTestMode?'TEST':'LIVE'?></span>
 </summary>
 <div class="card-body pt-3">
  <?php if(Auth::canManageScoringBackups()):?>
  <form method="post"<?=$backupActionAttribute?> class="row g-2 align-items-end mb-4">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="create_scoring_backup">
   <?php if($backupTestMode):?><input type="hidden" name="test_mode" value="<?=e($testMode??'manual')?>"><?php endif;?>
   <div class="col-md-9"><label class="form-label fw-semibold">Optional checkpoint note</label><input class="form-control form-control-sm" name="backup_label" maxlength="190" placeholder="Event name and current time are added automatically"></div>
   <div class="col-md-3"><button class="btn btn-primary btn-sm w-100">Back Up Scores Now</button></div>
  </form>
  <?php endif;?>
  <div class="table-responsive mb-4"><table class="table table-sm table-bordered align-middle mb-0">
   <thead><tr><th>Backup</th><th>Created</th><th>Trigger</th><th>Contents</th><th>Recovery</th></tr></thead>
   <tbody><?php if(!$scoringBackups):?><tr><td colspan="5" class="text-center text-muted py-4">No backups yet. The first dashboard change will create one automatically.</td></tr><?php endif;?>
   <?php foreach($scoringBackups as $backup):$summary=json_decode((string)$backup['summary_json'],true)?:[];?><tr>
    <td><strong>#<?=(int)$backup['id']?></strong> <span class="badge text-bg-<?=$backup['backup_type']==='manual'?'primary':($backup['backup_type']==='pre_restore'?'danger':'secondary')?>"><?=e(str_replace('_',' ',(string)$backup['backup_type']))?></span><?php if(!empty($backup['is_protected'])):?><span class="badge text-bg-warning">Protected</span><?php endif;?><?php if(!empty($backup['label'])):?><div class="small"><?=e((string)$backup['label'])?></div><?php endif;?></td>
    <td class="small text-nowrap"><?=e((string)$backup['created_at'])?></td>
    <td class="small"><?=e(ucwords(str_replace('_',' ',(string)$backup['action_name'])))?></td>
    <td class="small">Marks <?= (int)($summary['marks']??0) ?> · Results <?= (int)($summary['results']??0) ?><br>Pairs <?= (int)($summary['final_pairs']??0) ?> · Final marks <?= (int)($summary['final_marks']??0) ?></td>
    <td><?php if(!empty($backup['restored_at'])):?><div class="badge text-bg-success mb-1">Last restored <?=e((string)$backup['restored_at'])?></div><?php endif;?><?php if(Auth::canManageScoringBackups()):?><details><summary class="btn btn-danger btn-sm">Restore</summary><form method="post"<?=$backupActionAttribute?> class="border rounded bg-light p-2 mt-2" onsubmit="return confirm('Restore checkpoint #<?=(int)$backup['id']?> now? The current scoring state will first be backed up automatically.');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="backup_id" value="<?=(int)$backup['id']?>"><input type="hidden" name="action" value="restore_scoring_backup"><?php if($backupTestMode):?><input type="hidden" name="test_mode" value="<?=e($testMode??'manual')?>"><?php endif;?><div class="small mb-2"><strong>This restores:</strong> marks, calculated results, Final pairing, Final placements and judge submitted/draft locks shown in this checkpoint.</div><input class="form-control form-control-sm mb-2" name="restore_reason" maxlength="500" required placeholder="Why are you restoring this checkpoint?"><input class="form-control form-control-sm mb-2" name="restore_confirmation" required placeholder="Type RESTORE SCORES"><button class="btn btn-danger btn-sm w-100">Confirm Restore</button></form></details><?php else:?><span class="text-muted small">View only</span><?php endif;?></td>
   </tr><?php endforeach;?></tbody>
  </table></div>
  <details><summary class="fw-semibold">Score Transactions · latest 30</summary><div class="table-responsive mt-2"><table class="table table-sm table-striped"><thead><tr><th>Time</th><th>Action</th><th>User</th><th>What happened</th></tr></thead><tbody><?php foreach($scoreTransactions as $transaction):?><tr><td class="small text-nowrap"><?=e((string)$transaction['created_at'])?></td><td><?=e(ucwords(str_replace('_',' ',(string)$transaction['action'])))?></td><td><?=e((string)($transaction['user_name']??'System / Judge'))?></td><td class="small"><?=e($formatTransaction((string)$transaction['details_json']))?></td></tr><?php endforeach;?></tbody></table></div></details>
 </div>
</details>
