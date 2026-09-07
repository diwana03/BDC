#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json
p=Path('public/css/projector-safe-v616.css')
s=p.read_text()
block=r'''

/* dev702 Final Relative Placement couple identity repair.
   Keep dev700 pagination/dev701 sizing, but make both people consume their full half-column. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-couple{
  overflow:hidden!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-couple-inner{
  display:grid!important;
  grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;
  align-items:center!important;
  width:100%!important;
  min-width:0!important;
  overflow:hidden!important;
  gap:clamp(7px,.5vw,12px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person{
  display:grid!important;
  grid-template-columns:auto auto minmax(0,1fr)!important;
  align-items:center!important;
  width:100%!important;
  min-width:0!important;
  max-width:none!important;
  justify-self:stretch!important;
  overflow:hidden!important;
  gap:clamp(5px,.36vw,9px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person strong{
  display:block!important;
  flex:none!important;
  width:auto!important;
  max-width:none!important;
  overflow:visible!important;
  white-space:nowrap!important;
  font-size:clamp(14px,.86vw,20px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person .final-matrix-flag{
  display:block!important;
  width:clamp(28px,1.55vw,38px)!important;
  min-width:clamp(28px,1.55vw,38px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person>span:last-child{
  display:block!important;
  min-width:0!important;
  width:100%!important;
  overflow:hidden!important;
  text-overflow:ellipsis!important;
  white-space:nowrap!important;
  font-size:clamp(16px,.98vw,22px)!important;
  font-weight:950!important;
  text-align:left!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-amp{
  justify-self:center!important;
  margin:0!important;
}
'''
if 'dev702 Final Relative Placement couple identity repair' not in s:s+='\n'+block
p.write_text(s)

v=Path('VERSION.json');d=json.loads(v.read_text());d['version']='2.3.6-dev702';d['build']=3408
f='Final Relative Placement couple identity repair: leader and follower each use a full half of the Couple column so BIB, flag and first name remain visible while retaining dev700 judge pagination and projector safe area.'
if f not in d.setdefault('features',[]):d['features'].insert(0,f)
v.write_text(json.dumps(d,indent=2,ensure_ascii=False)+'\n')
PY
python3 <<'PY'
from pathlib import Path
s=Path('public/css/projector-safe-v616.css').read_text()
assert 'dev702 Final Relative Placement couple identity repair' in s
assert 'grid-template-columns:auto auto minmax(0,1fr)!important' in s
assert 'justify-self:stretch!important' in s
assert 'overflow:visible!important' in s
print('dev702 assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev702 Final couple identity repair'
git push origin HEAD:develop
