<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringJudgeAssignmentService;

Auth::requireAdmin();
$roundId=(int)($_POST['round_id']??0);
$return='automatic-round.php?round_id='.$roundId;
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){
    http_response_code(419);
    exit('Invalid security token.');
}

try{
    $pdo=Database::connection();
    $round=$pdo->prepare("SELECT scoring_mode FROM bdc_scoring_rounds WHERE id=:round LIMIT 1");
    $round->execute(['round'=>$roundId]);
    if((string)$round->fetchColumn()!=='automated')throw new RuntimeException('Automatic scoring round not found.');
    $rawNames=$_POST['judge_name']??[];$rawScopes=$_POST['judge_scope']??[];
    $rawAssignments=$_POST['judge_assignment_id']??[];$rawDirectory=$_POST['judge_directory_id']??[];$rows=[];
    foreach($rawNames as $index=>$name)$rows[(string)$index]=[
        'name'=>(string)$name,'scope'=>(string)($rawScopes[$index]??'all'),
        'assignment_id'=>(int)($rawAssignments[$index]??0),'directory_id'=>(int)($rawDirectory[$index]??0),
        'original_index'=>(int)$index,
    ];
    $saved=ScoringJudgeAssignmentService::save($pdo,$roundId,$rows,(int)($_POST['chief_index']??-1));
    $audit=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
    $audit->execute(['round'=>$roundId,'user'=>(int)(Auth::user()['id']??0)?:null,'action'=>'automatic_judges_saved','details'=>json_encode(['count'=>$saved['count'],'chief'=>$saved['chief_name'],'judge_profiles_created'=>$saved['created_directory_count']],JSON_UNESCAPED_SLASHES)]);
    $_SESSION['automatic_scoring_notice']='Judges saved and linked to the Judge Database.';
}catch(Throwable $e){
    http_response_code(422);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Judge setup could not be saved</title></head><body style="font-family:Arial;padding:32px"><h2>Judge setup could not be saved</h2><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p><p><a href="'.htmlspecialchars($return,ENT_QUOTES,'UTF-8').'">Return to Automatic Scoring</a></p></body></html>';
    exit;
}
header('Location: '.$return,true,303);exit;
