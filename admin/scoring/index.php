<?php
declare(strict_types=1);

$mode=(string)($_GET['mode']??'');
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);

/*
 * Special categories are categories, not a third scoring mode.
 * Keep old bookmarked links working by sending them into Manual Scoring.
 */
if($mode==='special'){
    $target='?mode=manual'.($roundId>0?'&round_id='.$roundId:'');
    header('Location: '.$target);
    exit;
}

/*
 * core.php intentionally remains the single Manual/Automatic scoring engine.
 * It historically validates only standard BDC divisions when creating a round,
 * so special-category creation is intercepted here and creates the same round
 * record before redirecting straight back into core.php.
 */
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
        <div class="col-md-6"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="flex-grow-1">Automatic scoring for the same competition categories. Special categories use the same scoring workflow with fixed points at publication.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

/*
 * Presentation adapter only. No scoring calculations are changed here.
 * Add the special categories to the existing Division dropdown and make an
 * existing special round use its fixed-point publication/registration backend.
 */
ob_start(static function(string $html):string{
    /* Detect the opened round BEFORE adding the special choices to the form. */
    $openedSpecialRound=stripos($html,'bachata_rising')!==false
        ||stripos($html,'bachata_open')!==false
        ||stripos($html,'bachata_invitational')!==false;

    $html=str_replace(
        '<option value="advanced">Advanced</option>\n<option value="all_star">All Star</option>',
        '<option value="advanced">Advanced</option>\n<option value="bachata_rising">Bachata Rising</option>\n<option value="bachata_open">Bachata Open</option>\n<option value="bachata_invitational">Bachata Invitational</option>',
        $html
    );
    $html=str_replace('<option value="all_star">All Star</option>','',$html);

    $html=str_replace(
        ['Bachata_rising','Bachata_open','Bachata_invitational','BACHATA_RISING','BACHATA_OPEN','BACHATA_INVITATIONAL'],
        ['Bachata Rising','Bachata Open','Bachata Invitational','BACHATA RISING','BACHATA OPEN','BACHATA INVITATIONAL'],
        $html
    );

    if($openedSpecialRound){
        $html=str_replace('publish.php?round_id=','special-publish.php?round_id=',$html);
        $html=str_replace('registration-desk/?token=','registration-desk/special.php?token=',$html);
    }

    return $html;
});
require __DIR__.'/core.php';
