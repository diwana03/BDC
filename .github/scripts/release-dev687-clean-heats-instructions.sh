#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('judge-scoring/index.php')
s=p.read_text()
old="""$roleTierConfig=RoleYesConfigurationService::resolve($pdo,$roundId,$yesLimit);
$roleYesLimits=['leader'=>$roleTierConfig['leader']['yes'],'follower'=>$roleTierConfig['follower']['yes']];
$roleTiers=['leader'=>$roleTierConfig['leader']['tier'],'follower'=>$roleTierConfig['follower']['tier']];"""
new="""$roleTierConfig=RoleYesConfigurationService::resolve($pdo,$roundId,$yesLimit);
foreach(['leader','follower'] as $resolvedRole){
    if(!($roleTierConfig[$resolvedRole]['saved']??false)){
        $recommended=RoleYesConfigurationService::recommended($allRoleCounts[$resolvedRole],$yesLimit);
        $roleTierConfig[$resolvedRole]['tier']=$recommended['tier'];
        $roleTierConfig[$resolvedRole]['yes']=$recommended['yes'];
    }
}
$roleYesLimits=['leader'=>$roleTierConfig['leader']['yes'],'follower'=>$roleTierConfig['follower']['yes']];
$roleTiers=['leader'=>$roleTierConfig['leader']['tier'],'follower'=>$roleTierConfig['follower']['tier']];"""
if old not in s: raise SystemExit('role config anchor missing')
s=s.replace(old,new,1)
old="""        $roleCfg=RoleYesConfigurationService::resolve($pdo,$roundId,max(1,(int)($weights['yes_count']??10)));
        $limit=(int)$roleCfg[$role]['yes'];"""
new="""        $legacyYes=max(1,(int)($weights['yes_count']??10));
        $roleCfg=RoleYesConfigurationService::resolve($pdo,$roundId,$legacyYes);
        if(!($roleCfg[$role]['saved']??false)){
            $recommended=RoleYesConfigurationService::recommended((int)$totalStmt->fetchColumn(),$legacyYes);
            $limit=(int)$recommended['yes'];
        }else $limit=(int)$roleCfg[$role]['yes'];"""
if old not in s: raise SystemExit('save limit anchor missing')
s=s.replace(old,new,1)
# The previous code consumed fetchColumn above; keep a stable total variable before recommendation.
s=s.replace("$totalStmt->execute(['round'=>$roundId,'role'=>$role]);\n        $legacyYes=", "$totalStmt->execute(['round'=>$roundId,'role'=>$role]);\n        $roleTotal=(int)$totalStmt->fetchColumn();\n        $legacyYes=",1)
s=s.replace("RoleYesConfigurationService::recommended((int)$totalStmt->fetchColumn(),$legacyYes)", "RoleYesConfigurationService::recommended($roleTotal,$legacyYes)",1)
old="""        $roleCfg=RoleYesConfigurationService::resolve($pdo,$roundId,$yesLimit);
        $requiredYes=(int)$roleCfg[$role]['yes'];"""
new="""        $roleCfg=RoleYesConfigurationService::resolve($pdo,$roundId,$yesLimit);
        $requiredYes=(int)$roleCfg[$role]['yes'];
        if(!($roleCfg[$role]['saved']??false))$requiredYes=(int)RoleYesConfigurationService::recommended($total,$yesLimit)['yes'];"""
if old not in s: raise SystemExit('submit validation anchor missing')
s=s.replace(old,new,1)
old="""<div class=\"alert success\"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style=\"margin:7px 0 0;padding-left:20px\"><?php $sheetScope=(string)$session['scoring_scope'];foreach(['leader'=>'Leaders','follower'=>'Followers'] as $sheetRole=>$sheetLabel):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];?><li><strong><?=e($sheetLabel)?> · Tier <?=(int)$cfg['tier']?> · <?=$allRoleCounts[$sheetRole]?> competitors:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>"""
new="""<div class=\"alert success\"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style=\"margin:7px 0 0;padding-left:20px\"><?php $sheetScope=(string)$session['scoring_scope'];$sheetSeen=[];foreach(['leader','follower'] as $sheetRole):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];$key=$cfg['yes'].'|'.$places['A1'].'|'.$places['A2'].'|'.$places['A3'];if(isset($sheetSeen[$key]))continue;$sheetSeen[$key]=true;?><li>Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>"""
if old not in s: raise SystemExit('green instruction anchor missing')
s=s.replace(old,new,1)
s=s.replace("const limit=Number(roleYesLimits[role]||0),tier=Number(roleTiers[role]||0);el.innerHTML='<span class=\"counter-item counter-yes\">'+(tier?'TIER '+tier+' · ':'')+'YES '+state.yes+' / '+limit+'", "const limit=Number(roleYesLimits[role]||0);el.innerHTML='<span class=\"counter-item counter-yes\">YES '+state.yes+' / '+limit+'",1)
s=s.replace("+' (Tier '+roleTiers[role]+'). Clear or change another YES first.'", "+'. Clear or change another YES first.'",1)
p.write_text(s)
PY
php -l judge-scoring/index.php
python3 <<'PY'
from pathlib import Path
s=Path('judge-scoring/index.php').read_text()
assert '<?=e($sheetLabel)?> · Tier' not in s
assert '$sheetSeen=[]' in s
assert "RoleYesConfigurationService::recommended($allRoleCounts[$resolvedRole],$yesLimit)" in s
assert "RoleYesConfigurationService::recommended($total,$yesLimit)['yes']" in s
assert "(tier?'TIER '" not in s
print('dev687 judge instruction/tier assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add judge-scoring/index.php
git commit -m 'Release dev687 clean heats instructions with role tiers'
git push origin HEAD:develop
