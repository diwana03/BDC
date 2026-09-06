#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('judge-scoring/index.php')
s=p.read_text()
old="<li><strong><?=e($criteriaLabel)?> · Tier <?=(int)$cfg['tier']?> · <?=$allRoleCounts[$criteriaRole]?> competitors:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li>"
new="<li>Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li>"
if old not in s:
    raise SystemExit('judge instruction anchor not found')
p.write_text(s.replace(old,new,1))
PY
php -l judge-scoring/index.php
git config user.name "BDC Release Bot"
git config user.email "actions@users.noreply.github.com"
git add judge-scoring/index.php
git diff --cached --quiet && exit 0
git commit -m "Simplify judge YES instruction wording"
git push origin HEAD:develop
