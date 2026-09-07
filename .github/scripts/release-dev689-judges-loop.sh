#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('app/Services/LiveDisplaySessionService.php')
s=p.read_text()
old='''            "a" => !empty($v["auto_page"]) ? 1 : 0,'''
new='''            "a" => ($type === "judges" ? 1 : (!empty($v["auto_page"]) ? 1 : 0)),'''
if old not in s: raise SystemExit('auto_page assignment anchor missing')
s=s.replace(old,new,1)
p.write_text(s)

vp=Path('VERSION.json');data=json.loads(vp.read_text());data['version']='2.3.6-dev689';data['build']=3396;data['release_date']='2026-09-07';data.setdefault('features',[]).insert(0,'Judges projection always starts on Page 1 and forces Auto Page on for multi-page judge boards, so paged judge screens continuously rotate instead of inheriting a stale disabled auto-page state.');vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/LiveDisplaySessionService.php
php -l live-display/state.php
php -l live-display/advance.php
python3 <<'PY'
from pathlib import Path
s=Path('app/Services/LiveDisplaySessionService.php').read_text()
assert '($type === "judges" ? 1 : (!empty($v["auto_page"]) ? 1 : 0))' in s
state=Path('live-display/state.php').read_text();adv=Path('live-display/advance.php').read_text()
assert '$s["screen_type"] === "judges"' in state
assert "['competitors', 'callbacks', 'finalists', 'heats_scores', 'score_matrix', 'judges', 'judge_call']" in adv
print('dev689 judges loop checks passed')
PY
git config user.name 'BDC Release Bot';git config user.email 'actions@users.noreply.github.com';git add app/Services/LiveDisplaySessionService.php VERSION.json;git commit -m 'Release 2.3.6-dev689 force judges auto paging';git push origin HEAD:develop
