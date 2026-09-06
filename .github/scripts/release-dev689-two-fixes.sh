#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path

# Fix 1: role labels in judge-facing Heats instructions, no tier/count exposure.
p=Path('judge-scoring/index.php')
s=p.read_text()
old='''<div class="alert success"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style="margin:7px 0 0;padding-left:20px"><?php $sheetScope=(string)$session['scoring_scope'];$sheetSeen=[];foreach(['leader','follower'] as $sheetRole):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];$key=$cfg['yes'].'|'.$places['A1'].'|'.$places['A2'].'|'.$places['A3'];if(isset($sheetSeen[$key]))continue;$sheetSeen[$key]=true;?><li>Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>'''
new='''<div class="alert success"><strong><?=e($selectionRoundLabel)?> Instructions</strong><ul style="margin:7px 0 0;padding-left:20px"><?php $sheetScope=(string)$session['scoring_scope'];foreach(['leader'=>'Leaders','follower'=>'Followers'] as $sheetRole=>$sheetLabel):if(!in_array($sheetScope,['all',$sheetRole],true)||!$entries[$sheetRole])continue;$cfg=$roleTierConfig[$sheetRole];$places=$roleAltPlaces[$sheetRole];?><li><strong><?=e($sheetLabel)?>:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li></ul></div>'''
if old in s:
    s=s.replace(old,new,1)
elif '<strong><?=e($sheetLabel)?>:</strong> Choose <strong>' not in s:
    raise SystemExit('judge instruction block not found')
p.write_text(s)

# Fix 2: old safe-area CSS was forcing judge cards to span rows. Force each card back into a normal grid cell.
css=Path('public/css/projector-safe-v616.css')
c=css.read_text()
marker='/* dev689 judge projection grid cell reset */'
if marker not in c:
    c += r'''

/* dev689 judge projection grid cell reset */
body[data-screen-type="judges"] .stage .list.judge-list{
  display:grid!important;
  grid-template-columns:repeat(4,minmax(0,1fr))!important;
  grid-template-rows:repeat(2,minmax(0,1fr))!important;
  grid-auto-flow:row!important;
  grid-auto-rows:minmax(0,1fr)!important;
  gap:clamp(10px,1cqw,20px)!important;
  min-height:0!important;
}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-1{grid-template-columns:minmax(0,.48fr)!important;grid-template-rows:minmax(0,1fr)!important;justify-content:center!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-2{grid-template-columns:repeat(2,minmax(0,1fr))!important;grid-template-rows:minmax(0,1fr)!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-3{grid-template-columns:repeat(3,minmax(0,1fr))!important;grid-template-rows:minmax(0,1fr)!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-4{grid-template-columns:repeat(4,minmax(0,1fr))!important;grid-template-rows:minmax(0,1fr)!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-5{grid-template-columns:repeat(5,minmax(0,1fr))!important;grid-template-rows:minmax(0,1fr)!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-6{grid-template-columns:repeat(3,minmax(0,1fr))!important;grid-template-rows:repeat(2,minmax(0,1fr))!important}
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-7,
body[data-screen-type="judges"] .stage .list.judge-list.judge-count-8{grid-template-columns:repeat(4,minmax(0,1fr))!important;grid-template-rows:repeat(2,minmax(0,1fr))!important}
body[data-screen-type="judges"] .stage .list.judge-list > .judge-card{
  grid-column:auto!important;
  grid-row:auto!important;
  position:relative!important;
  inset:auto!important;
  width:auto!important;
  height:auto!important;
  max-width:none!important;
  max-height:none!important;
  align-self:stretch!important;
  justify-self:stretch!important;
  display:grid!important;
  grid-template-columns:minmax(0,1fr)!important;
  grid-template-rows:minmax(0,1fr) auto auto!important;
  place-items:center!important;
  padding:clamp(10px,1cqh,16px)!important;
  overflow:hidden!important;
}
body[data-screen-type="judges"] .stage .judge-photo-frame{grid-column:1!important;grid-row:1!important;width:clamp(110px,min(11vw,17vh),175px)!important;height:clamp(110px,min(11vw,17vh),175px)!important}
body[data-screen-type="judges"] .stage .judge-name{grid-column:1!important;grid-row:2!important;align-self:center!important;font-size:clamp(24px,min(2vw,3.2vh),40px)!important;text-align:center!important;width:100%!important}
body[data-screen-type="judges"] .stage .judge-position,
body[data-screen-type="judges"] .stage .judge-scope{display:none!important}
body[data-screen-type="judges"] .stage .judge-country{grid-column:1!important;grid-row:3!important;align-self:start!important;padding:0!important}
'''
css.write_text(c)
PY
php -l judge-scoring/index.php
php -l live-display/feed.php
python3 <<'PY'
from pathlib import Path
j=Path('judge-scoring/index.php').read_text()
c=Path('public/css/projector-safe-v616.css').read_text()
assert '<strong><?=e($sheetLabel)?>:</strong> Choose <strong>' in j
assert 'dev689 judge projection grid cell reset' in c
assert 'grid-column:auto!important' in c
assert 'judge-count-8{grid-template-columns:repeat(4' in c
print('dev689 checks passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add judge-scoring/index.php public/css/projector-safe-v616.css
git commit -m 'Release dev689 judge instructions and 4x2 projection grid'
git push origin HEAD:develop
