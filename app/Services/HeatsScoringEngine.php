<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Single source of truth for BDC Heats/Semifinal result calculation.
 *
 * This class is storage-agnostic. Production and Test dashboards load their
 * own judges/entries/marks, call this engine, then persist the returned rows to
 * their respective tables. No points/publication writes occur here.
 */
final class HeatsScoringEngine
{
    /**
     * @param array<int,array<string,mixed>> $judges
     * @param array<int,array<string,mixed>> $entries
     * @param array<int,array<int,float|int|string>> $marksByEntry entry_id => judge_id => weighted score
     * @return array{leader:array<int,array<string,mixed>>,follower:array<int,array<string,mixed>>}
     */
    public static function calculate(array $judges,array $entries,array $marksByEntry,int $callbackCount):array
    {
        if(count($judges)<ScoringRulesService::MINIMUM_JUDGES_PER_ROLE){
            throw new RuntimeException('At least '.ScoringRulesService::MINIMUM_JUDGES_PER_ROLE.' judges are required.');
        }

        $chief=array_values(array_filter($judges,static fn(array $j):bool=>(int)($j['is_chief']??0)===1));
        if(count($chief)!==1)throw new RuntimeException('Exactly one Chief Judge is required.');

        $roleJudgeIds=[
            'leader'=>self::judgeIdsForRole($judges,'leader'),
            'follower'=>self::judgeIdsForRole($judges,'follower'),
        ];
        foreach(['leader','follower'] as $role){
            if(count($roleJudgeIds[$role])<ScoringRulesService::MINIMUM_JUDGES_PER_ROLE){
                throw new RuntimeException(ucfirst($role).' panel requires at least '.ScoringRulesService::MINIMUM_JUDGES_PER_ROLE.' assigned judges.');
            }
        }
        if(!$entries)throw new RuntimeException('Add competitors before calculating.');

        $grouped=['leader'=>[],'follower'=>[]];
        foreach($entries as $entry){
            $role=(string)($entry['dance_role']??'');
            if(!isset($grouped[$role]))continue;
            $assigned=$roleJudgeIds[$role];
            $chiefId=0;
            foreach($judges as $judge){
                if((int)($judge['is_chief']??0)!==1)continue;
                $jid=(int)($judge['id']??0);
                if(in_array($jid,$assigned,true)){$chiefId=$jid;break;}
            }
            $entryId=(int)($entry['id']??0);
            $total=0.0;$chiefScore=0.0;
            foreach(($marksByEntry[$entryId]??[]) as $judgeId=>$score){
                $judgeId=(int)$judgeId;
                if(!in_array($judgeId,$assigned,true))continue;
                $value=(float)$score;
                $total+=$value;
                if($judgeId===$chiefId)$chiefScore=$value;
            }
            $grouped[$role][]=['entry'=>$entry,'total'=>$total,'chief'=>$chiefScore];
        }

        return [
            'leader'=>self::rankRole($grouped['leader'],max(0,$callbackCount)),
            'follower'=>self::rankRole($grouped['follower'],max(0,$callbackCount)),
        ];
    }

    /** @param array<int,array<string,mixed>> $judges @return int[] */
    private static function judgeIdsForRole(array $judges,string $role):array
    {
        $ids=[];
        foreach($judges as $judge){
            $scope=(string)($judge['scoring_scope']??'all');
            if($scope==='all'||$scope===$role)$ids[]=(int)($judge['id']??0);
        }
        return array_values(array_filter($ids,static fn(int $id):bool=>$id>0));
    }

    /**
     * @param array<int,array<string,mixed>> $list
     * @return array<int,array<string,mixed>>
     */
    private static function rankRole(array $list,int $callbackCount):array
    {
        usort($list,static function(array $a,array $b):int{
            $totalOrder=((float)$b['total'])<=>((float)$a['total']);
            return $totalOrder!==0?$totalOrder:(((float)$b['chief'])<=>((float)$a['chief']));
        });

        $callbackLimit=min($callbackCount,count($list));
        $alternateLimit=min($callbackLimit+ScoringRulesService::ALTERNATE_COUNT,count($list));
        $result=[];$i=0;
        while($i<count($list)){
            $groupStart=$i;
            $groupTotal=(float)$list[$i]['total'];
            $groupChief=(float)$list[$i]['chief'];
            while($i+1<count($list)
                && (float)$list[$i+1]['total']===$groupTotal
                && (float)$list[$i+1]['chief']===$groupChief){$i++;}

            $groupEnd=$i;
            $startPosition=$groupStart+1;
            $endPosition=$groupEnd+1;
            $rank=$startPosition;
            $crossesCallback=$startPosition<=$callbackLimit&&$endPosition>$callbackLimit;
            $crossesAlternate=$startPosition<=$alternateLimit&&$endPosition>$alternateLimit;

            for($j=$groupStart;$j<=$groupEnd;$j++){
                $status='eliminated';$alternateRank=null;
                if($crossesCallback||$crossesAlternate)$status='tie_pending';
                elseif($endPosition<=$callbackLimit)$status='callback';
                elseif($startPosition>$callbackLimit&&$endPosition<=$alternateLimit){
                    $status='alternate';$alternateRank=$j-$callbackLimit+1;
                }
                $item=$list[$j];
                $result[]=[
                    'entry'=>$item['entry'],
                    'entry_id'=>(int)($item['entry']['id']??0),
                    'total_score'=>(float)$item['total'],
                    'chief_score'=>(float)$item['chief'],
                    'rank_number'=>$rank,
                    'result_status'=>$status,
                    'alternate_rank'=>$alternateRank,
                ];
            }
            $i++;
        }
        return $result;
    }
}
