<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\TestCompetitorCopyService;

Auth::requireAdmin();
$pdo=Database::connection();

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && (string)($_POST['action']??'')==='generate_test_competitors'){
    if(!Csrf::verify($_POST['_csrf']??null)){
        throw new RuntimeException('Invalid security token.');
    }
    $roundId=(int)($_POST['round_id']??0);
    $leaderCount=max(0,min(500,(int)($_POST['leader_count']??10)));
    $followerCount=max(0,min(500,(int)($_POST['follower_count']??10)));
    if($roundId<1)throw new RuntimeException('Open a test round first.');

    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO bdc_test_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),entry_status='active'");
        foreach(['leader'=>$leaderCount,'follower'=>$followerCount] as $role=>$count){
            if($count<1)continue;
            $rows=$pdo->prepare("SELECT * FROM bdc_competitors WHERE status='active' AND dance_role=:role ORDER BY RAND() LIMIT {$count}");
            $rows->execute(['role'=>$role]);
            $bibStmt=$pdo->prepare("SELECT COALESCE(MAX(bib_number),0) FROM bdc_test_scoring_entries WHERE round_id=:round AND dance_role=:role");
            $bibStmt->execute(['round'=>$roundId,'role'=>$role]);
            $bib=(int)$bibStmt->fetchColumn();
            foreach($rows->fetchAll() as $competitor){
                // Copies only columns that exist in test storage. Photo fields are optional.
                TestCompetitorCopyService::copy($pdo,$competitor);
                $insert->execute([
                    'round'=>$roundId,
                    'competitor'=>(int)$competitor['id'],
                    'role'=>$role,
                    'bib'=>++$bib,
                    'name'=>(string)$competitor['exact_name'],
                ]);
            }
        }
        $counts=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
        $counts->execute(['round'=>$roundId]);
        $roleCounts=['leader'=>0,'follower'=>0];
        foreach($counts->fetchAll() as $row)$roleCounts[(string)$row['dance_role']]=(int)$row['total'];
        $largest=max($roleCounts['leader'],$roleCounts['follower']);
        $yes=$largest<=15?5:($largest<=30?10:15);
        $pdo->prepare("UPDATE bdc_test_scoring_rounds SET yes_count=:yes,callback_count=:yes WHERE id=:round")
            ->execute(['yes'=>$yes,'round'=>$roundId]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
    header('Location: run.php?round_id='.$roundId, true, 303);
    exit;
}

// The established test UI remains the renderer/controller for all other actions.
$_GET['legacy']=1;
require __DIR__.'/index.php';
