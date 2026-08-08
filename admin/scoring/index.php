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
        <div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="flex-grow-1">Automatic scoring for the same competition categories. Judges score securely from their own browser and progress is visible live on the organiser dashboard.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

ob_start(static function(string $html)use($mode,$roundId):string{
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

    if($openedSpecialRound){
        $html=str_replace('publish.php?round_id=','special-publish.php?round_id=',$html);
        $html=str_replace('registration-desk/?token=','registration-desk/special.php?token=',$html);
    }

    if($mode==='automated' && $roundId>0){
        $html=str_replace('Automatic Relative Placement Final','Automatic Scoring Engine · Judge Browser Workflow',$html);
        $setup='<div class="card shadow-sm mb-4 border-primary" id="automatic-setup-panel">'
            .'<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"><strong>Automatic Scoring Setup</strong><span class="badge text-bg-light">DRAFT + AUTOSAVE</span></div>'
            .'<div class="card-body p-3"><iframe title="Automatic Scoring Setup" src="automatic-setup.php?round_id='.$roundId.'" style="width:100%;height:720px;border:0;border-radius:10px;background:#fff"></iframe></div></div>';
        $judgePanel='<div class="card shadow-sm mb-4 border-dark" id="automatic-judge-browser-panel">'
            .'<div class="card-header bg-dark text-white d-flex justify-content-between align-items-center"><strong>Judge Browser Scoring</strong><span class="badge text-bg-light">AFTER CONFIRMATION</span></div>'
            .'<div class="card-body p-3"><iframe title="Automatic Judge Browser Control" src="judge-control-v2.php?round_id='.$roundId.'" style="width:100%;height:610px;border:0;border-radius:10px;background:#fff"></iframe></div></div>';
        $needle='<div class="card shadow-sm mb-4 border-primary" id="registration-desk-sync">';
        $html=str_replace($needle,$setup.$needle.$judgePanel,$html);
        $html=str_replace('</body>',"<script>document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.card').forEach(card=>{const h=card.querySelector('h2,h3');if(!h)return;const t=h.textContent.trim();if(t.startsWith('1. Judge Panel')||t.startsWith('2. Judge Scores'))card.style.display='none';});});</script></body>",$html);
    }

    return $html;
});
require __DIR__.'/core.php';
