<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ScoringEventDuplicateService
{
    private static function columns(PDO $pdo,string $table):array
    {
        $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION');$q->execute(['table'=>$table]);return array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function insert(PDO $pdo,string $table,array $values):int
    {
        $columns=array_keys($values);$names=implode(',',array_map(static fn(string $name):string=>'`'.$name.'`',$columns));$marks=implode(',',array_map(static fn(string $name):string=>':'.$name,$columns));
        $pdo->prepare("INSERT INTO `{$table}` ({$names}) VALUES ({$marks})")->execute($values);return (int)$pdo->lastInsertId();
    }

    public static function duplicate(PDO $pdo,int $eventId,?int $userId,bool $test=false):array
    {
        $eventTable=$test?'bdc_test_events':'bdc_events';$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $event=$pdo->prepare("SELECT * FROM `{$eventTable}` WHERE id=:id LIMIT 1");$event->execute(['id'=>$eventId]);$sourceEvent=$event->fetch();if(!$sourceEvent)throw new RuntimeException('Jack & Jill event not found.');
        $rounds=$pdo->prepare("SELECT * FROM `{$roundTable}` WHERE event_id=:event AND status<>'archived' ORDER BY id");$rounds->execute(['event'=>$eventId]);$sourceRounds=$rounds->fetchAll();if(!$sourceRounds)throw new RuntimeException('This event has no active Jack & Jill scoring setup to duplicate.');
        $eventColumns=self::columns($pdo,$eventTable);$roundColumns=self::columns($pdo,$roundTable);
        $base=trim((string)$sourceEvent['name']).' Copy';$name=$base;$number=2;$check=$pdo->prepare("SELECT COUNT(*) FROM `{$eventTable}` WHERE name=:name");do{$check->execute(['name'=>$name]);if(!(int)$check->fetchColumn())break;$name=$base.' '.$number++;}while($number<1000);
        $slugBase=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'))?:'event-copy';$slug=$slugBase;$slugNumber=2;if(in_array('slug',$eventColumns,true)){$slugCheck=$pdo->prepare("SELECT COUNT(*) FROM `{$eventTable}` WHERE slug=:slug");do{$slugCheck->execute(['slug'=>$slug]);if(!(int)$slugCheck->fetchColumn())break;$slug=$slugBase.'-'.$slugNumber++;}while($slugNumber<1000);}
        $eventAllowed=['name','normalised_name','slug','event_date','location','venue','country','organiser_name','organiser_email','website_url','banner_url','event_mode','points_tier','scoring_mode','created_by'];$eventValues=[];foreach($eventAllowed as $column)if(in_array($column,$eventColumns,true))$eventValues[$column]=$sourceEvent[$column]??null;$eventValues['name']=$name;if(isset($eventValues['normalised_name']))$eventValues['normalised_name']=mb_strtolower($name);if(isset($eventValues['slug']))$eventValues['slug']=$slug;if(in_array('status',$eventColumns,true))$eventValues['status']='draft';if(array_key_exists('created_by',$eventValues))$eventValues['created_by']=$userId;
        $roundAllowed=['dance_style','round_type','scoring_mode','division','yes_count','callback_count','yes_weight','alt1_weight','alt2_weight','alt3_weight','tier_manual_override','scheduled_at','created_by'];
        $pdo->beginTransaction();try{
            $newEventId=self::insert($pdo,$eventTable,$eventValues);$map=[];$relations=[];
            foreach($sourceRounds as $round){$values=['event_id'=>$newEventId];foreach($roundAllowed as $column)if(in_array($column,$roundColumns,true))$values[$column]=$round[$column]??null;if(isset($values['scheduled_at']))$values['scheduled_at']=null;if(array_key_exists('created_by',$values))$values['created_by']=$userId;if(in_array('status',$roundColumns,true))$values['status']='draft';if(in_array('chief_judge_id',$roundColumns,true))$values['chief_judge_id']=null;if(in_array('parent_round_id',$roundColumns,true))$values['parent_round_id']=null;if(in_array('source_round_id',$roundColumns,true))$values['source_round_id']=null;$newRoundId=self::insert($pdo,$roundTable,$values);$map[(int)$round['id']]=$newRoundId;$relations[$newRoundId]=['parent'=>(int)($round['parent_round_id']??0),'source'=>(int)($round['source_round_id']??0)];}
            if(in_array('parent_round_id',$roundColumns,true)||in_array('source_round_id',$roundColumns,true)){foreach($relations as $newRoundId=>$relation){$sets=[];$params=['id'=>$newRoundId];if(in_array('parent_round_id',$roundColumns,true)&&isset($map[$relation['parent']])){$sets[]='parent_round_id=:parent';$params['parent']=$map[$relation['parent']];}if(in_array('source_round_id',$roundColumns,true)&&isset($map[$relation['source']])){$sets[]='source_round_id=:source';$params['source']=$map[$relation['source']];}if($sets)$pdo->prepare("UPDATE `{$roundTable}` SET ".implode(',',$sets).' WHERE id=:id')->execute($params);}}
            $entryTable=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';$judgeTable=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';$entryColumns=self::columns($pdo,$entryTable);$judgeColumns=self::columns($pdo,$judgeTable);$entryAllowed=['competitor_id','dance_role','bib_number','display_name','entry_status'];$judgeAllowed=['judge_id','judge_name','judge_order','is_chief','scoring_scope'];$newJudgeIds=[];
            foreach($sourceRounds as $round){$sourceRoundId=(int)$round['id'];$newRoundId=$map[$sourceRoundId];$entryQuery=$pdo->prepare("SELECT * FROM `{$entryTable}` WHERE round_id=:round ORDER BY id");$entryQuery->execute(['round'=>$sourceRoundId]);foreach($entryQuery->fetchAll() as $entry){$values=['round_id'=>$newRoundId];foreach($entryAllowed as $column)if(in_array($column,$entryColumns,true))$values[$column]=$entry[$column]??null;self::insert($pdo,$entryTable,$values);}$judgeQuery=$pdo->prepare("SELECT * FROM `{$judgeTable}` WHERE round_id=:round ORDER BY judge_order,id");$judgeQuery->execute(['round'=>$sourceRoundId]);$chiefId=null;foreach($judgeQuery->fetchAll() as $judge){$values=['round_id'=>$newRoundId];foreach($judgeAllowed as $column)if(in_array($column,$judgeColumns,true))$values[$column]=$judge[$column]??null;$newJudgeId=self::insert($pdo,$judgeTable,$values);$newJudgeIds[]=[$newRoundId,$newJudgeId,(string)($round['scoring_mode']??'manual')];if((int)($judge['is_chief']??0)===1)$chiefId=$newJudgeId;}if($chiefId&&in_array('chief_judge_id',$roundColumns,true))$pdo->prepare("UPDATE `{$roundTable}` SET chief_judge_id=:chief WHERE id=:round")->execute(['chief'=>$chiefId,'round'=>$newRoundId]);}
            $pdo->commit();
            foreach($newJudgeIds as [$newRoundId,$newJudgeId,$scoringMode])if($scoringMode==='automated'){try{if($test)TestAutomaticJudgeService::regenerate($pdo,$newRoundId,$newJudgeId);else AutomaticJudgeBrowserService::regenerate($pdo,$newRoundId,$newJudgeId);}catch(\Throwable){/* The Judge Links screen can safely regenerate a fresh token. */}}
            return ['event_id'=>$newEventId,'name'=>$name,'round_count'=>count($map),'first_round_id'=>(int)reset($map)];
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
