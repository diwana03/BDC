<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

\App\Core\Auth::requireAdmin();

$mode=(string)($_SESSION['bdc_test_scoring_mode']??'');
if(!in_array($mode,['manual','automated'],true)){
    header('Location: '.url('admin/scoring-tests/select-mode.php'),true,303);
    exit;
}

header('Location: '.url('admin/scoring-tests/index.php?legacy=1&test_mode='.$mode),true,303);
exit;
