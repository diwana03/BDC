<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
\App\Core\Auth::requireAdmin();
$_SESSION['bdc_test_scoring_mode']='automated';
$roundId=(int)($_GET['round_id']??0);
$target='admin/scoring-tests/index.php?legacy=1&test_mode=automated'.($roundId>0?'&round_id='.$roundId:'');
header('Location: '.url($target),true,303);
exit;
