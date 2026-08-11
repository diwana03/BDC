<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Database;
Auth::requireAdmin();$roundId=(int)($_GET['round_id']??0);$pdo=Database::connection();$s=$pdo->prepare("SELECT dance_style FROM bdc_scoring_rounds WHERE id=:id AND round_type='final' LIMIT 1");$s->execute(['id'=>$roundId]);$dance=(string)($s->fetchColumn()?:'');if($dance===''){http_response_code(404);exit('Final round not found.');}if($dance==='salsa'){header('Location: publish-salsa.php?round_id='.$roundId,true,303);exit;}header('Location: publish.php?round_id='.$roundId,true,303);exit;