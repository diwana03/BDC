#!/usr/bin/env bash
set -euo pipefail

python3 <<'PY'
from pathlib import Path
import json

# Repair the unapplied dev679 role YES migration so MigrationRunner receives a callable.
migration = Path('database/migrations/20260907_0438_role_yes_settings.php')
migration.write_text('''<?php\ndeclare(strict_types=1);\n\nreturn static function(PDO $pdo): void {\n    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_role_yes_settings (\n        round_id BIGINT UNSIGNED NOT NULL,\n        dance_role VARCHAR(16) NOT NULL,\n        yes_count INT UNSIGNED NOT NULL,\n        locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n        locked_by BIGINT UNSIGNED NULL,\n        PRIMARY KEY(round_id,dance_role),\n        CONSTRAINT fk_bdc_role_yes_round FOREIGN KEY(round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE,\n        INDEX idx_bdc_role_yes_locked_by(locked_by)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");\n};\n''')

# Keep the judge-facing Heats instructions easy to scan while deriving all numbers from the role tier/config.
p = Path('judge-scoring/index.php')
s = p.read_text()
old = '''<li><strong><?=e($criteriaLabel)?>:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Use the optional comment field for a private note.</li>'''
new = '''<li><strong><?=e($criteriaLabel)?> · Tier <?=(int)$cfg['tier']?> · <?=$allRoleCounts[$criteriaRole]?> competitors:</strong> Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>. A1 = <strong><?=e($places['A1'])?> place</strong>, A2 = <strong><?=e($places['A2'])?> place</strong>, A3 = <strong><?=e($places['A3'])?> place</strong>.</li><?php endforeach;?><li>Mark everyone else <strong>NO</strong>.</li><li>Comments are optional and private to this judge/device.</li>'''
if old not in s:
    raise SystemExit('Judge instruction anchor not found; refusing broad edit')
s = s.replace(old, new, 1)
p.write_text(s)

vp = Path('VERSION.json')
data = json.loads(vp.read_text())
data['version'] = '2.3.3-dev680'
data['build'] = 3387
data['release_date'] = '2026-09-07'
feature = 'Production migration repair and tier-aware judge instructions: the role-specific YES migration now returns the callable required by MigrationRunner, and Heats instructions show role, Tier, competitor count, “Choose N YES for your Top N best dancers,” dynamic A1/A2/A3 places, NO guidance and private-comment guidance using the saved role YES configuration.'
features = data.setdefault('features', [])
if not features or features[0] != feature:
    features.insert(0, feature)
vp.write_text(json.dumps(data, indent=2, ensure_ascii=False) + '\n')
PY

php -l database/migrations/20260907_0438_role_yes_settings.php
php -l judge-scoring/index.php
php -r '$m=require "database/migrations/20260907_0438_role_yes_settings.php"; if(!is_callable($m)){fwrite(STDERR,"migration is not callable\n"); exit(1);} echo "migration callable OK\n";'
python3 <<'PY'
from pathlib import Path
s=Path('judge-scoring/index.php').read_text()
assert "Tier <?=(int)$cfg['tier']?>" in s
assert "Choose <strong><?=(int)$cfg['yes']?> YES</strong> for your <strong>Top <?=(int)$cfg['yes']?> best dancers</strong>" in s
assert 'Comments are optional and private to this judge/device.' in s
print('judge instruction assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add database/migrations/20260907_0438_role_yes_settings.php judge-scoring/index.php VERSION.json
git commit -m 'Release dev680 migration repair and tier-aware judge instructions'
git push origin HEAD:develop
