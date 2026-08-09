<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class TestCompetitorGeneratorService
{
    public static function generate(PDO $pdo,int $roundId,int $leaderCount,int $followerCount,?int $userId=null):array
    {
        if($roundId<1)throw new RuntimeException('Open a test round first.');
        $leaderCount=max(0,min(500,$leaderCount));
        $followerCount=max(0,min(500,$followerCount));

        $roundStmt=$pdo->prepare('SELECT id FROM bdc_test_scoring_rounds WHERE id=:r LIMIT 1');
        $roundStmt->execute(['r'=>$roundId]);
        if(!$roundStmt->fetchColumn())throw new RuntimeException('Test scoring round not found.');

        $select=$pdo->prepare("SELECT id,bdc_id,exact_name,normalised_name,dance_role,current_division,country,status,is_historical FROM bdc_competitors WHERE status='active' AND dance_role=:role ORDER BY RAND() LIMIT :limit_count");
        $copy=$pdo->prepare("INSERT INTO bdc_test_competitors(id,bdc_id,exact_name,normalised_name,dance_role,current_division,country,status,is_historical) VALUES(:id,:bdc_id,:exact_name,:normalised_name,:dance_role,:current_division,:country,:status,:is_historical) ON DUPLICATE KEY UPDATE bdc_id=VALUES(bdc_id),exact_name=VALUES(exact_name),normalised_name=VALUES(normalised_name),dance_role=VALUES(dance_role),current_division=VALUES(current_division),country=VALUES(country),status=VALUES(status),is_historical=VALUES(is_historical)");
        $entry=$pdo->prepare("INSERT INTO bdc_test_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active') ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'");

        $pdo->beginTransaction();
        try{
            $counts=['leader'=>$leaderCount,'follower'=>$followerCount];
            foreach($counts as $role=>$count){
                if($count===0)continue;
                $select->bindValue(':role',$role,PDO::PARAM_STR);
                $select->bindValue(':limit_count',$count,PDO::PARAM_INT);
                $select->execute();
                $rows=$select->fetchAll(PDO::FETCH_ASSOC);
                $bibStmt=$pdo->prepare("SELECT COALESCE(MAX(bib_number),0) FROM bdc_test_scoring_entries WHERE round_id=:r AND dance_role=:role");
                $bibStmt->execute(['r'=>$roundId,'role'=>$role]);
                $bib=(int)$bibStmt->fetchColumn();
                foreach($rows as $row){
                    $copy->execute([
                        'id'=>(int)$row['id'],
                        'bdc_id'=>$row['bdc_id'],
                        'exact_name'=>$row['exact_name'],
                        'normalised_name'=>$row['normalised_name'],
                        'dance_role'=>$row['dance_role'],
                        'current_division'=>$row['current_division'],
                        'country'=>$row['country'],
                        'status'=>$row['status'],
                        'is_historical'=>(int)$row['is_historical'],
                    ]);
                    $entry->execute([
                        'round'=>$roundId,
                        'competitor'=>(int)$row['id'],
                        'role'=>$role,
                        'bib'=>++$bib,
                        'name'=>$row['exact_name'],
                    ]);
                }
            }

            $countStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' GROUP BY dance_role");
            $countStmt->execute(['r'=>$roundId]);
            $roleCounts=['leader'=>0,'follower'=>0];
            foreach($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row)$roleCounts[(string)$row['dance_role']]=(int)$row['total'];
            $tier=ScoringRulesService::tierFromRoleCounts($roleCounts['leader'],$roleCounts['follower']);
            $pdo->prepare("UPDATE bdc_test_scoring_rounds SET yes_count=:yes,callback_count=:cb,tier_manual_override=0 WHERE id=:r")
                ->execute(['yes'=>$tier['yes_count'],'cb'=>$tier['yes_count'],'r'=>$roundId]);

            $audit=$pdo->prepare("INSERT INTO bdc_test_scoring_audit(round_id,user_id,action,details_json) VALUES(:r,:u,'random_test_competitors_generated_safe',:d)");
            $audit->execute([
                'r'=>$roundId,
                'u'=>$userId?:null,
                'd'=>json_encode([
                    'requested'=>['leaders'=>$leaderCount,'followers'=>$followerCount],
                    'active_counts'=>$roleCounts,
                    'tier'=>$tier['tier'],
                    'photo_fields_copied'=>false,
                ],JSON_UNESCAPED_UNICODE),
            ]);
            $pdo->commit();
            return ['leaders'=>$roleCounts['leader'],'followers'=>$roleCounts['follower'],'tier'=>$tier['tier'],'largest'=>$tier['largest']];
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }
}
