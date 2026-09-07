#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('app/Services/LiveDisplaySessionService.php')
s=p.read_text()
old='''            "a" => ($type === "judges" ? 1 : (!empty($v["auto_page"]) ? 1 : 0)),'''
new='''            "a" => !empty($v["auto_page"]) ? 1 : 0,'''
if old not in s: raise SystemExit('forced judges auto_page anchor not found')
s=s.replace(old,new,1)
p.write_text(s)

vp=Path('VERSION.json')
data=json.loads(vp.read_text())
data['version']='2.3.6-dev690'
data['build']=3397
data['release_date']='2026-09-07'
data.setdefault('features',[]).insert(0,'Judges projector now uses the exact same existing Auto Page rotation path as Competitors: manual screen or round selection starts on Page 1, total judge pages are calculated at 8 per page, and the normal Auto Page checkbox plus delay drives Page 1 → Page 2 → Page 1 without any judge-only override.')
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/LiveDisplaySessionService.php
php -l live-display/state.php
php -l live-display/advance.php
python3 <<'PY'
from pathlib import Path
service=Path('app/Services/LiveDisplaySessionService.php').read_text()
state=Path('live-display/state.php').read_text()
advance=Path('live-display/advance.php').read_text()
index=Path('live-display/index.php').read_text()
assert '"a" => !empty($v["auto_page"]) ? 1 : 0,' in service
assert '$screenChanged || $roundChanged' in service
assert '$total = max(1, (int) ceil($judgeCount / 8));' in state
assert "'judges', 'judge_call'" in advance
assert "'competitors','callbacks','finalists','heats_scores','score_matrix','judges','judge_call'" in index
print('dev690 judges now uses competitor paging path assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Services/LiveDisplaySessionService.php VERSION.json
git commit -m 'Release 2.3.6-dev690 judges use competitor paging path'
git push origin HEAD:develop
