<?php
declare(strict_types=1);

$mode=(string)($_GET['mode']??'');
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);

if($mode==='special'){
    $target='?mode=manual'.($roundId>0?'&round_id='.$roundId:'');
    header('Location: '.$target);
    exit;
}

if(
    $_SERVER['REQUEST_METHOD']==='POST'
    && (string)($_POST['action']??'')==='create_round'
    && in_array((string)($_POST['division']??''),['bachata_rising','bachata_open','bachata_invitational'],true)
){
    require __DIR__.'/integrated-special-create.php';
    exit;
}

if($_SERVER['REQUEST_METHOD']==='GET' && $mode==='' && $roundId===0){
    require dirname(__DIR__,2).'/bootstrap.php';
    App\Core\Auth::requireAdmin();
    ?>
    <!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Select Scoring Mode | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{min-height:100vh;background:#f4f6f9}.mode-shell{max-width:900px}.mode-card{height:100%;border:1px solid #dfe3e8;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.07)}.mode-icon{display:grid;width:58px;height:58px;place-items:center;border-radius:15px;background:#111827;color:#fff;font-size:1.7rem}.mode-card p{color:#667085}</style></head>
    <body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></nav><main class="container mode-shell py-5"><div class="text-center mb-5"><h1 class="display-6 fw-bold">Select Scoring Mode</h1><p class="text-muted mb-0">Choose Manual or Automatic scoring, then select the competition category.</p></div><div class="row g-4 justify-content-center"><div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">✎</div><h2 class="h3">Manual Scoring</h2><p class="flex-grow-1">Manual scoring for Novice, Intermediate, Advanced, Bachata Rising, Bachata Open and Bachata Invitational.</p><a class="btn btn-dark btn-lg" href="?mode=manual">Continue</a></div></section></div><div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="flex-grow-1">Same Heats setup as Manual. Judges receive secure browser scoring links and their progress is visible live.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div></div></main></body></html>
    <?php
    exit;
}

$automaticSetupHtml='';
$automaticRoundHeading='';
if($mode==='automated' && $roundId>0){
    require_once dirname(__DIR__,2).'/bootstrap.php';
    require_once __DIR__.'/automatic-common-setup.php';
    try{
        $automaticSetupHtml=bdcRenderAutomaticCommonSetup($roundId);
        $pdo=App\Core\Database::connection();
        $stmt=$pdo->prepare("SELECT r.division,r.round_type,r.status,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
        $stmt->execute(['round'=>$roundId]);
        if($meta=$stmt->fetch()){
            $category=App\Services\SpecialCategoryService::isSpecial((string)$meta['division'])
                ?App\Services\SpecialCategoryService::label((string)$meta['division'])
                :ucfirst((string)$meta['division']);
            $automaticRoundHeading='<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4"><div><div class="text-uppercase text-primary fw-bold small">Automatic Scoring</div><h1 class="h2 mb-1">'.htmlspecialchars((string)$meta['event_name'],ENT_QUOTES,'UTF-8').'</h1><p class="text-muted mb-0">'.htmlspecialchars($category,ENT_QUOTES,'UTF-8').' · '.htmlspecialchars(ucfirst((string)$meta['round_type']),ENT_QUOTES,'UTF-8').'</p></div><span class="badge text-bg-primary">'.htmlspecialchars(ucwords(str_replace('_',' ',(string)$meta['status'])),ENT_QUOTES,'UTF-8').'</span></div>';
        }
    }catch(Throwable){$automaticSetupHtml='';$automaticRoundHeading='';}
}

ob_start(static function(string $html)use($mode,$roundId,$automaticSetupHtml,$automaticRoundHeading):string{
    $specialOptions='<option value="bachata_rising">Bachata Rising</option><option value="bachata_open">Bachata Open</option><option value="bachata_invitational">Bachata Invitational</option>';
    if(str_contains($html,'<option value="all_star">All Star</option>'))$html=str_replace('<option value="all_star">All Star</option>',$specialOptions,$html);
    elseif(!str_contains($html,'<option value="bachata_rising">'))$html=str_replace('<option value="advanced">Advanced</option>','<option value="advanced">Advanced</option>'.$specialOptions,$html);

    $html=str_replace(
        ['Bachata_rising','Bachata_open','Bachata_invitational','BACHATA_RISING','BACHATA_OPEN','BACHATA_INVITATIONAL'],
        ['Bachata Rising','Bachata Open','Bachata Invitational','BACHATA RISING','BACHATA OPEN','BACHATA INVITATIONAL'],
        $html
    );

    if(str_contains($html,'<h2 class="h5">Saved Rounds</h2>')){
        $html=str_replace('Manual Scoring Engine · Event Round Workflow','BDC Scoring Engine · Event Round Workflow',$html);
        $html=str_replace('<th>Round</th><th>Status</th>','<th>Round</th><th>Scoring Mode</th><th>Status</th>',$html);
        try{
            $pdo=App\Core\Database::connection();$modeRows=$pdo->query("SELECT id,scoring_mode FROM bdc_scoring_rounds")->fetchAll();$modeByRound=[];
            foreach($modeRows as $modeRow)$modeByRound[(int)$modeRow['id']]=(string)($modeRow['scoring_mode']??'manual');
            $html=preg_replace_callback('/<tr>(.*?)<\/tr>/s',static function(array $match)use($modeByRound):string{
                $row=$match[1];if(!preg_match('/href="\?round_id=(\d+)"/',$row,$idMatch))return $match[0];$id=(int)$idMatch[1];$storedMode=$modeByRound[$id]??'manual';$auto=$storedMode==='automated';$label=$auto?'AUTOMATIC':'MANUAL';$badge=$auto?'text-bg-primary':'text-bg-dark';$rowClass=$auto?'scoring-row-automatic':'scoring-row-manual';$target=$auto?'automated':'manual';$row=preg_replace('/href="\?round_id='.$id.'"/','href="?mode='.$target.'&round_id='.$id.'"',$row,1);$row=preg_replace('/^((?:.*?<\/td>){3})/s','$1<td><span class="badge '.$badge.'">'.$label.'</span></td>',$row,1);return '<tr class="'.$rowClass.'">'.$row.'</tr>';
            },$html)??$html;
            $html=str_replace('</head>','<style>.scoring-row-automatic>td{background:#eef5ff!important}.scoring-row-manual>td{background:#f7f7f8!important}.scoring-row-automatic:hover>td{background:#e3efff!important}.scoring-row-manual:hover>td{background:#eeeeef!important}</style></head>',$html);
        }catch(Throwable){}
    }

    if($mode==='automated' && $roundId>0){
        $browserPanel='<section class="card shadow-sm mb-4 border-dark" id="automatic-judge-browser-panel"><div class="card-body"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2"><div><h2 class="h5 mb-1">Judge Live Scoring</h2><p class="text-muted small mb-0">Judges score the same Heats competitors using YES / A1 / A2 / A3 from secure browser links. Share by Copy, WhatsApp, Email or Open.</p></div><span class="badge text-bg-dark">LIVE</span></div><iframe title="Automatic Judge Browser Control" src="judge-control.php?round_id='.$roundId.'" style="width:100%;height:690px;border:0;border-radius:10px;background:#fff"></iframe></div></section>';

        /* The legacy Automatic renderer exits early. Replace its entire main area in one operation. */
        if($automaticSetupHtml!=='' && preg_match('#<main class="container-fluid py-4" style="max-width:1600px">.*?</main>#s',$html)){
            $newMain='<main class="container-fluid py-4" style="max-width:1600px">'.$automaticRoundHeading.$automaticSetupHtml.$browserPanel.'</main>';
            $html=preg_replace('#<main class="container-fluid py-4" style="max-width:1600px">.*?</main>#s',static fn()=>$newMain,$html,1)??$html;
        }else{
            /* Shared Manual renderer path: keep every setup block and replace only Manual Score Entry. */
            $scorePattern='#<form method="post" id="heatsScoreForm".*?</form>#s';
            $html=preg_replace($scorePattern,static fn()=>$browserPanel,$html,1)??$html;
        }

        $script=<<<'JS'
<script>
function addJudge(){
  const w=document.getElementById('judgesWrap');if(!w)return;
  const i=w.querySelectorAll('.judge-row').length;
  const d=document.createElement('div');d.className='row g-2 mb-2 judge-row align-items-center';
  d.innerHTML='<div class="col-md-2"><strong>Judge '+(i+1)+'</strong></div><div class="col-md-5"><input class="form-control" name="judge_name[]" placeholder="Judge name" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="'+i+'"> Chief</label></div>';
  w.appendChild(d);d.querySelector('input[name="judge_name[]"]')?.focus();
}
function updateTierSummary(){const tier=document.getElementById('competitionTier'),out=document.getElementById('tierYesCount');if(tier&&out)out.value=({1:5,2:10,3:15})[tier.value]||10;}
</script>
JS;
        $html=str_replace('</body>',$script.'</body>',$html);
    }
    return $html;
});
require __DIR__.'/core.php';
