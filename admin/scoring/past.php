<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

Auth::requireAdmin();
if(!Auth::canViewPastScores()){
 http_response_code(403);
 exit('Past Event Scores are available only to Admin, Master Scorer and Super Admin accounts.');
}
$pdo=Database::connection();
$rounds=$pdo->query("SELECT r.*,e.name event_name,e.event_date FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.status IN('completed','archived') ORDER BY COALESCE(e.event_date,DATE(r.updated_at)) DESC,r.updated_at DESC")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Past Event Scores | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=274" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="./">Scoring Dashboard</a></div></nav><main class="container-fluid py-4" style="max-width:1400px"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4"><div><h1 class="h3 mb-1">Past Event Scores</h1><p class="text-muted mb-0">Completed and archived scoring records.</p></div><a class="btn btn-dark" href="./">Back to Active Scores</a></div><div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Event</th><th>Date</th><th>Division</th><th>Round</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php if(!$rounds):?><tr><td colspan="7" class="text-muted">No past event scores found.</td></tr><?php endif;?><?php foreach($rounds as $r):?><tr><td><strong><?=e($r['event_name'])?></strong></td><td><?=e((string)($r['event_date']?:'—'))?></td><td><?=e(ucwords(str_replace('_',' ',$r['division'])))?></td><td><?=e(ucfirst($r['round_type']))?></td><td><span class="badge <?=$r['status']==='archived'?'text-bg-dark':'text-bg-success'?>"><?=e(ucfirst($r['status']))?></span></td><td><?=e($r['updated_at'])?></td><td class="text-end"><a class="btn btn-sm btn-outline-dark" href="index.php?round_id=<?=(int)$r['id']?>">View Scores</a></td></tr><?php endforeach;?></tbody></table></div></div></div></main></body></html>
