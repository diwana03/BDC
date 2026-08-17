<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class NextRankedFinalistService
{
    private static function tables(bool $test): array
    {
        $prefix=$test?'bdc_test_scoring_':'bdc_scoring_';
        return [
            'rounds'=>$prefix.'rounds','entries'=>$prefix.'entries','results'=>$prefix.'results',
            'pairs'=>$prefix.'final_pairs','marks'=>$prefix.'final_marks','final_results'=>$prefix.'final_results',
            'audit'=>$prefix.'audit',
        ];
    }

    public static function state(PDO $pdo,int $roundId,bool $test=false): array
    {
        $t=self::tables($test);
        $roundStmt=$pdo->prepare("SELECT * FROM {$t['rounds']} WHERE id=:id");
        $roundStmt->execute(['id'=>$roundId]);
        $round=$roundStmt->fetch();
        if(!$round || (string)$round['round_type']!=='final')throw new RuntimeException('Final round not found.');
        $sourceRoundId=(int)($round['source_round_id']?:$round['parent_round_id']);
        if($sourceRoundId<1)return ['callback_derived'=>false,'roles'=>[],'scoring_started'=>false,'pair_count'=>0];

        $callbacks=['leader'=>0,'follower'=>0];
        $sourceTotals=['leader'=>0,'follower'=>0];
        $countStmt=$pdo->prepare("SELECT e.dance_role,COUNT(*) total,SUM(CASE WHEN r.result_status='callback' THEN 1 ELSE 0 END) callbacks FROM {$t['entries']} e JOIN {$t['results']} r ON r.round_id=e.round_id AND r.entry_id=e.id WHERE e.round_id=:r AND e.entry_status='active' GROUP BY e.dance_role");
        $countStmt->execute(['r'=>$sourceRoundId]);
        foreach($countStmt->fetchAll() as $row){$role=(string)$row['dance_role'];if(isset($callbacks[$role])){$callbacks[$role]=(int)$row['callbacks'];$sourceTotals[$role]=(int)$row['total'];}}
        $current=['leader'=>0,'follower'=>0];
        $currentStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM {$t['entries']} WHERE round_id=:r AND entry_status='active' GROUP BY dance_role");
        $currentStmt->execute(['r'=>$roundId]);
        foreach($currentStmt->fetchAll() as $row)if(isset($current[$row['dance_role']]))$current[$row['dance_role']]=(int)$row['total'];

        $startedStmt=$pdo->prepare("SELECT (SELECT COUNT(*) FROM {$t['marks']} m JOIN {$t['pairs']} p ON p.id=m.pair_id WHERE p.round_id=:r1)+(SELECT COUNT(*) FROM {$t['final_results']} fr JOIN {$t['pairs']} p2 ON p2.id=fr.pair_id WHERE p2.round_id=:r2)");
        $startedStmt->execute(['r1'=>$roundId,'r2'=>$roundId]);
        $scoringStarted=(int)$startedStmt->fetchColumn()>0;
        $pairStmt=$pdo->prepare("SELECT COUNT(*) FROM {$t['pairs']} WHERE round_id=:r");$pairStmt->execute(['r'=>$roundId]);$pairCount=(int)$pairStmt->fetchColumn();

        $roles=[];
        foreach(['leader','follower'] as $role){
            $candidate=null;
            if($current[$role]<$sourceTotals[$role]){
                $candidateStmt=$pdo->prepare("SELECT se.competitor_id,se.dance_role,se.bib_number,se.display_name,sr.rank_number,sr.total_score,sr.result_status FROM {$t['entries']} se JOIN {$t['results']} sr ON sr.round_id=se.round_id AND sr.entry_id=se.id WHERE se.round_id=:source AND se.dance_role=:role AND se.entry_status='active' AND NOT EXISTS(SELECT 1 FROM {$t['entries']} fe WHERE fe.round_id=:final AND fe.competitor_id=se.competitor_id AND fe.dance_role=se.dance_role) ORDER BY sr.rank_number ASC,sr.total_score DESC,se.bib_number ASC LIMIT 1");
                $candidateStmt->execute(['source'=>$sourceRoundId,'role'=>$role,'final'=>$roundId]);
                $candidate=$candidateStmt->fetch()?:null;
            }
            $roles[$role]=['current'=>$current[$role],'callback_count'=>$callbacks[$role],'source_total'=>$sourceTotals[$role],'candidate'=>$candidate,'can_promote'=>!$scoringStarted && $candidate!==null];
        }
        return ['callback_derived'=>true,'source_round_id'=>$sourceRoundId,'roles'=>$roles,'scoring_started'=>$scoringStarted,'pair_count'=>$pairCount];
    }

    public static function promote(PDO $pdo,int $roundId,string $role,int $userId,bool $test=false): array
    {
        if(!in_array($role,['leader','follower'],true))throw new RuntimeException('Invalid finalist role.');
        $state=self::state($pdo,$roundId,$test);
        if(empty($state['callback_derived']))throw new RuntimeException('Next-ranked promotion is only available for callback-derived Finals.');
        $roleState=$state['roles'][$role]??[];
        if(!empty($state['scoring_started']))throw new RuntimeException('Final scoring has started. Reopen or reset the Final before changing finalists.');
        $candidate=$roleState['candidate']??null;
        if(!$candidate)throw new RuntimeException('No additional ranked '.ucfirst($role).' is available.');
        $t=self::tables($test);
        $pdo->beginTransaction();
        try{
            $pairingReset=(int)$state['pair_count']>0;
            if($pairingReset)$pdo->prepare("DELETE FROM {$t['pairs']} WHERE round_id=:r")->execute(['r'=>$roundId]);
            $pdo->prepare("INSERT INTO {$t['entries']}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:r,:c,:role,:bib,:name,'active')")->execute(['r'=>$roundId,'c'=>$candidate['competitor_id'],'role'=>$role,'bib'=>$candidate['bib_number'],'name'=>$candidate['display_name']]);
            $details=json_encode(['role'=>$role,'competitor_id'=>(int)$candidate['competitor_id'],'source_rank'=>(int)$candidate['rank_number'],'source_status'=>(string)$candidate['result_status'],'previous_role_count'=>(int)$roleState['current'],'new_role_count'=>(int)$roleState['current']+1,'pairing_reset'=>$pairingReset],JSON_UNESCAPED_UNICODE);
            $pdo->prepare("INSERT INTO {$t['audit']}(round_id,user_id,action,details_json) VALUES(:r,:u,'next_ranked_finalist_promoted',:d)")->execute(['r'=>$roundId,'u'=>$userId?:null,'d'=>$details]);
            $pdo->commit();
            return ['candidate'=>$candidate,'pairing_reset'=>$pairingReset,'new_count'=>(int)$roleState['current']+1];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
