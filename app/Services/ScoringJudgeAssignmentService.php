<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ScoringJudgeAssignmentService
{
    private static function normaliseName(string $name):string
    {
        return mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$name)));
    }

    /** @return array<int,array<string,mixed>> */
    public static function directory(PDO $pdo): array
    {
        JudgeDirectoryService::ensure($pdo);
        return $pdo->query(
            "SELECT id,judge_code,full_name,display_name,country,country_code " .
            "FROM bdc_judges WHERE status='active' ORDER BY COALESCE(NULLIF(display_name,''),full_name),id"
        )->fetchAll();
    }

    /**
     * @param array<int,array{name:string,scope?:string,assignment_id?:int,directory_id?:int,original_index?:int|string}> $rows
     * @return array{count:int,chief_id:int,chief_name:string,created_directory_count:int,results_invalidated:bool}
     */
    public static function save(
        PDO $pdo,
        int $roundId,
        array $rows,
        int|string $chiefKey,
        string $table='bdc_scoring_judges',
        string $roundTable='bdc_scoring_rounds'
    ): array {
        if(!in_array($table,['bdc_scoring_judges','bdc_test_scoring_judges'],true)){
            throw new RuntimeException('Invalid scoring judge table.');
        }
        if(!in_array($roundTable,['bdc_scoring_rounds','bdc_test_scoring_rounds'],true)){
            throw new RuntimeException('Invalid scoring round table.');
        }

        JudgeDirectoryService::ensure($pdo);
        $clean=[];
        foreach($rows as $key=>$row){
            $name=trim((string)($row['name']??''));
            if($name==='')continue;
            $scope=(string)($row['scope']??'all');
            if(!in_array($scope,['all','leader','follower'],true))$scope='all';
            $clean[(string)$key]=[
                'name'=>$name,
                'scope'=>$scope,
                'assignment_id'=>(int)($row['assignment_id']??0),
                'directory_id'=>(int)($row['directory_id']??0),
                'original_index'=>(string)($row['original_index']??$key),
            ];
        }
        if(count($clean)<3)throw new RuntimeException('Minimum 3 judges required.');
        $normalised=array_map(static fn(array $row):string=>self::normaliseName($row['name']),array_values($clean));
        if(count($normalised)!==count(array_unique($normalised)))throw new RuntimeException('The same judge cannot be selected more than once.');
        $postedDirectoryIds=array_values(array_filter(array_map(static fn(array $row):int=>(int)$row['directory_id'],array_values($clean))));
        if(count($postedDirectoryIds)!==count(array_unique($postedDirectoryIds)))throw new RuntimeException('The same Judge Database profile cannot be assigned twice.');

        $chiefCleanKey=null;
        foreach($clean as $key=>$row){
            if((string)$key===(string)$chiefKey || $row['original_index']===(string)$chiefKey){$chiefCleanKey=$key;break;}
        }
        if($chiefCleanKey===null)throw new RuntimeException('Select one Chief Judge.');

        // Display order is a scoring invariant: the Chief Judge is always J1.
        // Keep the administrator's chosen order for every other judge.
        $chiefRow=$clean[$chiefCleanKey];
        unset($clean[$chiefCleanKey]);
        $clean=array_values(array_merge([$chiefRow],array_values($clean)));
        $chiefCleanKey='0';
        foreach(['leader','follower'] as $role){
            $count=count(array_filter($clean,static fn(array $row):bool=>in_array($row['scope'],['all',$role],true)));
            if($count<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');
        }

        // Resolve/create directory profiles before the assignment transaction. JudgeDirectoryService
        // performs schema checks, and DDL must never be allowed to commit an assignment transaction.
        $created=0;
        foreach($clean as $key=>$row){
            $directory=null;
            if($row['directory_id']>0){
                $stmt=$pdo->prepare("SELECT * FROM bdc_judges WHERE id=:id AND status='active' LIMIT 1");
                $stmt->execute(['id'=>$row['directory_id']]);
                $directory=$stmt->fetch()?:null;
                if($directory){
                    $typed=self::normaliseName($row['name']);
                    $full=self::normaliseName((string)$directory['full_name']);
                    $display=self::normaliseName((string)($directory['display_name']??''));
                    if($typed!==$full && ($display===''||$typed!==$display))$directory=null;
                }
            }
            if(!$directory){
                $stmt=$pdo->prepare("SELECT * FROM bdc_judges WHERE LOWER(full_name)=LOWER(:name) OR LOWER(COALESCE(display_name,''))=LOWER(:display) ORDER BY status='active' DESC,id LIMIT 1");
                $stmt->execute(['name'=>$row['name'],'display'=>$row['name']]);
                $directory=$stmt->fetch()?:null;
            }
            if(!$directory){$directory=JudgeDirectoryService::create($pdo,['full_name'=>$row['name']]);$created++;}
            if((string)$directory['status']!=='active')throw new RuntimeException($row['name'].' is inactive in the Judge Database.');
            $clean[$key]['directory_id']=(int)$directory['id'];
            $clean[$key]['name']=trim((string)($directory['display_name']?:$directory['full_name']));
        }
        $directoryIds=array_column($clean,'directory_id');
        if(count($directoryIds)!==count(array_unique($directoryIds)))throw new RuntimeException('The same Judge Database profile cannot be assigned twice.');

        $existingStmt=$pdo->prepare("SELECT * FROM {$table} WHERE round_id=:round ORDER BY judge_order,id");
        $existingStmt->execute(['round'=>$roundId]);
        $existing=$existingStmt->fetchAll();
        $existingById=[];$existingByDirectory=[];
        foreach($existing as $assignment){
            $existingById[(int)$assignment['id']]=$assignment;
            if((int)($assignment['judge_id']??0)>0)$existingByDirectory[(int)$assignment['judge_id']]=$assignment;
        }

        $pdo->beginTransaction();
        try{
            // Prevent unique(round_id,judge_order) collisions while rows are reordered.
            $pdo->prepare("UPDATE {$table} SET judge_order=judge_order+10000 WHERE round_id=:round")
                ->execute(['round'=>$roundId]);
            $update=$pdo->prepare("UPDATE {$table} SET judge_id=:directory,judge_name=:name,judge_order=:position,is_chief=:chief,scoring_scope=:scope WHERE id=:id AND round_id=:round");
            $insert=$pdo->prepare("INSERT INTO {$table}(judge_id,round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:directory,:round,:name,:position,:chief,:scope)");
            $kept=[];$chiefId=0;$position=1;$affectsResults=false;
            foreach($clean as $key=>$row){
                $assignment=null;
                if($row['assignment_id']>0 && isset($existingById[$row['assignment_id']]))$assignment=$existingById[$row['assignment_id']];
                elseif(isset($existingByDirectory[$row['directory_id']]))$assignment=$existingByDirectory[$row['directory_id']];
                $isChief=$key===$chiefCleanKey?1:0;
                if($assignment){
                    $assignmentId=(int)$assignment['id'];
                    $identityChanged=(int)($assignment['judge_id']??0)>0
                        ? (int)$assignment['judge_id']!==$row['directory_id']
                        : mb_strtolower(trim((string)$assignment['judge_name']))!==mb_strtolower($row['name']);
                    if($identityChanged){
                        $affectsResults=true;
                        $prefix=$table==='bdc_test_scoring_judges'?'bdc_test_scoring_':'bdc_scoring_';
                        foreach([$prefix.'judge_sessions',$prefix.'marks',$prefix.'final_marks'] as $dependent){
                            try{$pdo->prepare("DELETE FROM {$dependent} WHERE judge_id=:judge")->execute(['judge'=>$assignmentId]);}catch(Throwable){}
                        }
                    }
                    if((string)($assignment['scoring_scope']??'all')!==$row['scope'] || (int)$assignment['is_chief']!==$isChief)$affectsResults=true;
                    $update->execute(['directory'=>$row['directory_id'],'name'=>$row['name'],'position'=>$position,'chief'=>$isChief,'scope'=>$row['scope'],'id'=>$assignmentId,'round'=>$roundId]);
                }else{
                    $affectsResults=true;
                    $insert->execute(['directory'=>$row['directory_id'],'round'=>$roundId,'name'=>$row['name'],'position'=>$position,'chief'=>$isChief,'scope'=>$row['scope']]);
                    $assignmentId=(int)$pdo->lastInsertId();
                }
                $kept[]=$assignmentId;
                if($isChief)$chiefId=$assignmentId;
                $position++;
            }
            $remove=array_values(array_diff(array_map('intval',array_column($existing,'id')),$kept));
            if($remove){
                $affectsResults=true;
                $placeholders=implode(',',array_fill(0,count($remove),'?'));
                $prefix=$table==='bdc_test_scoring_judges'?'bdc_test_scoring_':'bdc_scoring_';
                foreach([$prefix.'judge_sessions',$prefix.'marks',$prefix.'final_marks'] as $dependent){
                    try{$pdo->prepare("DELETE FROM {$dependent} WHERE judge_id IN ({$placeholders})")->execute($remove);}catch(Throwable){}
                }
                $pdo->prepare("DELETE FROM {$table} WHERE round_id=? AND id IN ({$placeholders})")
                    ->execute(array_merge([$roundId],$remove));
            }
            $pdo->prepare("UPDATE {$roundTable} SET chief_judge_id=:chief WHERE id=:round")
                ->execute(['chief'=>$chiefId?:null,'round'=>$roundId]);
            if($affectsResults){
                $prefix=$table==='bdc_test_scoring_judges'?'bdc_test_scoring_':'bdc_scoring_';
                foreach([$prefix.'results',$prefix.'final_results'] as $resultTable){
                    try{$pdo->prepare("DELETE FROM {$resultTable} WHERE round_id=:round")->execute(['round'=>$roundId]);}catch(Throwable){}
                }
                $pdo->prepare("UPDATE {$roundTable} SET status=CASE WHEN status IN ('completed','pending_approval','archived') THEN status ELSE 'draft' END WHERE id=:round")->execute(['round'=>$roundId]);
            }
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        return ['count'=>count($clean),'chief_id'=>$chiefId,'chief_name'=>$clean[$chiefCleanKey]['name'],'created_directory_count'=>$created,'results_invalidated'=>$affectsResults];
    }
}
