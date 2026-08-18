<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;

Auth::requireAdmin();

$mode=(string)($_GET['mode']??'');
if(in_array($mode,['manual','automated'],true)){
    $_SESSION['bdc_test_scoring_mode']=$mode;
    $target=$mode==='automated'
        ? 'admin/scoring-tests/automatic-screen.php'
        : 'admin/scoring-tests/panel.php';
    header('Location: '.url($target),true,303);
    exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scoring Tests Dashboard | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=274" rel="stylesheet"><style>body{min-height:100vh;background:#f4f6f9}.mode-shell{max-width:1200px}.mode-card{height:100%;border:1px solid #dfe3e8;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.07)}.mode-icon{display:grid;width:58px;height:58px;place-items:center;border-radius:15px;background:#111827;color:#fff;font-size:1.7rem}</style></head><body>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></nav>
<main class="container mode-shell py-5"><div class="text-center mb-5"><div class="text-danger fw-bold text-uppercase small mb-2">Scoring Tests · TEST ONLY</div><h1 class="display-6 fw-bold">Scoring Tests Dashboard</h1><p class="text-muted mb-0">Choose Manual or Automatic Testing. Test data remains isolated from Live.</p></div><div class="row g-4">
<div class="col-lg-4"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">✎</div><h2 class="h3">Manual Scoring</h2><p class="text-muted flex-grow-1">Admin-entered Test scoring using isolated scoring data.</p><a class="btn btn-dark btn-lg" href="?mode=manual">Continue</a></div></section></div>
<div class="col-lg-4"><section class="card mode-card"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">⚙</div><h2 class="h3">Automatic Scoring</h2><p class="text-muted flex-grow-1">Secure Test judge workflow with live progress and Testing-only generators.</p><a class="btn btn-primary btn-lg" href="?mode=automated">Continue</a></div></section></div>
<div class="col-lg-4"><section class="card mode-card border-danger"><div class="card-body p-4 d-flex flex-column"><div class="mode-icon mb-4">▣</div><h2 class="h3">Live Screen Projector</h2><p class="text-muted flex-grow-1">Test the projector workflow using isolated Testing data.</p><a class="btn btn-danger btn-lg" target="_blank" rel="noopener" href="../live-screen/test-index.php">Open Test Projector</a></div></section></div></div></main></body></html>
