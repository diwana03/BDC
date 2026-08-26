<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

Auth::requireAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){
    http_response_code(419);
    exit('Invalid security token.');
}

$pdo=Database::connection();
$userId=(int)(Auth::user()['id']??0);
$mode=(string)($_POST['scoring_mode']??'manual');
$dance=(string)($_POST['dance_style']??'bachata');
$division=(string)($_POST['division']??'novice');
$roundType=(string)($_POST['round_type']??'heats');
$eventId=(int)($_POST['event_id']??0);
$newName=trim((string)($_POST['new_event_name']??''));
$newDate=trim((string)($_POST['new_event_date']??''));
$scheduled=trim((string)($_POST['scheduled_at']??''));

try{
    if(!in_array($mode,['manual','automated'],true))throw new RuntimeException('Invalid scoring mode.');
    if(!in_array($dance,['bachata','salsa'],true))throw new RuntimeException('Invalid dance style.');
    if(!in_array($division,['novice','intermediate','advanced','all_star'],true))throw new RuntimeException('Invalid division.');
    if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Invalid round type.');
    if($eventId>0&&$newName!=='')throw new RuntimeException('Select an existing event or create a new event, not both.');
    if($newDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');
    if($scheduled!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$scheduled))throw new RuntimeException('Enter a valid round date and time.');
    $scheduled=$scheduled===''?'':str_replace('T',' ',$scheduled).':00';

    $pdo->beginTransaction();
    if($eventId<1){
        if($newName==='')throw new RuntimeException('Select an existing event or enter a new event name.');
        $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$newName),'-'))?:'event';
        $slug=$base;$suffix=2;$check=$pdo->prepare('SELECT COUNT(*) FROM bdc_events WHERE slug=:slug');
        while(true){$check->execute(['slug'=>$slug]);if(!(int)$check->fetchColumn())break;$slug=$base.'-'.$suffix++;}
        $insert=$pdo->prepare("INSERT INTO bdc_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,NULLIF(:event_date,''),'draft')");
        $insert->execute(['name'=>$newName,'normalised'=>strtolower($newName),'slug'=>$slug,'event_date'=>$newDate]);
        $eventId=(int)$pdo->lastInsertId();
    }

    $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:event AND dance_style=:dance AND division=:division AND round_type=:round AND scoring_mode=:mode AND status<>'archived' ORDER BY id DESC LIMIT 1");
    $existing->execute(['event'=>$eventId,'dance'=>$dance,'division'=>$division,'round'=>$roundType,'mode'=>$mode]);
    $roundId=(int)$existing->fetchColumn();
    if($roundId<1){
        $insert=$pdo->prepare("INSERT INTO bdc_scoring_rounds(event_id,dance_style,round_type,scheduled_at,scoring_mode,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:event,:dance,:round,NULLIF(:scheduled,''),:mode,:division,10,10,10.00,4.50,4.30,4.20,:user)");
        $insert->execute(['event'=>$eventId,'dance'=>$dance,'round'=>$roundType,'scheduled'=>$scheduled,'mode'=>$mode,'division'=>$division,'user'=>$userId?:null]);
        $roundId=(int)$pdo->lastInsertId();
        $audit=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
        $audit->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>'round_created','details'=>json_encode(['dance_style'=>$dance,'round_type'=>$roundType,'scoring_mode'=>$mode],JSON_UNESCAPED_SLASHES)]);
        $link=$pdo->prepare('SELECT id FROM bdc_registration_desk_links WHERE event_id=:event AND dance_style=:dance AND division=:division LIMIT 1');
        $link->execute(['event'=>$eventId,'dance'=>$dance,'division'=>$division]);
        if(!(int)$link->fetchColumn()){
            $token=bin2hex(random_bytes(24));
            $save=$pdo->prepare('INSERT INTO bdc_registration_desk_links(event_id,dance_style,division,token_hash,token_hint,created_by) VALUES(:event,:dance,:division,:hash,:hint,:user)');
            $save->execute(['event'=>$eventId,'dance'=>$dance,'division'=>$division,'hash'=>hash('sha256',$token),'hint'=>substr($token,0,8),'user'=>$userId?:null]);
            $_SESSION['registration_desk_tokens'][(int)$pdo->lastInsertId()]=$token;
        }
    }
    $pdo->commit();
    header('Location: index.php?mode='.rawurlencode($mode).'&round_id='.$roundId,true,303);
    exit;
}catch(Throwable $exception){
    if($pdo->inTransaction())$pdo->rollBack();
    $_SESSION['scoring_create_error']=$exception->getMessage();
    header('Location: index.php?mode='.rawurlencode($mode),true,303);
    exit;
}
