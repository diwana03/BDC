#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('public/css/projector-safe-v616.css')
s=p.read_text()
block=r'''

/* dev701 Final Relative Placement audience presentation.
   Pagination stays dev700; this layer improves readability without changing scoring. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .stage{
  padding-top:10cqh!important;
  padding-bottom:10cqh!important;
  padding-left:5cqw!important;
  padding-right:5cqw!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-official{top:10cqh!important;right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row{margin-bottom:clamp(7px,.85cqh,13px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row>.projection-brand{width:clamp(72px,7.4cqh,110px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .event{font-size:clamp(25px,1.82vw,43px)!important;line-height:1.02!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .meta{font-size:clamp(14px,.88vw,20px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .title{font-size:clamp(27px,2vw,46px)!important;line-height:1.02!important;margin:.08em 0 .2em!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final{table-layout:fixed!important;font-size:clamp(16px,1.02vw,23px)!important;flex:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th,
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td{padding:.38em .42em!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(1),
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:nth-child(1){width:11%!important;text-align:center!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(2),
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:nth-child(2){width:38%!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(n+3){font-size:clamp(14px,.82vw,18px)!important;text-align:center!important;line-height:1.02!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(n+3) small{font-size:clamp(10px,.58vw,13px)!important;display:block!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:nth-child(n+3){font-size:clamp(18px,1.05vw,24px)!important;text-align:center!important;font-weight:900!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-couple-inner{display:grid!important;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;align-items:center!important;gap:clamp(8px,.55vw,14px)!important;width:100%!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person{display:flex!important;align-items:center!important;gap:clamp(6px,.42vw,10px)!important;min-width:0!important;white-space:nowrap!important;overflow:hidden!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person strong{flex:0 0 auto!important;font-size:clamp(15px,.92vw,21px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person>span:last-child{font-size:clamp(16px,1vw,23px)!important;font-weight:900!important;overflow:hidden!important;text-overflow:ellipsis!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-flag{flex:0 0 auto!important;width:clamp(28px,1.65vw,40px)!important;height:auto!important;aspect-ratio:3/2!important;object-fit:cover!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-amp{font-size:clamp(18px,1.1vw,26px)!important;font-weight:950!important;color:#f2cf72!important}
'''
if 'dev701 Final Relative Placement audience presentation' not in s:s+=block
p.write_text(s)

p=Path('VERSION.json');d=json.loads(p.read_text());d['version']='2.3.6-dev701';d['build']=3407
f='Final Relative Placement presentation: preserves dev700 8-judge pagination while enlarging couple identities, flags, judge headers and marks, and enforcing the 10% top/bottom plus 5% left/right projector safe area.'
if f not in d.setdefault('features',[]):d['features'].insert(0,f)
p.write_text(json.dumps(d,indent=2,ensure_ascii=False)+'\n')
PY
python3 <<'PY'
from pathlib import Path
s=Path('public/css/projector-safe-v616.css').read_text()
assert 'dev701 Final Relative Placement audience presentation' in s
assert 'padding-top:10cqh!important' in s
assert 'padding-left:5cqw!important' in s
assert '.matrix-final) .final-matrix-flag' in s
assert 'width:38%!important' in s
print('dev701 presentation assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev701 Final matrix audience presentation'
git push origin HEAD:develop
