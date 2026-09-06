#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('judge-scoring/index.php')
s=p.read_text()
old='''<li><strong><?=e($sheetLabel)?> · Tier <?=(int)$cfg['tier']?> · <?=$allRoleCounts[$sheetRole]?> competitors:</strong> choose <strong><?=(int)$cfg['yes']?> YES</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li>'''
new='''<li><strong><?=e($sheetLabel)?> · Tier <?=(int)$cfg['tier']?> · <?=$allRoleCounts[$sheetRole]?> competitors:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li>'''
if old not in s:
    raise SystemExit('Main judge sheet instruction anchor not found')
s=s.replace(old,new,1)
p.write_text(s)
vp=Path('VERSION.json');data=json.loads(vp.read_text());data['version']='2.3.3-dev681';data['build']=3388;data['release_date']='2026-09-07';feature='Completes tier-aware Heats wording on the active judge scoring sheet as well as the criteria screen: each assigned role shows Tier, competitor count, “Choose N YES for your Top N best dancers,” dynamic A1/A2/A3 places, NO guidance and private-comment guidance.';features=data.setdefault('features',[]);features.insert(0,feature) if not features or features[0]!=feature else None;vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l judge-scoring/index.php
python3 <<'PY'
from pathlib import Path
s=Path('judge-scoring/index.php').read_text()
phrase="for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>"
assert s.count(phrase)>=1
assert "competitors:</strong> choose <strong><?=(int)$cfg['yes']?> YES</strong>." not in s
assert 'Comments are optional and private to this judge/device.' in s
print('active judge sheet instruction assertions passed')
PY
git config user.name 'BDC Release Bot';git config user.email 'actions@users.noreply.github.com';git add judge-scoring/index.php VERSION.json;git commit -m 'Release dev681 tier-aware active judge instructions';git push origin HEAD:develop
