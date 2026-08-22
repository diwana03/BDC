<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
use App\Services\JudgeDirectoryService;
Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$term=trim((string)($_GET['q']??''));
if(mb_strlen($term)<1){echo json_encode(['ok'=>true,'items'=>[]]);exit;}
try{
 $rows=JudgeDirectoryService::search(Database::connection(),$term,100);
 $items=array_map(static fn(array $row):array=>[
  'id'=>(int)$row['id'],
  'name'=>(string)($row['display_name']?:$row['full_name']),
  'meta'=>trim(implode(' · ',array_filter([(string)($row['judge_code']??''),(string)($row['country']??'')]))),
 ],$rows);
 echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Judge Database search is unavailable.']);}
