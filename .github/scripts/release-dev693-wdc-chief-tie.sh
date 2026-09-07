#!/usr/bin/env bash
set -euo pipefail
python3 - <<'PY'
from pathlib import Path

svc=Path('app/Services/DanceCupScoringService.php')
s=svc.read_text()
needle="""        self::ensureWorkspaceTables($pdo, $test);\n        $tables = self::tables($test);\n        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';\n        $count = $pdo->prepare(\"SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition\");"""
repl="""        self::ensureWorkspaceTables($pdo, $test);\n        DanceCupTieService::ensure($pdo, $test);\n        $tables = self::tables($test);\n        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';\n        $count = $pdo->prepare(\"SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition\");"""
if needle not in s: raise SystemExit('calculate anchor missing')
s=s.replace(needle,repl,1)
needle="""        $pdo->beginTransaction();\n        try {\n            $pdo->prepare(\"DELETE FROM {$prefix}_scoring_results WHERE competition_id=:competition\")->execute(['competition' => $competitionId]);"""
repl="""        $pdo->beginTransaction();\n        try {\n            // A recalculation invalidates every previous Chief Judge tie decision/link.\n            $pdo->prepare(\"UPDATE {$prefix}_tie_tasks SET status='cancelled',token_hash=NULL,cancelled_at=NOW() WHERE competition_id=:competition AND status IN ('pending','resolved')\")->execute(['competition' => $competitionId]);\n            $pdo->prepare(\"DELETE FROM {$prefix}_scoring_results WHERE competition_id=:competition\")->execute(['competition' => $competitionId]);"""
if needle not in s: raise SystemExit('result transaction anchor missing')
s=s.replace(needle,repl,1)
needle="""        if((string)$row['status']!=='pending_approval')throw new RuntimeException('Only a submitted Dance Cup result awaiting approval can be published.');\n        $results=$pdo->prepare("""
repl="""        if((string)$row['status']!=='pending_approval')throw new RuntimeException('Only a submitted Dance Cup result awaiting approval can be published.');\n        if(DanceCupTieService::hasUnresolved($pdo,$competitionId,$test))throw new RuntimeException('WDC result contains an unresolved exact-score tie. The Chief Judge must confirm the final order before publication.');\n        $results=$pdo->prepare("""
if needle not in s: raise SystemExit('approval anchor missing')
s=s.replace(needle,repl,1)
svc.write_text(s)

for file in ['admin/dance-cup/category.php','admin/dance-cup/automation.php']:
    p=Path(file)
    if not p.exists(): continue
    s=p.read_text()
    if 'dance-cup-ties.js' in s: continue
    marker='</body>'
    # Inject through whichever output-buffer replacement owns the final body.
    candidates=[
        '<script src="../../public/js/dance-cup-scoring-live.js?v=432"></script></body>',
        '<script src="../../public/js/dance-cup-scoring-live.js?v=431"></script></body>',
        '</body>'
    ]
    done=False
    for c in candidates:
        if c in s:
            if c=='</body>': rep='<script src="../../public/js/dance-cup-ties.js?v=693"></script></body>'
            else: rep=c.replace('</body>','<script src="../../public/js/dance-cup-ties.js?v=693"></script></body>')
            s=s.replace(c,rep,1);done=True;break
    if not done: raise SystemExit(f'body anchor missing in {file}')
    p.write_text(s)

# Bump visible development version without touching unrelated release history.
vp=Path('VERSION.json')
import json
v=json.loads(vp.read_text())
v['version']='2.3.6-dev693';v['build']=int(v.get('build',0))+1
feat='WDC exact-score tie workflow: detects tied Dance Cup results, creates a 12-hour Chief Judge mobile decision link, preserves original scores, saves the CJ final order and audit state, invalidates tie decisions on recalculation, and blocks WDC publication until every exact-score tie is resolved.'
features=v.setdefault('features',[])
if feat not in features: features.insert(0,feat)
vp.write_text(json.dumps(v,indent=2,ensure_ascii=False)+'\n')
PY
php -l app/Services/DanceCupTieService.php
php -l app/Services/DanceCupScoringService.php
php -l admin/dance-cup/tie-api.php
php -l dance-cup-chief-tie.php
php -l admin/dance-cup/category.php
if [ -f admin/dance-cup/automation.php ]; then php -l admin/dance-cup/automation.php; fi
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Services/DanceCupScoringService.php admin/dance-cup/category.php admin/dance-cup/automation.php VERSION.json || true
if git diff --cached --quiet; then echo 'No generated changes'; exit 0; fi
git commit -m 'Release dev693 WDC Chief Judge tie workflow'
git push origin HEAD:develop
