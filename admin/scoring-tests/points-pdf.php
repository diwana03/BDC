<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Database;use App\Services\SchemaUpdater;use App\Services\SimplePdf;
Auth::requireAdmin();$pdo=Database::connection();$roundId=(int)($_GET['round_id']??0);
$s=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id");$s->execute(['id'=>$roundId]);$round=$s->fetch();if(!$round)exit('Round not found.');
$pdf=new SimplePdf();$pdf->line('BDC Jack & Jill',16,true);$pdf->line($round['event_name'],14,true);$pdf->line('FINAL RANKING & POINTS',12,true);$pdf->line('Date: '.($round['event_date']??''),9);$pdf->line('Venue: '.($round['venue']??''),9);$pdf->line('',8);
$p=$pdo->prepare("SELECT points_tier FROM bdc_test_scoring_publications WHERE final_round_id=:r ORDER BY id DESC LIMIT 1");$p->execute(['r'=>$roundId]);$tier=(int)$p->fetchColumn();$m=[1=>[1=>5,2=>4,3=>3,4=>2,5=>1],2=>[1=>10,2=>8,3=>6,4=>4,5=>2],3=>[1=>15,2=>12,3=>10,4=>8,5=>6]];$q=$pdo->prepare("SELECT fr.final_rank,l.display_name leader_name,f.display_name follower_name FROM bdc_test_scoring_final_results fr JOIN bdc_test_scoring_final_pairs fp ON fp.id=fr.pair_id JOIN bdc_test_scoring_entries l ON l.id=fp.leader_entry_id JOIN bdc_test_scoring_entries f ON f.id=fp.follower_entry_id WHERE fr.round_id=:r ORDER BY fr.final_rank");$q->execute(['r'=>$roundId]);foreach($q->fetchAll() as $x){$rank=(int)$x['final_rank'];$pts=(float)($m[$tier][$rank]??($tier===2&&$rank<=10?1:($tier===3?2:0)));$pdf->line($rank.'. '.$x['leader_name'].' & '.$x['follower_name'].' - '.number_format($pts,1).' points each',10);}
$pdf->output('BDC-Points-'.$roundId.'.pdf');
