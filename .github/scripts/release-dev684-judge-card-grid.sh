#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

p=Path('live-display/feed.php')
s=p.read_text()
old='$cols = $layout["columns"];'
new='''$cols = $layout["columns"];\nif ($type === "judges") {\n    // Judge pages are audience-first cards, not full-width rows.\n    $judgeCount = count($items);\n    $cols = $judgeCount <= 1 ? 1 : ($judgeCount === 2 ? 2 : ($judgeCount === 3 ? 3 : ($judgeCount === 4 ? 4 : ($judgeCount === 5 ? 5 : ($judgeCount === 6 ? 3 : 4)))));\n}'''
if new not in s:
    if old not in s: raise SystemExit('judge columns anchor missing')
    s=s.replace(old,new,1)

anchor='.judge-flag{flex:0 0 auto;width:clamp(18px,min(12cqw,12cqh),38px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.72);border-radius:3px;box-shadow:0 2px 5px rgba(0,0,0,.35)}</style></head>'
block=r'''.judge-flag{flex:0 0 auto;width:clamp(18px,min(12cqw,12cqh),38px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.72);border-radius:3px;box-shadow:0 2px 5px rgba(0,0,0,.35)}
/* dev684 judge board: force true audience card grid in the renderer itself. */
body[data-screen-type="judges"] .judge-list{display:grid!important;grid-auto-rows:minmax(0,1fr)!important;align-content:stretch!important;justify-content:stretch!important;gap:1.05%!important}
body[data-screen-type="judges"] .judge-list.judge-count-1{grid-template-columns:minmax(0,.44fr)!important;justify-content:center!important}
body[data-screen-type="judges"] .judge-list.judge-count-2{grid-template-columns:repeat(2,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-3{grid-template-columns:repeat(3,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-4{grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-5{grid-template-columns:repeat(5,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-6{grid-template-columns:repeat(3,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-7,body[data-screen-type="judges"] .judge-list.judge-count-8{grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-card{display:grid!important;grid-template-columns:minmax(0,1fr)!important;grid-template-rows:minmax(0,1fr) auto auto!important;place-items:center!important;gap:clamp(4px,.62cqh,8px)!important;padding:clamp(8px,1.05cqh,14px)!important;min-width:0!important;min-height:0!important}
body[data-screen-type="judges"] .judge-photo-frame{grid-column:1!important;grid-row:1!important;align-self:center!important;width:clamp(92px,min(11.2vw,17.5vh),176px)!important;height:clamp(92px,min(11.2vw,17.5vh),176px)!important}
body[data-screen-type="judges"] .judge-photo,body[data-screen-type="judges"] .judge-photo-fallback{border-radius:clamp(10px,1.1cqw,18px)!important}
body[data-screen-type="judges"] .judge-name{grid-column:1!important;grid-row:2!important;align-self:center!important;width:100%!important;font-size:clamp(21px,min(2.05vw,3.45vh),40px)!important;line-height:1!important;text-align:center!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;font-weight:950!important}
body[data-screen-type="judges"] .judge-position,body[data-screen-type="judges"] .judge-scope{display:none!important}
body[data-screen-type="judges"] .judge-country{grid-column:1!important;grid-row:3!important;align-self:start!important;display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;align-items:flex-start!important;justify-content:center!important;gap:clamp(8px,.75cqw,14px)!important;width:100%!important;max-width:100%!important;overflow:hidden!important;padding:0!important}
body[data-screen-type="judges"] .judge-country-entry{display:inline-flex!important;flex-direction:column!important;align-items:center!important;justify-content:flex-start!important;gap:clamp(2px,.35cqh,4px)!important;min-width:0!important}
body[data-screen-type="judges"] .judge-country .judge-flag{width:clamp(42px,min(4.2vw,6.8vh),72px)!important;height:auto!important;border-radius:4px!important}
body[data-screen-type="judges"] .judge-country-name{display:block!important;font-size:clamp(13px,min(1.05vw,1.75vh),21px)!important;line-height:1!important;font-weight:900!important;text-transform:uppercase!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;text-align:center!important}
body[data-screen-type="judges"] .judge-list.judge-count-7 .judge-photo-frame,body[data-screen-type="judges"] .judge-list.judge-count-8 .judge-photo-frame{width:clamp(88px,min(10.2vw,15.7vh),158px)!important;height:clamp(88px,min(10.2vw,15.7vh),158px)!important}
body[data-screen-type="judges"] .judge-list.judge-count-7 .judge-name,body[data-screen-type="judges"] .judge-list.judge-count-8 .judge-name{font-size:clamp(20px,min(1.9vw,3.05vh),36px)!important}
</style></head>'''
if 'dev684 judge board' not in s:
    if anchor not in s: raise SystemExit('main style anchor missing')
    s=s.replace(anchor,block,1)
p.write_text(s)

vp=Path('VERSION.json')
data=json.loads(vp.read_text())
data['version']='2.3.4-dev684'
data['build']=3391
data['release_date']='2026-09-07'
feature='Judge projection card-grid repair: renderer now explicitly chooses 1/2/3/4/5-column judge layouts after pagination, resets old horizontal judge positioning, and renders 7–8 judge pages as a readable 4×2 audience board with large photos, names, flags and country labels.'
features=data.setdefault('features',[])
if not features or features[0]!=feature: features.insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/feed.php
python3 <<'PY'
from pathlib import Path
s=Path('live-display/feed.php').read_text()
assert '$judgeCount = count($items);' in s
assert '$judgeCount === 6 ? 3 : 4' in s
assert 'dev684 judge board' in s
assert 'judge-list.judge-count-8{grid-template-columns:repeat(4' in s
assert '.judge-position,body[data-screen-type="judges"] .judge-scope{display:none!important}' in s
assert 'grid-template-columns:minmax(0,1fr)!important;grid-template-rows:minmax(0,1fr) auto auto!important' in s
print('dev684 judge card grid assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php VERSION.json
git commit -m 'Release dev684 judge card grid repair'
git push origin HEAD:develop
