<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use Throwable;
final class ScoringPageGuardService{
 public static function prepare(PDO $pdo,bool $test=false):void{
  $prefix=$test?'bdc_test_scoring_':'bdc_scoring_';$rounds=$prefix.'rounds';
  $tables=[$rounds,$prefix.'entries',$prefix.'judges',$prefix.'marks',$prefix.'results',$prefix.'final_pairs',$prefix.'final_marks',$prefix.'final_results'];
  $columns=[
   'parent_round_id'=>'BIGINT UNSIGNED NULL AFTER event_id',
   'source_round_id'=>'BIGINT UNSIGNED NULL AFTER parent_round_id',
   'scoring_mode'=>"ENUM('manual','automated') NOT NULL DEFAULT 'manual' AFTER round_type",
   'scheduled_at'=>'DATETIME NULL AFTER round_type',
   'dance_style'=>"ENUM('bachata','salsa') NOT NULL DEFAULT 'bachata' AFTER event_id",
   'tier_manual_override'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER callback_count',
   'witness_1'=>'VARCHAR(190) NULL AFTER source_round_id',
   'witness_2'=>'VARCHAR(190) NULL AFTER witness_1',
   'witness_3'=>'VARCHAR(190) NULL AFTER witness_2',
   'scoring_administrator'=>'VARCHAR(190) NULL AFTER witness_3'
  ];
  try{
   foreach($tables as $table){$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$s->execute(['table'=>$table]);if((int)$s->fetchColumn()!==1){SchemaUpdater::run($pdo);return;}}
   $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
   foreach($columns as $column=>$definition){$check->execute(['table'=>$rounds,'column'=>$column]);if(!(int)$check->fetchColumn()){$safeTable=str_replace('`','',$rounds);$safeColumn=str_replace('`','',$column);$pdo->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");}}
  }catch(Throwable $e){error_log('BDC scoring preflight failed: '.$e->getMessage());}
 }
 public static function renderFailure(Throwable $e,bool $test=false):never{
  $ref=strtoupper(bin2hex(random_bytes(4)));error_log('BDC scoring page failure ['.$ref.'] '.get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());http_response_code(500);$title=$test?'Scoring Test Dashboard could not open':'Scoring Dashboard could not open';
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:760px"><div class="card border-danger shadow-sm"><div class="card-body p-4"><h1 class="h4 text-danger">'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</h1><p>The server recorded the technical error. Your saved round and scores were not changed.</p><p><strong>Error reference:</strong> <code>'.htmlspecialchars($ref,ENT_QUOTES,'UTF-8').'</code></p><a class="btn btn-dark" href="./">Return to Scoring Dashboard</a></div></div></main></body></html>';exit;
 }
 public static function renderFatal(array $error,bool $test=false):never{$ref=strtoupper(bin2hex(random_bytes(4)));error_log('BDC scoring fatal ['.$ref.'] '.($error['message']??'Fatal loader error').' in '.($error['file']??'unknown').':'.($error['line']??0));if(!headers_sent())http_response_code(500);$title=$test?'Scoring Test Dashboard could not open':'Scoring Dashboard could not open';echo '<!doctype html><html><body style="font-family:Arial;padding:40px"><h2>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</h2><p>Your saved round and scores were not changed.</p><p><strong>Error reference:</strong> <code>'.htmlspecialchars($ref,ENT_QUOTES,'UTF-8').'</code></p><p><a href="./">Return to Scoring Dashboard</a></p></body></html>';exit;}
}
