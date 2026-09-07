#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import re, json
p=Path('live-display/feed.php')
s=p.read_text()
pat=re.compile(r'<td class="name final-matrix-couple"><div class="final-matrix-couple-inner">.*?</div></td><\?php foreach\(\$matrixJudges as \$judge\):\?><td>',re.S)
repl='''<td class="name final-matrix-couple"><div class="final-couple-row"><strong>BIB <?=(int)$x["leader_bib"]?></strong><?php if($leaderFlag=country_flag_url((string)($x["leader_country"]??""))):?><img class="final-matrix-flag" src="<?=e($leaderFlag)?>" alt="<?=e((string)$x["leader_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span><span class="final-matrix-amp">&amp;</span><strong>BIB <?=(int)$x["follower_bib"]?></strong><?php if($followerFlag=country_flag_url((string)($x["follower_country"]??""))):?><img class="final-matrix-flag" src="<?=e($followerFlag)?>" alt="<?=e((string)$x["follower_country"])?> flag"><?php endif;?><span class="final-couple-name"><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span></div></td><?php foreach($matrixJudges as $judge):?><td>'''
s2,n=pat.subn(repl,s,count=1)
if n!=1: raise SystemExit(f'final matrix couple markup replacement count={n}')
p.write_text(s2)

p=Path('public/css/projector-safe-v616.css')
s=p.read_text()
block=r'''

/* dev703 Final matrix: one normal table row, one normal Couple cell, no inner pseudo-cells. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-matrix-couple{
  display:table-cell!important;
  width:38%!important;
  padding:.38em .55em!important;
  border:1px solid rgba(255,255,255,.24)!important;
  background:transparent!important;
  box-shadow:none!important;
  overflow:hidden!important;
  vertical-align:middle!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-row{
  width:100%!important;
  min-width:0!important;
  display:flex!important;
  align-items:center!important;
  gap:clamp(7px,.42vw,11px)!important;
  white-space:nowrap!important;
  overflow:hidden!important;
  border:0!important;
  outline:0!important;
  background:transparent!important;
  box-shadow:none!important;
  padding:0!important;
  margin:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-row>*{
  border:0!important;
  outline:0!important;
  background:transparent!important;
  box-shadow:none!important;
  margin:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-row>strong{
  flex:0 0 auto!important;
  font-size:clamp(15px,.9vw,21px)!important;
  font-weight:950!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-name{
  flex:1 1 0!important;
  min-width:0!important;
  overflow:hidden!important;
  text-overflow:ellipsis!important;
  font-size:clamp(16px,1vw,23px)!important;
  font-weight:950!important;
  text-align:left!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-matrix-flag{
  flex:0 0 auto!important;
  display:block!important;
  width:clamp(28px,1.6vw,39px)!important;
  height:auto!important;
  aspect-ratio:3/2!important;
  object-fit:cover!important;
  border:1px solid rgba(255,255,255,.72)!important;
  border-radius:3px!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-matrix-amp{
  flex:0 0 auto!important;
  font-size:clamp(18px,1.1vw,26px)!important;
  font-weight:950!important;
  color:#f2cf72!important;
  padding:0 clamp(2px,.18vw,5px)!important;
}
'''
if 'dev703 Final matrix: one normal table row' not in s:s+=block
p.write_text(s)

p=Path('VERSION.json');d=json.loads(p.read_text());d['version']='2.3.6-dev703';d['build']=3409
f='Final Relative Placement standard-row repair: removes the nested leader/follower pseudo-cell layout and renders BIB, flag, first name, ampersand, BIB, flag, first name in one normal Couple table cell while preserving 8-judge pagination and scoring.'
if f not in d.setdefault('features',[]):d['features'].insert(0,f)
p.write_text(json.dumps(d,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/feed.php
python3 <<'PY'
from pathlib import Path
s=Path('live-display/feed.php').read_text(); c=Path('public/css/projector-safe-v616.css').read_text()
assert 'class="final-couple-row"' in s
assert '<td class="name final-matrix-couple"><div class="final-matrix-couple-inner">' not in s
assert 'dev703 Final matrix: one normal table row' in c
assert 'display:table-cell!important' in c
print('dev703 standard row assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev703 Final matrix standard couple row'
git push origin HEAD:develop
