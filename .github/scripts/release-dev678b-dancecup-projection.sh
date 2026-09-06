#!/usr/bin/env bash
set -euo pipefail

# Recover the existing dev678 projector release. The original script correctly
# builds the adaptive contestant/judge board, but its first Python block writes
# an older in-memory projector.php back over the safe-spacing/identity CSS
# replacements before the verification step. Let it build the working tree,
# then restore those four intended replacements and verify the complete result.
set +e
bash .github/scripts/release-dev678-dancecup-projection.sh
base_status=$?
set -e
if [ "$base_status" -eq 0 ]; then
  echo "dev678 was already applied successfully"
  exit 0
fi

python3 <<'PY'
from pathlib import Path

p = Path('admin/dance-cup/projector.php')
s = p.read_text()

def ensure_replace(old: str, new: str) -> None:
    global s
    if new in s:
        return
    if old not in s:
        raise SystemExit(f'required Dance Cup projector anchor missing: {old[:120]!r}')
    s = s.replace(old, new, 1)

ensure_replace(
    '#app{width:100vw;height:100vh;padding:10vh 5vw;display:flex;flex-direction:column;gap:clamp(10px,1.2vh,20px)}',
    '#app{width:100vw;height:100vh;padding:clamp(18px,2.6vh,34px) clamp(26px,2.8vw,54px) clamp(20px,2.7vh,36px);display:flex;flex-direction:column;gap:clamp(8px,1vh,16px)}'
)
ensure_replace(
    '.top{width:78vw;max-width:100%;margin-inline:auto;display:grid;',
    '.top{width:100%;max-width:100%;margin-inline:auto;display:grid;'
)
ensure_replace(
    'padding:clamp(20px,3.2vh,48px) 1vw clamp(12px,1.8vh,26px)',
    'padding:clamp(8px,1.1vh,14px) 1vw clamp(7px,.9vh,12px)'
)
ensure_replace(
    '.identity-meta.multi-country{flex-wrap:wrap;align-content:center}.identity-country{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;max-width:18%;line-height:1.05;text-align:center;overflow-wrap:anywhere}',
    '.identity-meta.multi-country{flex-wrap:wrap;align-content:center;justify-content:center}.identity-country{display:inline-flex;flex-direction:row;align-items:center;justify-content:center;gap:clamp(5px,.42vw,9px);min-width:0;max-width:100%;line-height:1.05;text-align:center;white-space:nowrap;overflow-wrap:normal;word-break:normal}.identity-country .flag-image{display:block;width:clamp(30px,2.15vw,46px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.72);border-radius:3px;box-shadow:0 2px 5px #0005}.identity-country>.flag:empty{display:none}'
)
p.write_text(s)
PY

php -l admin/dance-cup/projector.php
php -l admin/dance-cup/projection-feed.php
python3 <<'PY'
from pathlib import Path
import json
p=Path('admin/dance-cup/projector.php').read_text()
f=Path('admin/dance-cup/projection-feed.php').read_text()
v=json.loads(Path('VERSION.json').read_text())
assert 'dev678 exact Dance Cup audience boards' in p
assert 'grid-template-columns:repeat(5,minmax(0,1fr))!important' in p
assert 'grid-template-rows:repeat(2,minmax(0,1fr))!important' in p
assert "Math.max(1,data.judges.length)" in p
assert 'flex-direction:row' in p
assert 'overflow-wrap:normal' in p
assert 'flag-image' in p
assert 'country_codes' in f
assert 'padding:10vh 5vw' not in p
assert '.top{width:78vw' not in p
assert v.get('version') == '2.3.3-dev678'
print('dev678b exact Dance Cup projector recovery assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add admin/dance-cup/projector.php admin/dance-cup/projection-feed.php VERSION.json
if git diff --cached --quiet; then
  echo 'No release changes to commit.'
  exit 0
fi
git commit -m 'Release dev678b exact Dance Cup projection repair'
git push origin HEAD:develop
