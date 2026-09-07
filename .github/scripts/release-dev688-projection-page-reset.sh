#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

# 1) Judges screen must report real page count (8 judges per page).
p=Path('live-display/state.php')
s=p.read_text()
anchor='''if ($roundId && $s["screen_type"] === "judge_call") {
    $judgeTable = $test ? "bdc_test_scoring_judges" : "bdc_scoring_judges";
    $judgeCountQuery = $pdo->prepare("SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r");
    $judgeCountQuery->execute(["r" => $roundId]);
    $total = max(1, (int) $judgeCountQuery->fetchColumn());
}
'''
insert='''if ($roundId && $s["screen_type"] === "judges") {
    $judgeTable = $test ? "bdc_test_scoring_judges" : "bdc_scoring_judges";
    $judgeCountQuery = $pdo->prepare("SELECT COUNT(*) FROM {$judgeTable} WHERE round_id=:r");
    $judgeCountQuery->execute(["r" => $roundId]);
    $judgeCount = max(0, (int) $judgeCountQuery->fetchColumn());
    $total = max(1, (int) ceil($judgeCount / 8));
}
'''+anchor
if anchor not in s:
    raise SystemExit('state judge_call anchor missing')
s=s.replace(anchor,insert,1)
p.write_text(s)

# 2) Any manual screen/round change starts from page 1.
p=Path('app/Services/LiveDisplaySessionService.php')
s=p.read_text()
old='''        $page = max(1, (int) ($v["page_number"] ?? 1));
        if ($type === "judge_call") {
'''
new='''        $page = max(1, (int) ($v["page_number"] ?? 1));
        $screenChanged = $type !== (string)($current["screen_type"] ?? "holding");
        $roundChanged = $requestedRoundId !== (int)($current["current_round_id"] ?? 0);
        if ($screenChanged || $roundChanged) {
            $page = 1;
        }
        if ($type === "judge_call") {
'''
if old not in s:
    raise SystemExit('page selection anchor missing')
s=s.replace(old,new,1)
p.write_text(s)

# 3) Keep visible version sequence consistent.
vp=Path('VERSION.json')
data=json.loads(vp.read_text())
data['version']='2.3.6-dev688'
data['build']=3395
data['release_date']='2026-09-07'
feature='Projector paging reset and Judges rotation repair: every manual screen or round selection starts on Page 1, Judges reports the real page count at eight judges per page, and Auto Page can rotate multi-page judge boards instead of getting stuck on Page 2.'
data.setdefault('features',[]).insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/state.php
php -l app/Services/LiveDisplaySessionService.php
python3 <<'PY'
from pathlib import Path
s=Path('live-display/state.php').read_text()
svc=Path('app/Services/LiveDisplaySessionService.php').read_text()
assert '$s["screen_type"] === "judges"' in s
assert 'ceil($judgeCount / 8)' in s
assert '$screenChanged' in svc and '$roundChanged' in svc
assert 'if ($screenChanged || $roundChanged)' in svc
print('dev688 projector paging assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/state.php app/Services/LiveDisplaySessionService.php VERSION.json
git commit -m 'Release 2.3.6-dev688 projector page reset and judge rotation'
git push origin HEAD:develop
