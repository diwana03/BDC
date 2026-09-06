#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('judge-scoring/index.php')
s=p.read_text()
old='''<div class="alert success"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style="margin:7px 0 0;padding-left:20px"><?php $sheetScope=(string)$session['scoring_scope'];$sheetSeen=[];foreach(['leader','follower'] as $sheetRole):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];$key=$cfg['yes'].'|'.$places['A1'].'|'.$places['A2'].'|'.$places['A3'];if(isset($sheetSeen[$key]))continue;$sheetSeen[$key]=true;?><li>Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>'''
new='''<div class="alert success"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style="margin:7px 0 0;padding-left:20px"><?php $sheetScope=(string)$session['scoring_scope'];foreach(['leader'=>'Leaders','follower'=>'Followers'] as $sheetRole=>$sheetLabel):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];?><li><strong><?=e($sheetLabel)?>:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>'''
if old not in s: raise SystemExit('judge instruction anchor not found')
s=s.replace(old,new,1)
p.write_text(s)
PY
php -l judge-scoring/index.php
python3 <<'PY'
from pathlib import Path
s=Path('judge-scoring/index.php').read_text()
assert "['leader'=>'Leaders','follower'=>'Followers'] as $sheetRole=>$sheetLabel" in s
assert '<strong><?=e($sheetLabel)?>:</strong> Choose <strong>' in s
assert '$sheetSeen=[]' not in s
assert '· Tier <?=(int)$cfg' not in s
print('dev688 role-labelled instruction assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add judge-scoring/index.php
git commit -m 'Release dev688 role-labelled judge instructions'
git push origin HEAD:develop
