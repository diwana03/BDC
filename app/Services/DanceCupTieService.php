<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupTieService
{
    private static function prefix(bool $test): string { return $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup'; }

    public static function ensure(PDO $pdo,bool $test=false):void
    {
        $p=self::prefix($test);
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_tie_tasks(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            competition_id BIGINT UNSIGNED NOT NULL,
            tie_key CHAR(64) NOT NULL,
            tied_score DECIMAL(12,2) NOT NULL,
            entry_ids_json TEXT NOT NULL,
            token_hash CHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            resolved_order_json TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            chief_judge_assignment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            resolved_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            UNIQUE KEY uq_dc_tie_key(competition_id,tie_key),
            INDEX idx_dc_tie_status(competition_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** @return array<int,array<string,mixed>> */
    public static function ties(PDO $pdo,int $competitionId,bool $test=false):array
    {
         $p=self::prefix($test);
        $q=$pdo->prepare("SELECT total_score,MIN(placement) base_place,COUNT(*) qty FROM {$p}_scoring_results WHERE competition_id=:c GROUP BY total_score HAVING COUNT(*)>1 ORDER BY base_place,total_score DESC");
        $q->execute(['c'=>$competitionId]);$out=[];
        foreach($q->fetchAll() as $g){
            $e=$pdo->prepare("SELECT r.entry_id,r.total_score,r.placement,e.bib_number,e.display_name,e.competitor_id FROM {$p}_scoring_results r JOIN {$p}_entries e ON e.id=r.entry_id AND e.competition_id=r.competition_id WHERE r.competition_id=:c AND r.total_score=:s AND e.status='active' ORDER BY e.bib_number,e.id");
            $e->execute(['c'=>$competitionId,'s'=>$g['total_score']]);$entries=$e->fetchAll();$ids=array_map(static fn($x)=>(int)$x['entry_id'],$entries);sort($ids,SORT_NUMERIC);$key=hash('sha256',$competitionId.'|'.number_format((float)$g['total_score'],2,'.','').'|'.implode(',',$ids));
            $t=$pdo->prepare("SELECT id,status,expires_at,resolved_order_json FROM {$p}_tie_tasks WHERE competition_id=:c AND tie_key=:k LIMIT 1");$t->execute(['c'=>$competitionId,'k'=>$key]);$task=$t->fetch()?:null;
            $out[]=['tie_key'=>$key,'score'=>(float)$g['total_score'],'base_place'=>(int)$g['base_place'],'entries'=>$entries,'task'=>$task];
        }
        return $out;
    }

    public static function hasUnresolved(PDO $pdo,int $competitionId,bool $test=false):bool
    {
        foreach(self::ties($pdo,$competitionId,$test) as $tie){if((string)($tie['task']['status']??'')!=='resolved')return true;}return false;
    }

    /** @return array{token:string,url:string,task_id:int} */
    public static function createTask(PDO $pdo,int $competitionId,string $tieKey,int $userId,bool $test=false):array
    {
        $ties=self::ties($pdo,$competitionId,$test);$target=null;foreach($ties as $tie)if(hash_equals((string)$tie['tie_key'],$tieKey)){$target=$tie;break;}if(!$target)throw new RuntimeException('This tie is no longer current. Recalculate the results.');
        $p=self::prefix($test);$ids=array_map(static fn($x)=>(int)$x['entry_id'],$target['entries']);$chief=$pdo->prepare("SELECT id FROM {$p}_judges WHERE competition_id=:c AND is_chief=1 ORDER BY judge_order,id LIMIT 1");$chief->execute(['c'=>$competitionId]);$chiefId=(int)$chief->fetchColumn();if($chiefId<1)throw new RuntimeException('Assign a Chief Judge before sending a tie decision.');
        $token=rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');$hash=hash('sha256',$token);
        $sql="INSERT INTO {$p}_tie_tasks(competition_id,tie_key,tied_score,entry_ids_json,token_hash,status,created_by,chief_judge_assignment_id,expires_at) VALUES(:c,:k,:s,:ids,:h,'pending',:u,:j,DATE_ADD(NOW(),INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE tied_score=VALUES(tied_score),entry_ids_json=VALUES(entry_ids_json),token_hash=VALUES(token_hash),status='pending',resolved_order_json=NULL,created_by=VALUES(created_by),chief_judge_assignment_id=VALUES(chief_judge_assignment_id),created_at=NOW(),expires_at=VALUES(expires_at),resolved_at=NULL,cancelled_at=NULL";
        $pdo->prepare($sql)->execute(['c'=>$competitionId,'k'=>$tieKey,'s'=>$target['score'],'ids'=>json_encode($ids),'h'=>$hash,'u'=>$userId?:null,'j'=>$chiefId]);
        $q=$pdo->prepare("SELECT id FROM {$p}_tie_tasks WHERE competition_id=:c AND tie_key=:k");$q->execute(['c'=>$competitionId,'k'=>$tieKey]);$taskId=(int)$q->fetchColumn();
        $url=\absolute_url('dance-cup-chief-tie.php').'?token='.rawurlencode($token).'&mode='.($test?'test':'live');
        return ['token'=>$token,'url'=>$url,'task_id'=>$taskId];
    }

    /** @return array<string,mixed> */
    public static function taskByToken(PDO $pdo,string $token,bool $test=false):array
    {
        $p=self::prefix($test);$q=$pdo->prepare("SELECT t.*,c.category_name,c.round_name,e.name event_name,j.judge_name chief_name FROM {$p}_tie_tasks t JOIN ".DanceCupScoringService::tables($test)['competitions']." c ON c.id=t.competition_id JOIN ".DanceCupScoringService::tables($test)['events']." e ON e.id=c.event_id LEFT JOIN {$p}_judges j ON j.id=t.chief_judge_assignment_id WHERE t.token_hash=:h LIMIT 1");$q->execute(['h'=>hash('sha256',$token)]);$task=$q->fetch();if(!$task)throw new RuntimeException('Tie decision link is invalid.');if((string)$task['status']!=='pending')throw new RuntimeException('This tie decision is no longer pending.');if(empty($task['expires_at'])||strtotime((string)$task['expires_at'])<time())throw new RuntimeException('This tie decision link has expired.');
        $ids=json_decode((string)$task['entry_ids_json'],true);if(!is_array($ids)||!$ids)throw new RuntimeException('Tie task has no contestants.');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT r.entry_id,r.total_score,r.placement,e.bib_number,e.display_name,e.competitor_id FROM {$p}_scoring_results r JOIN {$p}_entries e ON e.id=r.entry_id AND e.competition_id=r.competition_id WHERE r.competition_id=? AND r.entry_id IN ({$ph}) ORDER BY e.bib_number,e.id");$q->execute(array_merge([(int)$task['competition_id']],array_map('intval',$ids)));$task['entries']=$q->fetchAll();return $task;
    }

    public static function resolve(PDO $pdo,string $token,array $orderedEntryIds,bool $test=false):void
    {
        $task=self::taskByToken($pdo,$token,$test);$expected=json_decode((string)$task['entry_ids_json'],true);$expected=array_map('intval',is_array($expected)?$expected:[]);$ordered=array_map('intval',$orderedEntryIds);$a=$expected;$b=$ordered;sort($a);sort($b);if(!$a||$a!==$b||count($ordered)!==count(array_unique($ordered)))throw new RuntimeException('Choose a complete final order for every tied contestant.');
        $p=self::prefix($test);$pdo->beginTransaction();try{$lock=$pdo->prepare("SELECT status FROM {$p}_tie_tasks WHERE id=:id FOR UPDATE");$lock->execute(['id'=>$task['id']]);if((string)$lock->fetchColumn()!=='pending')throw new RuntimeException('This tie has already been resolved.');$base=(int)min(array_column($task['entries'],'placement'));$up=$pdo->prepare("UPDATE {$p}_scoring_results SET placement=:p WHERE competition_id=:c AND entry_id=:e");foreach($ordered as $i=>$entryId)$up->execute(['p'=>$base+$i,'c'=>$task['competition_id'],'e'=>$entryId]);$pdo->prepare("UPDATE {$p}_tie_tasks SET status='resolved',resolved_order_json=:o,resolved_at=NOW(),token_hash=NULL WHERE id=:id")->execute(['o'=>json_encode($ordered),'id'=>$task['id']]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function resolveAsChief(PDO $pdo,int $competitionId,string $tieKey,array $orderedEntryIds,int $chiefAssignmentId,bool $test=false):void
    {
        $p=self::prefix($test);
        $chief=$pdo->prepare("SELECT COUNT(*) FROM {$p}_judges WHERE id=:judge AND competition_id=:competition AND is_chief=1");
        $chief->execute(['judge'=>$chiefAssignmentId,'competition'=>$competitionId]);
        if((int)$chief->fetchColumn()!==1)throw new RuntimeException('Chief Judge authorization required.');
        $target=null;foreach(self::ties($pdo,$competitionId,$test) as $tie)if(hash_equals((string)$tie['tie_key'],$tieKey)){$target=$tie;break;}
        if(!$target)throw new RuntimeException('This tie is no longer current. Refresh the scoring page.');
        $expected=array_map(static fn($x)=>(int)$x['entry_id'],$target['entries']);$ordered=array_map('intval',$orderedEntryIds);$a=$expected;$b=$ordered;sort($a);sort($b);
        if(!$a||$a!==$b||count($ordered)!==count(array_unique($ordered)))throw new RuntimeException('Choose a complete final order for every tied contestant.');
        $pdo->beginTransaction();try{
            $base=(int)$target['base_place'];$up=$pdo->prepare("UPDATE {$p}_scoring_results SET placement=:place WHERE competition_id=:competition AND entry_id=:entry");
            foreach($ordered as $i=>$entryId)$up->execute(['place'=>$base+$i,'competition'=>$competitionId,'entry'=>$entryId]);
            $ids=$expected;sort($ids,SORT_NUMERIC);$existing=$pdo->prepare("SELECT id FROM {$p}_tie_tasks WHERE competition_id=:competition AND tie_key=:tie LIMIT 1");$existing->execute(['competition'=>$competitionId,'tie'=>$tieKey]);$taskId=(int)$existing->fetchColumn();
            if($taskId>0){$pdo->prepare("UPDATE {$p}_tie_tasks SET status='resolved',resolved_order_json=:ordered,resolved_at=NOW(),token_hash=NULL,chief_judge_assignment_id=:chief WHERE id=:id")->execute(['ordered'=>json_encode($ordered),'chief'=>$chiefAssignmentId,'id'=>$taskId]);}
            else{$pdo->prepare("INSERT INTO {$p}_tie_tasks(competition_id,tie_key,tied_score,entry_ids_json,status,resolved_order_json,chief_judge_assignment_id,resolved_at) VALUES(:competition,:tie,:score,:ids,'resolved',:ordered,:chief,NOW())")->execute(['competition'=>$competitionId,'tie'=>$tieKey,'score'=>$target['score'],'ids'=>json_encode($ids),'ordered'=>json_encode($ordered),'chief'=>$chiefAssignmentId]);}
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function cancel(PDO $pdo,int $competitionId,string $tieKey,bool $test=false):void
    {
        $p=self::prefix($test);$pdo->prepare("UPDATE {$p}_tie_tasks SET status='cancelled',token_hash=NULL,cancelled_at=NOW() WHERE competition_id=:c AND tie_key=:k AND status='pending'")->execute(['c'=>$competitionId,'k'=>$tieKey]);
    }
}
