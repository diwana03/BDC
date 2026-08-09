<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
Auth::requireAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
$roundId=(int)($_POST['round_id']??0);$pdo=Database::connection();
$stmt=$pdo->prepare('SELECT event_id,division FROM bdc_scoring_rounds WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$roundId]);$round=$stmt->fetch();if(!$round){http_response_code(404);exit('Round not found.');}
$token=bin2hex(random_bytes(24));$hash=hash('sha256',$token);$hint=substr($token,0,8);
$pdo->prepare("INSERT INTO bdc_registration_desk_links(event_id,division,token_hash,token_hint,is_enabled,created_by) VALUES(:event,:division,:hash,:hint,1,:user) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),is_enabled=1,expires_at=NULL,created_by=VALUES(created_by),updated_at=NOW()")
 ->execute(['event'=>$round['event_id'],'division'=>$round['division'],'hash'=>$hash,'hint'=>$hint,'user'=>(int)(Auth::user()['id']??0)?:null]);
$linkId=(int)$pdo->lastInsertId();if($linkId<1){$s=$pdo->prepare('SELECT id FROM bdc_registration_desk_links WHERE event_id=:event AND division=:division');$s->execute(['event'=>$round['event_id'],'division'=>$round['division']]);$linkId=(int)$s->fetchColumn();}
$_SESSION['registration_desk_tokens'][$linkId]=$token;
header('Location: '.url('admin/scoring/index.php?round_id='.$roundId.'&desk_link_regenerated=1'),true,303);exit;
