<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ScoringParityTestService
{
    public static function run(PDO $pdo,int $userId):array
    {
        $stamp=date('Y-m-d H-i-s');
        $base='BDC LIVE PARITY TEST - DO NOT PUBLISH - '.$stamp;
        $competitors=[];
        foreach(['leader','follower'] as $role){
            $q=$pdo->prepare("SELECT * FROM bdc_competitors WHERE status='active' AND dance_role=:role ORDER BY id LIMIT 10");
            $q->execute(['role'=>$role]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)<8)throw new RuntimeException('Live parity testing requires at least 8 active '.$role.'s.');
            $competitors[$role]=$rows;
        }

        $ids=[];
        try{
            foreach(['live','test'] as $scope){
                $prefix=$scope==='test'?'bdc_test_':'bdc_';
                $name=($scope==='test'?'TEST MIRROR · ':'').$base;
                $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-')).'-'.random_int(100,999);
                $pdo->prepare("INSERT INTO {$prefix}events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,CURDATE(),'draft')")
                    ->execute(['name'=>$name,'normalised'=>strtolower($name),'slug'=>$slug]);
                $eventId=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO {$prefix}scoring_rounds(event_id,round_type,scoring_mode,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by,status) VALUES(:event,'heats','automated','novice',5,5,10.00,4.50,4.30,4.20,:user,'draft')")
                    ->execute(['event'=>$eventId,'user'=>$userId?:null]);
                $roundId=(int)$pdo->lastInsertId();$ids[$scope]=['event_id'=>$eventId,'round_id'=>$roundId,'name'=>$name];
                $entry=$pdo->prepare("INSERT INTO {$prefix}scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active')");
                foreach($competitors as $role=>$rows){$bib=0;foreach($rows as $row){if($scope==='test')TestCompetitorCopyService::copy($pdo,$row);$entry->execute(['round'=>$roundId,'competitor'=>(int)$row['id'],'role'=>$role,'bib'=>++$bib,'name'=>$row['exact_name']]);}}
                $judge=$pdo->prepare("INSERT INTO {$prefix}scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:position,:chief,'all')");
                $judgeIds=[];foreach(range(1,5) as $position){$judge->execute(['round'=>$roundId,'name'=>'Parity Judge '.$position,'position'=>$position,'chief'=>$position===1?1:0]);$judgeIds[]=(int)$pdo->lastInsertId();}
                $pdo->prepare("UPDATE {$prefix}scoring_rounds SET chief_judge_id=:chief WHERE id=:round")->execute(['chief'=>$judgeIds[0],'round'=>$roundId]);
                self::writeMarks($pdo,$prefix,$roundId,$judgeIds);
                if($scope==='test'){TestAutomaticJudgeService::syncRound($pdo,$roundId);$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='submitted',opened_at=NOW(),last_saved_at=NOW(),submitted_at=NOW() WHERE round_id=:round")->execute(['round'=>$roundId]);}
                else{AutomaticJudgeBrowserService::syncRound($pdo,$roundId);$pdo->prepare("UPDATE bdc_scoring_judge_sessions SET status='submitted',opened_at=NOW(),last_saved_at=NOW(),submitted_at=NOW() WHERE round_id=:round")->execute(['round'=>$roundId]);}
                $ids[$scope]['calculation']=ScoringCalculationService::calculateHeats($pdo,$roundId,$scope==='test'?ScoringCalculationService::TEST:ScoringCalculationService::PRODUCTION,$userId);
            }
            $live=self::results($pdo,'bdc_',$ids['live']['round_id']);$test=self::results($pdo,'bdc_test_',$ids['test']['round_id']);
            $differences=[];foreach($live as $key=>$row){if(!isset($test[$key])){$differences[]=$key.' missing from Test';continue;}foreach(['total_score','chief_score','rank_number','result_status','alternate_rank'] as $field)if((string)($row[$field]??'')!==(string)($test[$key][$field]??''))$differences[]=$key.' '.$field.' Live='.(string)($row[$field]??'NULL').' Test='.(string)($test[$key][$field]??'NULL');}
            foreach($test as $key=>$row)if(!isset($live[$key]))$differences[]=$key.' missing from Live';
            return $ids+['live_results'=>count($live),'test_results'=>count($test),'differences'=>$differences,'passed'=>!$differences];
        }catch(Throwable $e){throw $e;}
    }

    public static function archive(PDO $pdo,int $liveEventId,int $testEventId):void
    {
        $checks=[['bdc_',$liveEventId,'BDC LIVE PARITY TEST - DO NOT PUBLISH - %'],['bdc_test_',$testEventId,'TEST MIRROR · BDC LIVE PARITY TEST - DO NOT PUBLISH - %']];
        foreach($checks as [$prefix,$eventId,$pattern]){$q=$pdo->prepare("SELECT id FROM {$prefix}events WHERE id=:id AND name LIKE :pattern");$q->execute(['id'=>$eventId,'pattern'=>$pattern]);if(!(int)$q->fetchColumn())throw new RuntimeException('Parity event ownership check failed.');$pdo->prepare("UPDATE {$prefix}scoring_rounds SET status='archived' WHERE event_id=:event")->execute(['event'=>$eventId]);$pdo->prepare("UPDATE {$prefix}events SET status='cancelled' WHERE id=:event")->execute(['event'=>$eventId]);}
    }

    private static function writeMarks(PDO $pdo,string $prefix,int $roundId,array $judgeIds):void
    {
        $entryQuery=$pdo->prepare("SELECT id,competitor_id FROM {$prefix}scoring_entries WHERE round_id=:round AND dance_role=:role ORDER BY competitor_id");
        $insert=$pdo->prepare("INSERT INTO {$prefix}scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score) VALUES(:round,:entry,:judge,:type,:alt,:weight)");
        foreach($judgeIds as $judgeIndex=>$judgeId)foreach(['leader','follower'] as $role){$entryQuery->execute(['round'=>$roundId,'role'=>$role]);$entries=$entryQuery->fetchAll(PDO::FETCH_ASSOC);$count=count($entries);for($slot=0;$slot<min(8,$count);$slot++){$entry=$entries[($slot+$judgeIndex)%$count];$type=$slot<5?'yes':'alt';$alt=$slot<5?null:$slot-4;$weight=$slot<5?10.0:[1=>4.5,2=>4.3,3=>4.2][$alt];$insert->execute(['round'=>$roundId,'entry'=>$entry['id'],'judge'=>$judgeId,'type'=>$type,'alt'=>$alt,'weight'=>$weight]);}}
    }

    private static function results(PDO $pdo,string $prefix,int $roundId):array
    {
        $q=$pdo->prepare("SELECT e.competitor_id,e.dance_role,r.total_score,r.chief_score,r.rank_number,r.result_status,r.alternate_rank FROM {$prefix}scoring_results r JOIN {$prefix}scoring_entries e ON e.id=r.entry_id WHERE r.round_id=:round ORDER BY e.dance_role,e.competitor_id");$q->execute(['round'=>$roundId]);$out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['dance_role'].':'.$row['competitor_id']]=$row;return $out;
    }
}
