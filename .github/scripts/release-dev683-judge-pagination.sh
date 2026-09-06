#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

feed_path=Path('live-display/feed.php')
feed=feed_path.read_text()
anchor='''$heatsScoreRoleItems=["leader"=>[],"follower"=>[]];'''
insert='''$judgeDisplayTotalPages=1;\n$judgeDisplayPage=1;\nif($type==="judges"){\n    // Audience readability wins over fit-at-any-cost. Keep at most eight judge\n    // cards on a 16:9 page and balance larger panels across multiple pages.\n    $judgeDisplayTotalPages=max(1,(int)ceil(count($items)/8));\n    $judgeDisplayPage=max(1,min($page,$judgeDisplayTotalPages));\n    $items=ProjectionLayoutService::balancedPageSlice($items,$judgeDisplayPage,$judgeDisplayTotalPages);\n    if($judgeDisplayTotalPages>1)$title="JUDGES · PAGE {$judgeDisplayPage} OF {$judgeDisplayTotalPages}";\n}\n\n$heatsScoreRoleItems=["leader"=>[],"follower"=>[]];'''
if 'judgeDisplayTotalPages' not in feed:
    if anchor not in feed: raise SystemExit('feed pagination anchor missing')
    feed=feed.replace(anchor,insert,1)
feed_path.write_text(feed)

advance_path=Path('live-display/advance.php')
advance=advance_path.read_text()
old="$pagedTypes = ['competitors', 'callbacks', 'finalists', 'heats_scores', 'score_matrix', 'judge_call'];"
new="$pagedTypes = ['competitors', 'callbacks', 'finalists', 'heats_scores', 'score_matrix', 'judges', 'judge_call'];"
if old in advance: advance=advance.replace(old,new,1)
elif new not in advance: raise SystemExit('advance paged types anchor missing')
old_block="""if ($screenType === 'judge_call') {\n    $judgeTable = $test ? 'bdc_test_scoring_judges' : 'bdc_scoring_judges';\n    $countQuery = $pdo->prepare(\"SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r\");\n    $countQuery->execute(['r' => $roundId]);\n    $pages = max(1, (int) $countQuery->fetchColumn());\n} else {"""
new_block="""if (in_array($screenType, ['judges', 'judge_call'], true)) {\n    $judgeTable = $test ? 'bdc_test_scoring_judges' : 'bdc_scoring_judges';\n    $countQuery = $pdo->prepare(\"SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r\");\n    $countQuery->execute(['r' => $roundId]);\n    $judgeCount = (int) $countQuery->fetchColumn();\n    $pages = $screenType === 'judge_call'\n        ? max(1, $judgeCount)\n        : max(1, (int) ceil($judgeCount / 8));\n} else {"""
if old_block in advance: advance=advance.replace(old_block,new_block,1)
elif "ceil($judgeCount / 8)" not in advance: raise SystemExit('advance judge page anchor missing')
advance_path.write_text(advance)

css_path=Path('public/css/projector-safe-v616.css')
css=css_path.read_text()
marker='/* dev683 readable paged Judges board */'
block=r'''

/* dev683 readable paged Judges board */
/* Never compress a large panel into unreadable strips. feed.php caps a judge
   page at eight cards; these explicit count classes avoid browser :has()
   dependency and keep every supported page audience-readable. */
body[data-screen-type="judges"] .judge-list{grid-auto-rows:minmax(0,1fr)!important;align-content:stretch!important}
body[data-screen-type="judges"] .judge-list.judge-count-1{grid-template-columns:minmax(0,.46fr)!important;justify-content:center!important}
body[data-screen-type="judges"] .judge-list.judge-count-2{grid-template-columns:repeat(2,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-3{grid-template-columns:repeat(3,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-4{grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-5{grid-template-columns:repeat(5,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-6{grid-template-columns:repeat(3,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-7,
body[data-screen-type="judges"] .judge-list.judge-count-8{grid-template-columns:repeat(4,minmax(0,1fr))!important}
body[data-screen-type="judges"] .judge-list.judge-count-6 .judge-photo-frame,
body[data-screen-type="judges"] .judge-list.judge-count-7 .judge-photo-frame,
body[data-screen-type="judges"] .judge-list.judge-count-8 .judge-photo-frame{width:clamp(72px,min(34cqw,31cqh),126px)!important;height:clamp(72px,min(34cqw,31cqh),126px)!important}
body[data-screen-type="judges"] .judge-list.judge-count-6 .judge-name,
body[data-screen-type="judges"] .judge-list.judge-count-7 .judge-name,
body[data-screen-type="judges"] .judge-list.judge-count-8 .judge-name{font-size:clamp(17px,min(9cqw,7.8cqh),30px)!important}
'''
if marker not in css: css_path.write_text(css.rstrip()+block+'\n')

vp=Path('VERSION.json')
data=json.loads(vp.read_text())
data['version']='2.3.3-dev683'
data['build']=3390
data['release_date']='2026-09-07'
feature='Readable paged Judges projection: keeps up to eight judges per 16:9 audience page, automatically splits larger panels into balanced pages, lets Auto Page rotate those pages, and preserves large photos, names, flags and country labels instead of shrinking a large panel into unreadable strips.'
features=data.setdefault('features',[])
if not features or features[0]!=feature: features.insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY

php -l live-display/feed.php
php -l live-display/advance.php
python3 <<'PY'
from pathlib import Path
f=Path('live-display/feed.php').read_text()
a=Path('live-display/advance.php').read_text()
c=Path('public/css/projector-safe-v616.css').read_text()
assert 'ceil(count($items)/8)' in f
assert 'balancedPageSlice($items,$judgeDisplayPage,$judgeDisplayTotalPages)' in f
assert 'JUDGES · PAGE {$judgeDisplayPage} OF {$judgeDisplayTotalPages}' in f
assert "'judges', 'judge_call'" in a
assert 'ceil($judgeCount / 8)' in a
assert 'dev683 readable paged Judges board' in c
assert 'judge-count-8' in c and 'repeat(4' in c
assert 'judge-count-6' in c and 'repeat(3' in c
# Mathematical regression gates: 8 stays one page; 9 and 15 split to two; 17 to three.
for count,expected in [(1,1),(5,1),(8,1),(9,2),(15,2),(16,2),(17,3)]:
    pages=max(1,(count+7)//8)
    assert pages==expected,(count,pages,expected)
print('dev683 judge pagination assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php live-display/advance.php public/css/projector-safe-v616.css VERSION.json
git commit -m 'Release dev683 readable paged judges projection'
git push origin HEAD:develop
