<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringFlightService;

Auth::requireAdmin();
$pdo = Database::connection();
$test = ($_GET['data_mode'] ?? $_POST['data_mode'] ?? 'real') === 'test';
$roundId = (int)($_GET['round_id'] ?? $_POST['round_id'] ?? 0);
$roundTable = $test ? 'bdc_test_scoring_rounds' : 'bdc_scoring_rounds';
$eventTable = $test ? 'bdc_test_events' : 'bdc_events';
$stmt = $pdo->prepare("SELECT r.*,e.name event_name FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id WHERE r.id=:id LIMIT 1");
$stmt->execute(['id' => $roundId]);
$round = $stmt->fetch();
if (!$round) { http_response_code(404); exit('Scoring round not found.'); }

$notice = '';
$error = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) throw new RuntimeException('Your session expired. Refresh and try again.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'generate') {
            $locked = ScoringFlightService::scoringStarted($pdo, $roundId, $test);
            $override = $locked && Auth::canOverrideCompletedScores() && (string)($_POST['confirmation'] ?? '') === 'REBUILD';
            if ($locked && !$override) throw new RuntimeException('Flights are locked because scoring has started. An authorised scorer must enter a reason and type REBUILD.');
            ScoringFlightService::generate(
                $pdo,
                $roundId,
                $test,
                (int)($_POST['flight_size'] ?? 10),
                (int)(Auth::user()['id'] ?? 0),
                $override,
                (string)($_POST['reason'] ?? '')
            );
            $notice = $locked ? 'Safety checkpoint created and all Flights rebuilt in bib order.' : 'Flights created in bib-number order.';
        } elseif ($action === 'activate') {
            ScoringFlightService::setActive($pdo, $roundId, $test, (int)($_POST['flight_number'] ?? 1));
            $notice = 'Active Flight updated.';
        }
    }
    $summary = ScoringFlightService::summary($pdo, $roundId, $test);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    $summary = ScoringFlightService::summary($pdo, $roundId, $test);
}
$isFinal = (string)$round['round_type'] === 'final';
$locked = !empty($summary['locked']);
$returnUrl = $test
    ? url('admin/scoring-tests/?round_id=' . $roundId)
    : url('admin/scoring/?mode=' . rawurlencode((string)($round['scoring_mode'] ?? 'manual')) . '&round_id=' . $roundId);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Flights | BDC Scoring</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=304" rel="stylesheet"><style>body{background:#f3f6fb}.hero{background:linear-gradient(135deg,#111827,#35101a);color:#fff;border-radius:20px}.flight-card{border:1px solid #dbe3ef;border-radius:16px}.flight-card.active{border:2px solid #0d6efd;box-shadow:0 12px 30px rgba(13,110,253,.12)}.metric{background:#f7f9fc;border-radius:12px;padding:.8rem 1rem}.order-note{border-left:4px solid #0d6efd}</style></head><body><main class="container py-4" style="max-width:1100px"><section class="hero shadow-sm p-4 mb-4"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="small text-uppercase text-info fw-bold">Optional round organisation</div><h1 class="h2 mb-1">Flights</h1><div><?=e($round['event_name'])?> · <?=e(ucwords(str_replace('_',' ',(string)$round['division'])))?> · <?=e(ucfirst((string)$round['round_type']))?></div></div><a class="btn btn-light" href="<?=e($returnUrl)?>">← Back to Scoring</a></div></section>
<?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<section class="card shadow-sm border-0 rounded-4 mb-4"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h2 class="h4 mb-1"><?=empty($summary['flight_count'])?'Create Flights':'Flight Setup'?></h2><p class="text-muted mb-0">Flights are optional. Without them, the round continues using the normal full list.</p></div><span class="badge <?= $locked?'text-bg-warning':'text-bg-success' ?>"><?= $locked?'LOCKED · SCORING STARTED':'EDITABLE' ?></span></div>
<div class="alert alert-light order-note"><strong>Permanent ordering rule:</strong> <?= $isFinal ? 'confirmed couples are ordered by Leader bib, then Follower bib.' : 'Leaders and Followers are each ordered by bib and divided independently into the same numbered Flights.' ?></div>
<form method="post" class="row g-3 align-items-end"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="generate"><div class="col-md-4"><label class="form-label fw-semibold"><?= $isFinal?'Couples':'Dancers per role' ?> in each Flight</label><input class="form-control form-control-lg" type="number" name="flight_size" min="1" max="50" value="<?=(int)$summary['flight_size']?>" required><div class="form-text">Any size from 1–50; the number of Flights is unlimited.</div></div><?php if($locked):?><div class="col-md-4"><label class="form-label fw-semibold">Emergency rebuild reason</label><input class="form-control" name="reason" required placeholder="Why assignments must change"><label class="form-label fw-semibold mt-2">Type REBUILD</label><input class="form-control" name="confirmation" required autocomplete="off" placeholder="REBUILD"></div><?php endif;?><div class="col-md-4"><button class="btn <?= $locked?'btn-warning':'btn-primary' ?> btn-lg w-100" onclick="return confirm('<?= $locked?'Create a backup checkpoint and rebuild every Flight? Existing scores remain attached to the same competitors.':'Create Flight assignments in bib order?' ?>')"><?=empty($summary['flight_count'])?'Create Flights':'Rebuild Flights'?></button></div></form></div></section>
<?php if(!empty($summary['flight_count'])):?><section class="mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">Round Flights</h2><div class="text-muted"><?=(int)$summary['flight_count']?> Flights · <?=(int)$summary['flight_size']?> per <?= $isFinal?'Flight':'role' ?></div></div><div class="row g-3"><?php foreach($summary['flights'] as $flight):$active=(int)$summary['active_flight']===(int)$flight['number'];?><div class="col-md-6 col-xl-4"><article class="flight-card bg-white p-3 h-100 <?=$active?'active':''?>"><div class="d-flex justify-content-between align-items-center"><h3 class="h5 mb-0">Flight <?=(int)$flight['number']?></h3><?php if($active):?><span class="badge text-bg-primary">ACTIVE</span><?php endif;?></div><div class="d-flex gap-2 my-3"><?php foreach($flight['roles'] as $role=>$count):?><div class="metric flex-fill"><div class="small text-muted"><?=e($role==='couple'?'Couples':ucfirst($role).'s')?></div><strong class="fs-4"><?=(int)$count?></strong></div><?php endforeach;?></div><?php if(!$active):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="data_mode" value="<?=$test?'test':'real'?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="flight_number" value="<?=(int)$flight['number']?>"><button class="btn btn-outline-primary btn-sm w-100">Set Active Flight</button></form><?php endif;?></article></div><?php endforeach;?></div></section><?php endif;?>
</main></body></html>
