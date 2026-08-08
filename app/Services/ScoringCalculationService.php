<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Shared persistence adapter for BDC Heats/Semifinal calculation.
 *
 * Production and Test use different storage tables, but both load the same
 * inputs and call HeatsScoringEngine. The test scope is strictly limited to
 * bdc_test_* tables; no point/progression/publication writes occur here.
 */
final class ScoringCalculationService
{
    public const PRODUCTION='production';
    public const TEST='test';

    /** @return array{version:int,pending_tie:bool,rows:int,tier:?int,callback_count:int} */
    public static function calculateHeats(PDO $pdo,int $roundId,string $scope,?int $userId=null):array
    {
        $tables=self::tables($scope);
        $roundStmt=$pdo->prepare("SELECT * FROM {$tables['rounds']} WHERE id=:r LIMIT 1");
        $roundStmt->execute(['r'=>$roundId]);
        $round=$roundStmt->fetch();
        if(!$round)throw new RuntimeException('Scoring round not found.');
        if(!in_array((string)$round['round_type'],['heats','semifinal'],true)){
            throw new RuntimeException('Shared Heats engine applies only to Heats and Semi-Finals.');
        }

        $judgeStmt=$pdo->prepare("SELECT * FROM {$tables['judges']} WHERE round_id=:r ORDER BY judge_order");
        $judgeStmt->execute(['r'=>$roundId]);
        $judges=$judgeStmt->fetchAll();

        $entryStmt=$pdo->prepare("SELECT * FROM {$tables['entries']} WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");
        $entryStmt->execute(['r'=>$roundId]);
        $entries=$entryStmt->fetchAll();

        $roleCounts=['leader'=>0,'follower'=>0];
        foreach($entries as $entry){
            $role=(string)($entry['dance_role']??'');
            if(isset($roleCounts[$role]))$roleCounts[$role]++;
        }

        $tier=null;
        $callbackCount=(int)$round['callback_count'];
        $division=(string)($round['division']??'');
        $manualOverride=(int)($round['tier_manual_override']??0)===1;
        if(!SpecialCategoryService::isSpecial($division) && !$manualOverride){
            $tierInfo=ScoringRulesService::tierFromRoleCounts($roleCounts['leader'],$roleCounts['follower']);
            $tier=(int)$tierInfo['tier'];
            $callbackCount=(int)$tierInfo['yes_count'];
            $pdo->prepare("UPDATE {$tables['rounds']} SET yes_count=:yes,callback_count=:callbacks WHERE id=:r")
                ->execute(['yes'=>$callbackCount,'callbacks'=>$callbackCount,'r'=>$roundId]);
        }

        $markStmt=$pdo->prepare("SELECT entry_id,judge_id,mark_type,alt_rank,weighted_score FROM {$tables['marks']} WHERE round_id=:r");
        $markStmt->execute(['r'=>$roundId]);
        $marks=[];
        foreach($markStmt->fetchAll() as $mark){
            $type=strtolower((string)($mark['mark_type']??''));
            $weight=in_array($type,['yes','alt'],true)
                ? ScoringRulesService::markWeight($type,$mark['alt_rank']===null?null:(int)$mark['alt_rank'])
                : (float)$mark['weighted_score'];
            $marks[(int)$mark['entry_id']][(int)$mark['judge_id']]=$weight;
        }

        $calculated=HeatsScoringEngine::calculate($judges,$entries,$marks,$callbackCount);
        $version=(int)$round['generated_version']+1;
        $pendingTie=false;$rowCount=0;

        $pdo->beginTransaction();
        try{
            $pdo->prepare("DELETE FROM {$tables['results']} WHERE round_id=:r")->execute(['r'=>$roundId]);
            $insert=$pdo->prepare("INSERT INTO {$tables['results']}(round_id,entry_id,total_score,chief_score,rank_number,result_status,alternate_rank,generated_version) VALUES(:r,:e,:total,:chief,:rank,:status,:alt,:version)");
            foreach(['leader','follower'] as $role){
                foreach($calculated[$role] as $row){
                    if($row['result_status']==='tie_pending')$pendingTie=true;
                    $insert->execute([
                        'r'=>$roundId,
                        'e'=>$row['entry_id'],
                        'total'=>$row['total_score'],
                        'chief'=>$row['chief_score'],
                        'rank'=>$row['rank_number'],
                        'status'=>$row['result_status'],
                        'alt'=>$row['alternate_rank'],
                        'version'=>$version,
                    ]);
                    $rowCount++;
                }
            }
            $status=$pendingTie?'tie_pending':'awaiting_decision';
            $pdo->prepare("UPDATE {$tables['rounds']} SET status=:status,generated_version=:version WHERE id=:r")
                ->execute(['status'=>$status,'version'=>$version,'r'=>$roundId]);
            $audit=$pdo->prepare("INSERT INTO {$tables['audit']}(round_id,user_id,action,details_json) VALUES(:r,:u,'results_generated_shared_engine',:details)");
            $audit->execute([
                'r'=>$roundId,
                'u'=>$userId?:null,
                'details'=>json_encode([
                    'engine'=>HeatsScoringEngine::class,
                    'rules'=>ScoringRulesService::class,
                    'version'=>$version,
                    'pending_tie'=>$pendingTie,
                    'row_count'=>$rowCount,
                    'scope'=>$scope,
                    'tier'=>$tier,
                    'callback_count'=>$callbackCount,
                    'special_category'=>SpecialCategoryService::isSpecial($division),
                ],JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        return ['version'=>$version,'pending_tie'=>$pendingTie,'rows'=>$rowCount,'tier'=>$tier,'callback_count'=>$callbackCount];
    }

    /** @return array{rounds:string,judges:string,entries:string,marks:string,results:string,audit:string} */
    private static function tables(string $scope):array
    {
        if($scope===self::PRODUCTION){
            return [
                'rounds'=>'bdc_scoring_rounds','judges'=>'bdc_scoring_judges','entries'=>'bdc_scoring_entries',
                'marks'=>'bdc_scoring_marks','results'=>'bdc_scoring_results','audit'=>'bdc_scoring_audit',
            ];
        }
        if($scope===self::TEST){
            return [
                'rounds'=>'bdc_test_scoring_rounds','judges'=>'bdc_test_scoring_judges','entries'=>'bdc_test_scoring_entries',
                'marks'=>'bdc_test_scoring_marks','results'=>'bdc_test_scoring_results','audit'=>'bdc_test_scoring_audit',
            ];
        }
        throw new RuntimeException('Invalid scoring calculation scope.');
    }
}
