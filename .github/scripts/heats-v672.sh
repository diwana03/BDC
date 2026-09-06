#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
css_path=Path('public/css/projector-safe-v616.css')
css=css_path.read_text()
marker='/* dev672 Heats safe area */'
if marker not in css:
    css += r'''

/* dev672 Heats safe area */
body[data-screen-type="score_matrix"]:has(.matrix-heats) .stage{padding-top:10cqh!important;padding-bottom:10cqh!important;padding-left:5cqw!important;padding-right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-official{top:10cqh!important;right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-heading-row{margin-bottom:clamp(6px,.7cqh,10px)!important;gap:clamp(12px,1cqw,20px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-heading-row>.projection-brand{width:clamp(78px,7.2cqh,112px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .event{font-size:clamp(24px,1.7vw,42px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .meta{font-size:clamp(12px,.82vw,19px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .title{font-size:clamp(24px,1.85vw,44px)!important;margin:.1em 0 .06em!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .provisional-label{font-size:clamp(9px,.56vw,13px)!important;margin:0 0 .18cqh!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-role{min-height:clamp(28px,3.1cqh,38px)!important;font-size:clamp(14px,.95vw,22px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th,body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td{overflow:hidden!important;text-overflow:clip!important;white-space:nowrap!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:first-child,body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:first-child{width:7%!important;font-size:clamp(13px,.82vw,19px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:nth-child(2),body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:nth-child(2){width:11%!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:nth-child(2){font-size:clamp(20px,1.28vw,30px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:nth-child(3),body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:nth-child(3){width:34%!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .heats-competitor{font-size:clamp(18px,1.16vw,28px)!important;gap:clamp(6px,.45vw,10px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .heats-flag{width:clamp(30px,1.7vw,42px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:nth-child(n+4){font-size:clamp(13px,.82vw,19px)!important;line-height:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:nth-child(n+4) small{font-size:clamp(8px,.48vw,11px)!important;margin-top:.12em!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:nth-child(n+4){font-size:clamp(18px,1.05vw,25px)!important;line-height:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats th:last-child,body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:last-child{width:9%!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-heats td:last-child{font-size:clamp(18px,1.08vw,26px)!important}
'''
css_path.write_text(css)
v=Path('VERSION.json')
d=json.loads(v.read_text())
if d.get('version')!='2.3.3-dev671' or d.get('build')!=3377:
    raise SystemExit(f"unexpected baseline {d.get('version')} build {d.get('build')}")
d['version']='2.3.3-dev672'; d['build']=3378
d.setdefault('features',[]).insert(0,'Heats projector restores the 10% top/bottom and 5% left/right physical safe area, keeps the approved BIB/flag/name hierarchy, and sizes judge marks and score columns to remain readable without clipping or ellipsis.')
v.write_text(json.dumps(d,indent=2,ensure_ascii=False)+'\n')
PY
git diff --check
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git rm -f .github/workflows/apply-dev672.yml .github/workflows/heats-safe-area-v672.yml || true
git add public/css/projector-safe-v616.css VERSION.json .github/scripts/heats-v672.sh
git commit -m 'Release dev672 Heats safe area and readable fit'
git push origin HEAD:develop
