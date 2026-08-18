<?php

use App\Core\Auth;
use App\Services\ScoringBackupService;

$backupTestMode=(bool)($backupTestMode??false);
$scoringBackups=ScoringBackupService::list($pdo,$roundId,$backupTestMode,25);
$auditTable=$backupTestMode?'bdc_test_scoring_audit':'bdc_scoring_audit';
$transactionStmt=$pdo->prepare("SELECT a.action,a.details_json,a.created_at,u.full_name user_name FROM {$auditTable} a LEFT JOIN bdc_users u ON u.id=a.user_id WHERE a.round_id=:round ORDER BY a.id DESC LIMIT 30");
$transactionStmt->execute(['round'=>$roundId]);$scoreTransactions=$transactionStmt->fetchAll();
?>
<section class="card shadow-sm border-primary mt-4 mb-4" id="scoring-backups">
 <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h2 class="h5 mb-1">Scoring Backup &amp; Recovery</h2><div class="small text-muted">Automatic snapshots, manual safety copies and an immutable scoring transaction trail.</div></div>
  <span class="badge text-bg-<?=$backupTestMode?'warning':'primary'?>"><?=$backupTestMode?'TEST':'LIVE'?> DATA</span>
 </div>
 <div class="card-body">
  <?php if(Auth::canOverrideCompletedScores()):?>
  <form method="post" class="row g-2 align-items-end mb-4">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="create_scoring_backup">
   <?php if($backupTestMode):?><input type="hidden" name="test_mode" value="<?=e($testMode??'manual')?>"><?php endif;?>
   <div class="col-md-9"><label class="form-label fw-semibold">Backup label</label><input class="form-control" name="backup_label" maxlength="190" placeholder="Example: Before chief judge tie decision"></div>
   <div class="col-md-3"><button class="btn btn-primary w-100">Create Backup Now</button></div>
  </form>
  <?php endif;?>
  <div class="table-responsive mb-4"><table class="table table-sm table-bordered align-middle mb-0">
   <thead><tr><th>Backup</th><th>Created</th><th>Trigger</th><th>Contents</th><th>Recovery</th></tr></thead>
   <tbody><?php if(!$scoringBackups):?><tr><td colspan="5" class="text-center text-muted py-4">No backups yet. The first dashboard change will create one automatically.</td></tr><?php endif;?>
   <?php foreach($scoringBackups as $backup):$summary=json_decode((string)$backup['summary_json'],true)?:[];?><tr>
    <td><strong>#<?=(int)$backup['id']?></strong> <span class="badge text-bg-<?=$backup['backup_type']==='manual'?'primary':($backup['backup_type']==='pre_restore'?'danger':'secondary')?>"><?=e(str_replace('_',' ',(string)$backup['backup_type']))?></span><?php if(!empty($backup['label'])):?><div class="small"><?=e((string)$backup['label'])?></div><?php endif;?></td>
    <td class="small text-nowrap"><?=e((string)$backup['created_at'])?></td>
    <td class="small"><?=e(ucwords(str_replace('_',' ',(string)$backup['action_name'])))?></td>
    <td class="small">Marks <?= (int)($summary['marks']??0) ?> · Results <?= (int)($summary['results']??0) ?><br>Pairs <?= (int)($summary['final_pairs']??0) ?> · Final marks <?= (int)($summary['final_marks']??0) ?></td>
    <td><?php if(!empty($backup['restored_at'])):?><span class="badge text-bg-success">Restored <?=e((string)$backup['restored_at'])?></span><?php elseif(Auth::canOverrideCompletedScores()):?><details><summary class="btn btn-outline-danger btn-sm">Preview / Restore</summary><form method="post" class="border rounded bg-light p-2 mt-2" onsubmit="return confirm('Restore backup #<?=(int)$backup['id']?>? Current scoring state will first be backed up automatically.');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="backup_id" value="<?=(int)$backup['id']?>"><input type="hidden" name="action" value="restore_scoring_backup"><?php if($backupTestMode):?><input type="hidden" name="test_mode" value="<?=e($testMode??'manual')?>"><?php endif;?><div class="small mb-2"><strong>Restore preview:</strong> replaces current marks, results, Final pairing, Final placements and judge-session state with the counts shown here.</div><input class="form-control form-control-sm mb-2" name="restore_reason" maxlength="500" required placeholder="Required recovery reason"><input class="form-control form-control-sm mb-2" name="restore_confirmation" required placeholder="Type RESTORE SCORES"><button class="btn btn-danger btn-sm w-100">Restore This Backup</button></form></details><?php else:?><span class="text-muted small">View only</span><?php endif;?></td>
   </tr><?php endforeach;?></tbody>
  </table></div>
  <details><summary class="fw-semibold">Score Transactions · latest 30</summary><div class="table-responsive mt-2"><table class="table table-sm table-striped"><thead><tr><th>Time</th><th>Action</th><th>User</th><th>Details</th></tr></thead><tbody><?php foreach($scoreTransactions as $transaction):?><tr><td class="small text-nowrap"><?=e((string)$transaction['created_at'])?></td><td><?=e(ucwords(str_replace('_',' ',(string)$transaction['action'])))?></td><td><?=e((string)($transaction['user_name']??'System / Judge'))?></td><td class="small"><code><?=e(mb_strimwidth((string)$transaction['details_json'],0,180,'…'))?></code></td></tr><?php endforeach;?></tbody></table></div></details>
 </div>
</section>
