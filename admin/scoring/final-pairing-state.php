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
 $stmt=Database::connection()->prepare("SELECT pair_number,leader_entry_id,follower_entry_id,pairing_status FROM {$prefix}scoring_final_pairs WHERE round_id=:round ORDER BY pair_number");
 $stmt->execute(['round'=>$roundId]);
 $pairs=array_map(static fn(array $row):array=>['pair_number'=>(int)$row['pair_number'],'leader_entry_id'=>(int)$row['leader_entry_id'],'follower_entry_id'=>(int)($row['follower_entry_id']??0),'status'=>(string)$row['pairing_status']],$stmt->fetchAll());
 echo json_encode(['ok'=>true,'hash'=>hash('sha256',json_encode($pairs)),'pairs'=>$pairs],JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Pairing state is unavailable.']);}
