#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json

# 1) Provision WDC tie tasks as part of the normal Dance Cup workspace setup.
p=Path('app/Services/DanceCupScoringService.php')
s=p.read_text()
anchor='''        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_scoring_results(competition_id BIGINT UNSIGNED NOT NULL,entry_id BIGINT UNSIGNED NOT NULL,total_score DECIMAL(12,2) NOT NULL DEFAULT 0,placement INT UNSIGNED NOT NULL,calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(competition_id,entry_id),INDEX idx_dc_scoring_result_place(competition_id,placement)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");\n'''
insert=anchor+'''        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_tie_tasks(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,competition_id BIGINT UNSIGNED NOT NULL,tie_key CHAR(64) NOT NULL,tied_score DECIMAL(12,2) NOT NULL,entry_ids_json TEXT NOT NULL,token_hash CHAR(64) NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',resolved_order_json TEXT NULL,created_by BIGINT UNSIGNED NULL,chief_judge_assignment_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NULL,resolved_at DATETIME NULL,cancelled_at DATETIME NULL,UNIQUE KEY uq_dc_tie_key(competition_id,tie_key),INDEX idx_dc_tie_status(competition_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");\n'''
if '{$prefix}_tie_tasks' not in s:
    if anchor not in s:
        raise SystemExit('scoring-results workspace anchor missing')
    s=s.replace(anchor,insert,1)
p.write_text(s)

# 2) Tie reads/actions must not attempt schema creation during live API requests.
p=Path('app/Services/DanceCupTieService.php')
s=p.read_text()
s=s.replace('self::ensure($pdo,$test);$p=self::prefix($test);',' $p=self::prefix($test);',1)
s=s.replace('self::ensure($pdo,$test);$p=self::prefix($test);$q=$pdo->prepare(', '$p=self::prefix($test);$q=$pdo->prepare(',1)
s=s.replace('self::ensure($pdo,$test);$p=self::prefix($test);$pdo->prepare(', '$p=self::prefix($test);$pdo->prepare(',1)
p.write_text(s)

# 3) Bump release metadata.
p=Path('VERSION.json')
data=json.loads(p.read_text())
data['version']='2.3.6-dev695'
data['build']=3401
feature='WDC tie workflow reliability repair: provisions tie-task tables during normal Dance Cup workspace setup and removes schema creation from live tie API reads/actions, so Automatic Scoring tie controls load reliably without changing scores, placements or BDC/SDC logic.'
data.setdefault('features',[]).insert(0,feature)
p.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY

php -l app/Services/DanceCupScoringService.php
php -l app/Services/DanceCupTieService.php
php -l admin/dance-cup/tie-api.php
php -l admin/dance-cup/automatic-setup.php
python3 <<'PY'
from pathlib import Path
s=Path('app/Services/DanceCupScoringService.php').read_text()
t=Path('app/Services/DanceCupTieService.php').read_text()
assert 'CREATE TABLE IF NOT EXISTS {$prefix}_tie_tasks' in s
# The compatibility ensure() method may remain, but runtime tie reads/actions must no longer call it.
body=t.split('public static function ties',1)[1]
assert 'self::ensure($pdo,$test)' not in body
print('dev695 WDC tie workspace assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add app/Services/DanceCupScoringService.php app/Services/DanceCupTieService.php VERSION.json
git commit -m 'Release dev695 repair WDC tie workspace loading'
git push origin HEAD:develop
