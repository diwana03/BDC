#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

p=Path('admin/dance-cup/automatic-setup.php')
s=p.read_text()
script='<script src="../../public/js/dance-cup-ties.js?v=694"></script>'
if script not in s:
    if '</body>' not in s:
        raise SystemExit('automatic-setup body anchor missing')
    s=s.replace('</body>',script+'</body>',1)
p.write_text(s)

v=Path('VERSION.json')
data=json.loads(v.read_text())
data['version']='2.3.6-dev694'
data['build']=3400
feature='WDC Chief Judge tie workflow is now loaded on the Automatic Scoring setup page used by Asia Dance Cup, so exact-score ties show the Send Tie Decision to Chief Judge action on the live automatic form without changing scoring totals.'
data.setdefault('features',[]).insert(0,feature)
v.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l admin/dance-cup/automatic-setup.php
python3 <<'PY'
from pathlib import Path
s=Path('admin/dance-cup/automatic-setup.php').read_text()
assert 'dance-cup-ties.js?v=694' in s
print('dev694 automatic tie UI assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add admin/dance-cup/automatic-setup.php VERSION.json
git commit -m 'Release dev694 show WDC tie workflow on automatic setup'
git push origin HEAD:develop
