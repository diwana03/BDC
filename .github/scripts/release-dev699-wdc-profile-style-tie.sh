#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

p=Path('admin/dance-cup/competitor-edit.php')
s=p.read_text()

# 1) Dedicated Super Admin registration dance-style correction action.
post_anchor="""if($_SERVER['REQUEST_METHOD']==='POST'){try{\n if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');$name=trim((string)($_POST['display_name']??''));$type=(string)($_POST['entry_type']??'solo');"""
post_replace="""if($_SERVER['REQUEST_METHOD']==='POST'){try{\n if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');\n $action=(string)($_POST['action']??'save_profile');\n if($action==='update_registration_style'){\n  if($id<1)throw new RuntimeException('Save the WDC competitor before correcting a registration.');\n  if(!Auth::isSuperAdmin())throw new RuntimeException('Only a Super Admin can change a WDC registration dance style.');\n  $registrationId=(int)($_POST['registration_id']??0);$danceStyle=strtolower(trim((string)($_POST['dance_style']??'')));\n  if(!in_array($danceStyle,['salsa','bachata','cha_cha','other'],true))throw new RuntimeException('Invalid Dance Cup dance style.');\n  $registrationQuery=$pdo->prepare('SELECT id,dance_style,event_name,category_name FROM bdc_wdc_registrations WHERE id=:registration AND wdc_identity_id=:identity LIMIT 1');\n  $registrationQuery->execute(['registration'=>$registrationId,'identity'=>$id]);$registrationRow=$registrationQuery->fetch();\n  if(!$registrationRow)throw new RuntimeException('This WDC registration was not found for the competitor.');\n  $beforeStyle=(string)$registrationRow['dance_style'];\n  if($beforeStyle!==$danceStyle){\n   $pdo->prepare('UPDATE bdc_wdc_registrations SET dance_style=:style WHERE id=:registration AND wdc_identity_id=:identity')->execute(['style'=>$danceStyle,'registration'=>$registrationId,'identity'=>$id]);\n   Auth::audit((int)(Auth::user()['id']??0),'wdc_registration_dance_style_updated',['wdc_identity_id'=>$id,'registration_id'=>$registrationId,'event_name'=>$registrationRow['event_name'],'category_name'=>$registrationRow['category_name'],'before'=>$beforeStyle,'after'=>$danceStyle],'wdc_registration',$registrationId);\n  }\n  header('Location: competitor-edit.php?id='.$id.'&style_saved=1#wdc-registration-'.$registrationId,true,303);exit;\n }\n $name=trim((string)($_POST['display_name']??''));$type=(string)($_POST['entry_type']??'solo');"""
if "wdc_registration_dance_style_updated" not in s:
    if post_anchor not in s: raise SystemExit('POST anchor missing')
    s=s.replace(post_anchor,post_replace,1)

# 2) Include entry/competition identifiers in official history and annotate resolved WDC ties.
history_old="""$registrations=[];$history=[];$points=[];$sharedPhoto='';if($id){$stmt=$pdo->prepare('SELECT * FROM bdc_wdc_registrations WHERE wdc_identity_id=:id ORDER BY event_name,category_name');$stmt->execute(['id'=>$id]);$registrations=$stmt->fetchAll();$stmt=$pdo->prepare(\"SELECT e.name event_name,dc.category_name,h.placement,h.approved_at FROM bdc_dance_cup_result_history h JOIN bdc_dance_cup_competitions dc ON dc.id=h.competition_id JOIN bdc_events e ON e.id=h.event_id WHERE h.wdc_identity_id=:id ORDER BY h.approved_at DESC\");$stmt->execute(['id'=>$id]);$history=$stmt->fetchAll();$stmt=$pdo->prepare('SELECT division,placement,points,awarded_at FROM bdc_wdc_championship_points WHERE wdc_identity_id=:id ORDER BY awarded_at DESC');"""
history_new="""$registrations=[];$history=[];$points=[];$sharedPhoto='';if($id){$stmt=$pdo->prepare('SELECT * FROM bdc_wdc_registrations WHERE wdc_identity_id=:id ORDER BY event_name,category_name');$stmt->execute(['id'=>$id]);$registrations=$stmt->fetchAll();$stmt=$pdo->prepare(\"SELECT e.name event_name,dc.category_name,h.competition_id,h.entry_id,h.placement,h.total_score,h.approved_at FROM bdc_dance_cup_result_history h JOIN bdc_dance_cup_competitions dc ON dc.id=h.competition_id JOIN bdc_events e ON e.id=h.event_id WHERE h.wdc_identity_id=:id ORDER BY h.approved_at DESC\");$stmt->execute(['id'=>$id]);$history=$stmt->fetchAll();\n foreach($history as &$historyItem){\n  $tieQuery=$pdo->prepare(\"SELECT t.tied_score,t.resolved_order_json,t.resolved_at,j.judge_name chief_name FROM bdc_dance_cup_tie_tasks t LEFT JOIN bdc_dance_cup_judges j ON j.id=t.chief_judge_assignment_id AND j.competition_id=t.competition_id WHERE t.competition_id=:competition AND t.status='resolved' ORDER BY t.resolved_at DESC\");\n  $tieQuery->execute(['competition'=>(int)$historyItem['competition_id']]);\n  foreach($tieQuery->fetchAll() as $tieRow){$resolvedOrder=json_decode((string)$tieRow['resolved_order_json'],true);if(!is_array($resolvedOrder))continue;$resolvedOrder=array_map('intval',$resolvedOrder);$tieIndex=array_search((int)$historyItem['entry_id'],$resolvedOrder,true);if($tieIndex===false)continue;$historyItem['tie_decision']=$tieIndex===0?'winner':'resolved';$historyItem['tie_score']=$tieRow['tied_score'];$historyItem['tie_chief']=$tieRow['chief_name'];$historyItem['tie_resolved_at']=$tieRow['resolved_at'];break;}\n }\n unset($historyItem);$stmt=$pdo->prepare('SELECT division,placement,points,awarded_at FROM bdc_wdc_championship_points WHERE wdc_identity_id=:id ORDER BY awarded_at DESC');"""
if "tie_decision" not in s:
    if history_old not in s: raise SystemExit('History anchor missing')
    s=s.replace(history_old,history_new,1)

# 3) Success notice for style correction.
notice_old="""<?php if(isset($_GET['saved'])):?><div class=\"alert alert-success\">WDC competitor profile saved.</div><?php endif;?><?php if($error):?>"""
notice_new="""<?php if(isset($_GET['saved'])):?><div class=\"alert alert-success\">WDC competitor profile saved.</div><?php endif;?><?php if(isset($_GET['style_saved'])):?><div class=\"alert alert-success\">WDC registration dance style updated.</div><?php endif;?><?php if($error):?>"""
if "registration dance style updated" not in s:
    if notice_old not in s: raise SystemExit('Notice anchor missing')
    s=s.replace(notice_old,notice_new,1)

# 4) Registration card gets style editor. Keep identity-level profile untouched because one WDC identity may have registrations in multiple dance styles.
reg_old="""<?php foreach($registrations as $registration):?><div class=\"entry border rounded p-3 mt-3\"><strong><?=e($registration['category_name'])?></strong><div class=\"text-muted small\"><?=e($registration['event_name'])?> · <?=e(ucwords($registration['dance_style']))?> · <?=e(ucwords(str_replace('_',' ',$registration['competition_level'])))?></div><span class=\"badge text-bg-<?=$registration['status']==='registered'?'success':'secondary'?> mt-2\"><?=e($registration['status'])?></span></div><?php endforeach;?>"""
reg_new="""<?php foreach($registrations as $registration):?><div class=\"entry border rounded p-3 mt-3\" id=\"wdc-registration-<?=(int)$registration['id']?>\"><strong><?=e($registration['category_name'])?></strong><div class=\"text-muted small\"><?=e($registration['event_name'])?> · <?=e(ucwords(str_replace('_',' ',(string)$registration['competition_level'])))?></div><div class=\"d-flex gap-2 align-items-center flex-wrap mt-2\"><span class=\"badge text-bg-primary\"><?=e(ucwords(str_replace('_',' ',(string)$registration['dance_style'])))?></span><span class=\"badge text-bg-<?=$registration['status']==='registered'?'success':'secondary'?>\"><?=e($registration['status'])?></span></div><?php if(Auth::isSuperAdmin()):?><form method=\"post\" class=\"row g-2 align-items-end mt-2\"><input type=\"hidden\" name=\"_csrf\" value=\"<?=e(Csrf::token())?>\"><input type=\"hidden\" name=\"id\" value=\"<?=$id?>\"><input type=\"hidden\" name=\"action\" value=\"update_registration_style\"><input type=\"hidden\" name=\"registration_id\" value=\"<?=(int)$registration['id']?>\"><div class=\"col-sm-8\"><label class=\"form-label small fw-bold mb-1\">Dance style</label><select class=\"form-select form-select-sm\" name=\"dance_style\"><?php foreach(['salsa'=>'Salsa','bachata'=>'Bachata','cha_cha'=>'Cha Cha','other'=>'Other'] as $styleValue=>$styleLabel):?><option value=\"<?=$styleValue?>\" <?=((string)$registration['dance_style']===$styleValue)?'selected':''?>><?=e($styleLabel)?></option><?php endforeach;?></select></div><div class=\"col-sm-4\"><button class=\"btn btn-sm btn-outline-warning w-100\">Save style</button></div><div class=\"col-12\"><div class=\"form-text\">Super Admin correction · audited per registration. Permanent WDC ID is unchanged.</div></div></form><?php endif;?></div><?php endforeach;?>"""
if "Save style</button>" not in s:
    if reg_old not in s: raise SystemExit('Registration-card anchor missing')
    s=s.replace(reg_old,reg_new,1)

# 5) Official profile history shows the Chief Judge tie decision.
hist_card_old="""<?php foreach($history as $item):?><div class=\"history border rounded p-3 mt-3\"><strong><?=e($item['event_name'])?></strong><div class=\"small text-muted\"><?=e($item['category_name'])?></div><span class=\"badge text-bg-dark mt-2\">Place <?=e((string)$item['placement'])?></span></div><?php endforeach;?>"""
hist_card_new="""<?php foreach($history as $item):?><div class=\"history border rounded p-3 mt-3\"><strong><?=e($item['event_name'])?></strong><div class=\"small text-muted\"><?=e($item['category_name'])?></div><span class=\"badge text-bg-dark mt-2\">Place <?=e((string)$item['placement'])?></span><?php if(!empty($item['tie_decision'])):?><span class=\"badge text-bg-warning mt-2 ms-1\"><?=$item['tie_decision']==='winner'?'★ Chief Judge Tie Winner':'Tie resolved by Chief Judge'?></span><div class=\"small text-muted mt-2\">Tie score <?=e(rtrim(rtrim(number_format((float)$item['tie_score'],2,'.',''),'0'),'.'))?><?php if(!empty($item['tie_chief'])):?> · Chief Judge <?=e((string)$item['tie_chief'])?><?php endif;?><?php if(!empty($item['tie_resolved_at'])):?> · <?=e(date('d M Y H:i',strtotime((string)$item['tie_resolved_at'])))?><?php endif;?></div><?php endif;?></div><?php endforeach;?>"""
if "Chief Judge Tie Winner" not in s:
    if hist_card_old not in s: raise SystemExit('History-card anchor missing')
    s=s.replace(hist_card_old,hist_card_new,1)

p.write_text(s)

# Release metadata.
p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev699'
data['build']=3405
features=[
 'WDC competitor profile registration-style correction: Super Admin can correct Salsa/Bachata/Cha Cha/Other per championship registration with audit logging while the permanent WDC ID stays unchanged.',
 'WDC competitor profile tie history: published result cards identify the Chief Judge tie winner or other resolved tied placement and show tied score, Chief Judge name and resolution time; public projection is unchanged.'
]
for feature in reversed(features):
    if feature not in data.setdefault('features',[]): data['features'].insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l admin/dance-cup/competitor-edit.php
python3 <<'PY'
from pathlib import Path
s=Path('admin/dance-cup/competitor-edit.php').read_text()
assert 'update_registration_style' in s
assert 'wdc_registration_dance_style_updated' in s
assert 'Save style</button>' in s
assert 'Chief Judge Tie Winner' in s
assert 'resolved_order_json' in s
print('dev699 WDC profile style/tie assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add admin/dance-cup/competitor-edit.php VERSION.json
git commit -m 'Release dev699 WDC profile style correction and tie history'
git push origin HEAD:develop
