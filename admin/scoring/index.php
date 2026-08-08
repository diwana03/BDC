<?php
declare(strict_types=1);

$mode=(string)($_GET['mode']??'');
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);

/* Special categories are categories, not a third scoring mode. */
if($mode==='special'){
    $target='?mode=manual'.($roundId>0?'&round_id='.$roundId:'');
    header('Location: '.$target);
    exit;
}

/* Special-category creation still creates the same core scoring round. */
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
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Select Scoring Mode | BDC Admin</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <style>
        body{min-height:100vh;background:#f4f6f9}.mode-shell{max-width:900px}.mode-card{height:100%;border:1px solid #dfe3e8;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.07)}.mode-icon{display:grid;width:58px;height:58px;place-items:center;border-radius:15px;background:#111827;color:#fff;font-size:1.7rem}.mode-card p{color:#667085}
      </style>
    </head>
    <body>
    <nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></nav>
    <main class="container mode-shell py-5">
      <div class="text-center mb-5"><h1 class="display-6 fw-bold">Select Scoring Mode</h1><p class="text-muted mb-0">Choose Manual or Automatic scoring, then select the competition category.</p></div>
      <div class="row g-4 justify-content-center">
        <div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">✎</div><h2 class="h3">Manual Scoring</h2><p class="flex-grow-1">Manual scoring for Novice, Intermediate, Advanced, Bachata Rising, Bachata Open and Bachata Invitational.</p><a class="btn btn-dark btn-lg" href="?mode=manual">Continue</a></div></section></div>
        <div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="flex-grow-1">Uses the same registration, tier and judge setup as Manual Scoring. Judges receive secure browser scoring links and their progress is visible live.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$automaticSetupHtml='';
if($mode==='automated' && $roundId>0){
    require_once dirname(__DIR__,2).'/bootstrap.php';
    require_once __DIR__.'/automatic-common-setup.php';
    try{$automaticSetupHtml=bdcRenderAutomaticCommonSetup($roundId);}catch(Throwable){$automaticSetupHtml='';}
}

ob_start(static function(string $html)use($mode,$roundId,$automaticSetupHtml):string{
    $openedSpecialRound=$roundId>0 && (
        stripos($html,'BACHATA_RISING')!==false
        || stripos($html,'BACHATA_OPEN')!==false
        || stripos($html,'BACHATA_INVITATIONAL')!==false
        || stripos($html,'Bachata_rising')!==false
        || stripos($html,'Bachata_open')!==false
        || stripos($html,'Bachata_invitational')!==false
    );

    $specialOptions='<option value="bachata_rising">Bachata Rising</option>'
        .'<option value="bachata_open">Bachata Open</option>'
        .'<option value="bachata_invitational">Bachata Invitational</option>';
    if(str_contains($html,'<option value="all_star">All Star</option>')){
        $html=str_replace('<option value="all_star">All Star</option>',$specialOptions,$html);
    }elseif(!str_contains($html,'<option value="bachata_rising">')){
        $html=str_replace('<option value="advanced">Advanced</option>','<option value="advanced">Advanced</option>'.$specialOptions,$html);
    }

    $html=str_replace(
        ['Bachata_rising','Bachata_open','Bachata_invitational','BACHATA_RISING','BACHATA_OPEN','BACHATA_INVITATIONAL'],
        ['Bachata Rising','Bachata Open','Bachata Invitational','BACHATA RISING','BACHATA OPEN','BACHATA INVITATIONAL'],
        $html
    );

    /* Saved Rounds shows and preserves the stored scoring mode. */
    if(str_contains($html,'<h2 class="h5">Saved Rounds</h2>')){
        $html=str_replace('Manual Scoring Engine · Event Round Workflow','BDC Scoring Engine · Event Round Workflow',$html);
        $html=str_replace('<th>Round</th><th>Status</th>','<th>Round</th><th>Scoring Mode</th><th>Status</th>',$html);

        try{
            $pdo=App\Core\Database::connection();
            $modeRows=$pdo->query("SELECT id,scoring_mode FROM bdc_scoring_rounds")->fetchAll();
            $modeByRound=[];
            foreach($modeRows as $modeRow)$modeByRound[(int)$modeRow['id']]=(string)($modeRow['scoring_mode']??'manual');

            $html=preg_replace_callback('/<tr>(.*?)<\/tr>/s',static function(array $match)use($modeByRound):string{
                $row=$match[1];
                if(!preg_match('/href="\?round_id=(\d+)"/',$row,$idMatch))return $match[0];
                $id=(int)$idMatch[1];
                $storedMode=$modeByRound[$id]??'manual';
                $isAutomatic=$storedMode==='automated';
                $label=$isAutomatic?'AUTOMATIC':'MANUAL';
                $badge=$isAutomatic?'text-bg-primary':'text-bg-dark';
                $rowClass=$isAutomatic?'scoring-row-automatic':'scoring-row-manual';
                $targetMode=$isAutomatic?'automated':'manual';
                $row=preg_replace('/href="\?round_id='.$id.'"/','href="?mode='.$targetMode.'&round_id='.$id.'"',$row,1);
                $modeCell='<td><span class="badge '.$badge.'">'.$label.'</span></td>';
                $row=preg_replace('/^((?:.*?<\/td>){3})/s','$1'.$modeCell,$row,1);
                return '<tr class="'.$rowClass.'">'.$row.'</tr>';
            },$html)??$html;

            $modeStyles='<style>.scoring-row-automatic>td{background:#eef5ff!important}.scoring-row-manual>td{background:#f7f7f8!important}.scoring-row-automatic:hover>td{background:#e3efff!important}.scoring-row-manual:hover>td{background:#eeeeef!important}</style>';
            $html=str_replace('</head>',$modeStyles.'</head>',$html);
        }catch(Throwable){
            /* UI enhancement only; never block scoring. */
        }
    }

    if($openedSpecialRound){
        $html=str_replace('publish.php?round_id=','special-publish.php?round_id=',$html);
        /* Registration Desk intentionally stays the shared Manual/Heats desk. */
    }

    if($mode==='automated' && $roundId>0){
        $html=str_replace('Automatic Relative Placement Final','Automatic Scoring Engine · Same Heats Workflow',$html);

        $browserPanel='<section class="card shadow-sm mb-4 border-dark" id="automatic-judge-browser-panel"><div class="card-body">'
            .'<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2"><div><h2 class="h5 mb-1">Judge Live Scoring</h2><p class="text-muted small mb-0">Same competitors, bibs, judges and scoring rules as Manual Heats. Judges enter YES / A1 / A2 / A3 from their secure browser link. Use Copy, WhatsApp, Email or Open to send each link.</p></div><span class="badge text-bg-dark">LIVE</span></div>'
            .'<iframe title="Automatic Judge Browser Control" src="judge-control.php?round_id='.$roundId.'" style="width:100%;height:690px;border:0;border-radius:10px;background:#fff"></iframe></div></section>';

        /* Legacy Automatic Heats page: replace its separate setup with the shared Manual-style setup. */
        if($automaticSetupHtml!=='' && str_contains($html,'<h2 class="h5">1. Judge Panel</h2>')){
            $pattern='/<section class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">1\. Judge Panel<\/h2>.*?<\/section>\s*<section class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex.*?<h2 class="h5 mb-1">2\. Judge Scores<\/h2>.*?<\/section>/s';
            $replacement=$automaticSetupHtml.$browserPanel;
            $html=preg_replace($pattern,static fn()=>$replacement,$html,1)??$html;
        }else{
            /* Common Manual-style page / Final: remove manual score entry and insert live feed. */
            $html=str_replace(
                '<button class="btn btn-outline-primary">Save Judge Panel</button>',
                '<button type="button" class="btn btn-outline-secondary me-2" id="automaticAddJudge">+ Add Judge</button><button class="btn btn-outline-primary">Save Judge Panel</button>',
                $html
            );
            $html=str_replace('</main>',$browserPanel.'</main>',$html);
        }

        $script=<<<'JS'
<script>
document.addEventListener('DOMContentLoaded',function(){
  const scoreHeading=[...document.querySelectorAll('h2')].find(h=>h.textContent.trim().startsWith('Manual ')&&h.textContent.includes(' Score Entry'));
  const scoreSection=scoreHeading?scoreHeading.closest('.card'):null;
  if(scoreSection)scoreSection.remove();
});
</script>
JS;
        $html=str_replace('</body>',$script.'</body>',$html);
    }

    return $html;
});
require __DIR__.'/core.php';
