<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
Auth::requireAdmin();
$testMode=(string)($_GET['test_mode']??$_POST['test_mode']??$_SESSION['bdc_test_scoring_mode']??'manual');
if(!in_array($testMode,['manual','automated'],true))$testMode='manual';
$_SESSION['bdc_test_scoring_mode']=$testMode;
$_GET['legacy']=1;
$_GET['test_mode']=$testMode;
require __DIR__.'/index.php';
?>
<script>
window.BDC_SCORING_TEST_MODE=window.BDC_SCORING_TEST_MODE||{};
window.BDC_SCORING_TEST_MODE.mode=<?=json_encode($testMode)?>;
window.BDC_SCORING_TEST_MODE.automaticEndpoint=<?=json_encode(url('admin/scoring-tests/automatic-inline.php'))?>;
window.BDC_SCORING_TEST_MODE.actionEndpoint=<?=json_encode(url('admin/scoring-tests/automatic-inline.php'))?>;
</script>
<script src="<?=e(url('public/js/scoring-tests-mode-v2412.js'))?>"></script>
