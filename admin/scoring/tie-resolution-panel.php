<?php
declare(strict_types=1);
$tiePanelTest=(bool)($tiePanelTest??false);
$csrf=(string)($csrf??\App\Core\Csrf::token());
$tiePanelAction=(string)($tiePanelAction??'');
$tiePanelAttributes=(string)($tiePanelAttributes??'');
$sharedTieGroups=\App\Services\CallbackTieResolutionService::groups($pdo,$roundId,$tiePanelTest);
$chiefTieContact=null;
if($sharedTieGroups){
 try{
  $judgeTable=$tiePanelTest?'bdc_test_scoring_judges':'bdc_scoring_judges';
  $chiefContactQuery=$pdo->prepare("SELECT sj.judge_name,j.email,j.whatsapp,j.phone FROM {$judgeTable} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:round AND sj.is_chief=1 ORDER BY sj.id LIMIT 1");
  $chiefContactQuery->execute(['round'=>$roundId]);
  $chiefTieContact=$chiefContactQuery->fetch()?:null;
 }catch(\Throwable){}
}
?>
<?php if($sharedTieGroups):?>
<div class="alert alert-danger border-3" role="alert" data-chief-tie-alert><strong>Chief Judge action required.</strong> Scoring progression is locked until every callback or alternate tie below is resolved. Alert the Chief Judge now and record the explicit decision here.</div>
<?php $chiefMessage='Chief Judge decision required: an unresolved callback tie is blocking BDC scoring round '.$roundId.'. Please come to the scoring desk to review and confirm the decision.';$chiefWhatsapp=preg_replace('/\D+/','',(string)($chiefTieContact['whatsapp']??$chiefTieContact['phone']??''));?>
<div class="d-flex flex-wrap gap-2 mb-3" data-chief-tie-notification>
 <button type="button" class="btn btn-outline-danger" data-copy-chief-tie data-message="<?=e($chiefMessage)?>">Copy Chief Decision Request</button>
 <?php if(!empty($chiefTieContact['email'])):?><a class="btn btn-outline-primary" href="mailto:<?=e((string)$chiefTieContact['email'])?>?subject=<?=rawurlencode('Chief Judge tie decision required')?>&amp;body=<?=rawurlencode($chiefMessage)?>">Email <?=e((string)$chiefTieContact['judge_name'])?></a><?php endif;?>
 <?php if($chiefWhatsapp!==''):?><a class="btn btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/<?=e($chiefWhatsapp)?>?text=<?=rawurlencode($chiefMessage)?>">WhatsApp <?=e((string)($chiefTieContact['judge_name']??'Chief Judge'))?></a><?php endif;?>
</div>
<div class="card shadow-sm mt-3 mb-4 border-warning callback-tie-panel">
 <div class="card-header bg-warning-subtle fw-semibold">Chief Judge Tie Resolution</div>
 <div class="card-body">
  <?php foreach($sharedTieGroups as $tieGroup):$required=(int)$tieGroup['required_callbacks'];$alts=$tieGroup['available_alternate_ranks'];?>
  <form method="post" <?=$tiePanelAction!==''?'action="'.e($tiePanelAction).'"':''?> <?=$tiePanelAttributes?> class="border rounded p-3 mb-3 callback-tie-form" data-required="<?=$required?>" data-alt-required="<?=count($alts)?>">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
   <input type="hidden" name="action" value="resolve_callback_tie">
   <input type="hidden" name="round_id" value="<?=$roundId?>">
   <input type="hidden" name="tie_anchor_entry_id" value="<?=(int)$tieGroup['competitors'][0]['entry_id']?>">
   <div class="fw-semibold"><?=e(ucfirst($tieGroup['role']))?> callback boundary tie</div>
   <div class="alert alert-warning py-2 my-2">
    <?=(int)$tieGroup['confirmed_callbacks']?> of <?=(int)$tieGroup['quota']?> callbacks confirmed.
    Select exactly <strong><?=$required?></strong> of these <?=count($tieGroup['competitors'])?> tied competitors.
   </div>
   <div class="row g-2">
    <?php foreach($tieGroup['competitors'] as $candidate):$entryId=(int)$candidate['entry_id'];?>
    <div class="col-md-6 col-xl-4"><div class="border rounded p-2 h-100">
     <label class="d-flex gap-2 align-items-start mb-2">
      <input class="form-check-input tie-callback-choice" type="checkbox" name="selected_entry_ids[]" value="<?=$entryId?>">
      <span><strong>Bib <?=(int)$candidate['bib_number']?> · <?=e((string)$candidate['display_name'])?></strong><br><small>Total <?=number_format((float)$candidate['total_score'],1)?> (Chief included) · Chief mark <?=number_format((float)$candidate['chief_score'],1)?></small></span>
     </label>
     <?php if($alts):?><label class="small">If not Callback</label><select class="form-select form-select-sm tie-alt-order" name="alternate_order[<?=$entryId?>]"><option value="0">Eliminated</option><?php foreach($alts as $alt):?><option value="<?=(int)$alt?>">Alternate A<?=(int)$alt?></option><?php endforeach;?></select><?php endif;?>
    </div></div>
    <?php endforeach;?>
   </div>
   <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
    <span class="tie-selection-count fw-semibold">Selected 0 of <?=$required?></span>
    <button class="btn btn-warning tie-resolve-button" disabled>Resolve <?=e(ucfirst($tieGroup['role']))?> Tie</button>
   </div>
  </form>
  <?php endforeach;?>
  <div class="small text-muted">The Chief mark is already included in the total. The Chief Judge makes this explicit decision only because the totals remain tied. The server verifies the exact callback quantity and unique A1–A3 assignments before saving.</div>
 </div>
</div>
<script>
document.querySelectorAll('.callback-tie-form').forEach(function(form){
 const required=Number(form.dataset.required||0),altRequired=Number(form.dataset.altRequired||0),boxes=[...form.querySelectorAll('.tie-callback-choice')],alts=[...form.querySelectorAll('.tie-alt-order')],count=form.querySelector('.tie-selection-count'),button=form.querySelector('.tie-resolve-button');
 function refresh(){boxes.forEach((box,index)=>{if(alts[index]){alts[index].disabled=box.checked;if(box.checked)alts[index].value='0';}});const selected=boxes.filter(box=>box.checked).length,altValues=alts.filter(select=>!select.disabled&&Number(select.value)>0).map(select=>Number(select.value)),validAlts=altValues.length===altRequired&&new Set(altValues).size===altValues.length;count.textContent='Selected '+selected+' of '+required+(altRequired?' · Alternates '+altValues.length+' of '+altRequired:'');button.disabled=selected!==required||!validAlts;}
 boxes.forEach(box=>box.addEventListener('change',refresh));alts.forEach(select=>select.addEventListener('change',refresh));refresh();
 form.addEventListener('submit',function(event){if(button.disabled||!window.confirm('Confirm this Chief Judge tie decision?'))event.preventDefault();});
});
document.querySelector('[data-copy-chief-tie]')?.addEventListener('click',async function(){try{await navigator.clipboard.writeText(this.dataset.message||'');this.textContent='Decision Request Copied';}catch(error){window.prompt('Copy this Chief Judge decision request:',this.dataset.message||'');}});
</script>
<?php endif;?>
