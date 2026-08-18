<?php
declare(strict_types=1);
$mode=(string)($_GET['mode']??'');$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);$action=(string)($_POST['action']??'');
if($mode==='special'){header('Location: ?mode=manual'.($roundId?'&round_id='.$roundId:''));exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='create_round'&&in_array((string)($_POST['division']??''),['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'],true)){require __DIR__.'/integrated-special-create.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&in_array($action,['create_round','create_next_round','delete_scoring_workflow'],true)){require __DIR__.'/discipline-actions.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='add_entry'&&$roundId){require_once dirname(__DIR__,2).'/bootstrap.php';try{$pdo=App\Core\Database::connection();$s=$pdo->prepare('SELECT dance_style FROM bdc_scoring_rounds WHERE id=:id');$s->execute(['id'=>$roundId]);if((string)$s->fetchColumn()==='salsa'){require __DIR__.'/discipline-actions.php';exit;}}catch(Throwable){}}
if($_SERVER['REQUEST_METHOD']==='GET'&&$mode===''&&!$roundId){require dirname(__DIR__,2).'/bootstrap.php';App\Core\Auth::requireAdmin();?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scoring Dashboard | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=274" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><h1 class="text-center">Scoring Dashboard</h1><div class="row g-4 mt-3"><div class="col-md-4"><a class="btn btn-dark btn-lg w-100 py-4" href="?mode=manual">Manual Scoring</a></div><div class="col-md-4"><a class="btn btn-primary btn-lg w-100 py-4" href="?mode=automated">Automatic Scoring</a></div><div class="col-md-4"><a class="btn btn-danger btn-lg w-100 py-4" target="_blank" rel="noopener" href="../live-screen/">Live Screen Projector</a></div></div><div class="text-center mt-4"><a href="../">Dashboard</a> · <a href="../completed-events/">Completed Events</a></div></main></body></html><?php exit;}
if($_SERVER['REQUEST_METHOD']==='GET'&&$roundId===0&&in_array($mode,['manual','automated'],true)){require __DIR__.'/active-dashboard.php';exit;}
require_once dirname(__DIR__,2).'/bootstrap.php';App\Core\Auth::requireAdmin();
if(($_GET['judge_panel']??'')==='1'){require __DIR__.'/judge-control.php';exit;}
$guardPdo=App\Core\Database::connection();App\Services\ScoringPageGuardService::prepare($guardPdo,false);$reserve=str_repeat('x',262144);register_shutdown_function(static function()use(&$reserve,$mode,$roundId):void{$last=error_get_last();if(!$last||!in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))return;$reserve='';if($_SERVER['REQUEST_METHOD']==='POST'&&$mode==='automated'&&$roundId>0&&!headers_sent()){$_SESSION['automatic_scoring_error']='Automatic scoring stopped: '.(string)($last['message']??'fatal server error');header('Location: automatic-round.php?round_id='.$roundId,true,303);return;}App\Services\ScoringPageGuardService::renderFatal($last,false);});set_exception_handler(static function(Throwable $e):void{App\Services\ScoringPageGuardService::renderFailure($e,false);});
if($_SERVER['REQUEST_METHOD']==='GET'&&$mode==='automated'&&$roundId>0){try{$s=$guardPdo->prepare('SELECT round_type FROM bdc_scoring_rounds WHERE id=:id');$s->execute(['id'=>$roundId]);if((string)$s->fetchColumn()!=='final'){header('Location: automatic-round.php?round_id='.$roundId);exit;}}catch(Throwable $e){App\Services\ScoringPageGuardService::renderFailure($e,false);}}
if($_SERVER['REQUEST_METHOD']==='POST'&&$mode==='automated'&&$roundId>0&&in_array($action,['settings','add_entry','update_bib','remove_entry'],true)){
 @ini_set('zlib.output_compression','0');
 try{require __DIR__.'/core.php';}
 catch(Throwable $e){App\Services\ScoringPageGuardService::renderFailure($e,false);}
 exit;
}
ob_start(static function(string $html):string{if(preg_match('/round_id=(\d+)/',$html,$m)){$id=(int)$m[1];$html=str_replace('href="publish.php?round_id='.$id.'"','href="publish-gate.php?round_id='.$id.'"',$html);if(!str_contains($html,'Live Screen / Projection Control')){$button='<a class="btn btn-danger btn-sm" target="_blank" rel="noopener" href="../live-screen/control.php?round_id='.$id.'">Live Screen / Projection Control</a>';$html=str_replace('<a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a>',$button.'<a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a>',$html);}}return$html;});
try{require __DIR__.'/core.php';}catch(Throwable $e){
 while(ob_get_level()>0)ob_end_clean();
 if($_SERVER['REQUEST_METHOD']==='POST'&&$mode==='automated'&&$roundId>0){
  $_SESSION['automatic_scoring_error']=$e->getMessage();
  header('Location: automatic-round.php?round_id='.$roundId,true,303);
  exit;
 }
 App\Services\ScoringPageGuardService::renderFailure($e,false);
}
