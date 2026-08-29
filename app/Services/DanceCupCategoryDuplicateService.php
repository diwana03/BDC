<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupCategoryDuplicateService
{
    public static function duplicate(PDO $pdo,int $competitionId,?int $userId,bool $test=false):int
    {
        DanceCupScoringService::ensureWorkspaceTables($pdo,$test);$tables=DanceCupScoringService::tables($test);
        $q=$pdo->prepare("SELECT event_id,category_name,entry_type,dance_style,competition_level,gender_eligibility,performance_type,round_name,scoring_mode FROM {$tables['competitions']} WHERE id=:id LIMIT 1");
        $q->execute(['id'=>$competitionId]);$source=$q->fetch();if(!$source)throw new RuntimeException('Dance Cup category not found.');
        $criteria=$pdo->prepare("SELECT criterion_name,maximum_points FROM {$tables['criteria']} WHERE competition_id=:id ORDER BY sort_order,id");$criteria->execute(['id'=>$competitionId]);$rows=$criteria->fetchAll();if(!$rows)throw new RuntimeException('The source category has no scoring criteria to duplicate.');
        $base=trim((string)$source['category_name']).' Copy';$name=$base;$number=2;$exists=$pdo->prepare("SELECT COUNT(*) FROM {$tables['competitions']} WHERE event_id=:event AND category_name=:name AND round_name=:round");
        do{$exists->execute(['event'=>$source['event_id'],'name'=>$name,'round'=>$source['round_name']]);if(!(int)$exists->fetchColumn())break;$name=$base.' '.$number++;}while($number<1000);
        $newId=DanceCupScoringService::createCompetition($pdo,[...$source,'category_name'=>$name],array_map(static fn(array $row):array=>['name'=>(string)$row['criterion_name'],'max'=>(float)$row['maximum_points']],$rows),$userId,$test);
        $prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';
        try{
            $pdo->beginTransaction();
            $entries=$pdo->prepare("INSERT INTO {$prefix}_entries(competition_id,competitor_id,bib_number,display_name,status) SELECT :new_id,competitor_id,bib_number,display_name,status FROM {$prefix}_entries WHERE competition_id=:source_id ORDER BY id");
            $entries->execute(['new_id'=>$newId,'source_id'=>$competitionId]);
            $judges=$pdo->prepare("INSERT INTO {$prefix}_judges(competition_id,judge_id,judge_name,judge_order,is_chief) SELECT :new_id,judge_id,judge_name,judge_order,is_chief FROM {$prefix}_judges WHERE competition_id=:source_id ORDER BY judge_order,id");
            $judges->execute(['new_id'=>$newId,'source_id'=>$competitionId]);
            $pdo->commit();
            if((string)$source['scoring_mode']==='automatic')DanceCupScoringService::ensureAutomation($pdo,$newId,$test);
            return $newId;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare("DELETE FROM {$prefix}_judge_sessions WHERE competition_id=:id")->execute(['id'=>$newId]);
            $pdo->prepare("DELETE FROM {$prefix}_entries WHERE competition_id=:id")->execute(['id'=>$newId]);
            $pdo->prepare("DELETE FROM {$prefix}_judges WHERE competition_id=:id")->execute(['id'=>$newId]);
            $pdo->prepare("DELETE FROM {$tables['criteria']} WHERE competition_id=:id")->execute(['id'=>$newId]);
            $pdo->prepare("DELETE FROM {$tables['competitions']} WHERE id=:id AND status='draft'")->execute(['id'=>$newId]);
            throw $e;
        }
    }
}
