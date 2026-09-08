#!/usr/bin/env bash
set -euo pipefail

python3 <<'PY'
from pathlib import Path
import json

p = Path('live-display/feed.php')
s = p.read_text()
old = '''<td class="name final-matrix-couple"><div class="final-couple-row"><strong>BIB <?=(int)$x["leader_bib"]?></strong><?php if($leaderFlag=country_flag_url((string)($x["leader_country"]??""))):?><img class="final-matrix-flag" src="<?=e($leaderFlag)?>" alt="<?=e((string)$x["leader_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span><span class="final-matrix-amp">&amp;</span><strong>BIB <?=(int)$x["follower_bib"]?></strong><?php if($followerFlag=country_flag_url((string)($x["follower_country"]??""))):?><img class="final-matrix-flag" src="<?=e($followerFlag)?>" alt="<?=e((string)$x["follower_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span></div></td>'''
new = '''<td class="name final-matrix-couple"><div class="final-couple-row"><span class="final-couple-person final-couple-leader"><strong>BIB <?=(int)$x["leader_bib"]?></strong><?php if($leaderFlag=country_flag_url((string)($x["leader_country"]??""))):?><img class="final-matrix-flag" src="<?=e($leaderFlag)?>" alt="<?=e((string)$x["leader_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span></span><span class="final-matrix-amp">&amp;</span><span class="final-couple-person final-couple-follower"><strong>BIB <?=(int)$x["follower_bib"]?></strong><?php if($followerFlag=country_flag_url((string)($x["follower_country"]??""))):?><img class="final-matrix-flag" src="<?=e($followerFlag)?>" alt="<?=e((string)$x["follower_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span></span></div></td>'''
if old not in s:
    if 'final-couple-person final-couple-leader' not in s:
        raise SystemExit('dev704 final couple markup target not found')
else:
    s = s.replace(old, new, 1)
p.write_text(s)

p = Path('public/css/projector-safe-v616.css')
s = p.read_text()
block = r'''

/* dev704 Final matrix: keep the ampersand on the exact visual centerline.
   Equal leader/follower tracks prevent different name/BIB widths from shifting it. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-row{
  display:grid!important;
  grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;
  align-items:center!important;
  column-gap:clamp(7px,.42vw,11px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person{
  min-width:0!important;
  display:flex!important;
  align-items:center!important;
  gap:clamp(7px,.42vw,11px)!important;
  overflow:hidden!important;
  white-space:nowrap!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-leader{
  justify-self:stretch!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-follower{
  justify-self:stretch!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>strong,
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>.final-matrix-flag{
  flex:0 0 auto!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>.final-couple-name{
  flex:1 1 auto!important;
  min-width:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-matrix-amp{
  grid-column:2!important;
  justify-self:center!important;
  align-self:center!important;
  margin:0!important;
  padding:0 clamp(2px,.18vw,5px)!important;
}
'''
if 'dev704 Final matrix: keep the ampersand on the exact visual centerline.' not in s:
    s += block
p.write_text(s)

p = Path('VERSION.json')
d = json.loads(p.read_text())
d['version'] = '2.3.6-dev704'
d['build'] = 3410
feature = 'Final Relative Placement center alignment: the gold ampersand is locked to the exact center of the Couple column using equal leader/follower tracks, without changing scoring, judge values, BIBs, flags, names, pagination or matrix sizing.'
features = d.setdefault('features', [])
if feature not in features:
    features.insert(0, feature)
p.write_text(json.dumps(d, indent=2, ensure_ascii=False) + '\n')
PY

php -l live-display/feed.php

python3 <<'PY'
from pathlib import Path
import json
feed = Path('live-display/feed.php').read_text()
css = Path('public/css/projector-safe-v616.css').read_text()
ver = json.loads(Path('VERSION.json').read_text())
assert 'final-couple-person final-couple-leader' in feed
assert 'final-couple-person final-couple-follower' in feed
assert 'grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important' in css
assert 'grid-column:2!important' in css
assert ver['version'] == '2.3.6-dev704'
assert ver['build'] == 3410
print('dev704 centered ampersand assertions passed')
PY

git diff --check
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev704 center Final matrix ampersand'
git push origin HEAD:develop
