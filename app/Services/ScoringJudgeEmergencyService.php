<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ScoringJudgeEmergencyService
{
    /** @return array<string,mixed> */
    public static function remove(PDO $pdo,int $roundId,int $judgeId,bool $test,int $userId,string $reason,string $confirmation):array
    {
        $reason=trim($reason);
        if(mb_strlen($reason)<8)throw new RuntimeException('Enter a clear emergency reason (at least 8 characters).');
        if(strtoupper(trim($confirmation))!=='REMOVE JUDGE')throw new RuntimeException('Type REMOVE JUDGE to confirm this emergency change.');
        $prefix=$test?'bdc_test_scoring_':'bdc_scoring_';
        $judgeTable=$prefix.'judges';$roundTable=$prefix.'rounds';$auditTable=$prefix.'audit';
        $stmt=$pdo->prepare("SELECT * FROM {$judgeTable} WHERE id=:judge AND round_id=:round LIMIT 1");
        $stmt->execute(['judge'=>$judgeId,'round'=>$roundId]);$removed=$stmt->fetch();
        if(!$removed)throw new RuntimeException('Judge assignment not found for this round.');
        $panel=$pdo->prepare("SELECT * FROM {$judgeTable} WHERE round_id=:round AND id<>:judge ORDER BY is_chief DESC,judge_order,id");
        $panel->execute(['round'=>$roundId,'judge'=>$judgeId]);$remaining=$panel->fetchAll();
        if(count($remaining)<3)throw new RuntimeException('Removal blocked: a scoring round must retain at least 3 judges.');
        foreach(['leader','follower'] as $role){
            $count=count(array_filter($remaining,static fn(array $row):bool=>in_array((string)($row['scoring_scope']??'all'),['all',$role],true)));
            if($count<3)throw new RuntimeException('Removal blocked: the '.ucfirst($role).' panel would have fewer than 3 applicable judges. Assign a replacement first.');
        }
        $backupId=ScoringBackupService::create($pdo,$roundId,$test,$userId,'manual','emergency_judge_removal','Before emergency removal of '.$removed['judge_name']);
        $replacementChief=null;
        $pdo->beginTransaction();
        try{
            foreach([$prefix.'judge_sessions',$prefix.'marks',$prefix.'final_marks'] as $table){
                try{$pdo->prepare("DELETE FROM {$table} WHERE round_id=:round AND judge_id=:judge")->execute(['round'=>$roundId,'judge'=>$judgeId]);}catch(Throwable){}
            }
            foreach([$prefix.'results',$prefix.'final_results'] as $table){
                try{$pdo->prepare("DELETE FROM {$table} WHERE round_id=:round")->execute(['round'=>$roundId]);}catch(Throwable){}
            }
            $pdo->prepare("DELETE FROM {$judgeTable} WHERE round_id=:round AND id=:judge")->execute(['round'=>$roundId,'judge'=>$judgeId]);
            if((int)$removed['is_chief']===1){
                $replacementChief=$remaining[0];
                $pdo->prepare("UPDATE {$judgeTable} SET is_chief=(id=:chief) WHERE round_id=:round")->execute(['chief'=>(int)$replacementChief['id'],'round'=>$roundId]);
            }
            $ordered=$pdo->prepare("SELECT id,is_chief FROM {$judgeTable} WHERE round_id=:round ORDER BY is_chief DESC,judge_order,id");
            $ordered->execute(['round'=>$roundId]);$orderedRows=$ordered->fetchAll();
            $pdo->prepare("UPDATE {$judgeTable} SET judge_order=judge_order+10000 WHERE round_id=:round")->execute(['round'=>$roundId]);
            $renumber=$pdo->prepare("UPDATE {$judgeTable} SET judge_order=:position WHERE id=:id AND round_id=:round");
            $chiefId=0;
            foreach($orderedRows as $position=>$row){$id=(int)$row['id'];$renumber->execute(['position'=>$position+1,'id'=>$id,'round'=>$roundId]);if((int)$row['is_chief']===1)$chiefId=$id;}
            $pdo->prepare("UPDATE {$roundTable} SET chief_judge_id=:chief,status=CASE WHEN status IN ('completed','pending_approval','archived') THEN status ELSE 'draft' END WHERE id=:round")
                ->execute(['chief'=>$chiefId?:null,'round'=>$roundId]);
            $details=['judge_id'=>$judgeId,'judge_name'=>$removed['judge_name'],'judge_order'=>(int)$removed['judge_order'],'scope'=>$removed['scoring_scope']??'all','was_chief'=>(int)$removed['is_chief']===1,'replacement_chief'=>$replacementChief['judge_name']??null,'reason'=>$reason,'backup_id'=>$backupId,'results_invalidated'=>true];
            $pdo->prepare("INSERT INTO {$auditTable}(round_id,user_id,action,details_json) VALUES(:round,:user,'emergency_judge_removed',:details)")
                ->execute(['round'=>$roundId,'user'=>$userId?:null,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['judge_name'=>$removed['judge_name'],'backup_id'=>$backupId,'replacement_chief'=>$replacementChief['judge_name']??null];
    }
}
