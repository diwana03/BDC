<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
Auth::requireAdmin();
$pdo=Database::connection();$sessionId=(int)($_POST['session_id']??0);$eventId=(int)($_POST['event_id']??0);$roundId=(int)($_POST['round_id']??0);$test=($_POST['data_mode']??'real')==='test';$embed=($_POST['embed']??'')==='1';if($eventId<1&&$sessionId<1){$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$q=$pdo->prepare("SELECT event_id FROM {$roundTable} WHERE id=:r LIMIT 1");$q->execute(['r'=>$roundId]);$eventId=(int)$q->fetchColumn();}
$target='control.php?'.http_build_query(['round_id'=>$roundId,'session_id'=>$sessionId?:null,'data_mode'=>$test?'test':'real','embed'=>$embed?'1':'0']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid request.');}
try{
    $session=$sessionId>0?LiveDisplaySessionService::byId($pdo,$sessionId,$test):LiveDisplaySessionService::forEvent($pdo,$eventId,$test);if(!$session)throw new RuntimeException('Generate the Live Display link first.');
    $file=$_FILES['music']??null;if(!$file||empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('Choose a music file first.');
    if((int)$file['size']>60*1024*1024)throw new RuntimeException('Music must be no larger than 60 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg'];if(!isset($types[$mime]))throw new RuntimeException('Use MP3, M4A, WAV or OGG audio.');
    $dir=dirname(__DIR__,2).'/uploads/live-display/music';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Projector music folder is unavailable.');
    $name='music-'.(int)$session['id'].'-'.bin2hex(random_bytes(8)).'.'.$types[$mime];if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Music upload failed.');
    $displayName=trim((string)($file['name']??'Projector music'));$displayName=substr(preg_replace('/[^\pL\pN ._()\-]+/u','',$displayName)?:'Projector music',0,190);$url=url('uploads/live-display/music/'.$name);
    $pdo->prepare("UPDATE bdc_live_display_sessions SET music_url=:url,music_name=:name,music_status='paused',music_version=music_version+1,updated_by=:u,updated_at=NOW() WHERE id=:s")->execute(['url'=>$url,'name'=>$displayName,'u'=>(int)(Auth::user()['id']??0)?:null,'s'=>$session['id']]);
    $_SESSION['projection_settings_notice']='Music uploaded. Press Play Loop when the projector is open.';
}catch(Throwable $e){$_SESSION['projection_settings_notice']='Music upload failed: '.$e->getMessage();}
header('Location: '.$target,true,303);exit;
