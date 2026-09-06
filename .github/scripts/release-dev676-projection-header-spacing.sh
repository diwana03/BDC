#!/usr/bin/env bash
set -euo pipefail

python3 <<'PY'
from pathlib import Path

path = Path('admin/dance-cup/projector.php')
s = path.read_text()
old = '.top{display:grid;grid-template-columns:clamp(64px,7vw,118px) 1fr clamp(120px,13vw,220px);align-items:center;gap:2vw;min-height:clamp(70px,9vh,126px)}'
new = '.top{width:78vw;max-width:100%;margin-inline:auto;display:grid;grid-template-columns:clamp(64px,7vw,118px) minmax(0,1fr) clamp(120px,13vw,220px);align-items:center;gap:clamp(14px,1.15vw,24px);min-height:clamp(70px,9vh,126px)}'
if old not in s:
    raise SystemExit('Expected Dance Cup projection header rule not found')
s = s.replace(old, new, 1)
path.write_text(s)
PY

php -l admin/dance-cup/projector.php

git config user.name "BDC Release Bot"
git config user.email "actions@users.noreply.github.com"
git add admin/dance-cup/projector.php
if git diff --cached --quiet; then
  echo "No changes to commit"
  exit 0
fi
git commit -m "Fix projection header logo spacing"
git push origin HEAD:develop
