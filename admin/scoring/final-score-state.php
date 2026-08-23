<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$roundId=(int)($_GET['round_id']??0);
$test=(string)($_GET['data_mode']??'real')==='test';
if($roundId<1){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Round is required.']);exit;}
$prefix=$test?'bdc_test_':'bdc_';
try{
 $pdo=Database::connection();
 $roundStmt=$pdo->prepare("SELECT round_type,scoring_mode,status FROM {$prefix}scoring_rounds WHERE id=:round LIMIT 1");
 $roundStmt->execute(['round'=>$roundId]);$round=$roundStmt->fetch();
 if(!$round||$round['round_type']!=='final')throw new RuntimeException('Final round not found.');
 $judgeStmt=$pdo->prepare("SELECT j.id,j.judge_order,j.is_chief,COALESCE((SELECT s.status FROM {$prefix}scoring_judge_sessions s WHERE s.round_id=j.round_id AND s.judge_id=j.id ORDER BY s.id DESC LIMIT 1),'pending') session_status FROM {$prefix}scoring_judges j WHERE j.round_id=:round ORDER BY j.judge_order");
 $judgeStmt->execute(['round'=>$roundId]);
 $judges=array_map(static fn(array $row):array=>['id'=>(int)$row['id'],'order'=>(int)$row['judge_order'],'chief'=>(int)$row['is_chief']===1,'status'=>(string)$row['session_status']],$judgeStmt->fetchAll());
 $markStmt=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM {$prefix}scoring_final_marks WHERE round_id=:round ORDER BY pair_id,judge_id");
 $markStmt->execute(['round'=>$roundId]);
 $marks=array_map(static fn(array $row):array=>['pair_id'=>(int)$row['pair_id'],'judge_id'=>(int)$row['judge_id'],'rank'=>(int)$row['rank_value']],$markStmt->fetchAll());
 $resultStmt=$pdo->prepare("SELECT pair_id,final_rank,majority_level,majority_count,placement_sum,chief_rank FROM {$prefix}scoring_final_results WHERE round_id=:round ORDER BY pair_id");
 $resultStmt->execute(['round'=>$roundId]);
 $results=array_map(static fn(array $row):array=>['pair_id'=>(int)$row['pair_id'],'rank'=>(int)$row['final_rank'],'majority_level'=>(int)$row['majority_level'],'majority_count'=>(int)$row['majority_count'],'placement_sum'=>(int)$row['placement_sum'],'chief_rank'=>(int)$row['chief_rank']],$resultStmt->fetchAll());
 $state=['round_status'=>(string)$round['status'],'judges'=>$judges,'marks'=>$marks,'results'=>$results];
 echo json_encode(['ok'=>true,'hash'=>hash('sha256',json_encode($state))]+$state,JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$error->getMessage()]);}
