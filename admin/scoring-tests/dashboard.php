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

/* Preserve the single shared BDC calculation path while this wrapper renders the established dashboard. */
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
$endpoint=url('admin/scoring-tests/automatic-inline.php?round_id='.$roundId);
$actionEndpoint=url('admin/scoring-tests/automatic-inline.php');
$csrf=Csrf::token();

$enhancement=<<<HTML
<script id="bdc-test-mode-enhancement">
(function(){
  const mode=${json_encode($mode)};
  const roundId=${roundId};
  const endpoint=${json_encode($endpoint,JSON_UNESCAPED_SLASHES)};
  const actionEndpoint=${json_encode($actionEndpoint,JSON_UNESCAPED_SLASHES)};
  const csrf=${json_encode($csrf)};

  function postButton(label,action,className,confirmText,extra){
    const form=document.createElement('form');
    form.method='post';form.action=actionEndpoint;form.style.display='inline';
    if(confirmText)form.onsubmit=function(){return window.confirm(confirmText);};
    const fields=Object.assign({_csrf:csrf,round_id:String(roundId),action:action},extra||{});
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

  addCompetitorClearControl();
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
