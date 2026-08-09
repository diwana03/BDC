<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringCalculationService;

Auth::requireAdmin();

$mode=(string)($_GET['test_mode']??$_SESSION['bdc_test_scoring_mode']??'manual');
if(!in_array($mode,['manual','automated'],true))$mode='manual';
$_SESSION['bdc_test_scoring_mode']=$mode;

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && (string)($_POST['action']??'')==='generate_results'){
    if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
    $roundId=(int)($_POST['round_id']??0);
    if($roundId<1){http_response_code(400);exit('Invalid scoring round.');}
    ScoringCalculationService::calculateHeats(Database::connection(),$roundId,ScoringCalculationService::TEST,(int)(Auth::user()['id']??0));
    header('Location: '.url('admin/scoring-tests/dashboard.php?legacy=1&test_mode='.$mode.'&round_id='.$roundId.'&shared_engine=1'),true,303);
    exit;
}

$_GET['legacy']=1;
$_GET['test_mode']=$mode;

ob_start();
require __DIR__.'/index.php';
$html=(string)ob_get_clean();

$modeLabel=$mode==='automated'?'Automatic Scoring Test':'Manual Scoring Test';
$html=str_replace('Manual Scoring Engine · Event Round Workflow',$modeLabel.' · Event Round Workflow',$html);

$badge='<span style="display:inline-block;margin-left:10px;padding:4px 9px;border-radius:6px;background:'.($mode==='automated'?'#0d6efd':'#212529').';color:#fff;font-size:12px;font-weight:800;vertical-align:middle">'.($mode==='automated'?'AUTOMATIC TEST':'MANUAL TEST').'</span>';
$html=str_replace('Scoring Tests Dashboard</h1>','Scoring Tests Dashboard'.$badge.'</h1>',$html);

$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$endpoint=url('admin/scoring-tests/automatic-inline.php?round_id='.$roundId.'&test_mode='.$mode);
$actionEndpoint=url('admin/scoring-tests/automatic-inline.php');
$csrf=Csrf::token();
$judgeList=[];
if($roundId>0){
    try{
        $stmt=Database::connection()->prepare('SELECT id,judge_name,is_chief FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');
        $stmt->execute(['r'=>$roundId]);
        $judgeList=$stmt->fetchAll();
    }catch(Throwable){$judgeList=[];}
}
$modeJson=json_encode($mode,JSON_UNESCAPED_SLASHES);
$endpointJson=json_encode($endpoint,JSON_UNESCAPED_SLASHES);
$actionJson=json_encode($actionEndpoint,JSON_UNESCAPED_SLASHES);
$csrfJson=json_encode($csrf,JSON_UNESCAPED_SLASHES);
$judgeListJson=json_encode($judgeList,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

$enhancement=<<<HTML
<script id="bdc-test-mode-enhancement">
(function(){
  const mode=$modeJson;
  const roundId=$roundId;
  const endpoint=$endpointJson;
  const actionEndpoint=$actionJson;
  const csrf=$csrfJson;
  const judgeList=$judgeListJson;

  function postButton(label,action,className,confirmText,extra){
    const form=document.createElement('form');
    form.method='post';form.action=actionEndpoint;form.style.display='inline';
    if(confirmText)form.onsubmit=function(){return window.confirm(confirmText);};
    const fields=Object.assign({_csrf:csrf,round_id:String(roundId),test_mode:mode,action:action},extra||{});
    Object.entries(fields).forEach(([name,value])=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=String(value);form.appendChild(input);});
    const button=document.createElement('button');button.className=className;button.type='submit';button.textContent=label;form.appendChild(button);return form;
  }

  function addCompetitorClearControl(){
    if(!roundId||document.querySelector('[data-bdc-delete-all-competitors]'))return;
    const roleCards=[...document.querySelectorAll('.role-card')];
    if(!roleCards.length)return;
    const first=roleCards[0];
    const host=document.createElement('div');host.className='mb-2 d-flex justify-content-end';host.dataset.bdcDeleteAllCompetitors='1';
    host.appendChild(postButton('Delete All Competitors','delete_all_entries','btn btn-sm btn-outline-danger','Delete all competitors from this TEST round?'));
    first.parentElement.insertBefore(host,first);
  }

  function addJudgeControls(){
    if(!roundId||document.querySelector('[data-bdc-judge-delete-controls]'))return;
    const wrap=document.getElementById('judgesWrap');
    if(!wrap)return;
    const host=document.createElement('div');host.className='border-top pt-2 mt-2';host.dataset.bdcJudgeDeleteControls='1';
    const title=document.createElement('div');title.className='small fw-semibold mb-2';title.textContent='Test Judge Controls';host.appendChild(title);
    const row=document.createElement('div');row.className='d-flex flex-wrap gap-2 align-items-center';
    judgeList.forEach(j=>{row.appendChild(postButton('Delete '+j.judge_name,'delete_judge','btn btn-sm btn-outline-danger','Delete '+j.judge_name+' and this judge’s test marks?',{judge_id:j.id}));});
    row.appendChild(postButton('Delete All Judges','delete_all_judges','btn btn-sm btn-outline-danger','Delete all judges and their test marks?'));
    row.appendChild(postButton('Clear Entire Test Round','clear_round','btn btn-sm btn-danger','Clear judges, competitors, marks and results for this TEST round?'));
    host.appendChild(row);wrap.parentElement.appendChild(host);
  }

  addCompetitorClearControl();
  addJudgeControls();
  if(mode!=='automated'||!roundId)return;

  const form=document.getElementById('heatsScoreForm');
  if(!form)return;
  let target=form.closest('.card');
  if(!target)target=form;
  const shell=document.createElement('div');shell.id='bdcAutomaticInlineShell';
  target.replaceWith(shell);

  async function loadPanel(){
    try{
      const response=await fetch(endpoint,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
      if(!response.ok)throw new Error('Automatic judge panel could not load.');
      shell.innerHTML=await response.text();
      shell.querySelectorAll('script').forEach(oldScript=>{
        const script=document.createElement('script');script.textContent=oldScript.textContent;oldScript.replaceWith(script);
      });
    }catch(error){shell.innerHTML='<div class="alert alert-danger"><strong>Judge Live Scoring could not load.</strong><br>'+String(error.message||error)+'</div>';}
  }
  loadPanel();
})();
</script>
HTML;

$html=preg_replace('/<\/body>/i',$enhancement.'</body>',$html,1)??$html;
echo $html;
