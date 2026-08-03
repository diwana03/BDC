<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;
header('Content-Type: application/json; charset=utf-8');
try {
 Auth::requireAdmin();
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('POST required.');
 if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
 $pdo=Database::connection();SchemaUpdater::run($pdo);
 $roundId=(int)($_POST['round_id']??0);$entryId=(int)($_POST['entry_id']??0);$judgeId=(int)($_POST['judge_id']??0);$raw=strtoupper(trim((string)($_POST['value']??'')));
 if($roundId<1||$entryId<1||$judgeId<1)throw new RuntimeException('Invalid scoring cell.');
 $entryStmt=$pdo->prepare("SELECT dance_role FROM bdc_scoring_entries WHERE id=:entry AND round_id=:round AND entry_status='active'");$entryStmt->execute(['entry'=>$entryId,'round'=>$roundId]);$role=(string)$entryStmt->fetchColumn();
 if(!in_array($role,['leader','follower'],true))throw new RuntimeException('Competitor entry not found.');
 $judgeStmt=$pdo->prepare("SELECT scoring_scope FROM bdc_scoring_judges WHERE id=:judge AND round_id=:round");$judgeStmt->execute(['judge'=>$judgeId,'round'=>$roundId]);$scope=(string)$judgeStmt->fetchColumn();
 if($scope==='')throw new RuntimeException('Judge not found.');if($scope!=='all'&&$scope!==$role)throw new RuntimeException('Judge is not assigned to this role.');
 $type='blank';$alt=null;$weight=0.0;
 if(in_array($raw,['1','Y','YES'],true)){$type='yes';$weight=10.0;}
 elseif(in_array($raw,['A1','2'],true)){$type='alt';$alt=1;$weight=4.5;}
 elseif(in_array($raw,['A2','3'],true)){$type='alt';$alt=2;$weight=4.3;}
 elseif(in_array($raw,['A3','4'],true)){$type='alt';$alt=3;$weight=4.2;}
 $stmt=$pdo->prepare("INSERT INTO bdc_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:round,:entry,:judge,:type,:alt,:weight,:user) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=VALUES(updated_by),updated_at=NOW()");
 $stmt->execute(['round'=>$roundId,'entry'=>$entryId,'judge'=>$judgeId,'type'=>$type,'alt'=>$alt,'weight'=>$weight,'user'=>(int)(Auth::user()['id']??0)]);
 echo json_encode(['ok'=>true,'saved_at'=>date('H:i:s')]);
}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
