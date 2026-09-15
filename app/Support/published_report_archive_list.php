<?php
declare(strict_types=1);
use App\Core\Auth;
use App\Core\Database;

Auth::requireAdmin();
$prefix=$reportArchiveTest?'bdc_test_':'bdc_';
$pdo=Database::connection();
$reports=$pdo->query("SELECT p.final_round_id,r.dance_style,r.division,e.name,e.event_date
 FROM {$prefix}scoring_publications p
 JOIN {$prefix}scoring_rounds r ON r.id=p.final_round_id AND r.event_id=p.event_id
 JOIN {$prefix}events e ON e.id=p.event_id
 WHERE p.status='published' ORDER BY e.event_date DESC,p.id DESC")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Published scoring reports</title>
<style>body{font:16px system-ui;margin:24px;color:#182132;background:#f4f6fa}main{max-width:1100px;margin:auto}article{background:white;padding:20px;margin:16px 0;border-radius:12px}a{display:inline-block;margin:8px 16px 8px 0;color:#1749ab}small{display:block;color:#526075}</style></head><body><main>
<h1>Published Heats &amp; Final Reports</h1>
<p>Open Amateur, Open or past published competitions below. Refresh rebuilds their detailed reports at the existing public links, with backups. Points documents and scores are not refreshed.</p>
<p>Older uploaded files without saved scoring rounds cannot be reconstructed automatically; their originals are retained.</p>
<?php foreach($reports as $report):?><article><h2><?=e($report['name'])?></h2><small><?=e($report['event_date'])?> · <?=e(ucwords(str_replace('_',' ',$report['dance_style'].' '.$report['division'])))?></small>
<a href="publish.php?round_id=<?=(int)$report['final_round_id']?>">Review and refresh Heats &amp; Final</a>
<a target="_blank" rel="noopener" href="final-result.php?round_id=<?=(int)$report['final_round_id']?>">Detailed Final preview</a>
<a target="_blank" rel="noopener" href="final-result.php?round_id=<?=(int)$report['final_round_id']?>&amp;layout=fit">Landscape preview</a></article><?php endforeach;?>
<?php if(!$reports):?><p>No published scoring competitions found.</p><?php endif;?>
</main></body></html>
