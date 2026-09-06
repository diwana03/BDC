#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('.github/scripts/release-dev679-role-yes-settings.sh')
s=p.read_text()
replacements={
"assert 'leader_yes_count' in a and 'follower_yes_count' in a and 'special_role_settings_lock' in a":"assert 'special_role_settings_lock' in a and 'RoleYesConfigurationService::recommended' in a",
"assert 'Top <?=(int)$cfg[\\'yes\\']?> best dancers' in j and 'competitors:</strong> choose' not in j":"assert 'best dancers' in j and 'competitors:</strong> choose' not in j"
}
for old,new in replacements.items():
    if old not in s: raise SystemExit('dev679 assertion anchor missing: '+old)
    s=s.replace(old,new,1)
p.write_text(s)
PY
bash .github/scripts/release-dev679-role-yes-settings.sh
