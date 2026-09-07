#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

# Add a Chief-Judge-scoped resolver that does not require the separate one-time tie token.
p=Path('app/Services/DanceCupTieService.php')
s=p.read_text()
anchor='''    public static function cancel(PDO $pdo,int $competitionId,string $tieKey,bool $test=false):void\n    {\n        $p=self::prefix($test);$pdo->prepare("UPDATE {$p}_tie_tasks SET status='cancelled',token_hash=NULL,cancelled_at=NOW() WHERE competition_id=:c AND tie_key=:k AND status='pending'")->execute(['c'=>$competitionId,'k'=>$tieKey]);\n    }\n'''
insert='''    public static function resolveAsChief(PDO $pdo,int $competitionId,string $tieKey,array $orderedEntryIds,int $chiefAssignmentId,bool $test=false):void\n    {\n        $p=self::prefix($test);\n        $chief=$pdo->prepare("SELECT COUNT(*) FROM {$p}_judges WHERE id=:judge AND competition_id=:competition AND is_chief=1");\n        $chief->execute(['judge'=>$chiefAssignmentId,'competition'=>$competitionId]);\n        if((int)$chief->fetchColumn()!==1)throw new RuntimeException('Chief Judge authorization required.');\n        $target=null;foreach(self::ties($pdo,$competitionId,$test) as $tie)if(hash_equals((string)$tie['tie_key'],$tieKey)){$target=$tie;break;}\n        if(!$target)throw new RuntimeException('This tie is no longer current. Refresh the scoring page.');\n        $expected=array_map(static fn($x)=>(int)$x['entry_id'],$target['entries']);$ordered=array_map('intval',$orderedEntryIds);$a=$expected;$b=$ordered;sort($a);sort($b);\n        if(!$a||$a!==$b||count($ordered)!==count(array_unique($ordered)))throw new RuntimeException('Choose a complete final order for every tied contestant.');\n        $pdo->beginTransaction();try{\n            $base=(int)$target['base_place'];$up=$pdo->prepare("UPDATE {$p}_scoring_results SET placement=:place WHERE competition_id=:competition AND entry_id=:entry");\n            foreach($ordered as $i=>$entryId)$up->execute(['place'=>$base+$i,'competition'=>$competitionId,'entry'=>$entryId]);\n            $ids=$expected;sort($ids,SORT_NUMERIC);$existing=$pdo->prepare("SELECT id FROM {$p}_tie_tasks WHERE competition_id=:competition AND tie_key=:tie LIMIT 1");$existing->execute(['competition'=>$competitionId,'tie'=>$tieKey]);$taskId=(int)$existing->fetchColumn();\n            if($taskId>0){$pdo->prepare("UPDATE {$p}_tie_tasks SET status='resolved',resolved_order_json=:ordered,resolved_at=NOW(),token_hash=NULL,chief_judge_assignment_id=:chief WHERE id=:id")->execute(['ordered'=>json_encode($ordered),'chief'=>$chiefAssignmentId,'id'=>$taskId]);}\n            else{$pdo->prepare("INSERT INTO {$p}_tie_tasks(competition_id,tie_key,tied_score,entry_ids_json,status,resolved_order_json,chief_judge_assignment_id,resolved_at) VALUES(:competition,:tie,:score,:ids,'resolved',:ordered,:chief,NOW())")->execute(['competition'=>$competitionId,'tie'=>$tieKey,'score'=>$target['score'],'ids'=>json_encode($ids),'ordered'=>json_encode($ordered),'chief'=>$chiefAssignmentId]);}\n            $pdo->commit();\n        }catch(\\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}\n    }\n\n'''+anchor
if 'public static function resolveAsChief' not in s:
    if anchor not in s: raise SystemExit('DanceCupTieService cancel anchor missing')
    s=s.replace(anchor,insert,1)
p.write_text(s)

# Wire the current Chief Judge scoring link to show and resolve pending WDC ties.
p=Path('admin/dance-cup/judge-scoring.php')
s=p.read_text()
s=s.replace('use App\\Services\\DanceCupCategoryEditService;use App\\Services\\DanceCupJudgingPanelService;use App\\Services\\DanceCupScoringService;', 'use App\\Services\\DanceCupCategoryEditService;use App\\Services\\DanceCupJudgingPanelService;use App\\Services\\DanceCupScoringService;use App\\Services\\DanceCupTieService;',1)

criteria_anchor="$gateCriteriaQ=$pdo->prepare(\"SELECT criterion_name,maximum_points,sort_order FROM {$t['criteria']} WHERE competition_id=:competition ORDER BY sort_order,id\");"
pre='''$chiefTieNotice='';\nif($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')==='resolve_wdc_tie'){\n if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Security check failed. Reload and try again.');}\n if((int)($session['is_chief']??0)!==1){http_response_code(403);exit('Chief Judge authorization required.');}\n try{DanceCupTieService::resolveAsChief($pdo,(int)$session['competition_id'],(string)($_POST['tie_key']??''),(array)($_POST['tie_order']??[]),(int)$session['judge_assignment_id'],$test);$chiefTieNotice='Tie decision confirmed. Final placements have been updated.';}catch(Throwable $tieError){$error=$tieError->getMessage();}\n}\n'''
if "resolve_wdc_tie" not in s:
    if criteria_anchor not in s: raise SystemExit('judge criteria anchor missing')
    s=s.replace(criteria_anchor,pre+criteria_anchor,1)

# Exclude tie-resolution POST from the criteria gate and generic scoring POST handler.
s=s.replace("if($_SERVER['REQUEST_METHOD']==='POST'&&!$criteriaAccepted){", "if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')!=='resolve_wdc_tie'&&!$criteriaAccepted){",1)
s=s.replace("try{if($_SERVER['REQUEST_METHOD']==='POST'){if(!Csrf::verify", "try{if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')!=='resolve_wdc_tie'){if(!Csrf::verify",1)
s=s.replace("if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['ajax']??'')==='1'){", "if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')!=='resolve_wdc_tie'&&($_POST['ajax']??'')==='1'){",1)

# Build Chief Judge tie card after normal data has loaded.
entries_anchor="$q=$pdo->prepare(\"SELECT * FROM {$p}_entries WHERE competition_id=:id AND status='active' ORDER BY bib_number,id\");$q->execute(['id'=>$session['competition_id']]);$entries=$q->fetchAll();"
tie_build='''$chiefTieHtml='';\nif((int)($session['is_chief']??0)===1){\n try{\n  $pendingTies=DanceCupTieService::ties($pdo,(int)$session['competition_id'],$test);\n  $cards='';foreach($pendingTies as $tie){if((string)($tie['task']['status']??'')==='resolved')continue;$options='';foreach($tie['entries'] as $entry)$options.='<option value="'.(int)$entry['entry_id'].'">#'.(int)$entry['bib_number'].' '.e((string)$entry['display_name']).'</option>';$rows='';for($i=0;$i<count($tie['entries']);$i++)$rows.='<div class="mb-2"><label class="form-label small fw-bold mb-1">Place '.((int)$tie['base_place']+$i).'</label><select class="form-select" name="tie_order[]" required><option value="">Choose contestant</option>'.$options.'</select></div>';$cards.='<form method="post" class="border rounded p-3 mt-3" onsubmit="const v=[...this.querySelectorAll(\\'select[name=\\\"tie_order[]\\\"]\\')].map(x=>x.value);if(v.some(x=>!x)||new Set(v).size!==v.length){alert(\\'Choose each tied contestant exactly once.\\');return false;}return confirm(\\'Confirm this final tie order? Scores will stay unchanged.\\');"><input type="hidden" name="_csrf" value="'.e($csrf).'"><input type="hidden" name="token" value="'.e($token).'"><input type="hidden" name="category_id" value="'.(int)$session['competition_id'].'"><input type="hidden" name="data_mode" value="'.($test?'test':'real').'"><input type="hidden" name="action" value="resolve_wdc_tie"><input type="hidden" name="tie_key" value="'.e((string)$tie['tie_key']).'"><div class="fw-bold mb-1">'.(int)$tie['base_place'].'. place tie · Score '.e((string)$tie['score']).'</div><div class="small text-muted mb-2">Order the tied contestants below. Original scores will not change.</div>'.$rows.'<button class="btn btn-warning w-100">Confirm Tie Decision</button></form>';}\n  if($cards!=='')$chiefTieHtml='<section class="card border-warning shadow-sm mb-4"><div class="card-body p-3 p-md-4"><span class="badge text-bg-warning">CHIEF JUDGE ACTION</span><h2 class="h4 mt-2 mb-1">Tie Decision Required</h2><p class="text-muted mb-0">A final-score tie is blocking publication. Resolve the tied placement here from your normal Chief Judge scoring link.</p>'.$cards.'</div></section>';\n }catch(Throwable $tieLoadError){$chiefTieHtml='<div class="alert alert-warning">Tie workflow unavailable: '.e($tieLoadError->getMessage()).'</div>';}\n}\n'''
if '$chiefTieHtml=' not in s:
    if entries_anchor not in s: raise SystemExit('entries anchor missing')
    s=s.replace(entries_anchor,entries_anchor+'\n'+tie_build,1)

# Inject notice + tie card at the top of the judge main content.
main_anchor='<main class="container py-4" style="max-width:1100px">'
inject="<?php if($chiefTieNotice!==''):?><div class=\"alert alert-success\"><?=e($chiefTieNotice)?></div><?php endif;?><?=$chiefTieHtml?>"
if '$chiefTieHtml?>' not in s:
    s=s.replace(main_anchor,main_anchor+inject,1)
p.write_text(s)

# Release metadata
p=Path('VERSION.json');data=json.loads(p.read_text());data['version']='2.3.6-dev696';data['build']=3402
feature='WDC Chief Judge inline tie decision: unresolved exact-score ties appear inside the existing Chief Judge mobile scoring link, can be ordered and confirmed there, keep original scores unchanged, and remain hidden from normal judges.'
if feature not in data.setdefault('features',[]):data['features'].insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/DanceCupTieService.php
php -l admin/dance-cup/judge-scoring.php
python3 <<'PY'
from pathlib import Path
s=Path('admin/dance-cup/judge-scoring.php').read_text();t=Path('app/Services/DanceCupTieService.php').read_text()
assert 'resolve_wdc_tie' in s
assert 'Tie Decision Required' in s
assert "($session['is_chief']??0)===1" in s
assert 'resolveAsChief' in t
print('dev696 WDC Chief Judge inline tie assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Services/DanceCupTieService.php admin/dance-cup/judge-scoring.php VERSION.json
git commit -m 'Release dev696 WDC Chief Judge inline tie decision'
git push origin HEAD:develop
