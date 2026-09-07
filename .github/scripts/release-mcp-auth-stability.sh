#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('app/Services/McpOAuthService.php')
s=p.read_text()
old="t.access_expires_at>=NOW() LIMIT 1"
new="COALESCE(t.refresh_expires_at,t.access_expires_at)>=NOW() LIMIT 1"
if old not in s:
    raise SystemExit('authenticate expiry anchor not found')
s=s.replace(old,new,1)
old2="DATE_ADD(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 30 DAY)"
new2="DATE_ADD(NOW(),INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 30 DAY)"
if old2 not in s:
    raise SystemExit('token lifetime anchor not found')
s=s.replace(old2,new2,1)
old3="'expires_in'=>3600"
new3="'expires_in'=>2592000"
if old3 not in s:
    raise SystemExit('expires_in anchor not found')
s=s.replace(old3,new3,1)
p.write_text(s)
PY
php -l app/Services/McpOAuthService.php
php -l mcp/index.php
python3 <<'PY'
from pathlib import Path
s=Path('app/Services/McpOAuthService.php').read_text()
m=Path('mcp/index.php').read_text()
assert 'COALESCE(t.refresh_expires_at,t.access_expires_at)>=NOW()' in s
assert "INTERVAL 30 DAY),DATE_ADD(NOW(),INTERVAL 30 DAY)" in s
assert "'expires_in'=>2592000" in s
assert 'function mcpAccepted():never' in m
assert "if($method==='notifications/initialized')mcpAccepted();" in m
assert "['2025-11-25','2025-06-18','2025-03-26','2024-11-05']" in m
print('MCP execution/auth stability assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Services/McpOAuthService.php mcp/index.php
git commit -m 'Stabilize MCP execution and 30-day OAuth access'
git push origin HEAD:develop
