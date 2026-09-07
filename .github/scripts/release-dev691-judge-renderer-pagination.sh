#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('live-display/feed.php')
s=p.read_text()
old='''    if ($judgeCall) {
        $judgeTotal = count($items);
        $judgePage = max(1, min($page, max(1, $judgeTotal)));
        $items = $judgeTotal > 0 ? [$items[$judgePage - 1]] : [];
        $title = "CALLING JUDGE {$judgePage} OF " . max(1, $judgeTotal);
    }
'''
new='''    if ($judgeCall) {
        $judgeTotal = count($items);
        $judgePage = max(1, min($page, max(1, $judgeTotal)));
        $items = $judgeTotal > 0 ? [$items[$judgePage - 1]] : [];
        $title = "CALLING JUDGE {$judgePage} OF " . max(1, $judgeTotal);
    } else {
        $judgeTotal = count($items);
        $judgePageSize = 8;
        $judgePages = max(1, (int) ceil($judgeTotal / $judgePageSize));
        $judgePage = max(1, min($page, $judgePages));
        $items = array_slice($items, ($judgePage - 1) * $judgePageSize, $judgePageSize);
        if ($judgePages > 1) {
            $title = "JUDGES · PAGE {$judgePage} OF {$judgePages}";
        }
    }
'''
if old not in s:
    raise SystemExit('judge renderer anchor missing')
s=s.replace(old,new,1)
p.write_text(s)
PY
php -l live-display/feed.php
python3 <<'PY'
from pathlib import Path
s=Path('live-display/feed.php').read_text()
assert '$judgePageSize = 8;' in s
assert 'array_slice($items, ($judgePage - 1) * $judgePageSize, $judgePageSize)' in s
assert 'JUDGES · PAGE {$judgePage} OF {$judgePages}' in s
print('dev691 judge renderer pagination assertions passed')
PY
python3 <<'PY'
from pathlib import Path
import json
p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev691'
data['build']=3398
feature='Judges projector now uses the same proven paging path as Competitors end to end: manual selection starts on Page 1, existing Auto Page advances through state/advance.php, and feed.php renders the correct eight-judge slice for each page instead of re-rendering the full judge list.'
data.setdefault('features',[]).insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php VERSION.json
git commit -m 'Release 2.3.6-dev691 fix judge renderer pagination'
git push origin HEAD:develop
