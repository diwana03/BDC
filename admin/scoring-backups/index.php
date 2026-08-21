<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringBackupService;

Auth::requireAdmin();
$pdo=Database::connection();$userId=(int)(Auth::user()['id']??0);$csrf=Csrf::token();
$requestedMode=(string)($_GET['data_mode']??$_POST['data_mode']??$_POST['test_mode']??'live');
$backupTestMode=Auth::isSuperAdmin()&&$requestedMode==='test';
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);$notice='';$error='';

try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  if(!Auth::canManageScoringBackups())throw new RuntimeException('You do not have permission to manage scoring backups.');
  $action=(string)($_POST['action']??'');
  if($roundId<1)throw new RuntimeException('Select a scoring round first.');
  if($action==='create_scoring_backup'){
   $backupId=ScoringBackupService::create($pdo,$roundId,$backupTestMode,$userId,'manual','manual_backup',(string)($_POST['backup_label']??''));
   $audit=$backupTestMode?'bdc_test_scoring_audit':'bdc_scoring_audit';
   $pdo->prepare("INSERT INTO {$audit}(round_id,user_id,action,details_json) VALUES(:round,:user,'manual_scoring_backup_created',:details)")->execute(['round'=>$roundId,'user'=>$userId?:null,'details'=>json_encode(['backup_id'=>$backupId],JSON_UNESCAPED_SLASHES)]);
   $notice='Checkpoint #'.$backupId.' created.';
  }elseif($action==='restore_scoring_backup'){
   if(strtoupper(trim((string)($_POST['restore_confirmation']??'')))!=='RESTORE SCORES')throw new RuntimeException('Type RESTORE SCORES to confirm recovery.');
   $restored=ScoringBackupService::restore($pdo,(int)($_POST['backup_id']??0),$roundId,$backupTestMode,$userId,(string)($_POST['restore_reason']??''));
   $notice='Checkpoint #'.$restored['id'].' restored. The previous state was backed up first.';
  }elseif($action==='delete_scoring_backup'){
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE BACKUP')throw new RuntimeException('Type DELETE BACKUP to confirm permanent deletion.');
   $deleted=ScoringBackupService::delete($pdo,(int)($_POST['backup_id']??0),$roundId,$backupTestMode,$userId,(string)($_POST['delete_reason']??''));
   $notice='Checkpoint #'.$deleted['id'].' permanently deleted. The scoring round itself was not changed.';
  }elseif($action==='delete_selected_scoring_backups'){
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE SELECTED')throw new RuntimeException('Type DELETE SELECTED to confirm permanent deletion.');
   $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['backup_ids']??[])),static fn(int $id):bool=>$id>0)));
   if(!$ids)throw new RuntimeException('Select at least one scoring backup to delete.');
   $deleted=ScoringBackupService::deleteMany($pdo,$ids,$roundId,$backupTestMode,$userId,(string)($_POST['delete_reason']??''));
   $notice=$deleted['count'].' selected checkpoint'.($deleted['count']===1?'':'s').' permanently deleted. The scoring round itself was not changed.';
  }
 }
}catch(Throwable $e){$error=$e->getMessage();}

$roundTable=$backupTestMode?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$eventTable=$backupTestMode?'bdc_test_events':'bdc_events';
$rounds=$pdo->query("SELECT r.id,r.round_type,r.division,r.scoring_mode,r.status,r.updated_at,e.name event_name,e.event_date FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id ORDER BY COALESCE(e.event_date,'9999-12-31') DESC,r.id DESC LIMIT 500")->fetchAll();
$selectedRound=null;foreach($rounds as $candidate)if((int)$candidate['id']===$roundId){$selectedRound=$candidate;break;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scoring Backups | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=275" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="../scoring/">Scoring Dashboard</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></div></nav><main class="container-fluid py-4" style="max-width:1500px"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div><h1 class="h3 mb-1">Scoring Backups &amp; Transactions</h1><p class="text-muted mb-0">Central recovery checkpoints for every event and scoring round.</p></div><?php if(Auth::isSuperAdmin()):?><div class="btn-group"><a class="btn btn-sm <?=$backupTestMode?'btn-outline-dark':'btn-primary'?>" href="?data_mode=live">Live</a><a class="btn btn-sm <?=$backupTestMode?'btn-warning':'btn-outline-dark'?>" href="?data_mode=test">Test</a></div><?php endif;?></div><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="get" class="card card-body shadow-sm border-0 mb-3"><input type="hidden" name="data_mode" value="<?=$backupTestMode?'test':'live'?>"><label class="form-label fw-semibold">Event and scoring round</label><div class="d-flex gap-2 flex-wrap"><select class="form-select flex-grow-1" name="round_id" required><option value="">Choose an event checkpoint…</option><?php foreach($rounds as $item):?><option value="<?=(int)$item['id']?>" <?=$roundId===(int)$item['id']?'selected':''?>><?=e(($item['event_date']?:'Date pending').' · '.$item['event_name'].' · '.ucwords(str_replace('_',' ',$item['division'])).' · '.ucfirst($item['round_type']).' · '.ucfirst($item['scoring_mode']))?></option><?php endforeach;?></select><button class="btn btn-dark">Open History</button></div></form>
<?php if($selectedRound):?><div class="alert alert-primary py-2"><strong><?=e($selectedRound['event_name'])?></strong> · <?=e(ucwords(str_replace('_',' ',$selectedRound['division'])))?> · <?=e(ucfirst($selectedRound['round_type']))?> · <?=e(ucfirst($selectedRound['scoring_mode']))?> · <?=e(ucwords(str_replace('_',' ',$selectedRound['status'])))?></div><?php $testMode=$backupTestMode?'test':'live';require dirname(__DIR__).'/scoring/backup-panel.php';?><?php else:?><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Select an event and round to view its checkpoints, transactions and restore controls.</div></div><?php endif;?></main></body></html>
