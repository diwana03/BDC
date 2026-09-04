<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Config;
use PDO;
use RuntimeException;

final class MobileProjectionRemoteService
{
    public static function ensure(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_mobile_projection_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,data_mode ENUM('real','test') NOT NULL DEFAULT 'real',token_hash CHAR(64) NOT NULL,token_value CHAR(48) NULL,status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',expires_at DATETIME NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_mobile_projection_session_event(session_id,event_id,data_mode),UNIQUE INDEX uq_mobile_projection_token(token_hash),INDEX idx_mobile_projection_expiry(status,expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function generate(PDO $pdo,int $sessionId,int $eventId,bool $test,int $userId):string
    {
        self::ensure($pdo);
        $session=LiveDisplaySessionService::byId($pdo,$sessionId,$test);
        if(!$session)throw new RuntimeException('Generate the Live Display link first.');
        self::assertEventMember($pdo,$session,$eventId,$test);
        $token=bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO bdc_mobile_projection_links(session_id,event_id,data_mode,token_hash,token_value,status,expires_at,created_by) VALUES(:s,:e,:m,:h,:t,'active',DATE_ADD(NOW(),INTERVAL 12 HOUR),:u) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_value=VALUES(token_value),status='active',expires_at=VALUES(expires_at),created_by=VALUES(created_by),updated_at=NOW()")->execute(['s'=>$sessionId,'e'=>$eventId,'m'=>$test?'test':'real','h'=>hash('sha256',$token),'t'=>$token,'u'=>$userId?:null]);
        return $token;
    }

    public static function activeLink(PDO $pdo,int $sessionId,int $eventId,bool $test):?array
    {
        self::ensure($pdo);$q=$pdo->prepare("SELECT token_value,expires_at FROM bdc_mobile_projection_links WHERE session_id=:s AND event_id=:e AND data_mode=:m AND status='active' AND expires_at>NOW() LIMIT 1");$q->execute(['s'=>$sessionId,'e'=>$eventId,'m'=>$test?'test':'real']);$row=$q->fetch();if(!$row||empty($row['token_value']))return null;$row['url']=self::url((string)$row['token_value']);return $row;
    }

    public static function byToken(PDO $pdo,string $token):?array
    {
        self::ensure($pdo);if(!preg_match('/^[a-f0-9]{48}$/',$token))return null;$q=$pdo->prepare("SELECT * FROM bdc_mobile_projection_links WHERE token_hash=:h AND status='active' AND expires_at>NOW() LIMIT 1");$q->execute(['h'=>hash('sha256',$token)]);return $q->fetch()?:null;
    }

    public static function state(PDO $pdo,array $link):array
    {
        $test=$link['data_mode']==='test';$session=LiveDisplaySessionService::byId($pdo,(int)$link['session_id'],$test);if(!$session)throw new RuntimeException('The projector session is no longer available.');self::assertActiveEvent($session,(int)$link['event_id']);
        $roundId=(int)($session['current_round_id']??0);$round=null;if($roundId>0){$table=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$q=$pdo->prepare("SELECT id,division,round_type FROM {$table} WHERE id=:r AND event_id=:e LIMIT 1");$q->execute(['r'=>$roundId,'e'=>(int)$link['event_id']]);$round=$q->fetch()?:null;}
        return ['session'=>$session,'round'=>$round,'judges'=>$round?self::assignedJudges($pdo,(int)$round['id'],$test):[]];
    }

    public static function assignedJudges(PDO $pdo,int $roundId,bool $test):array
    {
        $table=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
        $q=$pdo->prepare("SELECT judge_order,judge_name,is_chief,scoring_scope FROM {$table} WHERE round_id=:r ORDER BY is_chief DESC,judge_order,id");
        $q->execute(['r'=>$roundId]);
        $rows=$q->fetchAll()?:[];
        return array_map(static function(array $row,int $index):array{
            $scope=(string)($row['scoring_scope']??'all');
            return [
                'page'=>$index+1,
                'judge_order'=>(int)($row['judge_order']??0),
                'judge_name'=>(string)($row['judge_name']??'Judge'),
                'is_chief'=>(int)($row['is_chief']??0)===1,
                'scope_label'=>$scope==='leader'?'Leaders Only':($scope==='follower'?'Followers Only':'Leaders & Followers'),
            ];
        },$rows,array_keys($rows));
    }

    public static function command(PDO $pdo,array $link,string $action,array $input):array
    {
        $test=$link['data_mode']==='test';$state=self::state($pdo,$link);$session=$state['session'];$round=$state['round'];if(!$round)throw new RuntimeException('Select this event and round from the main Projection Control first.');$sessionId=(int)$session['id'];$eventId=(int)$link['event_id'];
        if($action==='effect'){$effect=(string)($input['effect_type']??'none');if(!in_array($effect,['none','countdown','hearts','balloons','heart_smiles','finger_hearts'],true))throw new RuntimeException('This effect is not available on the mobile remote.');return LiveDisplaySessionService::effect($pdo,$eventId,$test,$effect,0,$sessionId);}
        $safe=self::safeScreens((string)$round['round_type']);$screen=(string)($input['screen_type']??$session['screen_type']??'holding');if($action==='screen'&&!in_array($screen,array_keys($safe),true))throw new RuntimeException('This projector screen is protected from the mobile remote.');if($action!=='screen'&&!in_array((string)$session['screen_type'],array_keys($safe),true))throw new RuntimeException('Return to a mobile-safe screen before using page controls.');
        $page=max(1,(int)($session['page_number']??1));if($action==='screen')$page=max(1,(int)($input['page_number']??1));elseif($action==='previous_page')$page=max(1,$page-1);elseif($action==='next_page')$page++;$auto=(bool)($session['auto_page']??false);if($action==='auto_on')$auto=true;elseif($action==='auto_off')$auto=false;
        $delay=(int)($session['page_delay_seconds']??15);if($action==='set_page_delay')$delay=(int)($input['page_delay_seconds']??15);if(!in_array($delay,[5,10,15,20,30,45,60],true))throw new RuntimeException('Choose a valid page delay.');
        if($action==='screen'&&$screen==='flights'){$summary=ScoringFlightService::summary($pdo,(int)$round['id'],$test);$available=array_map(static fn(array $flight):int=>(int)($flight['number']??0),$summary['flights']??[]);if(!in_array($page,$available,true))throw new RuntimeException('That Flight Round is not configured for this scoring round.');}
        if(!in_array($action,['screen','previous_page','next_page','auto_on','auto_off','set_page_delay'],true))throw new RuntimeException('Unsupported mobile remote command.');
        $callbackReveal=$action==='screen'&&$screen==='callbacks';
        $updated=LiveDisplaySessionService::update($pdo,$eventId,$test,['round_id'=>(int)$round['id'],'screen_type'=>$action==='screen'?$screen:(string)$session['screen_type'],'page_number'=>$page,'auto_page'=>$auto?1:0,'page_delay_seconds'=>$delay,'loop_enabled'=>0,'effect_type'=>$callbackReveal?'countdown':''],0,$sessionId);
        return $updated;
    }

    public static function safeScreens(string $roundType):array
    {
        if($roundType==='heats')return ['holding'=>'Holding','flights'=>'Flight Round','judges'=>'Judges','judge_call'=>'Call Judges One by One','competitors'=>'Competitors','scoring'=>'Scoring Status','score_matrix'=>'Live Score Matrix','callbacks'=>'Callbacks'];
        if($roundType==='semifinal')return ['holding'=>'Holding','flights'=>'Flight Round','judges'=>'Judges','judge_call'=>'Call Judges One by One','competitors'=>'Competitors','scoring'=>'Scoring Status','score_matrix'=>'Live Score Matrix','finalists'=>'Finalists'];
        return ['holding'=>'Holding','flights'=>'Flight Round','judges'=>'Judges','judge_call'=>'Call Judges One by One','competitors'=>'Finalists / Couples','scoring'=>'Scoring Status','score_matrix'=>'Live Score Matrix','matching'=>'Emcee Live Matching'];
    }

    public static function url(string $token):string
    {
        return absolute_url('projection-remote/?token='.rawurlencode($token));
    }

    private static function assertEventMember(PDO $pdo,array $session,int $eventId,bool $test):void
    {
        if((int)$session['event_id']===$eventId)return;$members=array_map(static fn(array $row):int=>(int)$row['id'],LiveDisplaySessionService::members($pdo,(int)$session['id'],$test));if(!in_array($eventId,$members,true))throw new RuntimeException('This event is not part of the selected projector session.');
    }

    private static function assertActiveEvent(array $session,int $eventId):void
    {
        if((int)($session['active_event_id']??$session['event_id'])!==$eventId)throw new RuntimeException('This event is not currently active on the shared projector. Select it from the main Projection Control first.');
    }
}
