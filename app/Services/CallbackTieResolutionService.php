<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CallbackTieResolutionService
{
    private static function tables(bool $test):array
    {
        $prefix=$test?'bdc_test_':'bdc_';
        return [
            'rounds'=>$prefix.'scoring_rounds',
            'entries'=>$prefix.'scoring_entries',
            'results'=>$prefix.'scoring_results',
            'audit'=>$prefix.'scoring_audit',
        ];
    }

    public static function groups(PDO $pdo,int $roundId,bool $test):array
    {
        $t=self::tables($test);
        $roundStmt=$pdo->prepare("SELECT callback_count FROM {$t['rounds']} WHERE id=:r");
        $roundStmt->execute(['r'=>$roundId]);
        $quota=(int)$roundStmt->fetchColumn();
        if($quota<1)return [];

        $stmt=$pdo->prepare("SELECT sr.entry_id,sr.rank_number,sr.total_score,sr.chief_score,se.dance_role,se.display_name,se.bib_number
            FROM {$t['results']} sr JOIN {$t['entries']} se ON se.id=sr.entry_id
            WHERE sr.round_id=:r AND sr.result_status='tie_pending'
            ORDER BY se.dance_role,sr.rank_number,se.bib_number,se.id");
        $stmt->execute(['r'=>$roundId]);
        $groups=[];
        foreach($stmt->fetchAll() as $row){
            // Chief is already included in total_score under BDC rules. All
            // identical totals belong to one decision group even when the
            // Chief's individual mark differs.
            $key=$row['dance_role'].'|'.$row['rank_number'].'|'.$row['total_score'];
            if(!isset($groups[$key]))$groups[$key]=[
                'role'=>(string)$row['dance_role'],'rank'=>(int)$row['rank_number'],
                'total'=>(float)$row['total_score'],'chief'=>(float)$row['chief_score'],
                'competitors'=>[]
            ];
            $groups[$key]['competitors'][]=$row;
        }

        $countStmt=$pdo->prepare("SELECT result_status,alternate_rank,COUNT(*) total
            FROM {$t['results']} sr JOIN {$t['entries']} se ON se.id=sr.entry_id
            WHERE sr.round_id=:r AND se.dance_role=:role AND sr.result_status IN('callback','alternate')
            GROUP BY result_status,alternate_rank");
        foreach($groups as &$group){
            $countStmt->execute(['r'=>$roundId,'role'=>$group['role']]);
            $confirmed=0;$usedAlternates=[];
            foreach($countStmt->fetchAll() as $count){
                if($count['result_status']==='callback')$confirmed+=(int)$count['total'];
                elseif($count['alternate_rank']!==null)$usedAlternates[]=(int)$count['alternate_rank'];
            }
            $required=max(0,min(count($group['competitors']),$quota-$confirmed));
            $available=array_values(array_diff([1,2,3],$usedAlternates));
            $alternateNeeded=min(count($group['competitors'])-$required,count($available));
            $group['quota']=$quota;
            $group['confirmed_callbacks']=$confirmed;
            $group['required_callbacks']=$required;
            $group['available_alternate_ranks']=array_slice($available,0,$alternateNeeded);
        }
        unset($group);
        return array_values(array_filter($groups,static fn(array $g):bool=>count($g['competitors'])>1));
    }

    public static function resolve(PDO $pdo,int $roundId,bool $test,int $anchorEntryId,array $selectedIds,array $alternateOrder,int $userId):array
    {
        $groups=self::groups($pdo,$roundId,$test);
        $group=null;
        foreach($groups as $candidate){
            if(in_array($anchorEntryId,array_map('intval',array_column($candidate['competitors'],'entry_id')),true)){$group=$candidate;break;}
        }
        if(!$group)throw new RuntimeException('This tie group is no longer unresolved.');

        $groupIds=array_map('intval',array_column($group['competitors'],'entry_id'));
        $selected=array_values(array_unique(array_map('intval',$selectedIds)));
        if(array_diff($selected,$groupIds))throw new RuntimeException('The callback selection contains a competitor outside this tie.');
        $required=(int)$group['required_callbacks'];
        if(count($selected)!==$required)throw new RuntimeException('Select exactly '.$required.' callback competitor'.($required===1?'':'s').' from this tie.');

        $available=array_map('intval',$group['available_alternate_ranks']);
        $altAssignments=[];
        foreach($alternateOrder as $entryId=>$rank){
            $entryId=(int)$entryId;$rank=(int)$rank;
            if($rank<1)continue;
            if(!in_array($entryId,$groupIds,true)||in_array($entryId,$selected,true))throw new RuntimeException('Alternate positions may be assigned only to unselected tied competitors.');
            if(!in_array($rank,$available,true))throw new RuntimeException('Select only the available alternate positions.');
            if(in_array($rank,$altAssignments,true))throw new RuntimeException('Each alternate position may be assigned only once.');
            $altAssignments[$entryId]=$rank;
        }
        sort($available);
        $assignedRanks=array_values($altAssignments);sort($assignedRanks);
        if($assignedRanks!==$available)throw new RuntimeException('Assign '.implode(', ',array_map(static fn(int $r):string=>'A'.$r,$available)).' to the remaining tied competitors.');

        $t=self::tables($test);
        $update=$pdo->prepare("UPDATE {$t['results']} SET result_status=:status,rank_number=:rank,alternate_rank=:alt,updated_at=NOW() WHERE round_id=:r AND entry_id=:e");
        $callbackRank=(int)$group['confirmed_callbacks']+1;
        $eliminatedRank=(int)$group['quota']+4;
        $pdo->beginTransaction();
        try{
            foreach($group['competitors'] as $competitor){
                $entryId=(int)$competitor['entry_id'];
                if(in_array($entryId,$selected,true)){
                    $update->execute(['status'=>'callback','rank'=>$callbackRank++,'alt'=>null,'r'=>$roundId,'e'=>$entryId]);
                }elseif(isset($altAssignments[$entryId])){
                    $alt=$altAssignments[$entryId];
                    $update->execute(['status'=>'alternate','rank'=>(int)$group['quota']+$alt,'alt'=>$alt,'r'=>$roundId,'e'=>$entryId]);
                }else{
                    $update->execute(['status'=>'eliminated','rank'=>$eliminatedRank++,'alt'=>null,'r'=>$roundId,'e'=>$entryId]);
                }
            }
            $remaining=$pdo->prepare("SELECT COUNT(*) FROM {$t['results']} WHERE round_id=:r AND result_status='tie_pending'");
            $remaining->execute(['r'=>$roundId]);
            if((int)$remaining->fetchColumn()===0)$pdo->prepare("UPDATE {$t['rounds']} SET status='awaiting_decision' WHERE id=:r")->execute(['r'=>$roundId]);
            $audit=$pdo->prepare("INSERT INTO {$t['audit']}(round_id,user_id,action,details_json) VALUES(:r,:u,'callback_tie_resolved_multi',:d)");
            $audit->execute(['r'=>$roundId,'u'=>$userId?:null,'d'=>json_encode([
                'role'=>$group['role'],'quota'=>$group['quota'],'confirmed_before'=>$group['confirmed_callbacks'],
                'selected_callback_ids'=>$selected,'alternate_order'=>$altAssignments
            ],JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        return ['role'=>$group['role'],'selected'=>count($selected),'quota'=>$group['quota']];
    }
}
