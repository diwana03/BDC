<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class TestAutomaticJudgeService
{
    public static function ensureSchema(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_test_scoring_judge_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id BIGINT UNSIGNED NOT NULL,
            judge_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_hint VARCHAR(12) NOT NULL,
            status ENUM('not_started','scoring','submitted') NOT NULL DEFAULT 'not_started',
            opened_at DATETIME NULL,
            last_saved_at DATETIME NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uq_test_judge_session_judge(judge_id),
            UNIQUE KEY uq_test_judge_session_token(token_hash),
            KEY idx_test_judge_session_round(round_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function syncRound(PDO $pdo,int $roundId):array
    {
        self::ensureSchema($pdo);$judges=self::judges($pdo,$roundId);$items=[];
        foreach($judges as $judge){$session=self::sessionForJudge($pdo,(int)$judge['id']);$plain='';if(!$session){[$session,$plain]=self::createSession($pdo,$roundId,(int)$judge['id']);}$items[]=$judge+['session'=>$session,'plain_token'=>$plain];}
        return $items;
    }

    public static function regenerate(PDO $pdo,int $roundId,int $judgeId):string
    {
        self::ensureSchema($pdo);$stmt=$pdo->prepare('SELECT id FROM bdc_test_scoring_judges WHERE id=:judge AND round_id=:round');$stmt->execute(['judge'=>$judgeId,'round'=>$roundId]);if(!(int)$stmt->fetchColumn())throw new RuntimeException('Test judge not found.');
        $token=bin2hex(random_bytes(24));$pdo->prepare("INSERT INTO bdc_test_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status) VALUES(:round,:judge,:hash,:hint,'not_started') ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),status='not_started',opened_at=NULL,last_saved_at=NULL,submitted_at=NULL,updated_at=NOW()")
            ->execute(['round'=>$roundId,'judge'=>$judgeId,'hash'=>hash('sha256',$token),'hint'=>substr($token,0,8)]);return $token;
    }

    public static function byToken(PDO $pdo,string $token):?array
    {
        self::ensureSchema($pdo);if(!preg_match('/^[a-f0-9]{48}$/',$token))return null;$stmt=$pdo->prepare("SELECT s.*,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,r.round_type,r.division,e.name event_name FROM bdc_test_scoring_judge_sessions s JOIN bdc_test_scoring_judges j ON j.id=s.judge_id JOIN bdc_test_scoring_rounds r ON r.id=s.round_id JOIN bdc_test_events e ON e.id=r.event_id WHERE s.token_hash=:hash LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);return $stmt->fetch()?:null;
    }

    public static function markOpened(PDO $pdo,int $sessionId):void{$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status=CASE WHEN status='not_started' THEN 'scoring' ELSE status END,opened_at=COALESCE(opened_at,NOW()) WHERE id=:id")->execute(['id'=>$sessionId]);}
    public static function markSaved(PDO $pdo,int $sessionId):void{$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='scoring',last_saved_at=NOW() WHERE id=:id AND status<>'submitted'")->execute(['id'=>$sessionId]);}
    public static function submit(PDO $pdo,int $sessionId):void{$pdo->prepare("UPDATE bdc_test_scoring_judge_sessions SET status='submitted',last_saved_at=NOW(),submitted_at=NOW() WHERE id=:id AND status<>'submitted'")->execute(['id'=>$sessionId]);}

    public static function progress(PDO $pdo,int $roundId):array
    {
        self::ensureSchema($pdo);$roundStmt=$pdo->prepare('SELECT round_type,yes_count FROM bdc_test_scoring_rounds WHERE id=:round');$roundStmt->execute(['round'=>$roundId]);$round=$roundStmt->fetch()?:['round_type'=>'heats','yes_count'=>10];$final=(string)$round['round_type']==='final';$yesLimit=max(0,(int)$round['yes_count'];
        $stmt=$pdo->prepare("SELECT j.id judge_id,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,COALESCE(s.status,'not_started') session_status,s.token_hint,s.opened_at,s.last_saved_at,s.submitted_at FROM bdc_test_scoring_judges j LEFT JOIN bdc_test_scoring_judge_sessions s ON s.judge_id=j.id WHERE j.round_id=:round ORDER BY j.judge_order");$stmt->execute(['round'=>$roundId]);$rows=$stmt->fetchAll();
        foreach($rows as &$row){$submitted=(string)$row['session_status']==='submitted';
            if($final){$totalStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");$totalStmt->execute(['round'=>$roundId]);$total=(int)$totalStmt->fetchColumn();$doneStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_final_marks WHERE round_id=:round AND judge_id=:judge AND rank_value IS NOT NULL");$doneStmt->execute(['round'=>$roundId,'judge'=>$row['judge_id']]);$done=(int)$doneStmt->fetchColumn();if($submitted)$done=$total;$row['leaders_done']=$done;$row['leaders_total']=$total;$row['followers_done']=0;$row['followers_total']=0;$row['done']=$done;$row['total']=$total;
            }else{$scope=(string)$row['scoring_scope'];$counts=[];foreach(['leader','follower'] as $role){$allowed=$scope==='all'||$scope===$role;if(!$allowed){$counts[$role]=[0,0];continue;}$q=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active'");$q->execute(['round'=>$roundId,'role'=>$role]);$active=(int)$q->fetchColumn();$target=min($active,$yesLimit+3);$q=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_marks m JOIN bdc_test_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.mark_type IN('yes','alt')");$q->execute(['round'=>$roundId,'judge'=>$row['judge_id'],'role'=>$role]);$done=min((int)$q->fetchColumn(),$target);if($submitted)$done=$target;$counts[$role]=[$done,$target];}[$ld,$lt]=$counts['leader'];[$fd,$ft]=$counts['follower'];$row['leaders_done']=$ld;$row['leaders_total']=$lt;$row['followers_done']=$fd;$row['followers_total']=$ft;$row['done']=$ld+$fd;$row['total']=$lt+$ft;}
            $row['percent']=$submitted?100:($row['total']>0?(int)round($row['done']*100/$row['total']):0);
        }unset($row);return $rows;
    }

    public static function publicUrl(string $token):string
    {
        $path=url('test-judge-scoring/?token='.rawurlencode($token));$appUrl=rtrim((string)Config::get('app.url',''),'/');if($appUrl==='')return $path;$parts=parse_url($appUrl);if(!is_array($parts)||!isset($parts['scheme'],$parts['host']))return $path;return $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'').$path;
    }

    public static function clearJudgeSessions(PDO $pdo,int $roundId):void{self::ensureSchema($pdo);$pdo->prepare('DELETE FROM bdc_test_scoring_judge_sessions WHERE round_id=:round')->execute(['round'=>$roundId]);}
    private static function judges(PDO $pdo,int $roundId):array{$stmt=$pdo->prepare('SELECT id,judge_name,judge_order,is_chief,scoring_scope FROM bdc_test_scoring_judges WHERE round_id=:round ORDER BY judge_order');$stmt->execute(['round'=>$roundId]);return $stmt->fetchAll();}
    private static function createSession(PDO $pdo,int $roundId,int $judgeId):array{$token=bin2hex(random_bytes(24));$session=['round_id'=>$roundId,'judge_id'=>$judgeId,'token_hash'=>hash('sha256',$token),'token_hint'=>substr($token,0,8),'status'=>'not_started'];$pdo->prepare("INSERT INTO bdc_test_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status) VALUES(:round_id,:judge_id,:token_hash,:token_hint,:status)")->execute($session);$session['id']=(int)$pdo->lastInsertId();return [$session,$token];}
    private static function sessionForJudge(PDO $pdo,int $judgeId):?array{$stmt=$pdo->prepare('SELECT * FROM bdc_test_scoring_judge_sessions WHERE judge_id=:judge LIMIT 1');$stmt->execute(['judge'=>$judgeId]);return $stmt->fetch()?:null;}
}
