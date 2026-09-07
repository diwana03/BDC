#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

p=Path('app/Views/admin/dance-cup-automatic-page.php')
s=p.read_text()
s=s.replace('dance-cup-ties.js?v=696','dance-cup-ties.js?v=698')
p.write_text(s)

p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev698'
data['build']=3404
feature='WDC admin tie-resolution audit: resolved exact-score ties show the Chief Judge-selected winner/order, Chief Judge name and resolution timestamp on Automatic Scoring results while public projection remains unchanged.'
if feature not in data.setdefault('features',[]): data['features'].insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/DanceCupTieService.php
php -l app/Views/admin/dance-cup-automatic-page.php
node --check public/js/dance-cup-ties.js
python3 <<'PY'
from pathlib import Path
s=Path('public/js/dance-cup-ties.js').read_text()
svc=Path('app/Services/DanceCupTieService.php').read_text()
page=Path('app/Views/admin/dance-cup-automatic-page.php').read_text()
assert 'Winner:' in s
assert 'Decision:' in s
assert 'Resolved:' in s
assert 'Chief Judge Tie Decision' in s
assert 'chief_name' in svc and 'resolved_at' in svc
assert 'dance-cup-ties.js?v=698' in page
print('dev698 WDC tie audit assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Views/admin/dance-cup-automatic-page.php VERSION.json
git commit -m 'Release dev698 WDC Chief Judge tie audit display'
git push origin HEAD:develop
