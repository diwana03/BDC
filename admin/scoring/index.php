<?php
declare(strict_types=1);

$mode=(string)($_GET['mode']??'');
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);

if($mode==='special'){
    if($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action']??'')==='create_special_round'){
        $_POST['scoring_mode']='manual';
    }
    if($roundId===0){
        ob_start();
        require __DIR__.'/special.php';
        $specialHtml=(string)ob_get_clean();
        $specialHtml=str_replace(
            '<div class="col-md-4"><label class="form-label">Scoring Mode</label><select class="form-select" name="scoring_mode"><option value="manual">Manual Scoring</option><option value="automated">Automatic Scoring</option></select></div>',
            '<div class="col-md-4"><label class="form-label">Scoring Mode</label><input type="hidden" name="scoring_mode" value="manual"><div class="form-control bg-light">Manual Scoring</div><div class="form-text">Special-category automatic scoring can be added in a later release.</div></div>',
            $specialHtml
        );
        echo $specialHtml;
        exit;
    }
    require __DIR__.'/special.php';
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
        body{min-height:100vh;background:#f4f6f9}.mode-shell{max-width:1180px}.mode-card{height:100%;border:1px solid #dfe3e8;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.07)}.mode-icon{display:grid;width:58px;height:58px;place-items:center;border-radius:15px;background:#111827;color:#fff;font-size:1.7rem}.special-icon{background:#6b1730}.mode-card p{color:#667085}
      </style>
    </head>
    <body>
    <nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></nav>
    <main class="container mode-shell py-5">
      <div class="text-center mb-5"><h1 class="display-6 fw-bold">Select Scoring Mode</h1><p class="text-muted mb-0">Choose the competition workflow you want to run.</p></div>
      <div class="row g-4">
        <div class="col-md-4"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">✎</div><h2 class="h3">Manual Scoring</h2><p class="flex-grow-1">Existing BDC Novice, Intermediate and Advanced manual scoring workflow.</p><a class="btn btn-dark btn-lg" href="?mode=manual">Continue</a></div></section></div>
        <div class="col-md-4"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="flex-grow-1">Existing BDC automatic scoring for Novice, Intermediate and Advanced.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div>
        <div class="col-md-4"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon special-icon mb-4">★</div><h2 class="h3">Special Categories</h2><p class="flex-grow-1">Bachata Rising, Bachata Open and Bachata Invitational with fixed special-category points.</p><a class="btn btn-danger btn-lg" href="?mode=special">Continue</a></div></section></div>
      </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

ob_start(static function(string $html):string{
    return str_replace('<option value="all_star">All Star</option>','',$html);
});
require __DIR__.'/core.php';
