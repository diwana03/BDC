<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Database;use App\Services\SchemaUpdater;use App\Services\SimplePdf;
Auth::requireAdmin();$pdo=Database::connection();SchemaUpdater::run($pdo);$roundId=(int)($_GET['round_id']??0);
$s=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id");$s->execute(['id'=>$roundId]);$round=$s->fetch();if(!$round)exit('Round not found.');
$pdf=new SimplePdf();$pdf->line('BDC Jack & Jill',16,true);$pdf->line($round['event_name'],14,true);$pdf->line('HEATS RESULTS',12,true);$pdf->line('Date: '.($round['event_date']??''),9);$pdf->line('Venue: '.($round['venue']??''),9);$pdf->line('',8);
$j=$pdo->prepare("SELECT judge_name FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order");$j->execute(['r'=>$roundId]);$e=$pdo->prepare("SELECT x.*,r.total_score,r.result_status,r.result_rank FROM bdc_test_scoring_entries x LEFT JOIN bdc_test_scoring_results r ON r.round_id=x.round_id AND r.entry_id=x.id WHERE x.round_id=:r ORDER BY x.dance_role,r.result_rank,x.bib_number");$e->execute(['r'=>$roundId]);$role='';foreach($e->fetchAll() as $x){if($role!==$x['dance_role']){$role=$x['dance_role'];$pdf->line(strtoupper($role.'s'),11,true);}$pdf->line('Bib '.$x['bib_number'].'  '.$x['display_name'].'  '.number_format((float)$x['total_score'],1).'  '.($x['result_status']??''),9);}$pdf->line('',8);$pdf->line('Judges: '.implode(', ',$j->fetchAll(PDO::FETCH_COLUMN)),9);
$pdf->output('BDC-Heats-'.$roundId.'.pdf');
