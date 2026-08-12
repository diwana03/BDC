<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
$pdo=Database::connection();
$token=trim((string)($_GET['token']??''));
$s=LiveDisplaySessionService::byToken($pdo,$token);
if(!$s||empty($s['loop_enabled'])){http_response_code(204);exit;}
$allowed=['holding','judges','competitors','scoring','callbacks','finalists','heats_scores','final_results','results','winners'];
$screens=array_values(array_intersect($allowed,array_filter(array_map('trim',explode(',',(string)($s['loop_screens']??''))))));
if(count($screens)<2){http_response_code(204);exit;}
if(empty($s['results_unlocked']))$screens=array_values(array_diff($screens,['final_results','results','winners']));
if(count($screens)<2){http_response_code(204);exit;}
$at=array_search((string)$s['screen_type'],$screens,true);$next=$screens[$at===false?0:(($at+1)%count($screens))];
$pdo->prepare("UPDATE bdc_live_display_sessions SET screen_type=:t,reveal_place=:rp,page_number=1,state_version=state_version+1,updated_at=NOW() WHERE id=:id AND loop_enabled=1")->execute(['t'=>$next,'rp'=>$next==='winners'?'all':null,'id'=>$s['id']]);
http_response_code(204);
