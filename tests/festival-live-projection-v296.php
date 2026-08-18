<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=file_get_contents($root.'/app/Services/LiveDisplaySessionService.php');$workspace=file_get_contents($root.'/admin/live-screen/projection-workspace.php');$live=file_get_contents($root.'/admin/live-screen/index.php');$test=file_get_contents($root.'/admin/live-screen/test-index.php');$control=file_get_contents($root.'/admin/live-screen/control.php');$feed=file_get_contents($root.'/live-display/feed.php');$state=file_get_contents($root.'/live-display/state.php');$fx=file_get_contents($root.'/live-display/index.php');
$checks=[
 'shared Live workspace'=>str_contains($live,'$projectionTest=false')&&str_contains($live,'projection-workspace.php'),
 'shared Test workspace'=>str_contains($test,'$projectionTest=true')&&str_contains($test,'projection-workspace.php'),
 'multi-event selection UI'=>str_contains($workspace,'Multi-Event Festival Projection')&&str_contains($workspace,'event_ids[]'),
 'persistent festival session'=>str_contains($service,'function generateFestival')&&str_contains($service,'bdc_live_display_session_events'),
 'isolated Test and Live validation'=>str_contains($service,"\$test?'bdc_test_events':'bdc_events'")&&str_contains($service,"\$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds'"),
 'membership enforcement'=>str_contains($service,'Selected event is not part of this festival projection.'),
 'event switch forces holding'=>str_contains($service,"active_event_id=:ae,current_round_id=:r,screen_type='holding'"),
 'shared controller session'=>str_contains($control,'LiveDisplaySessionService::byId')&&str_contains($control,'Shared Festival Link'),
 'feed follows active event'=>str_contains($feed,'$activeEventId=(int)($session["active_event_id"]??$session["event_id"])'),
 'state follows active event'=>str_contains($state,'active_event_id'),
 'visible bounded fireworks'=>str_contains($fx,'260-particles.length')&&str_contains($fx,'innerHeight*.18,72')&&str_contains($fx,'},1050)'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}echo "PASS: {$label}\n";}
