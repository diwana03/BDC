#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

# 1) Final Relative Placement: page judges instead of squeezing every judge into one screen.
p=Path('live-display/feed.php')
s=p.read_text()
old='''    $matrixJudges=$jq->fetchAll();
    if($isFinalMatrix){
'''
new='''    $matrixJudges=$jq->fetchAll();
    $matrixJudgeTotal=count($matrixJudges);
    $matrixJudgePageSize=$isFinalMatrix?8:max(1,$matrixJudgeTotal);
    $matrixJudgeTotalPages=$isFinalMatrix?max(1,(int)ceil($matrixJudgeTotal/$matrixJudgePageSize)):1;
    $matrixJudgePage=$isFinalMatrix?max(1,min($page,$matrixJudgeTotalPages)):1;
    if($isFinalMatrix&&$matrixJudgeTotalPages>1){
        $matrixJudges=array_slice($matrixJudges,($matrixJudgePage-1)*$matrixJudgePageSize,$matrixJudgePageSize);
        $title="FINAL RELATIVE PLACEMENT · PAGE {$matrixJudgePage} OF {$matrixJudgeTotalPages}";
    }
    if($isFinalMatrix){
'''
if old not in s:
    raise SystemExit('feed Final matrix judge anchor missing')
s=s.replace(old,new,1)
p.write_text(s)

# 2) State endpoint must advertise Final matrix pages so existing auto-page/advance logic can rotate them.
p=Path('live-display/state.php')
s=p.read_text()
anchor='''if ($roundId && $s["screen_type"] === "judges") {
'''
insert='''if ($roundId && $s["screen_type"] === "score_matrix" && $roundType === "final") {
    $judgeTable = $test ? "bdc_test_scoring_judges" : "bdc_scoring_judges";
    $judgeCountQuery = $pdo->prepare("SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r");
    $judgeCountQuery->execute(["r" => $roundId]);
    $judgeCount = max(0, (int) $judgeCountQuery->fetchColumn());
    // Eight Final judges per page keeps names and relative placements audience-readable.
    $total = max(1, (int) ceil($judgeCount / 8));
}
'''+anchor
if 'Eight Final judges per page' not in s:
    if anchor not in s: raise SystemExit('state judges pagination anchor missing')
    s=s.replace(anchor,insert,1)
p.write_text(s)

# 3) Any newly selected projector feed starts at Page 1 unless the button explicitly represents a page/flight/judge call.
p=Path('admin/live-screen/control.php')
s=p.read_text()
old="""const extra={};if(b.dataset.page){pageNumber.value=b.dataset.page;extra.page_number=b.dataset.page;}const j=await postAction({action:'update',screen_type:b.dataset.screen,...extra});"""
new="""const extra={};if(b.dataset.page){pageNumber.value=b.dataset.page;extra.page_number=b.dataset.page;}else{pageNumber.value='1';extra.page_number='1';}const j=await postAction({action:'update',screen_type:b.dataset.screen,...extra});"""
if old not in s:
    raise SystemExit('control feed page-reset anchor missing')
s=s.replace(old,new,1)
p.write_text(s)

# 4) Apply the locked projector safe area + readable type floor to Final Relative Placement.
p=Path('public/css/projector-safe-v616.css')
c=p.read_text()
block=r'''

/* dev700 Final Relative Placement: safe-area + readable judge pagination.
   Final judge columns are paged in feed.php (8/page); never squeeze 20+ judges. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .stage{
  padding-top:10cqh!important;
  padding-bottom:10cqh!important;
  padding-left:5cqw!important;
  padding-right:5cqw!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-official{top:10cqh!important;right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row{margin-bottom:clamp(6px,.75cqh,11px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row>.projection-brand{width:clamp(70px,7cqh,104px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .event{font-size:clamp(24px,1.7vw,40px)!important;line-height:1.02!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .meta{font-size:clamp(13px,.84vw,19px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .title{font-size:clamp(23px,1.72vw,40px)!important;line-height:1.02!important;margin:.08em 0!important}
body[data-screen-type="score_matrix"] .matrix-final{font-size:clamp(14px,.88vw,20px)!important;table-layout:fixed!important}
body[data-screen-type="score_matrix"] .matrix-final th,
body[data-screen-type="score_matrix"] .matrix-final td{padding:.32em .22em!important}
body[data-screen-type="score_matrix"] .matrix-final th:first-child,
body[data-screen-type="score_matrix"] .matrix-final td:first-child{width:8%!important}
body[data-screen-type="score_matrix"] .matrix-final th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-final td:nth-child(2){width:36%!important}
body[data-screen-type="score_matrix"] .matrix-final th:nth-child(n+3):not(:last-child){font-size:clamp(13px,.74vw,17px)!important;line-height:1!important}
body[data-screen-type="score_matrix"] .matrix-final th:nth-child(n+3):not(:last-child) small{display:block!important;font-size:clamp(10px,.52vw,12px)!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
body[data-screen-type="score_matrix"] .matrix-final td:nth-child(n+3):not(:last-child){font-size:clamp(16px,.92vw,21px)!important;font-weight:900!important;text-align:center!important}
body[data-screen-type="score_matrix"] .matrix-final th:last-child,
body[data-screen-type="score_matrix"] .matrix-final td:last-child{width:8%!important;text-align:center!important}
'''
if 'dev700 Final Relative Placement' not in c:
    c += block
p.write_text(c)

# 5) Release metadata.
p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev700'
data['build']=3406
feature='Final Relative Placement projector pagination: Final judge columns are limited to 8 per page, page count drives the existing projector auto-loop, every newly selected screen starts on Page 1, and Final matrices enforce the 10% vertical / 5% horizontal audience safe area with readable typography.'
if feature not in data.setdefault('features',[]): data['features'].insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY

php -l live-display/feed.php
php -l live-display/state.php
php -l admin/live-screen/control.php
python3 <<'PY'
from pathlib import Path
f=Path('live-display/feed.php').read_text()
s=Path('live-display/state.php').read_text()
c=Path('admin/live-screen/control.php').read_text()
css=Path('public/css/projector-safe-v616.css').read_text()
assert '$matrixJudgePageSize=$isFinalMatrix?8' in f
assert 'FINAL RELATIVE PLACEMENT · PAGE {$matrixJudgePage} OF {$matrixJudgeTotalPages}' in f
assert 'Eight Final judges per page' in s
assert '$total = max(1, (int) ceil($judgeCount / 8));' in s
assert "else{pageNumber.value='1';extra.page_number='1';}" in c
assert 'dev700 Final Relative Placement' in css
assert 'padding-top:10cqh!important' in css
assert 'padding-left:5cqw!important' in css
print('dev700 Final matrix pagination assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php live-display/state.php admin/live-screen/control.php public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev700 Final Relative Placement pagination'
git push origin HEAD:develop
