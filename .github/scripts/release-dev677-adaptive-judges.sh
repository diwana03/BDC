#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

css_path=Path('public/css/projector-safe-v616.css')
css=css_path.read_text()
marker='/* dev677 adaptive one-screen Judges board */'
block=r'''

/* dev677 adaptive one-screen Judges board */
body[data-screen-type="judges"] .stage{
  padding:clamp(22px,3.2cqh,38px) clamp(30px,3.1cqw,62px) clamp(24px,3cqh,38px)!important;
}
body[data-screen-type="judges"] .projection-official{top:clamp(18px,2.4cqh,30px)!important;right:clamp(30px,3.1cqw,62px)!important}
body[data-screen-type="judges"] .projection-heading-row{
  gap:clamp(16px,1.25cqw,26px)!important;
  margin-bottom:clamp(12px,1.65cqh,22px)!important;
}
body[data-screen-type="judges"] .projection-heading-row>.projection-brand{width:clamp(82px,9.2cqh,122px)!important}
body[data-screen-type="judges"] .event{font-size:clamp(27px,2vw,48px)!important;line-height:1.03!important}
body[data-screen-type="judges"] .meta{font-size:clamp(14px,.95vw,22px)!important;line-height:1.04!important}
body[data-screen-type="judges"] .title{font-size:clamp(25px,2vw,48px)!important;line-height:1.02!important;margin:.12em 0!important}
body[data-screen-type="judges"] .list{
  flex:1 1 auto!important;
  min-height:0!important;
  width:100%!important;
  grid-template-columns:minmax(0,1fr)!important;
  grid-auto-rows:minmax(0,1fr)!important;
  gap:clamp(10px,.8cqw,18px)!important;
  align-content:stretch!important;
  justify-content:center!important;
}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(2)){grid-template-columns:repeat(2,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(3)){grid-template-columns:repeat(3,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(4)){grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(5)){grid-template-columns:repeat(5,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(6)){grid-template-columns:repeat(6,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(7)){grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(9)){grid-template-columns:repeat(5,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(11)){grid-template-columns:repeat(6,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(13)){grid-template-columns:repeat(5,minmax(0,1fr))!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(16)){grid-template-columns:repeat(6,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-card{
  grid-template-columns:minmax(0,1fr)!important;
  grid-template-rows:minmax(72px,1fr) auto auto auto auto!important;
  gap:clamp(3px,.48cqh,7px)!important;
  padding:clamp(8px,1.15cqh,16px)!important;
  overflow:hidden!important;
}
body[data-screen-type="judges"] .judge-photo-frame{grid-column:1!important;grid-row:1!important;width:clamp(68px,min(38cqw,38cqh),142px)!important;height:clamp(68px,min(38cqw,38cqh),142px)!important}
body[data-screen-type="judges"] .judge-name{grid-column:1!important;grid-row:2!important;align-self:center!important;font-size:clamp(15px,min(9.2cqw,7.4cqh),30px)!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
body[data-screen-type="judges"] .judge-position{grid-column:1!important;grid-row:3!important;font-size:clamp(11px,min(6.2cqw,5.5cqh),19px)!important;line-height:1!important;white-space:nowrap!important}
body[data-screen-type="judges"] .judge-scope{grid-column:1!important;grid-row:4!important;align-self:center!important;padding:0!important;font-size:clamp(10px,min(5.6cqw,4.8cqh),16px)!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
body[data-screen-type="judges"] .judge-country{
  grid-column:1!important;grid-row:5!important;align-self:center!important;
  width:100%!important;max-width:100%!important;min-height:clamp(30px,5.2cqh,50px)!important;
  display:flex!important;flex-direction:row!important;flex-wrap:wrap!important;align-items:center!important;justify-content:center!important;
  gap:clamp(5px,.45cqw,10px)!important;padding:clamp(2px,.3cqh,5px) 0 0!important;overflow:hidden!important;
}
body[data-screen-type="judges"] .judge-country-entry,
body[data-screen-type="judges"] .judge-country-entry:only-child{
  flex:0 1 auto!important;min-width:0!important;max-width:100%!important;width:auto!important;
  display:inline-flex!important;flex-direction:row!important;align-items:center!important;justify-content:center!important;
  gap:clamp(5px,.38cqw,9px)!important;overflow:hidden!important;text-align:left!important;
}
body[data-screen-type="judges"] .judge-country-name,
body[data-screen-type="judges"] .judge-country-entry:only-child .judge-country-name{
  min-width:0!important;max-width:100%!important;width:auto!important;display:block!important;
  white-space:nowrap!important;overflow:hidden!important;overflow-wrap:normal!important;word-break:normal!important;text-overflow:ellipsis!important;
  text-transform:uppercase!important;text-align:left!important;font-size:clamp(10px,min(5.7cqw,4.4cqh),16px)!important;font-weight:900!important;letter-spacing:.025em!important;line-height:1!important;
}
body[data-screen-type="judges"] .judge-country .judge-flag{
  flex:0 0 auto!important;width:clamp(30px,min(15cqw,5.4cqh),54px)!important;height:auto!important;aspect-ratio:3/2!important;object-fit:cover!important;
}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(7)) .judge-card{padding:clamp(6px,.8cqh,11px)!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(7)) .judge-photo-frame{width:clamp(58px,min(32cqw,30cqh),112px)!important;height:clamp(58px,min(32cqw,30cqh),112px)!important}
body[data-screen-type="judges"] .list:has(>.judge-card:nth-child(13)) .judge-photo-frame{width:clamp(48px,min(27cqw,25cqh),86px)!important;height:clamp(48px,min(27cqw,25cqh),86px)!important}
'''
if marker not in css:
    css_path.write_text(css.rstrip()+block+'\n')

vp=Path('VERSION.json')
data=json.loads(vp.read_text())
data['version']='2.3.3-dev677'
data['build']=3383
data['release_date']='2026-09-07'
feature='Adaptive one-screen Judges projector: always fits the full judge panel on one audience screen, uses a clean five-card row for five judges, balanced multi-row grids for larger panels, preserves prominent real flags with horizontal country labels, and replaces rigid judge-screen dead space with responsive safe padding without changing Heats, Finals, matrix, scoring or results.'
features=data.setdefault('features',[])
if not features or features[0]!=feature: features.insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/feed.php
python3 - <<'PY'
from pathlib import Path
s=Path('public/css/projector-safe-v616.css').read_text()
assert 'dev677 adaptive one-screen Judges board' in s
assert 'nth-child(5)' in s and 'repeat(5' in s
assert 'flex-direction:row!important' in s
assert 'word-break:normal!important' in s
print('dev677 judge projection assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev677 adaptive one-screen judges'
git push origin HEAD:develop
