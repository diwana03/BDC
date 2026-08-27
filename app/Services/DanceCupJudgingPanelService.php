<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupJudgingPanelService
{
    public static function prefix(bool $test):string{return $test?'bdc_test_dance_cup':'bdc_dance_cup';}

    public static function ensureTables(PDO $pdo,bool $test):void
    {
        $p=self::prefix($test);
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panels(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,panel_name VARCHAR(190) NOT NULL,discipline VARCHAR(80) NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_panel_name(event_id,panel_name),INDEX idx_dc_panel_event(event_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panel_categories(panel_id BIGINT UNSIGNED NOT NULL,competition_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 1,PRIMARY KEY(panel_id,competition_id),UNIQUE KEY uq_dc_panel_category(competition_id),INDEX idx_dc_panel_category_order(panel_id,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panel_judges(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,panel_id BIGINT UNSIGNED NOT NULL,judge_id BIGINT UNSIGNED NULL,judge_name VARCHAR(190) NOT NULL,judge_order INT UNSIGNED NOT NULL DEFAULT 1,is_chief TINYINT(1) NOT NULL DEFAULT 0,access_token CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dc_panel_judge_name(panel_id,judge_name),UNIQUE KEY uq_dc_panel_judge_token(access_token),INDEX idx_dc_panel_judge_order(panel_id,is_chief,judge_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function create(PDO $pdo,int $eventId,string $name,string $discipline,array $categoryIds,?int $userId,bool $test):int
    {
        $name=trim($name);$discipline=trim($discipline);$categoryIds=array_values(array_unique(array_filter(array_map('intval',$categoryIds))));
        if($eventId<1||$name===''||$discipline===''||!$categoryIds)throw new RuntimeException('Panel name, discipline and at least one category are required.');
        self::ensureTables($pdo,$test);$p=self::prefix($test);$t=DanceCupScoringService::tables($test);
        $placeholders=implode(',',array_fill(0,count($categoryIds),'?'));
        $q=$pdo->prepare("SELECT id,event_id,scoring_mode,status FROM {$t['competitions']} WHERE id IN ({$placeholders})");$q->execute($categoryIds);$rows=$q->fetchAll();
        if(count($rows)!==count($categoryIds))throw new RuntimeException('One or more selected categories no longer exist.');
        foreach($rows as $row){if((int)$row['event_id']!==$eventId)throw new RuntimeException('Every panel category must belong to the same Dance Cup event.');if((string)$row['scoring_mode']!=='automatic')throw new RuntimeException('Only Automatic categories can be assigned to a judging panel.');if(in_array((string)$row['status'],['submitted','pending_approval','approved'],true))throw new RuntimeException('A locked or published category cannot be added to a new panel.');$marks=$pdo->prepare("SELECT COUNT(*) FROM {$p}_marks WHERE competition_id=:competition");$marks->execute(['competition'=>$row['id']]);if((int)$marks->fetchColumn()>0)throw new RuntimeException('Categories with existing marks cannot be moved into a new judging panel.');}
        $pdo->beginTransaction();
        try{
            $insert=$pdo->prepare("INSERT INTO {$p}_judging_panels(event_id,panel_name,discipline,created_by) VALUES(:event,:name,:discipline,:user)");$insert->execute(['event'=>$eventId,'name'=>$name,'discipline'=>$discipline,'user'=>$userId]);$panelId=(int)$pdo->lastInsertId();
            $map=$pdo->prepare("INSERT INTO {$p}_judging_panel_categories(panel_id,competition_id,sort_order) VALUES(:panel,:competition,:sort)");foreach($categoryIds as $sort=>$competition)$map->execute(['panel'=>$panelId,'competition'=>$competition,'sort'=>$sort+1]);
            $pdo->commit();return $panelId;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function addJudge(PDO $pdo,int $panelId,?int $judgeId,string $judgeName,bool $chief,bool $test):void
    {
        $judgeName=trim($judgeName);if($panelId<1||$judgeName==='')throw new RuntimeException('Choose a judge from the Judge Database or enter a judge name.');
        self::ensureTables($pdo,$test);$p=self::prefix($test);$pdo->beginTransaction();
        try{
            $lock=$pdo->prepare("SELECT id FROM {$p}_judging_panels WHERE id=:panel AND status='active' FOR UPDATE");$lock->execute(['panel'=>$panelId]);if(!$lock->fetchColumn())throw new RuntimeException('Judging panel not found.');
            $started=$pdo->prepare("SELECT COUNT(*) FROM {$p}_judging_panel_categories pc JOIN {$p}_marks m ON m.competition_id=pc.competition_id WHERE pc.panel_id=:panel");$started->execute(['panel'=>$panelId]);if((int)$started->fetchColumn()>0)throw new RuntimeException('Panel judges cannot change after scoring has started.');
            $next=$pdo->prepare("SELECT COALESCE(MAX(judge_order),0)+1 FROM {$p}_judging_panel_judges WHERE panel_id=:panel");$next->execute(['panel'=>$panelId]);$order=(int)$next->fetchColumn();if($order===1)$chief=true;
            if($chief)$pdo->prepare("UPDATE {$p}_judging_panel_judges SET is_chief=0 WHERE panel_id=:panel")->execute(['panel'=>$panelId]);
            $insert=$pdo->prepare("INSERT INTO {$p}_judging_panel_judges(panel_id,judge_id,judge_name,judge_order,is_chief,access_token) VALUES(:panel,:judge,:name,:sort,:chief,:token)");$insert->execute(['panel'=>$panelId,'judge'=>$judgeId?:null,'name'=>$judgeName,'sort'=>$order,'chief'=>$chief?1:0,'token'=>bin2hex(random_bytes(32))]);
            self::sync($pdo,$panelId,$test);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function setChief(PDO $pdo,int $panelId,int $panelJudgeId,bool $test):void
    {
        self::ensureTables($pdo,$test);$p=self::prefix($test);self::assertPanelNotStarted($pdo,$p,$panelId);$pdo->beginTransaction();try{$pdo->prepare("UPDATE {$p}_judging_panel_judges SET is_chief=(id=:judge) WHERE panel_id=:panel")->execute(['judge'=>$panelJudgeId,'panel'=>$panelId]);self::sync($pdo,$panelId,$test);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function removeJudge(PDO $pdo,int $panelId,int $panelJudgeId,bool $test):void
    {
        self::ensureTables($pdo,$test);$p=self::prefix($test);self::assertPanelNotStarted($pdo,$p,$panelId);$pdo->beginTransaction();try{$pdo->prepare("DELETE FROM {$p}_judging_panel_judges WHERE id=:judge AND panel_id=:panel")->execute(['judge'=>$panelJudgeId,'panel'=>$panelId]);$chief=$pdo->prepare("SELECT COUNT(*) FROM {$p}_judging_panel_judges WHERE panel_id=:panel AND is_chief=1");$chief->execute(['panel'=>$panelId]);if((int)$chief->fetchColumn()===0)$pdo->prepare("UPDATE {$p}_judging_panel_judges SET is_chief=1 WHERE panel_id=:panel ORDER BY judge_order,id LIMIT 1")->execute(['panel'=>$panelId]);self::sync($pdo,$panelId,$test);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function assertPanelNotStarted(PDO $pdo,string $p,int $panelId):void
    {
        $started=$pdo->prepare("SELECT COUNT(*) FROM {$p}_judging_panel_categories pc JOIN {$p}_marks m ON m.competition_id=pc.competition_id WHERE pc.panel_id=:panel");$started->execute(['panel'=>$panelId]);if((int)$started->fetchColumn()>0)throw new RuntimeException('Panel judges cannot change after scoring has started.');
    }

    public static function sync(PDO $pdo,int $panelId,bool $test):void
    {
        $p=self::prefix($test);
        $categories=$pdo->prepare("SELECT competition_id FROM {$p}_judging_panel_categories WHERE panel_id=:panel ORDER BY sort_order,competition_id");$categories->execute(['panel'=>$panelId]);$categoryIds=array_map('intval',$categories->fetchAll(PDO::FETCH_COLUMN));
        $judges=$pdo->prepare("SELECT * FROM {$p}_judging_panel_judges WHERE panel_id=:panel ORDER BY is_chief DESC,judge_order,id");$judges->execute(['panel'=>$panelId]);$panelJudges=$judges->fetchAll();
        $add=$pdo->prepare("INSERT INTO {$p}_judges(competition_id,judge_id,judge_name,judge_order,is_chief) VALUES(:competition,:judge,:name,:sort,:chief) ON DUPLICATE KEY UPDATE judge_id=COALESCE(VALUES(judge_id),judge_id),judge_order=VALUES(judge_order),is_chief=VALUES(is_chief)");
        $find=$pdo->prepare("SELECT id FROM {$p}_judges WHERE competition_id=:competition AND judge_name=:name LIMIT 1");
        $session=$pdo->prepare("INSERT IGNORE INTO {$p}_judge_sessions(competition_id,judge_assignment_id,access_token) VALUES(:competition,:judge,:token)");
        if(!$panelJudges){foreach($categoryIds as $competition){$pdo->prepare("DELETE s FROM {$p}_judge_sessions s JOIN {$p}_judges j ON j.id=s.judge_assignment_id WHERE s.competition_id=:competition")->execute(['competition'=>$competition]);$pdo->prepare("DELETE FROM {$p}_judges WHERE competition_id=:competition")->execute(['competition'=>$competition]);}return;}
        $names=array_column($panelJudges,'judge_name');$nameMarks=implode(',',array_fill(0,count($names),'?'));
        foreach($categoryIds as $competition){$stale=$pdo->prepare("SELECT id FROM {$p}_judges WHERE competition_id=? AND judge_name NOT IN ({$nameMarks})");$stale->execute(array_merge([$competition],$names));$staleIds=array_map('intval',$stale->fetchAll(PDO::FETCH_COLUMN));if($staleIds){$idMarks=implode(',',array_fill(0,count($staleIds),'?'));$pdo->prepare("DELETE FROM {$p}_judge_sessions WHERE competition_id=? AND judge_assignment_id IN ({$idMarks})")->execute(array_merge([$competition],$staleIds));$pdo->prepare("DELETE FROM {$p}_judges WHERE competition_id=? AND id IN ({$idMarks})")->execute(array_merge([$competition],$staleIds));}foreach($panelJudges as $judge){$add->execute(['competition'=>$competition,'judge'=>$judge['judge_id']?:null,'name'=>$judge['judge_name'],'sort'=>$judge['judge_order'],'chief'=>$judge['is_chief']]);$find->execute(['competition'=>$competition,'name'=>$judge['judge_name']]);$assignment=(int)$find->fetchColumn();$session->execute(['competition'=>$competition,'judge'=>$assignment,'token'=>bin2hex(random_bytes(32))]);}}
    }

    public static function categorySessionsForToken(PDO $pdo,string $token,bool $test):array
    {
        self::ensureTables($pdo,$test);$p=self::prefix($test);$t=DanceCupScoringService::tables($test);
        $q=$pdo->prepare("SELECT pj.id panel_judge_id,pj.panel_id,pj.judge_name,pj.judge_order,pj.is_chief,p.panel_name,p.discipline,pc.sort_order,s.id,s.competition_id,s.judge_assignment_id,s.status,s.started_at,s.submitted_at,s.last_seen_at,c.category_name,c.round_name,c.dance_style,c.competition_level,e.name event_name,(SELECT COUNT(*) FROM {$p}_entries x WHERE x.competition_id=c.id AND x.status='active') entry_count,(SELECT COUNT(*) FROM {$t['criteria']} x WHERE x.competition_id=c.id) criterion_count,(SELECT COUNT(*) FROM {$p}_marks x WHERE x.competition_id=c.id AND x.judge_id=s.judge_assignment_id) mark_count FROM {$p}_judging_panel_judges pj JOIN {$p}_judging_panels p ON p.id=pj.panel_id JOIN {$p}_judging_panel_categories pc ON pc.panel_id=p.id JOIN {$t['competitions']} c ON c.id=pc.competition_id JOIN {$t['events']} e ON e.id=c.event_id JOIN {$p}_judges j ON j.competition_id=c.id AND ((pj.judge_id IS NOT NULL AND j.judge_id=pj.judge_id) OR (pj.judge_id IS NULL AND j.judge_name=pj.judge_name)) JOIN {$p}_judge_sessions s ON s.competition_id=c.id AND s.judge_assignment_id=j.id WHERE pj.access_token=:token AND p.status='active' ORDER BY pc.sort_order,c.id");
        $q->execute(['token'=>$token]);return $q->fetchAll();
    }
}
