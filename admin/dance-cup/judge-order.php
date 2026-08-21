<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');

try{
    if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
    $test=(string)($_POST['data_mode']??'')==='test';
    if($test&&!Auth::isSuperAdmin())throw new RuntimeException('Super Admin required.');
    $competitionId=(int)($_POST['competition_id']??0);
    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['judge_ids']??[])))));
    if($competitionId<1||!$ids)throw new RuntimeException('Judge order is empty.');
    $table=$test?'bdc_test_dance_cup_judges':'bdc_dance_cup_judges';
    $pdo=Database::connection();
    $q=$pdo->prepare("SELECT id,is_chief FROM {$table} WHERE competition_id=:competition");
    $q->execute(['competition'=>$competitionId]);
    $existing=$q->fetchAll();
    $validIds=array_map('intval',array_column($existing,'id'));
    if(count($ids)!==count($validIds)||array_diff($ids,$validIds))throw new RuntimeException('Judge order does not match this competition.');
    $chiefId=(int)(array_column(array_filter($existing,static fn(array $judge):bool=>(int)$judge['is_chief']===1),'id')[0]??0);
    if($chiefId>0){$ids=array_values(array_diff($ids,[$chiefId]));array_unshift($ids,$chiefId);}
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE {$table} SET judge_order=judge_order+10000 WHERE competition_id=:competition")->execute(['competition'=>$competitionId]);
    $update=$pdo->prepare("UPDATE {$table} SET judge_order=:position WHERE id=:id AND competition_id=:competition");
    foreach($ids as $position=>$judgeId)$update->execute(['position'=>$position+1,'id'=>$judgeId,'competition'=>$competitionId]);
    $pdo->commit();
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
