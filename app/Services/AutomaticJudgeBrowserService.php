<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class AutomaticJudgeBrowserService
{
    public static function isSetupConfirmed(PDO $pdo,int $roundId):bool
    {
        try{
            $stmt=$pdo->prepare('SELECT confirmed_at FROM bdc_scoring_round_setup WHERE round_id=:round LIMIT 1');
            $stmt->execute(['round'=>$roundId]);
            return (bool)$stmt->fetchColumn();
        }catch(\Throwable){
            return false;
        }
    }

    public static function syncRound(PDO $pdo,int $roundId):array
    {
        $round=self::round($pdo,$roundId);
        if(($round['scoring_mode']??'manual')!=='automated'||!self::isSetupConfirmed($pdo,$roundId))return [];
        $judges=$pdo->prepare('SELECT id,judge_name,judge_order,is_chief,scoring_scope FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order');
        $judges->execute(['round'=>$roundId]);
        $items=[];
        foreach($judges->fetchAll() as $judge){
            $session=self::sessionForJudge($pdo,(int)$judge['id']);
            $plain='';
            if(!$session){
                [$session,$plain]=self::createSession($pdo,$roundId,(int)$judge['id']);
            }
            $items[]=$judge+['session'=>$session,'plain_token'=>$plain];
        }
        return $items;
    }

    public static function regenerate(PDO $pdo,int $roundId,int $judgeId):string
    {
        if(!self::isSetupConfirmed($pdo,$roundId))throw new RuntimeException('Confirm judges and competitors before generating judge links.');
        $stmt=$pdo->prepare('SELECT id FROM bdc_scoring_judges WHERE id=:judge AND round_id=:round');
        $stmt->execute(['judge'=>$judgeId,'round'=>$roundId]);
        if(!(int)$stmt->fetchColumn())throw new RuntimeException('Judge not found for this round.');
        $token=bin2hex(random_bytes(24));
        $hash=hash('sha256',$token);
        $hint=substr($token,0,8);
        $pdo->prepare("INSERT INTO bdc_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status)
            VALUES(:round,:judge,:hash,:hint,'not_started')
            ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),status='not_started',opened_at=NULL,last_saved_at=NULL,submitted_at=NULL,updated_at=NOW()")
            ->execute(['round'=>$roundId,'judge'=>$judgeId,'hash'=>$hash,'hint'=>$hint]);
        return $token;
    }

    public static function byToken(PDO $pdo,string $token):?array
    {
        if(!preg_match('/^[a-f0-9]{48}$/',$token))return null;
        $stmt=$pdo->prepare("SELECT s.*,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,
            r.round_type,r.division,r.scoring_mode,r.status round_status,e.name event_name,e.event_date
            FROM bdc_scoring_judge_sessions s
            JOIN bdc_scoring_judges j ON j.id=s.judge_id
            JOIN bdc_scoring_rounds r ON r.id=s.round_id
            JOIN bdc_events e ON e.id=r.event_id
            WHERE s.token_hash=:hash LIMIT 1");
        $stmt->execute(['hash'=>hash('sha256',$token)]);
        $row=$stmt->fetch()?:null;
        if($row&&!self::isSetupConfirmed($pdo,(int)$row['round_id']))return null;
        return $row;
    }

    public static function markOpened(PDO $pdo,int $sessionId):void
    {
        $pdo->prepare("UPDATE bdc_scoring_judge_sessions
            SET status=CASE WHEN status='not_started' THEN 'scoring' ELSE status END,
                opened_at=COALESCE(opened_at,NOW()) WHERE id=:id")
            ->execute(['id'=>$sessionId]);
    }

    public static function markSaved(PDO $pdo,int $sessionId):void
    {
        $pdo->prepare("UPDATE bdc_scoring_judge_sessions SET status='scoring',last_saved_at=NOW() WHERE id=:id AND status<>'submitted'")
            ->execute(['id'=>$sessionId]);
    }

    public static function submit(PDO $pdo,int $sessionId):void
    {
        $pdo->prepare("UPDATE bdc_scoring_judge_sessions SET status='submitted',last_saved_at=NOW(),submitted_at=NOW() WHERE id=:id")
            ->execute(['id'=>$sessionId]);
    }

    public static function unlock(PDO $pdo,int $roundId,int $judgeId,int $userId,string $reason):void
    {
        $reason=trim($reason);
        if($reason==='')throw new RuntimeException('Enter a reason before unlocking judge scores.');
        $stmt=$pdo->prepare("UPDATE bdc_scoring_judge_sessions
            SET status='scoring',submitted_at=NULL,unlocked_at=NOW(),unlocked_by=:user,unlock_reason=:reason
            WHERE round_id=:round AND judge_id=:judge");
        $stmt->execute(['user'=>$userId,'reason'=>$reason,'round'=>$roundId,'judge'=>$judgeId]);
        if($stmt->rowCount()!==1)throw new RuntimeException('Judge scoring session not found.');
    }

    public static function progress(PDO $pdo,int $roundId):array
    {
        if(!self::isSetupConfirmed($pdo,$roundId))return [];
        $round=self::round($pdo,$roundId);
        $final=($round['round_type']??'')==='final';
        $stmt=$pdo->prepare("SELECT j.id judge_id,j.judge_name,j.judge_order,j.is_chief,j.scoring_scope,
            COALESCE(s.status,'not_started') session_status,s.token_hint,s.opened_at,s.last_saved_at,s.submitted_at
            FROM bdc_scoring_judges j
            LEFT JOIN bdc_scoring_judge_sessions s ON s.judge_id=j.id
            WHERE j.round_id=:round ORDER BY j.judge_order");
        $stmt->execute(['round'=>$roundId]);
        $rows=$stmt->fetchAll();
        foreach($rows as &$row){
            if($final){
                $totalStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");
                $totalStmt->execute(['round'=>$roundId]);
                $total=(int)$totalStmt->fetchColumn();
                $doneStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_marks WHERE round_id=:round AND judge_id=:judge AND rank_value IS NOT NULL");
                $doneStmt->execute(['round'=>$roundId,'judge'=>$row['judge_id']]);
                $done=(int)$doneStmt->fetchColumn();
                $row['leaders_done']=$done;$row['leaders_total']=$total;$row['followers_done']=0;$row['followers_total']=0;
                $row['done']=$done;$row['total']=$total;
            }else{
                $scope=(string)$row['scoring_scope'];
                $counts=[];
                foreach(['leader','follower'] as $role){
                    $allowed=$scope==='all'||$scope===$role;
                    if(!$allowed){$counts[$role]=[0,0];continue;}
                    $totalStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active'");
                    $totalStmt->execute(['round'=>$roundId,'role'=>$role]);
                    $total=(int)$totalStmt->fetchColumn();
                    $doneStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_marks m JOIN bdc_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.weighted_score IS NOT NULL");
                    $doneStmt->execute(['round'=>$roundId,'judge'=>$row['judge_id'],'role'=>$role]);
                    $counts[$role]=[(int)$doneStmt->fetchColumn(),$total];
                }
                [$ld,$lt]=$counts['leader'];[$fd,$ft]=$counts['follower'];
                $row['leaders_done']=$ld;$row['leaders_total']=$lt;$row['followers_done']=$fd;$row['followers_total']=$ft;
                $row['done']=$ld+$fd;$row['total']=$lt+$ft;
            }
            $row['percent']=$row['total']>0?(int)round($row['done']*100/$row['total']):0;
        }
        unset($row);
        return $rows;
    }

    public static function allSubmitted(PDO $pdo,int $roundId):bool
    {
        if(!self::isSetupConfirmed($pdo,$roundId))return false;
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_judges j
            LEFT JOIN bdc_scoring_judge_sessions s ON s.judge_id=j.id
            WHERE j.round_id=:round AND COALESCE(s.status,'not_started')<>'submitted'");
        $stmt->execute(['round'=>$roundId]);
        $judgeCount=$pdo->prepare('SELECT COUNT(*) FROM bdc_scoring_judges WHERE round_id=:round');
        $judgeCount->execute(['round'=>$roundId]);
        return (int)$judgeCount->fetchColumn()>=3 && (int)$stmt->fetchColumn()===0;
    }

    public static function publicUrl(string $token):string
    {
        $path=url('judge-scoring/?token='.rawurlencode($token));
        $appUrl=rtrim((string)Config::get('app.url',''),'/');
        if($appUrl==='')return $path;
        $parts=parse_url($appUrl);
        if(!is_array($parts)||!isset($parts['scheme'],$parts['host']))return $path;
        $origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'');
        return $origin.$path;
    }

    private static function createSession(PDO $pdo,int $roundId,int $judgeId):array
    {
        $token=bin2hex(random_bytes(24));
        $hash=hash('sha256',$token);$hint=substr($token,0,8);
        $pdo->prepare("INSERT INTO bdc_scoring_judge_sessions(round_id,judge_id,token_hash,token_hint,status) VALUES(:round,:judge,:hash,:hint,'not_started')")
            ->execute(['round'=>$roundId,'judge'=>$judgeId,'hash'=>$hash,'hint'=>$hint]);
        return [[
            'id'=>(int)$pdo->lastInsertId(),'round_id'=>$roundId,'judge_id'=>$judgeId,'token_hash'=>$hash,'token_hint'=>$hint,'status'=>'not_started'
        ],$token];
    }

    private static function sessionForJudge(PDO $pdo,int $judgeId):?array
    {
        $stmt=$pdo->prepare('SELECT * FROM bdc_scoring_judge_sessions WHERE judge_id=:judge LIMIT 1');
        $stmt->execute(['judge'=>$judgeId]);
        return $stmt->fetch()?:null;
    }

    private static function round(PDO $pdo,int $roundId):array
    {
        $stmt=$pdo->prepare('SELECT * FROM bdc_scoring_rounds WHERE id=:round LIMIT 1');
        $stmt->execute(['round'=>$roundId]);
        $round=$stmt->fetch();
        if(!$round)throw new RuntimeException('Scoring round not found.');
        return $round;
    }
}
