<?php
declare(strict_types=1);
namespace App\Services;
final class ScoringRoundNavigation{
 public static function html(int $roundId,bool $test,string $backUrl,string $dashboardUrl):string{
  if($roundId<1)return '';
  $live=$test?'../live-screen/test-control.php?round_id='.$roundId:'../live-screen/control.php?round_id='.$roundId;
  return '<div class="d-flex gap-2 align-items-center bdc-round-nav"><a class="btn btn-danger btn-sm" href="'.htmlspecialchars($live,ENT_QUOTES,'UTF-8').'">Live Screen</a><a class="btn btn-outline-light btn-sm" href="'.htmlspecialchars($backUrl,ENT_QUOTES,'UTF-8').'">← Back</a><a class="btn btn-light btn-sm" href="'.htmlspecialchars($dashboardUrl,ENT_QUOTES,'UTF-8').'">⌂ Dashboard</a></div>';
 }
}