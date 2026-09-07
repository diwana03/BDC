#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('live-display/index.php')
s=p.read_text()
old="['competitors','callbacks','finalists','heats_scores','score_matrix','judge_call'].includes(s.screen_type)"
new="['competitors','callbacks','finalists','heats_scores','score_matrix','judges','judge_call'].includes(s.screen_type)"
if old not in s:
    raise SystemExit('judge auto-page anchor not found')
s=s.replace(old,new,1)
p.write_text(s)

v=Path('VERSION.json')
data=json.loads(v.read_text())
data['version']='2.3.6-dev687'
data['build']=3394
data['release_date']='2026-09-07'
features=data.setdefault('features',[])
features.insert(0,'Repairs the BDC Portal MCP execution path by preserving OAuth Bearer Authorization through Apache/FastCGI and accepting standards-compatible refresh requests while keeping exact resource binding on authorization, and fixes paged Judges projection auto-rotation by including the Judges board in the existing projector advance timer.')
v.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/index.php
php -l mcp/index.php
php -l app/Services/McpOAuthService.php
python3 <<'PY'
from pathlib import Path
import json
s=Path('live-display/index.php').read_text()
assert "'score_matrix','judges','judge_call'" in s
m=Path('mcp/index.php').read_text()
assert 'REDIRECT_HTTP_AUTHORIZATION' in m and 'getallheaders' in m
a=Path('.htaccess').read_text()
assert 'HTTP_AUTHORIZATION:%{HTTP:Authorization}' in a
o=Path('app/Services/McpOAuthService.php').read_text()
assert "if($resource!=='')self::requireResource($resource)" in o
v=json.loads(Path('VERSION.json').read_text())
assert v['version']=='2.3.6-dev687' and v['build']==3394
print('dev687 connector + judge paging assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/index.php VERSION.json
git commit -m 'Release 2.3.6-dev687 connector execution and judge paging'
git push origin HEAD:develop
