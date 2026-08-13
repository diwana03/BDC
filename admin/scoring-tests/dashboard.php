<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringCalculationService;
use App\Services\SpecialCategoryService;
use App\Services\TestCompetitorGeneratorService;

Auth::requireAdmin();
$pdo=Database::connection();
SpecialCategoryService::ensureSchema($pdo);

$mode=(string)($_GET['test_mode']??$_POST['test_mode']??$_SESSION['bdc_test_scoring_mode']??'manual');
if(!in_array($mode,['manual','automated'],true))$mode='manual';
$_SESSION['bdc_test_scoring_mode']=$mode;

function bdcTestDashboardRedirect(string $mode,int $roundId=0,array $extra=[]):never
{
    $query=['legacy'=>1,'test_mode'=>$mode];
    if($roundId>0)$query['round_id']=$roundId;
    foreach($extra as $key=>$value)$query[$key]=$value;
    header('Location: '.url('admin/scoring-tests/dashboard.php?'.http_build_query($query)),true,303);
    exit;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $action=(string)($_POST['action']??'');

    if(in_array($action,['generate_results','generate_test_competitors','create_round','generate_test_event','settings'],true)){
        if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
    }

    if($action==='generate_results'){
        $roundId=(int)($_POST['round_id']??0);
        if($roundId<1){http_response_code(400);exit('Invalid scoring round.');}
        ScoringCalculationService::calculateHeats($pdo,$roundId,ScoringCalculationService::TEST,(int)(Auth::user()['id']??0));
        bdcTestDashboardRedirect($mode,$roundId,['shared_engine'=>1]);
    }

    if($action==='generate_test_competitors'){
        $roundId=(int)($_POST['round_id']??0);
        TestCompetitorGeneratorService::generate(
            $pdo,
            $roundId,
            (int)($_POST['leader_count']??10),
            (int)($_POST['follower_count']??10),
            (int)(Auth::user()['id']??0)
        );
        bdcTestDashboardRedirect($mode,$roundId,['competitors_generated'=>1]);
    }

    if($action==='settings'){
        $roundId=(int)($_POST['round_id']??0);
        $roundStmt=$pdo->prepare('SELECT division FROM bdc_test_scoring_rounds WHERE id=:id LIMIT 1');
        $roundStmt->execute(['id'=>$roundId]);
        $division=(string)$roundStmt->fetchColumn();
        if(SpecialCategoryService::isSpecial($division)){
            $intent=(string)($_POST['special_settings_intent']??'lock');
            $marks=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_marks WHERE round_id=:id AND (mark_type<>'blank' OR weighted_score>0)");$marks->execute(['id'=>$roundId]);
            if((int)$marks->fetchColumn()>0)throw new RuntimeException('The YES tier cannot be changed after judging has started.');
            if($intent==='unlock'){
                $pdo->prepare('UPDATE bdc_test_scoring_rounds SET tier_manual_override=0 WHERE id=:id')->execute(['id'=>$roundId]);
            }else{
                $yes=(int)($_POST['special_yes_count']??0);if(!in_array($yes,[5,10,15],true))throw new RuntimeException('Select 5, 10 or 15 YES per judge.');
                $pdo->prepare('UPDATE bdc_test_scoring_rounds SET yes_count=:yes,callback_count=:yes,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')
                    ->execute(['yes'=>$yes,'id'=>$roundId]);
            }
            bdcTestDashboardRedirect($mode,$roundId,['special_settings_saved'=>1]);
        }
    }

    if($action==='create_round'){
        $division=(string)($_POST['division']??'novice');
        if(SpecialCategoryService::isSpecial($division)){
            $eventId=(int)($_POST['event_id']??0);
            $newEventName=trim((string)($_POST['new_event_name']??''));
            $newEventDate=trim((string)($_POST['new_event_date']??''));
            $roundType=(string)($_POST['round_type']??'heats');
            if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Invalid round type.');
            if($eventId>0&&$newEventName!=='')throw new RuntimeException('Select an existing event or create a new event, not both.');
            if($eventId<1){
                if($newEventName==='')throw new RuntimeException('Select an existing event or enter a new event name.');
                if($newEventDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newEventDate))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');
                $baseSlug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$newEventName),'-'))?:'event';
                $slug=$baseSlug;$n=2;$check=$pdo->prepare('SELECT COUNT(*) FROM bdc_test_events WHERE slug=:slug');
                while(true){$check->execute(['slug'=>$slug]);if(!(int)$check->fetchColumn())break;$slug=$baseSlug.'-'.$n++;}
                $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,NULLIF(:event_date,''),'draft')")
                    ->execute(['name'=>$newEventName,'normalised'=>strtolower($newEventName),'slug'=>$slug,'event_date'=>$newEventDate]);
                $eventId=(int)$pdo->lastInsertId();
            }
            $existing=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:rt AND status<>'archived' ORDER BY id DESC LIMIT 1");
            $existing->execute(['e'=>$eventId,'d'=>$division,'rt'=>$roundType]);
            $roundId=(int)$existing->fetchColumn();
            if($roundId<1){
                $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:rt,:d,10,10,10.00,4.50,4.30,4.20,:u)")
                    ->execute(['e'=>$eventId,'rt'=>$roundType,'d'=>$division,'u'=>(int)(Auth::user()['id']??0)?:null]);
                $roundId=(int)$pdo->lastInsertId();
            }
            bdcTestDashboardRedirect($mode,$roundId,['special_category'=>1]);
        }
    }

    if($action==='generate_test_event'){
        $division=(string)($_POST['division']??'novice');
        if(SpecialCategoryService::isSpecial($division)){
            $roundType=(string)($_POST['round_type']??'heats');
            if(!in_array($roundType,['heats','final'],true))$roundType='heats';
            $eventName='TEST EVENT '.date('Y-m-d H-i-s');
            $slug='test-event-'.date('YmdHis').'-'.random_int(100,999);
            $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,CURDATE(),'draft')")
                ->execute(['name'=>$eventName,'normalised'=>strtolower($eventName),'slug'=>$slug]);
            $eventId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:rt,:d,10,10,10.00,4.50,4.30,4.20,:u)")
                ->execute(['e'=>$eventId,'rt'=>$roundType,'d'=>$division,'u'=>(int)(Auth::user()['id']??0)?:null]);
            $roundId=(int)$pdo->lastInsertId();
            bdcTestDashboardRedirect($mode,$roundId,['special_category'=>1]);
        }
    }
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

$roundId=(int)($roundId??0);
$currentDivision=(string)($round['division']??'');
$currentYes=(int)($round['yes_count']??10);
$currentYesLocked=(int)($round['tier_manual_override']??0)===1;
$currentRecommendedYes=$currentYes;if($roundId>0&&!$currentYesLocked){$roleCountStmt=$pdo->prepare("SELECT COALESCE(MAX(total),0) FROM (SELECT COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' GROUP BY dance_role) role_counts");$roleCountStmt->execute(['r'=>$roundId]);$largestRole=(int)$roleCountStmt->fetchColumn();$currentRecommendedYes=$largestRole<=15?5:($largestRole<=30?10:15);}
$currentScoringStarted=false;if($roundId>0){$startedStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_marks WHERE round_id=:r AND (mark_type<>'blank' OR weighted_score>0)");$startedStmt->execute(['r'=>$roundId]);$currentScoringStarted=(int)$startedStmt->fetchColumn()>0;}
$endpoint=url('admin/scoring-tests/automatic-inline.php?round_id='.$roundId.'&test_mode='.$mode);
$actionEndpoint=url('admin/scoring-tests/automatic-inline.php');
$csrf=Csrf::token();
$judgeList=[];
if($roundId>0){
    try{
        $stmt=$pdo->prepare('SELECT id,judge_name,is_chief FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');
        $stmt->execute(['r'=>$roundId]);
        $judgeList=$stmt->fetchAll();
    }catch(Throwable){$judgeList=[];}
}
$specialCategories=SpecialCategoryService::categories();
$specialSchedules=[];
foreach($specialCategories as $key=>$label)$specialSchedules[$key]=SpecialCategoryService::schedule($key);

$modeJson=json_encode($mode,JSON_UNESCAPED_SLASHES);
$endpointJson=json_encode($endpoint,JSON_UNESCAPED_SLASHES);
$actionJson=json_encode($actionEndpoint,JSON_UNESCAPED_SLASHES);
$csrfJson=json_encode($csrf,JSON_UNESCAPED_SLASHES);
$judgeListJson=json_encode($judgeList,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$currentDivisionJson=json_encode($currentDivision,JSON_UNESCAPED_SLASHES);
$currentYesLockedJson=json_encode($currentYesLocked);
$currentScoringStartedJson=json_encode($currentScoringStarted);
$specialCategoriesJson=json_encode($specialCategories,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$specialSchedulesJson=json_encode($specialSchedules,JSON_UNESCAPED_SLASHES);

$enhancement=<<<HTML
<script id="bdc-test-mode-enhancement">
(function(){
  const mode=$modeJson;
  const roundId=$roundId;
  const endpoint=$endpointJson;
  const actionEndpoint=$actionJson;
  const csrf=$csrfJson;
  const judgeList=$judgeListJson;
  const currentDivision=$currentDivisionJson;
  const currentYes=$currentYes;
  const currentRecommendedYes=$currentRecommendedYes;
  const currentYesLocked=$currentYesLockedJson;
  const currentScoringStarted=$currentScoringStartedJson;
  const specialCategories=$specialCategoriesJson;
  const specialSchedules=$specialSchedulesJson;

  function isSpecial(value){return Object.prototype.hasOwnProperty.call(specialCategories,String(value||''));}
  function scheduleText(value){
    const schedule=specialSchedules[value]||{};
    return Object.entries(schedule).map(([rank,points])=>rank+'='+points).join(' · ')+' points';
  }

  document.querySelectorAll('select[name="division"]').forEach(select=>{
    Object.entries(specialCategories).forEach(([value,label])=>{
      if(!select.querySelector('option[value="'+value+'"]')){
        const option=document.createElement('option');option.value=value;option.textContent=label;select.appendChild(option);
      }
    });
    const update=()=>{
      const form=select.closest('form');if(!form)return;
      const tier=form.querySelector('select[name="competition_tier"]');
      if(tier){
        const holder=tier.closest('[class*="col-"]')||tier.parentElement;
        if(holder)holder.style.display=isSpecial(select.value)?'none':'';
        tier.disabled=isSpecial(select.value);
      }
      let note=form.querySelector('[data-special-category-note]');
      if(isSpecial(select.value)){
        if(!note){note=document.createElement('div');note.dataset.specialCategoryNote='1';note.className='alert alert-info py-2 mt-2 mb-0';form.appendChild(note);}
        note.innerHTML='<strong>'+specialCategories[select.value]+' fixed points:</strong> '+scheduleText(select.value)+'. Participant-count tiers do not apply.';
      }else if(note){note.remove();}
    };
    select.addEventListener('change',update);update();
  });

  if(currentDivision&&specialCategories[currentDivision]){
    const tier=document.getElementById('competitionTier');
    if(tier){
      const settingsForm=tier.closest('form');
      const holder=tier.closest('.col-12')||tier.parentElement;
      if(holder)holder.style.display='none';
      if(settingsForm&&!settingsForm.querySelector('[data-special-settings]')){
        const oldButton=settingsForm.querySelector('button:not([type="button"])');if(oldButton)oldButton.remove();
        const block=document.createElement('div');block.className='col-12';block.dataset.specialSettings='1';
        block.innerHTML='<div class="alert alert-info mb-3"><strong>'+specialCategories[currentDivision]+' fixed points:</strong> '+scheduleText(currentDivision)+'<br><span class="small">Participant-count point tiers are disabled.</span></div><label class="form-label">YES Tier per Judge</label><select class="form-select" name="special_yes_count" '+(currentYesLocked?'disabled':'')+'><option value="5" '+(currentRecommendedYes===5?'selected':'')+'>Tier 1 · 5 YES</option><option value="10" '+(currentRecommendedYes===10?'selected':'')+'>Tier 2 · 10 YES</option><option value="15" '+(currentRecommendedYes===15?'selected':'')+'>Tier 3 · 15 YES</option></select>'+(currentYesLocked?'<input type="hidden" name="special_yes_count" value="'+currentYes+'">':'')+'<div class="form-text">Recommended from the larger Leader or Follower count. You may amend it before locking.</div><div class="border rounded p-3 bg-light mt-3"><div class="fw-semibold mb-2">Alternates · Locked</div><div class="row text-center"><div class="col-4">ALT 1<br><strong>4.5</strong></div><div class="col-4">ALT 2<br><strong>4.3</strong></div><div class="col-4">ALT 3<br><strong>4.2</strong></div></div></div><div class="mt-3">'+(currentScoringStarted?'<span class="badge text-bg-secondary">Locked because judging has started</span>':(currentYesLocked?'<button class="btn btn-outline-warning btn-sm" name="special_settings_intent" value="unlock">Unlock YES Count</button>':'<button class="btn btn-dark btn-sm" name="special_settings_intent" value="lock">Save &amp; Lock YES Count</button>'))+'</div>';
        settingsForm.querySelector('.row')?.appendChild(block);
      }
    }
    document.querySelectorAll('body *').forEach(node=>{
      if(node.children.length===0&&node.textContent===currentDivision)node.textContent=specialCategories[currentDivision];
    });
  }

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
