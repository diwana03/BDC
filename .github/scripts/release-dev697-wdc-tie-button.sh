#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json,re

p=Path('admin/dance-cup/judge-scoring.php')
s=p.read_text()
pattern=r'<button class="btn btn-warning w-100" onclick="const f=this\.form,v=\[\.\.\.f\.querySelectorAll\(.*?">Confirm Tie Decision</button>'
replacement='<button class="btn btn-warning w-100">Confirm Tie Decision</button>'
ns,n=re.subn(pattern,replacement,s,count=1)
if n!=1:
    raise SystemExit(f'Expected to replace one malformed tie button, replaced {n}')
p.write_text(ns)

p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev697'
data['build']=3403
feature='WDC Chief Judge tie button markup repair: removes fragile inline selector JavaScript that leaked into the button label; server-side unique-order validation remains authoritative.'
if feature not in data.setdefault('features',[]): data['features'].insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l admin/dance-cup/judge-scoring.php
python3 <<'PY'
from pathlib import Path
s=Path('admin/dance-cup/judge-scoring.php').read_text()
chunk=s[s.index("$chiefTieHtml=''"):s.index("$q=$pdo->prepare(\"SELECT entry_id")]
assert '<button class="btn btn-warning w-100">Confirm Tie Decision</button>' in chunk
assert 'querySelectorAll' not in chunk
print('dev697 WDC tie button assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add admin/dance-cup/judge-scoring.php VERSION.json
git commit -m 'Release dev697 repair Chief Judge tie button markup'
git push origin HEAD:develop
