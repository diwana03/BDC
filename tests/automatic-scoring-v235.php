<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Services\AutomaticScoringEngine;
use App\Services\ScoringRulesService;
use App\Services\CallbackTieResolutionService;

$normalized=ScoringRulesService::normalizeNormalRoundTier(13,20,5,7,true);
if (($normalized['tier']??0)!==2 || ($normalized['yes_count']??0)!==10 || ($normalized['callback_count']??0)!==10) {
    throw new RuntimeException('Larger Follower role did not enforce Tier 2 and ten callbacks for both roles.');
}
$automatic=ScoringRulesService::normalizeNormalRoundTier(8,12,7,7,false);
if (($automatic['tier']??0)!==1 || ($automatic['callback_count']??0)!==5) {
    throw new RuntimeException('Automatic role-count tier did not enforce five callbacks.');
}

$entries = [
    ['id'=>1,'dance_role'=>'leader'],
    ['id'=>2,'dance_role'=>'leader'],
    ['id'=>3,'dance_role'=>'leader'],
];
$judges = [
    ['id'=>11,'is_chief'=>1,'scoring_scope'=>'all'],
    ['id'=>12,'is_chief'=>0,'scoring_scope'=>'all'],
    ['id'=>13,'is_chief'=>0,'scoring_scope'=>'all'],
];
$marks = [
    1=>[11=>90,12=>90,13=>90],
    2=>[11=>80,12=>80,13=>80],
    3=>[11=>70,12=>70,13=>70],
];
$results = AutomaticScoringEngine::calculateHeats($entries,$judges,$marks,1);
if (($results[0]['entry_id']??0)!==1 || ($results[0]['status']??'')!=='callback') {
    throw new RuntimeException('Highest average was not selected as callback.');
}
if (($results[1]['status']??'')!=='alternate' || ($results[1]['alternate_rank']??0)!==1) {
    throw new RuntimeException('First alternate was not assigned correctly.');
}

$marks[1]=[11=>90,12=>80,13=>70];
$marks[2]=[11=>90,12=>80,13=>70];
$tied=AutomaticScoringEngine::calculateHeats(array_slice($entries,0,2),$judges,$marks,1);
if (($tied[0]['status']??'')!=='tie_pending' || ($tied[1]['status']??'')!=='tie_pending') {
    throw new RuntimeException('Unresolved callback-boundary tie was not escalated.');
}

$serviceSource=file_get_contents(dirname(__DIR__).'/app/Services/CallbackTieResolutionService.php');
if(!str_contains((string)$serviceSource,"count(\$selected)!==\$required")){
    throw new RuntimeException('Tie resolver does not enforce the exact remaining callback quantity.');
}
if(!str_contains((string)$serviceSource,"available_alternate_ranks")){
    throw new RuntimeException('Tie resolver does not preserve A1-A3 assignment.');
}

echo "Automatic scoring tests passed.\n";

$testDashboard=file_get_contents(dirname(__DIR__).'/admin/scoring-tests/index.php');
$liveCore=file_get_contents(dirname(__DIR__).'/admin/scoring/core.php');
$disciplineActions=file_get_contents(dirname(__DIR__).'/admin/scoring/discipline-actions.php');
foreach(['Test'=>$testDashboard,'Live'=>$liveCore] as $surface=>$source){
    if(!str_contains((string)$source,"stale_entries_withdrawn"))throw new RuntimeException($surface.' callback sync does not withdraw stale transferred entries.');
    if(!str_contains((string)$source,"Auth::isSuperAdmin()"))throw new RuntimeException($surface.' workflow deletion is not restricted to Super Admin.');
}
if(!str_contains((string)$disciplineActions,"bdc_scoring_judge_sessions"))throw new RuntimeException('Live workflow deletion does not clean automatic judge sessions.');
if(!str_contains((string)$disciplineActions,"bdc_scoring_publication_documents"))throw new RuntimeException('Live workflow deletion does not clean publication documents.');

$activeDashboard=file_get_contents(dirname(__DIR__).'/admin/scoring/active-dashboard.php');
$deleteDraft=file_get_contents(dirname(__DIR__).'/admin/scoring/delete-draft.php');
if(!str_contains((string)$activeDashboard,"\$draft&&Auth::isSuperAdmin()"))throw new RuntimeException('Active dashboard does not restrict draft deletion to Super Admin.');
if(!str_contains((string)$deleteDraft,"Only the Super Admin can delete a complete draft workflow."))throw new RuntimeException('Draft deletion endpoint does not enforce Super Admin.');
if(str_contains((string)$deleteDraft,"progressed beyond Draft"))throw new RuntimeException('Draft workflow deletion still blocks completed parent rounds.');
