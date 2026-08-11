<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Database;use App\Services\SchemaUpdater;use App\Services\SimplePdf;
Auth::requireAdmin();$pdo=Database::connection();$roundId=(int)($_GET['round_id']??0);
$s=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id");$s->execute(['id'=>$roundId]);$round=$s->fetch();if(!$round)exit('Round not found.');
$pdf=new SimplePdf();$pdf->line('BDC Jack & Jill',16,true);$pdf->line($round['event_name'],14,true);$pdf->line('FINAL RESULTS',12,true);$pdf->line('Date: '.($round['event_date']??''),9);$pdf->line('Venue: '.($round['venue']??''),9);$pdf->line('',8);
$q=$pdo->prepare("SELECT fr.final_rank,fp.pair_number,l.display_name leader_name,f.display_name follower_name FROM bdc_test_scoring_final_results fr JOIN bdc_test_scoring_final_pairs fp ON fp.id=fr.pair_id JOIN bdc_test_scoring_entries l ON l.id=fp.leader_entry_id JOIN bdc_test_scoring_entries f ON f.id=fp.follower_entry_id WHERE fr.round_id=:r ORDER BY fr.final_rank");$q->execute(['r'=>$roundId]);foreach($q->fetchAll() as $x)$pdf->line($x['final_rank'].'. Couple '.$x['pair_number'].' - '.$x['leader_name'].' & '.$x['follower_name'],10);
$pdf->output('BDC-Final-'.$roundId.'.pdf');
