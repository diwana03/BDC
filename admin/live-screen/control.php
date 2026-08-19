<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . "/bootstrap.php";
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ProjectionSettingsService;
use App\Services\LiveDisplaySessionService;
use App\Services\RandomPairingService;
Auth::requireAdmin();
$test = ($_GET["data_mode"] ?? ($_POST["data_mode"] ?? "real")) === "test";
$embed = ($_GET["embed"] ?? ($_POST["embed"] ?? "")) === "1";
$pdo = Database::connection();
$roundId = (int) ($_GET["round_id"] ?? ($_POST["round_id"] ?? 0));
$roundTable = $test ? "bdc_test_scoring_rounds" : "bdc_scoring_rounds";
$eventTable = $test ? "bdc_test_events" : "bdc_events";
$s = $pdo->prepare(
    "SELECT r.*,e.name event_name FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id WHERE r.id=:id LIMIT 1",
);
$s->execute(["id" => $roundId]);
$round = $s->fetch();
if (!$round) {
    http_response_code(404);
    exit("Round not found.");
}
$roundEventId = (int) $round["event_id"];
$sessionId = (int) ($_GET["session_id"] ?? ($_POST["session_id"] ?? 0));
$session = $sessionId > 0 ? LiveDisplaySessionService::byId($pdo, $sessionId, $test) : null;
if ($sessionId > 0 && !$session) {
    http_response_code(404);
    exit("Festival projection session not found.");
}
if ($session) {
    $memberIds = array_map(fn(array $row): int => (int) $row["id"], LiveDisplaySessionService::members($pdo, $sessionId, $test));
    if (!in_array($roundEventId, $memberIds, true)) {
        http_response_code(403);
        exit("This event is not part of the selected festival projection.");
    }
}
$eventId = $session ? (int) $session["event_id"] : $roundEventId;
$settings = ProjectionSettingsService::get($pdo, $roundId, $test);
$notice = $_SESSION["projection_settings_notice"] ?? "";
$error = "";
unset($_SESSION["projection_settings_notice"]);
if ($_SERVER["REQUEST_METHOD"] === "POST" && Csrf::verify($_POST["_csrf"] ?? null)) {
  try {
    $action = (string) ($_POST["action"] ?? "");
    if ($action === "generate_live") {
        if ($sessionId > 0) {
            throw new RuntimeException("The shared festival link is managed from Multi-Event Festival Projection.");
        }
        LiveDisplaySessionService::generate($pdo, $eventId, $test, (int) (Auth::user()["id"] ?? 0));
        $notice = "Live Display link generated. Give this one link to the projector operator.";
    } elseif ($action === "generate_emcee" && $round["round_type"] === "final") {
        if (!LiveDisplaySessionService::forEvent($pdo, $eventId, $test)) {
            LiveDisplaySessionService::generate($pdo, $eventId, $test, (int) (Auth::user()["id"] ?? 0));
        }
        RandomPairingService::generateLink($pdo, $roundId, $test, (int) (Auth::user()["id"] ?? 0));
        $notice = "Emcee access generated for this event projector. Matching will appear on the same Live Display link.";
    }
  } catch (Throwable $exception) {
      $error = $exception->getMessage();
  }
}
$session = $sessionId > 0 ? LiveDisplaySessionService::byId($pdo, $sessionId, $test) : LiveDisplaySessionService::forEvent($pdo, $eventId, $test);
$emceeLink = $round["round_type"] === "final" ? RandomPairingService::activeLink($pdo, $roundId, $test) : null;
$randomMatchLocked = $round["round_type"] === "final" && RandomPairingService::scoringStarted($pdo, $roundId, $test);
$selection = ($_GET["selection"] ?? "") === "1" || $embed;
if ($selection && $session) {
    if ((int)($session["current_round_id"] ?? 0) !== $roundId || (int)($session["active_event_id"] ?? $session["event_id"]) !== $roundEventId) {
        $session = LiveDisplaySessionService::beginSelection($pdo,$eventId,$roundId,$test,(int)(Auth::user()["id"]??0));
        $notice = "Holding Screen selected. Event changed safely; previous effects and projector loop were cleared.";
    }
}
$stage = ucfirst((string) $round["round_type"]);
$types =
    $round["round_type"] === "heats"
        ? [
            "judges" => "Judges",
            "competitors" => "Competitors",
            "scoring" => "Scoring Status",
            "score_matrix" => "Live Score Matrix · Provisional",
            "callbacks" => "Callback Reveal",
            "heats_scores" => "Heats Full Scores · Landscape",
        ]
        : ($round["round_type"] === "semifinal"
            ? [
                "judges" => "Judges",
                "competitors" => "Semifinal Competitors",
                "scoring" => "Scoring Status",
                "score_matrix" => "Live Score Matrix · Provisional",
                "finalists" => "Finalists Reveal",
            ]
            : [
                "judges" => "Judges",
                "competitors" => "Finalists / Couples",
                "scoring" => "Scoring Status",
                "score_matrix" => "Live Relative Placement Matrix · Provisional",
                "matching" => "Emcee Live Matching",
                "winners" => "Winner Podium",
                "final_results" => "Final Full Results · Landscape",
            ]);
$formats = [
    "16:9" => "16:9 Landscape",
    "9:16" => "9:16 Portrait",
    "4:3" => "4:3 Standard",
    "16:10" => "16:10 Landscape",
    "21:9" => "21:9 Ultra-wide",
    "32:9" => "32:9 Super-wide",
    "1:1" => "1:1 Square",
    "custom" => "Custom Resolution",
];
$displayUrl = !empty($session["token_value"])
    ? url(
        "live-display/launch.php?token=" . rawurlencode((string) $session["token_value"]),
    )
    : "";
$soundDisplayUrl = $displayUrl !== "" ? $displayUrl . "&sound=1" : "";
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Live Screen Control | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:<?= $embed
    ? "transparent"
    : "#f4f6f9" ?>}.card{border:0;border-radius:15px}.settings{border-left:5px solid #dc3545}.embed-main{padding:0!important}.locked{opacity:.55}.status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#198754;margin-right:6px}.reveal-panel{border:1px solid #f0c36d;background:#fff9e8;border-radius:10px;padding:12px}.live-state{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#111827;color:#fff;border-radius:8px;padding:8px 10px}</style></head><body><?php if (
    !$embed
): ?><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="./">Live Screen Projector</a><a class="btn btn-outline-light btn-sm" href="../scoring/?mode=<?= e(
    (string) ($round["scoring_mode"] ?? "manual"),
) ?>&round_id=<?= $roundId ?>">Back to Scoring</a></div></nav><?php endif; ?><main class="<?= $embed
    ? "embed-main"
    : "container py-4" ?>"><?php
if ($notice): ?><div class="alert alert-success"><?= e(
    $notice,
) ?></div><?php endif;
if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif;
if (
    !$embed
): ?><div class="mb-4"><div class="text-uppercase text-danger fw-bold small"><?= e(
    strtoupper($stage),
) ?> LIVE SCREEN CONTROL</div><h1 class="h2 mb-1"><?= e(
     $round["event_name"],
 ) ?></h1><div class="text-muted"><?= e(
    ucwords(str_replace("_", " ", $round["division"])),
) ?> · <?= e($stage) ?></div></div><?php endif;
?><div class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Projector Live Feed Link</h2><?php if (
    $displayUrl
): ?><span class="small text-success"><span class="status-dot"></span>Live link ready</span><?php endif; ?></div><?php if (
    $displayUrl
): ?><div class="input-group mt-3"><input id="liveUrl" class="form-control" readonly value="<?= e(
    $displayUrl,
) ?>"><button type="button" id="copyLiveUrl" class="btn btn-outline-primary">Copy Link</button><a target="_blank" rel="noopener" class="btn btn-primary" href="<?= e(
    $displayUrl,
) ?>">Open Muted</a><a target="_blank" rel="noopener" class="btn btn-success projector-open" href="<?= e($soundDisplayUrl) ?>">Open Projector With Sound</a></div><div class="small text-muted mt-2">Every Open action first switches the audience display to the Holding Screen. Choose sound here before the audience sees the projector. The public screen has no sound controls.</div><?php else: ?><p class="text-muted mt-2">Generate one link. The projector operator opens it once and leaves it full-screen.</p><?php endif; ?><form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?= e(
    Csrf::token(),
) ?>"><input type="hidden" name="session_id" value="<?= $sessionId ?>"><input type="hidden" name="round_id" value="<?= $roundId ?>"><input type="hidden" name="data_mode" value="<?= $test
    ? "test"
    : "real" ?>"><input type="hidden" name="embed" value="<?= $embed
    ? "1"
    : "0" ?>"><?php if($sessionId<1):?><button class="btn btn-dark" name="action" value="generate_live">Generate / Regenerate Live Display Link</button><?php else:?><span class="badge text-bg-dark">Shared Festival Link</span><?php endif;?></form></div></div><?php if ($round["round_type"] === "final"): ?><div id="emcee-match" class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Emcee Matching · Event Projection</h2><p class="text-muted mb-2">Restricted Emcee access controls the matching view on this event's existing Live Display. There is no second projector link.</p><?php if ($emceeLink): ?><div class="input-group mb-2"><input id="emceeUrl" class="form-control" readonly value="<?= e((string) $emceeLink["url"]) ?>"><button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('emceeUrl').value)">Copy Emcee Access</button><a target="_blank" rel="noopener" class="btn btn-danger" href="<?= e((string) $emceeLink["url"]) ?>">Open Emcee Control</a></div><div class="small text-muted">Access expires <?= e((string) $emceeLink["expires_at"]) ?>.</div><?php else: ?><p class="text-muted">Generate restricted Emcee access when the host is ready to randomize the Final couples.</p><?php endif; ?><form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="session_id" value="<?= $sessionId ?>"><input type="hidden" name="round_id" value="<?= $roundId ?>"><input type="hidden" name="data_mode" value="<?= $test ? "test" : "real" ?>"><input type="hidden" name="embed" value="<?= $embed ? "1" : "0" ?>"><button class="btn btn-outline-danger" name="action" value="generate_emcee"><?= $emceeLink ? "Regenerate" : "Generate" ?> Emcee Access</button></form></div></div><?php endif; ?><form class="card settings shadow-sm mb-4" method="post" action="settings.php"><div class="card-body"><input type="hidden" name="_csrf" value="<?= e(
    Csrf::token(),
) ?>"><input type="hidden" name="round_id" value="<?= $roundId ?>"><input type="hidden" name="data_mode" value="<?= $test
    ? "test"
    : "real" ?>"><input type="hidden" name="embed" value="<?= $embed
    ? "1"
    : "0" ?>"><h2 class="h5">Screen & Layout</h2><div class="row g-2"><div class="col-md-4"><label class="form-label">Screen Format</label><select id="format" name="screen_format" class="form-select"><?php foreach (
    $formats
    as $v => $l
): ?><option value="<?= e($v) ?>" <?= $settings["screen_format"] === $v
    ? "selected"
    : "" ?>><?= e(
    $l,
) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Density</label><select name="density" class="form-select"><option value="maximum" <?= $settings[
    "density"
] === "maximum"
    ? "selected"
    : "" ?>>Maximum Density</option><option value="auto" <?= $settings[
    "density"
] === "auto"
    ? "selected"
    : "" ?>>Auto</option><option value="large" <?= $settings["density"] ===
"large"
    ? "selected"
    : "" ?>>Large Cards</option></select></div><div class="col-md-2"><label class="form-label">Custom Width</label><input id="cw" name="custom_width" class="form-control" type="number" min="100" value="<?= e(
    (string) ($settings["custom_width"] ?? ""),
) ?>"></div><div class="col-md-2"><label class="form-label">Custom Height</label><input id="ch" name="custom_height" class="form-control" type="number" min="100" value="<?= e(
    (string) ($settings["custom_height"] ?? ""),
) ?>"></div></div><div class="d-flex gap-2 mt-3"><button class="btn btn-danger" name="action" value="apply">Apply Screen Settings</button><button class="btn btn-outline-secondary" name="action" value="reset">Reset to 16:9</button></div></div></form><div class="card shadow-sm"><div class="card-body"><input type="hidden" id="csrf" value="<?= e(
    Csrf::token(),
) ?>"><h2 class="h5">Live Feed Remote Control</h2><div id="liveState" class="live-state small mb-2">LIVE STATE: <?= e(
    strtoupper((string) ($session["screen_type"] ?? "holding")),
) ?> · Round <?= $roundId ?> · Version <?= (int) ($session["state_version"] ??
     0) ?></div><div id="remoteMessage" class="small mb-2 text-muted">Click a feed and the projector screen will update automatically.</div><div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="feed btn btn-outline-dark" data-screen="holding">Holding Screen</button><?php foreach (
    $types
    as $v => $l
):
    $protected = in_array(
        $v,
        ["final_results", "results", "winners"],
        true,
    ); ?><button type="button" class="feed btn <?= ($session["screen_type"] ??
    "") ===
$v
    ? "btn-danger"
    : "btn-outline-danger" ?> <?= $protected &&
 empty($session["results_unlocked"])
     ? "locked"
     : "" ?>" data-screen="<?= e($v) ?>" data-protected="<?= $protected
    ? "1"
    : "0" ?>" <?= $protected && empty($session["results_unlocked"])
    ? "disabled"
    : "" ?>><?=
$protected && empty($session["results_unlocked"]) ? "🔒 " : ""
?><?= e($l)
?></button><?php
endforeach; ?></div><div class="border rounded p-3 mb-3"><div class="fw-bold">Presentation Effects · Transparent Overlay</div><div class="small text-muted mb-2">The active projector screen remains visible beneath every effect.</div><div class="d-flex flex-wrap gap-2"><button type="button" class="effect btn btn-outline-dark" data-effect="drumroll">Drum Roll</button><button type="button" class="effect btn btn-outline-primary" data-effect="fireworks">Cinematic Fireworks</button><button type="button" class="effect btn btn-outline-warning" data-effect="confetti">Celebration Confetti</button><button type="button" class="effect btn btn-outline-warning" data-effect="gold_rain">Gold Celebration</button><button type="button" class="effect btn btn-outline-info" data-effect="laser_sweep">Laser Sweep</button><button type="button" class="effect btn btn-outline-danger" data-effect="champion_impact">Champion Impact</button><button type="button" class="effect btn btn-outline-secondary" data-effect="none">Clear Effect</button></div></div><?php if (
    $round["round_type"] === "final"
): ?><div id="podiumReveal" class="reveal-panel mb-3 <?= ($session[
    "screen_type"
] ??
    "") ===
"winners"
    ? ""
    : "d-none" ?>"><div class="fw-bold mb-2">Winner Podium Reveal</div><div class="small text-muted mb-2">Reveal progressively from 5th to 1st. Previous placements remain on the podium. No effects play automatically.</div><div class="d-flex flex-wrap gap-2"><?php foreach (
    [5, 4, 3, 2, 1]
    as $p
): ?><button type="button" class="btn btn-outline-dark btn-sm reveal" data-place="<?= $p ?>">Reveal <?= $p ?></button><?php endforeach; ?><button type="button" class="btn btn-warning btn-sm reveal" data-place="all">Show Full Podium</button></div></div><div class="border rounded p-3 mb-3 bg-light"><div class="fw-bold">Results Reveal Safety</div><div class="small text-muted mb-2">Winner Podium and Final Ranking are locked to prevent accidental public reveal.</div><button type="button" id="unlockResults" class="btn <?= !empty(
    $session["results_unlocked"]
)
    ? "btn-success"
    : "btn-warning" ?> btn-sm"><?= !empty($session["results_unlocked"])
     ? "Results Reveal Unlocked"
     : "Unlock Results Reveal" ?></button><button type="button" id="lockResults" class="btn btn-outline-secondary btn-sm ms-2" <?= empty(
    $session["results_unlocked"]
)
    ? "disabled"
    : "" ?>>Lock</button></div><?php endif; ?><div class="border rounded p-3 mb-3"><div class="fw-bold">Projector Tab Loop</div><div class="small text-muted mb-2">Select two or more tabs. The projector rotates through them automatically.</div><div id="loopChoices" class="d-flex flex-wrap gap-3 mb-3"></div><div class="d-flex flex-wrap gap-2 align-items-end"><div><label class="form-label">Tab Delay</label><select id="loopDelay" class="form-select"><option value="5">5 seconds</option><option value="10">10 seconds</option><option value="15" selected>15 seconds</option><option value="20">20 seconds</option><option value="30">30 seconds</option><option value="45">45 seconds</option><option value="60">60 seconds</option></select></div><button type="button" id="startLoop" class="btn btn-primary">Start Loop</button><button type="button" id="stopLoop" class="btn btn-outline-secondary">Stop Loop</button></div></div><div class="row g-2"><div class="col-md-3"><label class="form-label">Page</label><input id="pageNumber" class="form-control" type="number" min="1" value="<?= (int) ($session[
    "page_number"
] ??
    1) ?>"></div><div class="col-md-3"><label class="form-label">Auto Page</label><select id="autoPage" class="form-select"><option value="1" <?= !isset(
    $session["auto_page"],
) || $session["auto_page"]
    ? "selected"
    : "" ?>>On</option><option value="0" <?= isset($session["auto_page"]) &&
!$session["auto_page"]
    ? "selected"
    : "" ?>>Off</option></select></div><div class="col-md-3"><label class="form-label">Page Delay</label><select id="pageDelay" class="form-select"><?php foreach (
    [10, 15, 30, 45, 60]
    as $d
): ?><option value="<?= $d ?>" <?= ($session["page_delay_seconds"] ?? 30) === $d
    ? "selected"
    : "" ?>><?= $d ?> seconds</option><?php endforeach; ?></select></div></div><div class="small text-muted mt-2">Auto Page defaults to 30 seconds and applies to multi-page Competitors, Callbacks and Finalists.</div></div></div></main><script>(()=>{'use strict';const eventId=<?= $eventId ?>,roundId=<?= $roundId ?>;const csrfEl=document.getElementById('csrf'),msg=document.getElementById('remoteMessage'),liveState=document.getElementById('liveState'),pageNumber=document.getElementById('pageNumber'),autoPage=document.getElementById('autoPage'),pageDelay=document.getElementById('pageDelay'),formatEl=document.getElementById('format'),cwEl=document.getElementById('cw'),chEl=document.getElementById('ch'),copyBtn=document.getElementById('copyLiveUrl'),liveUrl=document.getElementById('liveUrl'),loopDelay=document.getElementById('loopDelay'),startLoop=document.getElementById('startLoop'),stopLoop=document.getElementById('stopLoop');if(!csrfEl||!msg||!pageNumber||!autoPage||!pageDelay){console.error('BDC projector controls missing required DOM elements');return;}const csrf=csrfEl.value;const loopTypes={holding:'Holding Screen',judges:'Judges',competitors:'Competitors / Couples',scoring:'Scoring Status',callbacks:'Callbacks',finalists:'Finalists',score_matrix:'Live Score Matrix · Provisional',heats_scores:'Heats Full Scores · Landscape',winners:'Winner Podium',final_results:'Final Full Results · Landscape'};const allowedHere=[...document.querySelectorAll('.feed')].map(b=>b.dataset.screen);document.getElementById('loopChoices').innerHTML=Object.entries(loopTypes).filter(([v])=>v==='holding'||allowedHere.includes(v)).map(([v,l])=>'<label class="form-check"><input class="form-check-input loop-screen" type="checkbox" value="'+v+'"> <span class="form-check-label">'+l+'</span></label>').join('');const savedLoop=<?= json_encode(
    array_values(
        array_filter(explode(",", (string) ($session["loop_screens"] ?? ""))),
    ),
) ?>;document.querySelectorAll('.loop-screen').forEach(x=>x.checked=savedLoop.includes(x.value));if(loopDelay)loopDelay.value=String(<?= json_encode(
    (int) ($session["loop_delay_seconds"] ?? 15),
) ?>);if(stopLoop)stopLoop.disabled=<?= empty($session["loop_enabled"])
    ? "true"
    : "false" ?>;let resultsUnlocked=<?= !empty($session["results_unlocked"])
    ? "true"
    : "false" ?>;async function postAction(data){const body=new URLSearchParams();body.set('_csrf',csrf);body.set('session_id',<?=json_encode((string)$sessionId)?>);body.set('event_id',String(eventId));body.set('round_id',String(roundId));body.set('data_mode',<?= json_encode(
    $test ? "test" : "real",
) ?>);body.set('page_number',String(pageNumber.value||1));body.set('auto_page',String(autoPage.value||'0'));body.set('page_delay_seconds',String(pageDelay.value||30));body.set('loop_delay_seconds',String(loopDelay?.value||15));body.set('loop_screens',[...document.querySelectorAll('.loop-screen:checked')].map(x=>x.value).join(','));Object.entries(data).forEach(([k,v])=>body.set(k,String(v)));const r=await fetch('live-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},cache:'no-store',credentials:'same-origin',body:body.toString()});const text=await r.text();let j;try{j=JSON.parse(text)}catch{throw new Error('Controller returned non-JSON response ('+r.status+').');}if(!r.ok||!j.ok)throw new Error(j.error||('Update failed ('+r.status+').'));return j;}function paint(j){if(j.session){resultsUnlocked=!!j.session.results_unlocked;document.querySelectorAll('.feed').forEach(b=>{const active=b.dataset.screen===j.session.screen_type;b.classList.toggle('btn-danger',active&&b.dataset.screen!=='holding');b.classList.toggle('btn-outline-danger',!active&&b.dataset.screen!=='holding');b.classList.toggle('btn-dark',active&&b.dataset.screen==='holding');b.classList.toggle('btn-outline-dark',!active&&b.dataset.screen==='holding');});const rp=document.getElementById('podiumReveal');if(rp)rp.classList.toggle('d-none',j.session.screen_type!=='winners');if(liveState)liveState.textContent='LIVE STATE: '+String(j.session.screen_type||'holding').toUpperCase()+' · Round '+roundId+' · Version '+Number(j.session.state_version||0);if(stopLoop)stopLoop.disabled=!j.session.loop_enabled;syncLock();}msg.className='small mb-2 text-success';msg.textContent='Server accepted command. Projector state updated.';}function syncLock(){document.querySelectorAll('[data-protected="1"]').forEach(b=>{b.disabled=!resultsUnlocked;b.classList.toggle('locked',!resultsUnlocked);const raw=b.textContent.replace(/^🔒\s*/,'');b.textContent=(resultsUnlocked?'':'🔒 ')+raw;});const u=document.getElementById('unlockResults'),l=document.getElementById('lockResults');if(u){u.textContent=resultsUnlocked?'Results Reveal Unlocked':'Unlock Results Reveal';u.className='btn '+(resultsUnlocked?'btn-success':'btn-warning')+' btn-sm';if(l)l.disabled=!resultsUnlocked;}}document.querySelectorAll('.feed').forEach(b=>b.addEventListener('click',async()=>{try{msg.className='small mb-2 text-muted';msg.textContent='Sending '+b.dataset.screen+' to projector…';const extra={};const j=await postAction({action:'update',screen_type:b.dataset.screen,...extra});paint(j);}catch(e){console.error(e);msg.className='small mb-2 text-danger';msg.textContent='Projector command failed: '+e.message;}}));document.querySelectorAll('.effect').forEach(b=>b.addEventListener('click',async()=>{try{msg.className='small mb-2 text-muted';msg.textContent='Sending '+b.dataset.effect+' effect to projector…';paint(await postAction({action:'effect',effect_type:b.dataset.effect}));msg.textContent=(b.dataset.effect==='none'?'Projector effect cleared.':b.textContent.trim()+' sent to projector.');}catch(e){msg.className='small mb-2 text-danger';msg.textContent='Effect command failed: '+e.message;}}));document.querySelectorAll('.reveal').forEach(b=>b.addEventListener('click',async()=>{try{msg.className='small mb-2 text-muted';msg.textContent='Revealing podium '+b.dataset.place+'…';paint(await postAction({action:'update',screen_type:'winners',reveal_place:b.dataset.place}));}catch(e){msg.className='small mb-2 text-danger';msg.textContent='Podium command failed: '+e.message;}}));if(startLoop)startLoop.addEventListener('click',async()=>{const chosen=[...document.querySelectorAll('.loop-screen:checked')];if(chosen.length<2){alert('Select at least two tabs to start the loop.');return;}try{paint(await postAction({action:'update',screen_type:chosen[0].value,loop_enabled:'1'}));}catch(e){alert(e.message);}});if(stopLoop)stopLoop.addEventListener('click',async()=>{try{paint(await postAction({action:'update',screen_type:'holding',loop_enabled:'0'}));}catch(e){alert(e.message);}});const unlock=document.getElementById('unlockResults');if(unlock)unlock.addEventListener('click',async()=>{if(resultsUnlocked)return;if(!confirm('This can reveal official competition results on the live projector. Continue?'))return;try{paint(await postAction({action:'unlock_results'}));}catch(e){alert(e.message);}});const lock=document.getElementById('lockResults');if(lock)lock.addEventListener('click',async()=>{try{paint(await postAction({action:'lock_results'}));}catch(e){alert(e.message);}});if(copyBtn&&liveUrl)copyBtn.addEventListener('click',()=>navigator.clipboard.writeText(liveUrl.value));function customState(){if(!formatEl||!cwEl||!chEl)return;const on=formatEl.value==='custom';cwEl.disabled=!on;chEl.disabled=!on;}if(formatEl)formatEl.addEventListener('change',customState);customState();<?php if (
    $embed
): ?>function report(){parent.postMessage({type:'bdc-control-height',height:document.documentElement.scrollHeight},'*');}new ResizeObserver(report).observe(document.body);window.addEventListener('load',report);<?php endif; ?>})();</script><script>(()=>{const delay=document.getElementById('loopDelay'),stop=document.getElementById('stopLoop'),message=document.getElementById('remoteMessage');if(!delay||!stop)return;delay.addEventListener('change',async()=>{if(stop.disabled){message.className='small mb-2 text-muted';message.textContent='Loop delay set to '+delay.value+' seconds. It will apply when the loop starts.';return;}const checked=[...document.querySelectorAll('.loop-screen:checked')].map(x=>x.value);if(checked.length<2)return;const body=new URLSearchParams({_csrf:document.getElementById('csrf').value,event_id:'<?=$eventId?>',round_id:'<?=$roundId?>',data_mode:<?=json_encode($test?'test':'real')?>,action:'update',screen_type:document.querySelector('.feed.btn-danger,.feed.btn-dark')?.dataset.screen||checked[0],page_number:document.getElementById('pageNumber').value||'1',auto_page:document.getElementById('autoPage').value||'0',page_delay_seconds:document.getElementById('pageDelay').value||'30',loop_enabled:'1',loop_screens:checked.join(','),loop_delay_seconds:delay.value});try{message.className='small mb-2 text-muted';message.textContent='Updating loop delay…';const r=await fetch('live-action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},credentials:'same-origin',cache:'no-store',body:body.toString()});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Update failed.');message.className='small mb-2 text-success';message.textContent='Loop delay updated to '+delay.value+' seconds.';}catch(e){message.className='small mb-2 text-danger';message.textContent='Loop delay update failed: '+e.message;}});})();</script></body></html>
