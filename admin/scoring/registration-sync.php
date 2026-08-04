<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
header('Content-Type: application/json; charset=utf-8');
try{
 Auth::requireAdmin();
 $pdo=Database::connection();
 $roundId=(int)($_GET['round_id']??0);
 $roundStmt=$pdo->prepare("SELECT event_id,division FROM bdc_scoring_rounds WHERE id=:id");
 $roundStmt->execute(['id'=>$roundId]);$round=$roundStmt->fetch();
 if(!$round)throw new RuntimeException('Round not found.');
 $stmt=$pdo->prepare("SELECT dance_role,COUNT(*) total,SUM(CASE WHEN desk_ready=1 THEN 1 ELSE 0 END) ready,SUM(CASE WHEN bib_number IS NULL OR bib_number<1 THEN 1 ELSE 0 END) missing,MAX(desk_updated_at) updated FROM bdc_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
 $stmt->execute(['round'=>$roundId]);
 $stats=['leader'=>['total'=>0,'ready'=>0,'missing'=>0],'follower'=>['total'=>0,'ready'=>0,'missing'=>0]];
 $last=null;
 foreach($stmt->fetchAll() as $row){$stats[$row['dance_role']]=['total'=>(int)$row['total'],'ready'=>(int)$row['ready'],'missing'=>(int)$row['missing']];if($row['updated']&&(!$last||$row['updated']>$last))$last=$row['updated'];}
 echo json_encode(['ok'=>true,'leaders_total'=>$stats['leader']['total'],'leaders_ready'=>$stats['leader']['ready'],'followers_total'=>$stats['follower']['total'],'followers_ready'=>$stats['follower']['ready'],'missing_bibs'=>$stats['leader']['missing']+$stats['follower']['missing'],'last_update'=>$last]);
}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
